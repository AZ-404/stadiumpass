<?php
/* Fan: my bookings + cancellation - Malak
 * A booking can be cancelled only if:
 *   - it belongs to the logged-in user,
 *   - it is still "confirmed",
 *   - the match is upcoming and has not started yet,
 *   - no ticket of it has been used at the gate.
 * Cancelling sets the booking and all its tickets to "cancelled".
 * Editing a booking = cancel it and book again.
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        // Lock the booking row; the user_id check stops people cancelling others' bookings
        $stmt = $pdo->prepare(
            "SELECT b.id, b.status, m.match_date, m.match_time, m.status AS match_status
             FROM bookings b
             JOIN matches m ON m.id = b.match_id
             WHERE b.id = :id AND b.user_id = :u
             FOR UPDATE"
        );
        $stmt->execute([':id' => $booking_id, ':u' => $user_id]);
        $b = $stmt->fetch();

        if (!$b) {
            $error = 'Booking not found.';
        } elseif ($b['status'] !== 'confirmed') {
            $error = 'This booking is already cancelled.';
        } elseif ($b['match_status'] !== 'upcoming'
                  || strtotime($b['match_date'] . ' ' . $b['match_time']) <= time()) {
            $error = 'You cannot cancel a booking for a match that has already started or finished.';
        } else {
            $used = $pdo->prepare(
                "SELECT COUNT(*) FROM tickets WHERE booking_id = :id AND status = 'used'"
            );
            $used->execute([':id' => $booking_id]);

            if ((int)$used->fetchColumn() > 0) {
                $error = 'A ticket from this booking has already been used.';
            } else {
                $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :id")
                    ->execute([':id' => $booking_id]);
                $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE booking_id = :id")
                    ->execute([':id' => $booking_id]);
                $success = 'Booking #' . $booking_id . ' was cancelled. Your seats are available again.';
            }
        }

        if ($error !== '') { $pdo->rollBack(); } else { $pdo->commit(); }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = 'Could not cancel the booking. Please try again.';
    }
}

// The user's bookings (newest first)
$stmt = $pdo->prepare(
    "SELECT b.id, b.total, b.status, b.created_at,
            m.match_date, m.match_time, m.status AS match_status,
            th.name AS home_team, ta.name AS away_team
     FROM bookings b
     JOIN matches m ON m.id = b.match_id
     JOIN teams th  ON th.id = m.home_team_id
     JOIN teams ta  ON ta.id = m.away_team_id
     WHERE b.user_id = :u
     ORDER BY b.created_at DESC, b.id DESC"
);
$stmt->execute([':u' => $user_id]);
$bookings = $stmt->fetchAll();

// Tickets of those bookings, grouped by booking id
$tickets = [];
if ($bookings) {
    $ids = array_column($bookings, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT t.id, t.booking_id, t.status, se.row_label, se.seat_number, sec.name AS section_name
         FROM tickets t
         JOIN seats se     ON se.id  = t.seat_id
         JOIN sections sec ON sec.id = se.section_id
         WHERE t.booking_id IN ($in)
         ORDER BY sec.id, se.row_label, se.seat_number"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $t) {
        $tickets[$t['booking_id']][] = $t;
    }
}

$page_title = 'My Bookings';
require_once __DIR__ . '/includes/header.php';
?>

    <h1 class="page-title">My Bookings</h1>

    <!-- Interactive 3D stadium preview -->
    <div class="card" style="margin-bottom:1.5rem;background:#0b1220;color:#fff;padding:1rem;border-radius:14px;">
      <div id="stadium3d-title" style="font-weight:700;font-size:1.1rem;margin-bottom:.35rem;color:#f8fafc;">Your place in the stadium</div>
      <p id="stadium3d-subtitle" style="margin:0 0 .75rem;color:#cbd5e1;font-size:.9rem;">Choose a booking seat to see its location.</p>
      <div id="stadium3d" role="img" aria-label="Interactive 3D stadium showing your booked stand" style="width:100%;height:340px;max-width:900px;margin:0 auto;overflow:hidden;border-radius:10px;border:1px solid #334155;background:#08101d;position:relative;"><div style="padding:2rem;text-align:center;color:#cbd5e1;">Loading 3D stadium…</div></div>
      <div style="display:flex;gap:.6rem;flex-wrap:wrap;justify-content:center;margin-top:.75rem;font-size:.82rem;color:#cbd5e1;"><span>🟩 Pitch</span><span>🟨 VIP Stand</span><span>🟦 North Stand</span><span>🟥 South Stand</span></div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (count($bookings) === 0): ?>
      <div class="card">
        <p>You have no bookings yet. <a href="booking.php?action=matches">Browse matches</a> to book your first seat.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($bookings as $b):
        $starts_at  = strtotime($b['match_date'] . ' ' . $b['match_time']);
        $can_cancel = $b['status'] === 'confirmed'
                   && $b['match_status'] === 'upcoming'
                   && $starts_at > time();
    ?>
      <div class="card booking-card" data-booking-id="<?= (int)$b['id'] ?>" data-booking-status="<?= htmlspecialchars($b['status'], ENT_QUOTES) ?>">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:.75rem;">
          <div>
            <h2 style="margin-bottom:.25rem;">
              <?= htmlspecialchars($b['home_team']) ?> vs <?= htmlspecialchars($b['away_team']) ?>
            </h2>
            <span style="color:#64748b;">
              <?= htmlspecialchars($b['match_date']) ?> at <?= htmlspecialchars(substr($b['match_time'], 0, 5)) ?>
              &middot; Booking #<?= (int)$b['id'] ?>
              &middot; Total $<?= number_format((float)$b['total'], 2) ?>
            </span>
          </div>
          <div><span class="badge badge-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></div>
        </div>

        <div class="table-wrap">
          <table class="data">
            <tr><th>Ticket</th><th>Section</th><th>Row</th><th>Seat</th><th>Status</th><th></th></tr>
            <?php foreach ($tickets[$b['id']] ?? [] as $t): ?>
              <tr class="booking-seat-row" tabindex="0" data-section="<?= htmlspecialchars($t['section_name'], ENT_QUOTES) ?>" data-row="<?= htmlspecialchars($t['row_label'], ENT_QUOTES) ?>" data-seat="<?= (int)$t['seat_number'] ?>" data-ticket-status="<?= htmlspecialchars($t['status'], ENT_QUOTES) ?>" style="cursor:pointer;">
                <td>#<?= (int)$t['id'] ?></td>
                <td><?= htmlspecialchars($t['section_name']) ?></td>
                <td><?= htmlspecialchars($t['row_label']) ?></td>
                <td><?= (int)$t['seat_number'] ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($t['status']) ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                <td>
                  <?php if ($t['status'] !== 'cancelled'): ?>
                    <a href="ticket.php?id=<?= (int)$t['id'] ?>" class="btn btn-small">View ticket</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>

        <?php if ($can_cancel): ?>
          <form method="post" action="my_bookings.php" style="margin-top:1rem;"
                onsubmit="return confirm('Cancel this whole booking? This cannot be undone.');">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="btn btn-danger">Cancel booking</button>
            <span style="color:#64748b; margin-left:.5rem;">To change seats, cancel and book again.</span>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

<script src="assets/vendor/three.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const host=document.getElementById('stadium3d'), title=document.getElementById('stadium3d-title'), subtitle=document.getElementById('stadium3d-subtitle');
  if(!host||!window.THREE){if(host)host.textContent='3D preview could not load. Your booked seats are listed below.';return;}
  host.style.height='min(68vw,520px)'; host.style.minHeight='360px';
  const scene=new THREE.Scene(); scene.background=new THREE.Color(0x17212b); scene.fog=new THREE.Fog(0x17212b,48,105);
  const camera=new THREE.PerspectiveCamera(38,host.clientWidth/host.clientHeight,.1,180); camera.position.set(0,28,43); camera.lookAt(0,4,0);
  const renderer=new THREE.WebGLRenderer({antialias:true,alpha:false}); renderer.setPixelRatio(Math.min(devicePixelRatio||1,2)); renderer.setSize(host.clientWidth,host.clientHeight); renderer.shadowMap.enabled=true; renderer.shadowMap.type=THREE.PCFSoftShadowMap;
  if('outputColorSpace' in renderer && THREE.SRGBColorSpace) renderer.outputColorSpace=THREE.SRGBColorSpace;
  host.innerHTML='';host.appendChild(renderer.domElement); renderer.domElement.style.display='block';renderer.domElement.style.width='100%';renderer.domElement.style.height='100%';
  scene.add(new THREE.HemisphereLight(0xeaf3ff,0x222b35,2.0));
  const keyLight=new THREE.DirectionalLight(0xfff3db,2.5);keyLight.position.set(-18,35,22);keyLight.castShadow=true;scene.add(keyLight);
  const fillLight=new THREE.DirectionalLight(0xc6e2ff,1.2);fillLight.position.set(20,20,-20);scene.add(fillLight);
  const material=(c,r=.85,m=0)=>new THREE.MeshStandardMaterial({color:c,roughness:r,metalness:m});
  const turf=material(0x176b38), turfAlt=material(0x237a40), white=material(0xf4f4ed), concrete=material(0x858b90), concreteDark=material(0x454b51), roofMat=material(0x68737b,.62,.35), steel=material(0xa5adb3,.42,.65);
  const ground=new THREE.Mesh(new THREE.PlaneGeometry(180,180),material(0x252c32));ground.rotation.x=-Math.PI/2;ground.position.y=-.65;ground.receiveShadow=true;scene.add(ground);
  // Pitch with alternating cut-grass strips, realistic proportions and markings.
  const pitch=new THREE.Mesh(new THREE.PlaneGeometry(17,28),turf);pitch.rotation.x=-Math.PI/2;pitch.position.y=.02;pitch.receiveShadow=true;scene.add(pitch);
  for(let i=0;i<10;i++){const strip=new THREE.Mesh(new THREE.PlaneGeometry(1.7,27.8),i%2?turf:turfAlt);strip.rotation.x=-Math.PI/2;strip.position.set(-7.65+i*1.7,.035,0);scene.add(strip);}
  function pitchLine(x1,z1,x2,z2,y=.055,w=.075){const dx=x2-x1,dz=z2-z1;let mesh;if(Math.abs(dx)<.001)mesh=new THREE.Mesh(new THREE.BoxGeometry(w,.018,Math.abs(dz)),white);else if(Math.abs(dz)<.001)mesh=new THREE.Mesh(new THREE.BoxGeometry(Math.abs(dx),.018,w),white);else {const len=Math.hypot(dx,dz);mesh=new THREE.Mesh(new THREE.BoxGeometry(len,.018,w),white);mesh.rotation.y=-Math.atan2(dz,dx);}mesh.position.set((x1+x2)/2,y,(z1+z2)/2);scene.add(mesh);}
  pitchLine(-8.5,-14,8.5,-14);pitchLine(-8.5,14,8.5,14);pitchLine(-8.5,-14,-8.5,14);pitchLine(8.5,-14,8.5,14);pitchLine(-8.5,0,8.5,0);
  const circle=new THREE.Mesh(new THREE.TorusGeometry(3,.055,8,72),white);circle.rotation.x=Math.PI/2;circle.position.y=.06;scene.add(circle);
  for(const sign of [-1,1]){const z=sign*9.3;pitchLine(-3.2,z,3.2,z,.06);pitchLine(-3.2,z, -3.2,z-sign*4.6,.06);pitchLine(3.2,z,3.2,z-sign*4.6,.06);const goal=new THREE.Mesh(new THREE.BoxGeometry(3.4,2.1,.15),new THREE.MeshStandardMaterial({color:0xe8edf0,wireframe:true}));goal.position.set(0,1.05,sign*14.3);scene.add(goal);}
  // Continuous oval bowl, built from hundreds of individual seat blocks across four tiers.
  const seatMaterials={VIP:material(0x9b8050,.78),North:material(0x3e6079,.78),South:material(0x87565a,.78),East:material(0x626d75,.78)};
  const seatMeshes={VIP:[],North:[],South:[],East:[]};
  const sectionForAngle=a=>{const c=Math.cos(a),s=Math.sin(a);if(s<-.48)return 'VIP';if(s>.48)return 'North';if(c>.55)return 'South';return 'East';};
  // Stadium-shaped oval radius with softened corners approximated by an ellipse.
  for(let tier=0;tier<5;tier++){
    const rx=12.1+tier*1.95, rz=18.4+tier*1.9, y=1.0+tier*1.05;
    for(let a=0;a<Math.PI*2;a+=.075){const sec=sectionForAngle(a);const x=rx*Math.cos(a),z=rz*Math.sin(a);const seat=new THREE.Mesh(new THREE.BoxGeometry(.82,.48,.54),seatMaterials[sec]);seat.position.set(x,y,z);seat.rotation.y=-a;seat.castShadow=true;seat.receiveShadow=true;scene.add(seat);seatMeshes[sec].push(seat);}
    // Concrete riser ring beneath every tier gives the bowl depth and weight.
    const ring=new THREE.Mesh(new THREE.TorusGeometry((rx+rz)/2,.52,8,160),concrete);ring.scale.set(1.0,rz/rx,1);ring.rotation.x=Math.PI/2;ring.position.y=y-.38;scene.add(ring);
  }
  // Outer concourse fascia and upper rim.
  const fascia=new THREE.Mesh(new THREE.TorusGeometry(24,.75,10,180),concreteDark);fascia.scale.set(1,1.2,1);fascia.rotation.x=Math.PI/2;fascia.position.y=5.6;scene.add(fascia);
  const rim=new THREE.Mesh(new THREE.TorusGeometry(27.4,.38,8,180),steel);rim.scale.set(1,1.25,1);rim.rotation.x=Math.PI/2;rim.position.y=7.1;scene.add(rim);
  // Partial roof canopy around the stands, with visible steel trusses and supports.
  for(let a=0;a<Math.PI*2;a+=.23){const x1=20*Math.cos(a),z1=30*Math.sin(a),x2=29*Math.cos(a),z2=38*Math.sin(a);const dx=x2-x1,dy=3.3,dz=z2-z1;const length=Math.hypot(dx,dy,dz);const beam=new THREE.Mesh(new THREE.CylinderGeometry(.095,.095,length,7),steel);beam.position.set((x1+x2)/2,7.7,(z1+z2)/2);beam.rotation.z=-Math.atan2(dy,Math.hypot(dx,dz));beam.rotation.y=Math.atan2(dx,dz);scene.add(beam);}
  const roof=new THREE.Mesh(new THREE.RingGeometry(23,32,160,1),new THREE.MeshStandardMaterial({color:0x68757e,roughness:.65,metalness:.25,side:THREE.DoubleSide,transparent:true,opacity:.93}));roof.rotation.x=Math.PI/2;roof.position.y=9.4;roof.scale.y=1.32;scene.add(roof);
  // Floodlight towers and bright lamp banks at the stadium corners.
  for(const [x,z] of [[-28,-31],[28,-31],[-28,31],[28,31]]){const pole=new THREE.Mesh(new THREE.CylinderGeometry(.18,.28,12,8),steel);pole.position.set(x,6,z);scene.add(pole);const bank=new THREE.Mesh(new THREE.BoxGeometry(3.8,.45,1.1),material(0xe9f4ff,.35,.1));bank.position.set(x,12.2,z);scene.add(bank);const lamp=new THREE.PointLight(0xd9eaff,1.0,40);lamp.position.set(x,11.8,z);scene.add(lamp);}
  // A subtle running track / apron around the pitch and low advertising boards.
  const apron=new THREE.Mesh(new THREE.RingGeometry(18.5,20.1,120),material(0x6b747a));apron.rotation.x=-Math.PI/2;apron.position.y=.01;apron.scale.set(1,1.58,1);scene.add(apron);
  for(let i=0;i<100;i++){const a=i*Math.PI*2/100;const board=new THREE.Mesh(new THREE.BoxGeometry(1.3,.48,.28),material(i%4===0?0x34434c:(i%4===1?0xeeeeea:(i%4===2?0x9c3437:0x263f52)),.7));board.position.set(18.7*Math.cos(a),.42,29.1*Math.sin(a));board.rotation.y=-a;scene.add(board);}
  let markerGroup=new THREE.Group();scene.add(markerGroup);let selectedSection='VIP';
  function normalizeSection(v){const n=(v||'').toLowerCase();if(n.includes('vip')||n.includes('premium'))return 'VIP';if(n.includes('north'))return 'North';if(n.includes('south'))return 'South';if(n.includes('east')||n.includes('west'))return 'East';return 'VIP';}
  function updateSeat(section,row,seat,raw){selectedSection=normalizeSection(section);Object.keys(seatMeshes).forEach(k=>seatMeshes[k].forEach(m=>{const active=k===selectedSection;m.material=active?new THREE.MeshStandardMaterial({color:seatMaterials[k].color,roughness:.72,emissive:active?seatMaterials[k].color:0x000000,emissiveIntensity:active?.18:0}):seatMaterials[k];}));scene.remove(markerGroup);markerGroup=new THREE.Group();scene.add(markerGroup);
    const number=Math.max(1,Number(seat)||1), angle=selectedSection==='VIP'?Math.PI*1.5:selectedSection==='North'?Math.PI/2:selectedSection==='South'?0:Math.PI;const spread=((number-1)%20)/19-.5;const a=angle+spread*.72;const radius=20.5;const pos=new THREE.Vector3(radius*Math.cos(a),4.7,radius*1.42*Math.sin(a));
    const halo=new THREE.Mesh(new THREE.SphereGeometry(.68,20,16),new THREE.MeshStandardMaterial({color:0xffd35a,emissive:0xffb000,emissiveIntensity:1.7}));halo.position.copy(pos);markerGroup.add(halo);
    const beam=new THREE.Mesh(new THREE.CylinderGeometry(.08,.28,3.5,12),new THREE.MeshStandardMaterial({color:0xffd35a,emissive:0x9a5d00,emissiveIntensity:.5,transparent:true,opacity:.82}));beam.position.set(pos.x,2.6,pos.z);markerGroup.add(beam);
    title.textContent='Your seat · '+(raw||selectedSection+' Stand');subtitle.textContent='Row '+(row||'—')+' · Seat '+(seat||'—')+' — highlighted in the '+selectedSection+' stand. Drag to orbit around the stadium; scroll to zoom.';
  }
  let dragging=false,px=0,py=0,rotY=0,rotX=0,zoom=1;renderer.domElement.style.cursor='grab';renderer.domElement.addEventListener('pointerdown',e=>{dragging=true;px=e.clientX;py=e.clientY;renderer.domElement.setPointerCapture(e.pointerId);renderer.domElement.style.cursor='grabbing';});renderer.domElement.addEventListener('pointermove',e=>{if(!dragging)return;rotY+=(e.clientX-px)*.006;rotX+=(e.clientY-py)*.003;rotX=Math.max(-.12,Math.min(.35,rotX));px=e.clientX;py=e.clientY;});renderer.domElement.addEventListener('pointerup',()=>{dragging=false;renderer.domElement.style.cursor='grab';});renderer.domElement.addEventListener('pointercancel',()=>{dragging=false;renderer.domElement.style.cursor='grab';});renderer.domElement.addEventListener('wheel',e=>{zoom=Math.max(.72,Math.min(1.5,zoom+e.deltaY*.0008));},{passive:true});
  function resize(){const w=host.clientWidth,h=host.clientHeight;camera.aspect=w/h;camera.updateProjectionMatrix();renderer.setSize(w,h);}window.addEventListener('resize',resize);
  function animate(){requestAnimationFrame(animate);scene.rotation.y=rotY;scene.rotation.x=rotX;camera.position.set(0,28*zoom,43*zoom);camera.lookAt(0,4,0);renderer.render(scene,camera);}animate();
  document.querySelectorAll('.booking-seat-row').forEach(r=>{const show=()=>updateSeat(r.dataset.section,r.dataset.row,r.dataset.seat,r.dataset.section);r.addEventListener('mouseenter',show);r.addEventListener('click',show);r.addEventListener('focus',show);r.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();show();}});});
  const first=document.querySelector('.booking-seat-row');if(first)updateSeat(first.dataset.section,first.dataset.row,first.dataset.seat,first.dataset.section);else{title.textContent='Full stadium view';subtitle.textContent='Your booked seat will be highlighted here after you book a ticket.';}
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>