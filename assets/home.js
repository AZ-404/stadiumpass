/* =========================================================
   Stadium Pass — homepage behaviour (appearance only)
   1. Navbar state          2. Fixtures UI (countdown / filters / tilt / reveal)
   3. Scroll-to-kick-off 3D scene (Three.js + GSAP ScrollTrigger)
   Nothing here touches booking data or sends requests.
   ========================================================= */
(function () {
  'use strict';

  var doc = document, root = doc.documentElement;
  root.classList.add('js-home');

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
  function $(s, c) { return (c || doc).querySelector(s); }
  function $$(s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); }
  function clamp(x, a, b) { return Math.min(b, Math.max(a, x)); }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function smooth(a, b, x) { var t = clamp((x - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); }

  /* ---------------------------------------------------------
     1. Navbar: transparent over the hero, dark once scrolled
     --------------------------------------------------------- */
  var header = $('.site-header');
  function navState() { if (header) header.classList.toggle('is-scrolled', window.scrollY > 40); }
  navState();
  window.addEventListener('scroll', navState, { passive: true });

  /* ---------------------------------------------------------
     2. Fixtures
     --------------------------------------------------------- */
  var cards = $$('.fx-card');

  // --- countdown + badge ---
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function parseKick(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})$/.exec(s || '');
    return m ? new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5], +m[6]).getTime() : NaN;
  }
  cards.forEach(function (c) { c._kick = parseKick(c.getAttribute('data-kickoff')); });

  function tick() {
    var now = Date.now();
    cards.forEach(function (c) {
      var box = $('[data-countdown]', c), badge = $('[data-badge]', c);
      if (isNaN(c._kick)) { box.style.display = 'none'; return; }
      var d = c._kick - now;
      if (d <= 0) {
        box.innerHTML = '<div class="is-done">Kick-off time</div>';
        return;
      }
      var s = Math.floor(d / 1000), days = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600),
          m = Math.floor(s % 3600 / 60), sec = s % 60;
      box.innerHTML =
        '<div><b>' + pad(days) + '</b><small>Days</small></div>' +
        '<div><b>' + pad(h) + '</b><small>Hrs</small></div>' +
        '<div><b>' + pad(m) + '</b><small>Min</small></div>' +
        '<div><b>' + pad(sec) + '</b><small>Sec</small></div>';
      var soon = d < 48 * 3600 * 1000;
      badge.textContent = soon ? 'Starting soon' : 'Upcoming';
      badge.classList.toggle('is-soon', soon);
    });
  }
  if (cards.length) { tick(); setInterval(tick, 1000); }

  // --- filters (built from the cards already on the page) ---
  var bar = $('[data-filters]');
  if (bar && cards.length) {
    var teamSel = $('[data-filter="team"]', bar), dateSel = $('[data-filter="date"]', bar);
    var teams = {}, dates = {};
    cards.forEach(function (c) {
      teams[c.dataset.home] = 1; teams[c.dataset.away] = 1;
      dates[c.dataset.date] = c.querySelector('.fx-meta span').textContent.trim();
    });
    Object.keys(teams).sort().forEach(function (t) { var o = doc.createElement('option'); o.value = t; o.textContent = t; teamSel.appendChild(o); });
    Object.keys(dates).sort().forEach(function (d) { var o = doc.createElement('option'); o.value = d; o.textContent = dates[d]; dateSel.appendChild(o); });
    // Only worth showing when there is something to choose between.
    bar.hidden = cards.length < 2;

    var countEl = $('[data-count]', bar), noneEl = $('[data-none]');
    var apply = function () {
      var t = teamSel.value, d = dateSel.value, shown = 0, first = true;
      cards.forEach(function (c) {
        var ok = (!t || c.dataset.home === t || c.dataset.away === t) && (!d || c.dataset.date === d);
        c.hidden = !ok; c.style.display = ok ? '' : 'none';
        c.classList.remove('is-featured');
        if (ok) {
          shown++;
          if (first) { c.classList.add('is-featured'); first = false; }
          c.classList.add('is-in');
        }
      });
      countEl.textContent = shown + (shown === 1 ? ' fixture' : ' fixtures');
      if (noneEl) noneEl.hidden = shown !== 0;
    };
    teamSel.addEventListener('change', apply);
    dateSel.addEventListener('change', apply);
    $('[data-filter-reset]', bar).addEventListener('click', function () { teamSel.value = ''; dateSel.value = ''; apply(); });
    apply();
  }

  // --- 3D tilt + cursor glow ---
  if (!reduceMotion && !coarse) {
    cards.forEach(function (c) {
      var raf = 0, ev;
      c.addEventListener('pointermove', function (e) {
        ev = e;
        if (raf) return;
        raf = requestAnimationFrame(function () {
          raf = 0;
          var r = c.getBoundingClientRect(), x = (ev.clientX - r.left) / r.width, y = (ev.clientY - r.top) / r.height;
          c.style.setProperty('--mx', (x * 100).toFixed(1) + '%');
          c.style.setProperty('--my', (y * 100).toFixed(1) + '%');
          c.style.setProperty('--ry', ((x - .5) * 9).toFixed(2) + 'deg');
          c.style.setProperty('--rx', ((.5 - y) * 7).toFixed(2) + 'deg');
        });
      });
      c.addEventListener('pointerleave', function () { c.style.setProperty('--rx', '0deg'); c.style.setProperty('--ry', '0deg'); });
    });
  }

  // --- reveal as they enter the viewport ---
  var revealEls = $$('[data-reveal], .fx-card');
  if ('IntersectionObserver' in window) {
    var idx = 0;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target;
        el.style.transitionDelay = (idx++ % 3) * 90 + 'ms';
        el.classList.add('is-in');
        setTimeout(function () { el.style.transitionDelay = ''; el.classList.add('is-live'); idx = 0; }, 1100);
        io.unobserve(el);
      });
    }, { threshold: .12, rootMargin: '0px 0px -6% 0px' });
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add('is-in'); });
  }

  // --- CTA: glide down through the kick-off ---
  $$('[data-scroll-to]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var t = $(a.getAttribute('data-scroll-to'));
      if (!t) return;
      e.preventDefault();
      window.scrollTo({ top: t.getBoundingClientRect().top + window.scrollY, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  });

  /* ---------------------------------------------------------
     3. Hero: scroll-to-kick-off
     --------------------------------------------------------- */
  var hero = $('[data-hero]');
  if (!hero) return;
  if (root.classList.contains('video-hero')) return;   // hero is driven by assets/scrollvideo.js instead of the 3D scene

  function fallback(why) {
    root.classList.add('no-3d', 'no-scrub');
    if (window.console && why) console.info('[hero] static fallback:', why);
  }

  function webglOK() {
    try {
      var c = doc.createElement('canvas');
      return !!(window.WebGLRenderingContext && (c.getContext('webgl') || c.getContext('experimental-webgl')));
    } catch (e) { return false; }
  }

  function boot() {
    if (!window.THREE || !webglOK()) { fallback('WebGL/Three unavailable'); return; }
    try { initScene(); } catch (err) { fallback(err); }
  }
  if (doc.readyState === 'complete') boot(); else window.addEventListener('load', boot);

  function initScene() {
    var THREE = window.THREE;
    var canvas = $('#stage');
    var stage = $('.hero-stage', hero);
    var copy = $('[data-hero-copy]'), cue = $('[data-scroll-cue]');
    var flash = $('.hero-flash'), fade = $('.hero-fade'), speedFx = $('.hero-speed');
    var small = Math.min(window.innerWidth, window.innerHeight) < 700 || (navigator.deviceMemory && navigator.deviceMemory < 4);
    var scrubbing = !reduceMotion;
    if (!scrubbing) root.classList.add('no-scrub');

    var renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: !small, alpha: false, powerPreference: 'high-performance' });
    var maxDPR = small ? 1.25 : 1.75;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, maxDPR));
    renderer.outputEncoding = THREE.sRGBEncoding;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.0;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.setClearColor(0x03070a, 1);

    var scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0x040a08, 0.021);
    var camera = new THREE.PerspectiveCamera(44, 1, 0.05, 260);

    var disposables = [];
    function track(o) { disposables.push(o); return o; }
    function canvasTex(w, h, draw, repeat) {
      var c = doc.createElement('canvas'); c.width = w; c.height = h;
      draw(c.getContext('2d'), w, h);
      var t = track(new THREE.CanvasTexture(c));
      t.encoding = THREE.sRGBEncoding; t.anisotropy = 4;
      if (repeat) { t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(repeat[0], repeat[1]); }
      return t;
    }
    function glowTex() {
      return canvasTex(128, 128, function (g, w, h) {
        var gr = g.createRadialGradient(w / 2, h / 2, 0, w / 2, h / 2, w / 2);
        gr.addColorStop(0, 'rgba(255,255,255,1)'); gr.addColorStop(.25, 'rgba(255,255,255,.55)'); gr.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = gr; g.fillRect(0, 0, w, h);
      });
    }
    var glow = glowTex();

    /* ----- pitch ----- */
    var PW = 80, PL = 110;
    var pitchTex = canvasTex(1024, 1408, function (g, w, h) {
      var stripes = 16, sh = h / stripes;
      for (var i = 0; i < stripes; i++) { g.fillStyle = i % 2 ? '#1c5d33' : '#216b3a'; g.fillRect(0, i * sh, w, sh + 1); }
      // soft noise
      for (var n = 0; n < 9000; n++) { g.fillStyle = 'rgba(' + (Math.random() < .5 ? '0,0,0' : '255,255,255') + ',' + (Math.random() * .05).toFixed(3) + ')'; g.fillRect(Math.random() * w, Math.random() * h, 2 + Math.random() * 5, 2 + Math.random() * 5); }
      // markings (scaled so the centre circle sits at the ball)
      var sx = w / PW, sz = h / PL, cx = w / 2, cy = h / 2;
      g.strokeStyle = 'rgba(255,255,255,.88)'; g.lineWidth = 5;
      g.strokeRect(cx - 34 * sx, cy - 52 * sz, 68 * sx, 104 * sz);
      g.beginPath(); g.moveTo(cx - 34 * sx, cy); g.lineTo(cx + 34 * sx, cy); g.stroke();
      g.beginPath(); g.ellipse(cx, cy, 9.15 * sx, 9.15 * sz, 0, 0, Math.PI * 2); g.stroke();
      g.fillStyle = 'rgba(255,255,255,.9)'; g.beginPath(); g.ellipse(cx, cy, .35 * sx, .35 * sz, 0, 0, Math.PI * 2); g.fill();
      [-1, 1].forEach(function (d) { g.strokeRect(cx - 20.16 * sx, d < 0 ? cy - 52 * sz : cy + 52 * sz - 16.5 * sz, 40.32 * sx, 16.5 * sz); });
    });
    var pitch = new THREE.Mesh(track(new THREE.PlaneGeometry(PW, PL)), track(new THREE.MeshStandardMaterial({ map: pitchTex, roughness: 1, metalness: 0 })));
    pitch.rotation.x = -Math.PI / 2; pitch.receiveShadow = true; scene.add(pitch);

    // grass detail near the ball: tiled speckle overlay + instanced blades
    var detailTex = canvasTex(256, 256, function (g, w, h) {
      for (var i = 0; i < 2600; i++) {
        var lite = Math.random() < .5; g.fillStyle = lite ? 'rgba(160,255,150,' + (Math.random() * .22).toFixed(2) + ')' : 'rgba(0,25,5,' + (Math.random() * .3).toFixed(2) + ')';
        g.fillRect(Math.random() * w, Math.random() * h, 1, 2 + Math.random() * 5);
      }
    }, [14, 14]);
    var detail = new THREE.Mesh(track(new THREE.PlaneGeometry(28, 28)), track(new THREE.MeshBasicMaterial({ map: detailTex, transparent: true, depthWrite: false, opacity: .9 })));
    detail.rotation.x = -Math.PI / 2; detail.position.y = 0.003; scene.add(detail);

    var BLADES = small ? 900 : 3200;
    var bladeGeo = track(new THREE.ConeGeometry(0.012, 0.11, 3)); bladeGeo.translate(0, .055, 0);
    var blades = new THREE.InstancedMesh(bladeGeo, track(new THREE.MeshStandardMaterial({ color: 0x2f8f48, roughness: .9 })), BLADES);
    var dm = new THREE.Object3D();
    for (var b = 0; b < BLADES; b++) {
      var a = Math.random() * Math.PI * 2, r = Math.sqrt(Math.random()) * 4.2;
      dm.position.set(Math.cos(a) * r + .1, 0, Math.sin(a) * r);
      dm.rotation.set((Math.random() - .5) * .5, Math.random() * 6, (Math.random() - .5) * .5);
      var sc = .6 + Math.random() * .9; dm.scale.set(sc, sc * (.7 + Math.random() * .8), sc);
      dm.updateMatrix(); blades.setMatrixAt(b, dm.matrix);
    }
    scene.add(blades);

    /* ----- stadium bowl + crowd ----- */
    var SX = 1.0, SZ = 1.35, R0 = 40, R1 = 64, SH = 26;
    var crowdTex = canvasTex(1024, 256, function (g, w, h) {
      g.fillStyle = '#0a0f0d'; g.fillRect(0, 0, w, h);
      var cols = ['#1b2a24', '#2c3d35', '#3a4a42', '#53695d', '#6f8a7b', '#7b3030', '#d0d6d2', '#2f5a45'];
      for (var y = 4; y < h; y += 7) for (var x = (y / 7 % 2) * 4; x < w; x += 8) {
        g.fillStyle = cols[(Math.random() * cols.length) | 0]; g.globalAlpha = .55 + Math.random() * .45;
        g.fillRect(x, y, 5, 5);
        if (Math.random() < .012) { g.globalAlpha = 1; g.fillStyle = '#fff'; g.fillRect(x + 1, y - 2, 3, 3); }
      }
      g.globalAlpha = 1;
      var gr = g.createLinearGradient(0, 0, 0, h); gr.addColorStop(0, 'rgba(0,0,0,0)'); gr.addColorStop(.7, 'rgba(0,0,0,.25)'); gr.addColorStop(1, 'rgba(0,0,0,.85)');
      g.fillStyle = gr; g.fillRect(0, 0, w, h);
    }, [7, 1]);
    var bowlGeo = track(new THREE.CylinderGeometry(R1, R0, SH, 72, 1, true));
    var bowl = new THREE.Mesh(bowlGeo, track(new THREE.MeshBasicMaterial({ map: crowdTex, color: 0xb4c4bb, side: THREE.BackSide })));
    bowl.position.y = SH / 2 + .4; bowl.scale.set(SX, 1, SZ); scene.add(bowl);
    // roof ring + dark outer wall so the sky edge reads as a stadium
    var roof = new THREE.Mesh(track(new THREE.CylinderGeometry(R1 + 8, R1, 8, 72, 1, true)), track(new THREE.MeshBasicMaterial({ color: 0x050908, side: THREE.BackSide })));
    roof.position.y = SH + 4.4; roof.scale.set(SX, 1, SZ); scene.add(roof);

    // twinkling crowd lights
    function crowdLights(n, size) {
      var pos = new Float32Array(n * 3);
      for (var i = 0; i < n; i++) {
        var th = Math.random() * Math.PI * 2, t = Math.random();
        var r = lerp(R0, R1, t) - .6;
        pos[i * 3] = Math.cos(th) * r * SX; pos[i * 3 + 1] = lerp(.8, SH, t) + .4; pos[i * 3 + 2] = Math.sin(th) * r * SZ;
      }
      var g = track(new THREE.BufferGeometry()); g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
      var m = track(new THREE.PointsMaterial({ map: glow, size: size, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, color: 0xdfeee6, opacity: .8 }));
      var p = new THREE.Points(g, m); scene.add(p); return m;
    }
    var twinkleA = crowdLights(small ? 220 : 520, 1.1), twinkleB = crowdLights(small ? 200 : 460, .8);

    /* ----- floodlights ----- */
    var towers = [], beams = [], glows = [];
    function floodlight(x, y, z, rim) {
      var g = new THREE.Group(); g.position.set(x, y, z);
      var panel = new THREE.Mesh(track(new THREE.BoxGeometry(9, 5, .5)), track(new THREE.MeshBasicMaterial({ color: 0xffffff, toneMapped: false })));
      panel.lookAt(0, 1, 0); g.add(panel);
      var sp = new THREE.Sprite(track(new THREE.SpriteMaterial({ map: glow, color: 0xe8f4ff, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false, opacity: .9, fog: false })));
      sp.scale.set(46, 46, 1); g.add(sp); glows.push(sp);
      scene.add(g); towers.push(g);
      // visible beam toward the pitch
      var target = new THREE.Vector3(0, .6, 0), L = target.distanceTo(g.position);
      var cg = track(new THREE.ConeGeometry(7.5, L, 28, 1, true)); cg.translate(0, -L / 2, 0); cg.rotateX(-Math.PI / 2);
      var cone = new THREE.Mesh(cg, track(new THREE.MeshBasicMaterial({ color: 0xbfe8d4, transparent: true, opacity: .05, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide, fog: false })));
      cone.position.copy(g.position); cone.lookAt(target); scene.add(cone); beams.push(cone);
      if (rim) {
        var s = new THREE.SpotLight(0xdce8ff, 2, 0, .42, .7, 1);
        s.position.copy(g.position); s.target.position.set(0, 1, -.3); scene.add(s, s.target); return s;
      }
    }
    var rimA = floodlight(-30, 30, -50, true), rimB = floodlight(30, 30, -50, true);
    floodlight(-34, 30, 44, false); floodlight(34, 30, 44, false);

    var key = new THREE.SpotLight(0xfff6ea, 1.2, 0, .5, .8, 1);
    key.position.set(-7, 15, 11); key.target.position.set(0, .8, -.2);
    key.castShadow = !small; key.shadow.mapSize.set(1024, 1024); key.shadow.bias = -.0004; key.shadow.camera.near = 4; key.shadow.camera.far = 40;
    scene.add(key, key.target);
    var hemi = new THREE.HemisphereLight(0x6f9a86, 0x0b1a10, .42); scene.add(hemi);
    var ballLight = new THREE.PointLight(0x22f58b, 0, 7, 2); scene.add(ballLight);

    /* ----- ball ----- */
    var BR = .15, BALL0 = new THREE.Vector3(0, BR, 0);
    var ballTex = (function () {
      var phi = (1 + Math.sqrt(5)) / 2, dirs = [];
      [[0, 1, phi], [0, -1, phi], [0, 1, -phi], [0, -1, -phi], [1, phi, 0], [-1, phi, 0], [1, -phi, 0], [-1, -phi, 0], [phi, 0, 1], [-phi, 0, 1], [phi, 0, -1], [-phi, 0, -1]].forEach(function (v) {
        var l = Math.hypot(v[0], v[1], v[2]); dirs.push([v[0] / l, v[1] / l, v[2] / l, 1]);
      });
      var verts = dirs.slice(), faces = [];
      for (var i = 0; i < 12; i++) for (var j = i + 1; j < 12; j++) for (var k = j + 1; k < 12; k++) {
        var a = verts[i], b = verts[j], c = verts[k];
        var dij = a[0] * b[0] + a[1] * b[1] + a[2] * b[2], dik = a[0] * c[0] + a[1] * c[1] + a[2] * c[2], djk = b[0] * c[0] + b[1] * c[1] + b[2] * c[2];
        if (Math.abs(dij - .4472) < .01 && Math.abs(dik - .4472) < .01 && Math.abs(djk - .4472) < .01) {
          var x = a[0] + b[0] + c[0], y = a[1] + b[1] + c[1], z = a[2] + b[2] + c[2], l = Math.hypot(x, y, z); faces.push([x / l, y / l, z / l, 0]);
        }
      }
      var pts = verts.concat(faces);
      return canvasTex(512, 256, function (g, w, h) {
        var img = g.createImageData(w, h), d = img.data;
        for (var py = 0; py < h; py++) {
          var lat = (.5 - (py + .5) / h) * Math.PI, cl = Math.cos(lat), sl = Math.sin(lat);
          for (var px = 0; px < w; px++) {
            var lon = ((px + .5) / w) * Math.PI * 2, vx = cl * Math.cos(lon), vy = sl, vz = cl * Math.sin(lon);
            var b1 = -2, b2 = -2, t1 = 0;
            for (var q = 0; q < pts.length; q++) {
              var dd = vx * pts[q][0] + vy * pts[q][1] + vz * pts[q][2];
              if (dd > b1) { b2 = b1; b1 = dd; t1 = pts[q][3]; } else if (dd > b2) b2 = dd;
            }
            var o = (py * w + px) * 4, v = 246, seam = b1 - b2 < .018;
            if (t1 === 1) v = 18; else if (seam) v = 120;
            d[o] = d[o + 1] = d[o + 2] = v; d[o + 3] = 255;
          }
        }
        g.putImageData(img, 0, 0);
      });
    })();
    var ball = new THREE.Mesh(track(new THREE.SphereGeometry(BR, 36, 24)), track(new THREE.MeshStandardMaterial({ map: ballTex, roughness: .42, metalness: 0 })));
    ball.castShadow = true; ball.position.copy(BALL0); scene.add(ball);

    // trail (a pure function of scroll, so it scrubs both ways)
    var TRAIL = small ? 26 : 46, trailPos = new Float32Array(TRAIL * 3), trailCol = new Float32Array(TRAIL * 3);
    var trailGeo = track(new THREE.BufferGeometry());
    trailGeo.setAttribute('position', new THREE.BufferAttribute(trailPos, 3)); trailGeo.setAttribute('color', new THREE.BufferAttribute(trailCol, 3));
    var trail = new THREE.Points(trailGeo, track(new THREE.PointsMaterial({ map: glow, size: .55, vertexColors: true, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, fog: false })));
    trail.frustumCulled = false; scene.add(trail);

    // floating dust
    var DUST = small ? 140 : 320, dustBase = new Float32Array(DUST * 3), dustPos = new Float32Array(DUST * 3);
    for (var d = 0; d < DUST; d++) { dustBase[d * 3] = (Math.random() - .5) * 20; dustBase[d * 3 + 1] = Math.random() * 7; dustBase[d * 3 + 2] = -8 + Math.random() * 16; }
    var dustGeo = track(new THREE.BufferGeometry()); dustGeo.setAttribute('position', new THREE.BufferAttribute(dustPos, 3));
    var dust = new THREE.Points(dustGeo, track(new THREE.PointsMaterial({ map: glow, size: .09, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, opacity: .55, color: 0xcfe9dc })));
    dust.frustumCulled = false; scene.add(dust);

    /* ----- player (procedural rigged figure) ----- */
    var mat = function (c, r) { return track(new THREE.MeshStandardMaterial({ color: c, roughness: r == null ? .62 : r, metalness: 0 })); };
    var M = { skin: mat(0xb97d5a, .7), hair: mat(0x15110e, .8), jersey: mat(0x0e9f62, .55), trim: mat(0xf4f7f5, .6), shorts: mat(0x0a1210, .6), sock: mat(0x0e9f62, .7), boot: mat(0x22f58b, .35), glove: mat(0xf4f7f5, .6) };
    function mesh(geo, m, x, y, z) { var o = new THREE.Mesh(track(geo), m); o.position.set(x || 0, y || 0, z || 0); o.castShadow = true; return o; }

    var player = new THREE.Group(), hips = new THREE.Group();
    player.add(hips); hips.position.y = 1.02; player.scale.setScalar(.95);
    // pelvis + torso
    hips.add(mesh(new THREE.CylinderGeometry(.18, .2, .22, 16), M.shorts, 0, .02, 0));
    var torso = new THREE.Group(); torso.position.y = .08; hips.add(torso);
    var chest = mesh(new THREE.CapsuleGeometry(.16, .34, 6, 16), M.jersey, 0, .31, 0); chest.scale.set(1.32, 1, .8); torso.add(chest);
    var stripe = mesh(new THREE.BoxGeometry(.08, .5, .03), M.trim, 0, .33, .13); torso.add(stripe);
    torso.add(mesh(new THREE.CylinderGeometry(.05, .06, .09, 10), M.skin, 0, .64, 0));
    var head = new THREE.Group(); head.position.set(0, .78, 0); torso.add(head);
    head.add(mesh(new THREE.SphereGeometry(.118, 22, 16), M.skin, 0, 0, 0));
    var hair = mesh(new THREE.SphereGeometry(.124, 22, 12, 0, Math.PI * 2, 0, Math.PI * .52), M.hair, 0, .012, -.012); hair.rotation.x = -.18; head.add(hair);
    [-.045, .045].forEach(function (x) { head.add(mesh(new THREE.SphereGeometry(.012, 8, 8), M.hair, x, .01, .108)); });
    // arms
    function arm(side) {
      var sh = new THREE.Group(); sh.position.set(side * .235, .53, 0); torso.add(sh);
      sh.add(mesh(new THREE.CapsuleGeometry(.052, .2, 4, 10), M.skin, 0, -.15, 0));
      sh.add(mesh(new THREE.CapsuleGeometry(.062, .1, 4, 10), M.jersey, 0, -.085, 0));
      var el = new THREE.Group(); el.position.y = -.3; sh.add(el);
      el.add(mesh(new THREE.CapsuleGeometry(.043, .2, 4, 10), M.skin, 0, -.13, 0));
      el.add(mesh(new THREE.SphereGeometry(.05, 10, 8), M.skin, 0, -.29, 0));
      return { sh: sh, el: el };
    }
    var armL = arm(1), armR = arm(-1);
    // legs
    function leg(side) {
      var th = new THREE.Group(); th.position.set(side * .1, -.04, 0); hips.add(th);
      th.add(mesh(new THREE.CapsuleGeometry(.082, .3, 4, 12), M.skin, 0, -.23, 0));
      th.add(mesh(new THREE.CylinderGeometry(.095, .105, .24, 14), M.shorts, 0, -.12, 0));
      var kn = new THREE.Group(); kn.position.y = -.46; th.add(kn);
      kn.add(mesh(new THREE.CapsuleGeometry(.062, .3, 4, 12), M.sock, 0, -.22, 0));
      var ft = new THREE.Group(); ft.position.y = -.46; kn.add(ft);
      ft.add(mesh(new THREE.BoxGeometry(.095, .07, .27), M.boot, 0, -.035, .065));
      var toe = new THREE.Object3D(); toe.position.set(0, -.065, .2); ft.add(toe);
      var heel = new THREE.Object3D(); heel.position.set(0, -.065, -.04); ft.add(heel);
      return { th: th, kn: kn, ft: ft, toe: toe, heel: heel };
    }
    var legK = leg(-1), legS = leg(1);   // kicking (player's right) / support (left)
    scene.add(player);

    // pose keyframes: [rootZ(rel), lean, twist, kT, kS, kF, sT, sS, armLx, armRx, spread, headX]
    var KP = [0, .34, .6, .76, 1];
    var KEYS = [
      [-.42, .10, 0,    .05, .10, 0,   -.06, .10, -.06, .06, .20, .12],
      [-.30, .04, .10,  .55, .85, .2,  -.22, .25, -.30, .25, .30, .06],
      [-.16, -.10, .30, 1.00, 1.50, .35, -.40, .55, -.85, .55, .55, 0],
      [0,    .22, -.15, -.32, .14, -.1, -.38, .55, .30, -.65, .70, .10],
      [.50,  .34, -.40, -1.25, .35, -.5, -.20, .25, .90, -.95, .95, .12]
    ];
    var poseV = new Array(12), strikeRootZ = 0;
    function samplePose(kp) {
      var i = 0; while (i < KP.length - 2 && kp > KP[i + 1]) i++;
      var t = smooth(KP[i], KP[i + 1], kp), a = KEYS[i], b = KEYS[i + 1];
      for (var n = 0; n < 12; n++) poseV[n] = lerp(a[n], b[n], t);
      return poseV;
    }
    var sole = new THREE.Vector3();
    function applyPose(kp, hop) {
      var v = samplePose(kp);
      torso.rotation.set(v[1], v[2], 0);
      legK.th.rotation.x = v[3]; legK.kn.rotation.x = v[4]; legK.ft.rotation.x = v[5];
      legS.th.rotation.x = v[6]; legS.kn.rotation.x = v[7]; legS.ft.rotation.x = -.1;
      armL.sh.rotation.set(v[8], 0, v[10] * .55); armR.sh.rotation.set(v[9], 0, -v[10] * .55);
      armL.el.rotation.x = -.35 - Math.abs(v[8]) * .5; armR.el.rotation.x = -.35 - Math.abs(v[9]) * .5;
      head.rotation.x = v[11];
      player.position.z = strikeRootZ + v[0];
      // plant the lowest sole on the grass so the figure never floats or sinks
      hips.position.y = 1.0; player.position.y = 0; player.updateMatrixWorld(true);
      var low = 9;
      [legK, legS].forEach(function (L) { [L.toe, L.heel].forEach(function (p) { p.getWorldPosition(sole); low = Math.min(low, sole.y); }); });
      player.position.y = -low + hop;
    }
    // work out where the body must be so the kicking toe meets the ball at contact
    strikeRootZ = 0; applyPose(KP[3], 0); player.updateMatrixWorld(true);
    legK.toe.getWorldPosition(sole); strikeRootZ = BALL0.z - sole.z - BR * .55 - .02;

    /* ----- timeline (everything below is a pure function of scroll progress P) ----- */
    var P_START = .035, P_STRIKE = .42, P_END = .86;
    function kickParam(P) {
      if (P <= P_STRIKE) return KP[3] * clamp((P - P_START) / (P_STRIKE - P_START), 0, 1);
      return KP[3] + (1 - KP[3]) * smooth(P_STRIKE, P_STRIKE + .16, P);
    }
    var cam0 = new THREE.Vector3(2.7, 1.5, 6.4), cam1 = new THREE.Vector3(1.15, .78, 3.05), cam2 = new THREE.Vector3(.8, .72, 2.55);
    var look0 = new THREE.Vector3(0, 1.0, -.5), look1 = new THREE.Vector3(.02, .42, .05);
    var tmpA = new THREE.Vector3(), camPos = new THREE.Vector3(), camLook = new THREE.Vector3(), ballPos = new THREE.Vector3(), ballEnd = new THREE.Vector3();

    function camAt(P, out) {
      var a = smooth(.0, .4, P), b = smooth(.4, 1, P);
      out.copy(cam0).lerp(cam1, a).lerp(cam2, b * .8);
      return out;
    }
    function ballAt(P, out) {
      if (P < P_STRIKE) {
        // gentle settle as the boot approaches
        var near = smooth(P_STRIKE - .08, P_STRIKE, P);
        return out.copy(BALL0).add(tmpA.set(0, 0, -near * .01));
      }
      var u = clamp((P - P_STRIKE) / (P_END - P_STRIKE), 0, 1), s = Math.pow(u, 1.7);
      camAt(.92, ballEnd); ballEnd.y -= .02; ballEnd.z -= .55; ballEnd.x -= .04;
      out.copy(BALL0).lerp(ballEnd, s);
      out.y += Math.sin(Math.PI * Math.min(1, s * 1.15)) * .55 * (1 - s * .5);
      return out;
    }

    /* ----- sizing ----- */
    var W = 1, H = 1;
    function resize() {
      var r = stage.getBoundingClientRect(); W = Math.max(2, r.width | 0); H = Math.max(2, r.height | 0);
      renderer.setSize(W, H, false);
      camera.aspect = W / H;
      if (W / H > 1.25) camera.setViewOffset(W, H, -W * .13, 0, W, H); else camera.clearViewOffset();
      camera.updateProjectionMatrix();
      fit = W / H < 1 ? 1 + (1 - W / H) * .95 : 1;
    }
    var fit = 1;
    resize();
    var ro = 'ResizeObserver' in window ? new ResizeObserver(resize) : null;
    if (ro) ro.observe(stage); else window.addEventListener('resize', resize);

    /* ----- scroll → progress ----- */
    var target = 0, prog = 0, st = null;
    if (scrubbing && window.gsap && window.ScrollTrigger) {
      window.gsap.registerPlugin(window.ScrollTrigger);
      st = window.ScrollTrigger.create({ trigger: hero, start: 'top top', end: 'bottom bottom', onUpdate: function (self) { target = self.progress; } });
      window.gsap.from([$('.eyebrow', copy), $('.hero-title', copy), $('.hero-sub', copy), $('.hero-cta', copy)], { y: 36, opacity: 0, duration: 1, stagger: .12, ease: 'power3.out', delay: .15 });
    } else if (scrubbing) {
      var calc = function () { var r = hero.getBoundingClientRect(); target = clamp(-r.top / (r.height - innerHeight), 0, 1); };
      window.addEventListener('scroll', calc, { passive: true }); calc();
    }

    /* ----- render ----- */
    var t0 = performance.now(), running = false, raf = 0, frames = 0, slow = 0, lastT = performance.now();
    function render(now) {
      raf = 0;
      var time = (now - t0) / 1000;
      prog += (target - prog) * (scrubbing ? .14 : 1);
      if (Math.abs(target - prog) < .0004) prog = target;
      var P = prog;

      // player + ball
      var kp = kickParam(P), hop = smooth(P_STRIKE + .01, P_STRIKE + .1, P) * (1 - smooth(P_STRIKE + .1, P_STRIKE + .2, P)) * .12;
      applyPose(kp, hop);
      ballAt(P, ballPos);
      ball.position.copy(ballPos);
      var u = clamp((P - P_STRIKE) / (P_END - P_STRIKE), 0, 1), speed = P > P_STRIKE ? smooth(0, .25, u) : 0;
      ball.rotation.x = u * 22; ball.rotation.z = u * 9;
      ball.scale.set(1, 1, 1 + speed * .5); ball.lookAt(camPos.x, camPos.y, camPos.z); ball.rotateZ(u * 9);

      // camera path, push-in, shake, fov
      camAt(P, camPos);
      camLook.copy(look0).lerp(look1, smooth(0, .36, P));
      var follow = smooth(P_STRIKE, P_END, P) * .75; camLook.lerp(ballPos, follow);
      var shake = smooth(P_STRIKE, P_STRIKE + .015, P) * (1 - smooth(P_STRIKE + .015, P_STRIKE + .13, P)) * .06 + speed * .006;
      camPos.sub(camLook).multiplyScalar(fit).add(camLook);
      camera.position.set(camPos.x + Math.sin(time * 61) * shake, camPos.y + Math.cos(time * 53) * shake, camPos.z + Math.sin(time * 47) * shake * .6);
      camera.lookAt(camLook);
      var fov = lerp(44, 34, smooth(0, .4, P)); fov = lerp(fov, 66, smooth(P_STRIKE, P_END, P));
      if (Math.abs(camera.fov - fov) > .01) { camera.fov = fov; camera.updateProjectionMatrix(); }

      // lighting intensifies with scroll
      var li = smooth(0, .45, P);
      rimA.intensity = rimB.intensity = lerp(1.4, 4.2, li);
      key.intensity = lerp(1.1, 2.9, li);
      hemi.intensity = lerp(.42, .6, li);
      renderer.toneMappingExposure = lerp(.95, 1.28, li);
      beams.forEach(function (c) { c.material.opacity = lerp(.045, .13, li); });
      glows.forEach(function (g, i) { g.material.opacity = lerp(.75, 1, li) * (.94 + Math.sin(time * 1.3 + i) * .06); });
      twinkleA.opacity = .55 + Math.sin(time * 2.1) * .3; twinkleB.opacity = .55 + Math.cos(time * 1.7) * .3;
      ballLight.position.copy(ballPos); ballLight.intensity = speed * 2.4;

      // trail
      for (var i = 0; i < TRAIL; i++) {
        var Pi = P - i * .0075;
        if (P > P_STRIKE + .004 && Pi >= P_STRIKE) {
          ballAt(Pi, tmpA);
          trailPos[i * 3] = tmpA.x; trailPos[i * 3 + 1] = tmpA.y; trailPos[i * 3 + 2] = tmpA.z;
          var f = (1 - i / TRAIL) * Math.min(1, speed * 3) * .95;
          trailCol[i * 3] = .15 * f; trailCol[i * 3 + 1] = 1 * f; trailCol[i * 3 + 2] = .55 * f;
        } else { trailPos[i * 3] = ballPos.x; trailPos[i * 3 + 1] = ballPos.y; trailPos[i * 3 + 2] = ballPos.z; trailCol[i * 3] = trailCol[i * 3 + 1] = trailCol[i * 3 + 2] = 0; }
      }
      trailGeo.attributes.position.needsUpdate = true; trailGeo.attributes.color.needsUpdate = true;

      // dust drift
      for (var k = 0; k < DUST; k++) {
        dustPos[k * 3] = dustBase[k * 3] + Math.sin(time * .15 + k) * .5;
        dustPos[k * 3 + 1] = (dustBase[k * 3 + 1] + time * .06 * (1 + (k % 5) * .3)) % 7;
        dustPos[k * 3 + 2] = dustBase[k * 3 + 2] + Math.cos(time * .12 + k * 1.7) * .5;
      }
      dustGeo.attributes.position.needsUpdate = true;

      renderer.render(scene, camera);

      // DOM overlays
      var fadeCopy = 1 - smooth(.03, .2, P);
      copy.style.opacity = fadeCopy.toFixed(3);
      copy.style.transform = (W > 860 ? 'translateY(calc(-50% - ' + (smooth(0, .2, P) * 50).toFixed(1) + 'px))' : 'translateY(' + (-smooth(0, .2, P) * 50).toFixed(1) + 'px)');
      copy.style.pointerEvents = fadeCopy < .2 ? 'none' : '';
      cue.style.opacity = (1 - smooth(.01, .08, P)).toFixed(3);
      speedFx.style.opacity = (smooth(P_STRIKE, P_STRIKE + .1, P) * (1 - smooth(.82, .9, P)) * .9).toFixed(3);
      speedFx.style.transform = 'scale(' + (1 + u * .5).toFixed(3) + ')';
      flash.style.opacity = (smooth(.8, .89, P) * (1 - smooth(.93, .99, P))).toFixed(3);
      fade.style.opacity = smooth(.93, 1, P).toFixed(3);

      // quality guard: if the first seconds are slow, trade pixels for smoothness
      var dt = now - lastT; lastT = now; frames++;
      if (frames > 20 && frames < 140 && dt > 40) slow++;
      if (frames === 140 && slow > 55) { renderer.setPixelRatio(1); key.castShadow = false; resize(); }

      if (running && (scrubbing || Math.abs(target - prog) > .0004)) raf = requestAnimationFrame(render);
    }
    function start() { if (!running) { running = true; if (!raf) raf = requestAnimationFrame(render); } }
    function stop() { running = false; if (raf) cancelAnimationFrame(raf); raf = 0; }

    // only animate while the hero is on screen
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (e) { e[0].isIntersecting ? start() : stop(); }, { threshold: 0 }).observe(hero);
    } else start();
    if (!scrubbing) { target = prog = 0; render(performance.now()); }
    doc.addEventListener('visibilitychange', function () { doc.hidden ? stop() : start(); });
    canvas.addEventListener('webglcontextlost', function (e) { e.preventDefault(); stop(); fallback('context lost'); });
    window.__heroRender = function (p) { target = prog = p; render(performance.now()); };   // debugging aid

    // clean up GPU resources when leaving the page
    window.addEventListener('pagehide', function () {
      stop(); if (st) st.kill(); if (ro) ro.disconnect();
      disposables.forEach(function (d) { if (d && d.dispose) d.dispose(); });
      renderer.dispose();
    }, { once: true });
  }
})();
