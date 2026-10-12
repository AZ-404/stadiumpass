<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_role('staff', 'admin');

$staff_id = (int)$_SESSION['user_id'];

$result  = "";     
$details = "";     

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['ticket_code'] ?? '');
    $ticket_no = ctype_digit($code) ? (int)$code : 0;

    $stmt = $pdo->prepare(
        "SELECT t.id, t.status, sec.id AS section_id, sec.name AS section_name,
                se.row_label, se.seat_number
         FROM tickets t
         JOIN seats se     ON se.id  = t.seat_id
         JOIN sections sec ON sec.id = se.section_id
         WHERE t.qr_code = :code OR t.id = :id
         LIMIT 1"
    );
    $stmt->execute([':code' => $code, ':id' => $ticket_no]);
    $ticket = $stmt->fetch();

    if (!$ticket || $code === '') {
        $result = "invalid";                       
    } elseif ($ticket['status'] === 'cancelled') {
        $result = "invalid";                       
    } elseif ($ticket['status'] === 'used') {
        $result = "used";                         
    } else {
        try {
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE tickets SET status = 'used'
                                  WHERE id = :id AND status = 'valid'");
            $upd->execute([':id' => $ticket['id']]);

            if ($upd->rowCount() === 1) {
                $ins = $pdo->prepare("INSERT INTO entries (ticket_id, section_id, staff_id)
                                      VALUES (:t, :s, :st)");
                $ins->execute([
                    ':t'  => $ticket['id'],
                    ':s'  => $ticket['section_id'],
                    ':st' => $staff_id
                ]);
                $pdo->commit();
                $result  = "valid";
                $details = $ticket['section_name'] . " - Row " . $ticket['row_label']
                         . ", Seat " . $ticket['seat_number'];
            } else {
                $pdo->rollBack();
                $result = "used";                 
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $result = "invalid";
        }
    }
}

$page_title = 'Verify Ticket';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 480px; margin: 1.5rem auto;">
    <h1 class="page-title" style="font-size: 1.5rem; margin-bottom: 1.25rem;">Verify Ticket</h1>

    <form method="post" action="verify.php">
        <div class="form-group">
            <label for="ticket_code">Ticket number or QR code</label>
            <input type="text" id="ticket_code" name="ticket_code" class="form-control"
                   placeholder="Type or scan ticket code" required autofocus>
        </div>
        <button type="submit" class="btn" style="width: 100%;">Check Ticket</button>
    </form>

    <?php if ($result === "valid"): ?>
        <div class="alert alert-success" style="margin-top: 1.5rem; text-align: center; font-size: 1.1rem; font-weight: 700;">
            Valid. Welcome in!
            <div style="font-size: 0.95rem; font-weight: 500; margin-top: 0.5rem; color: #155724;"><?= htmlspecialchars($details) ?></div>
        </div>
    <?php elseif ($result === "used"): ?>
        <div class="alert" style="margin-top: 1.5rem; text-align: center; font-weight: 700; background: #fff3cd; color: #856404; border: 1px solid #ffeeba;">
            Already used
        </div>
    <?php elseif ($result === "invalid"): ?>
        <div class="alert alert-danger" style="margin-top: 1.5rem; text-align: center; font-weight: 700;">
            Invalid or cancelled ticket
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
