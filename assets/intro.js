/* Stadium Pass — intro video controller (appearance only; no requests, no booking data). */
(function () {
  'use strict';
  var root = document.documentElement, intro = document.getElementById('intro');
  if (!intro) { root.classList.remove('has-intro'); return; }
  if (!root.classList.contains('has-intro')) { intro.parentNode.removeChild(intro); return; }
  root.classList.add('intro-ready');

  var video = intro.querySelector('.intro__video');
  var skipBtn = intro.querySelector('[data-intro-skip]'), enterBtn = intro.querySelector('[data-intro-enter]');
  var playBtn = intro.querySelector('[data-intro-play]'), soundBtn = intro.querySelector('[data-intro-sound]');
  var behind = [].slice.call(document.querySelectorAll('.site-header, .hero, .fixtures, .site-footer'));
  var done = false, src = intro.getAttribute('data-src'), enterTimer = 0, loadTimer = 0;

  function setInert(on) { behind.forEach(function (el) { try { if (on) el.setAttribute('inert', ''); else el.removeAttribute('inert'); } catch (e) {} }); }
  setInert(true);

  function showEnter() { enterBtn.classList.add('is-in'); }
  enterTimer = setTimeout(showEnter, 1500);

  function finish() {
    if (done) return; done = true;
    clearTimeout(enterTimer); clearTimeout(loadTimer);
    try { sessionStorage.setItem('sp_intro_seen', '1'); } catch (e) {}
    try { video.pause(); } catch (e) {}
    root.classList.remove('has-intro');               // unlock scroll right away
    root.classList.add('intro-ready');
    intro.style.display = 'block';                    // keep it visible while it fades
    intro.classList.add('is-leaving');
    setInert(false);
    window.dispatchEvent(new Event('resize'));
    setTimeout(function () {
      if (intro.parentNode) intro.parentNode.removeChild(intro);
      window.dispatchEvent(new Event('resize'));
      var cta = document.querySelector('.hero-cta'); if (cta && cta.focus) cta.focus({ preventScroll: true });
    }, 950);
  }

  function blocked() {
    intro.classList.remove('is-loading');
    intro.classList.add('is-blocked');
    playBtn.hidden = false; showEnter();
  }

  function start() {
    intro.classList.add('is-loading'); intro.classList.remove('is-blocked'); playBtn.hidden = true;
    if (!video.getAttribute('src')) { video.src = src; }
    var p; try { p = video.play(); } catch (e) { blocked(); return; }
    if (p && p.catch) p.catch(function (err) { if (!done && err && err.name !== 'AbortError') blocked(); });
    clearTimeout(loadTimer);
    loadTimer = setTimeout(function () { if (!done && !intro.classList.contains('is-playing')) showEnter(); }, 4000);
  }

  video.addEventListener('playing', function () {
    intro.classList.remove('is-loading', 'is-blocked'); intro.classList.add('is-playing'); playBtn.hidden = true;
    soundBtn.hidden = false;
  });
  video.addEventListener('waiting', function () { if (!done) intro.classList.add('is-loading'); });
  video.addEventListener('canplay', function () { intro.classList.remove('is-loading'); });
  video.addEventListener('timeupdate', function () {
    if (video.duration && video.duration - video.currentTime < 1) intro.classList.add('is-ending');
  });
  video.addEventListener('ended', finish);
  video.addEventListener('error', finish);            // missing / undecodable file: go straight to the site

  skipBtn.addEventListener('click', finish);
  enterBtn.addEventListener('click', finish);
  playBtn.addEventListener('click', start);
  soundBtn.addEventListener('click', function () {
    video.muted = !video.muted;
    soundBtn.setAttribute('aria-pressed', String(!video.muted));
    soundBtn.querySelector('span').textContent = video.muted ? 'Sound off' : 'Sound on';
  });
  document.addEventListener('keydown', function onKey(e) {
    if (done) { document.removeEventListener('keydown', onKey); return; }
    if (e.key === 'Escape') finish();
  });

  // Save-Data / very slow connections: don't pull the video until the visitor asks for it.
  var c = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  if (c && (c.saveData || /(^|slow-)2g$/.test(c.effectiveType || ''))) blocked(); else start();
  skipBtn.focus({ preventScroll: true });
})();
