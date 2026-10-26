/* Stadium Pass — hero video scrubbed by scrolling (no requests, no booking data).
   Scroll position -> video.currentTime, smoothed; scrolling up plays it backwards. */
(function () {
  'use strict';
  var root = document.documentElement;
  var hero = document.querySelector('[data-hero]'), video = hero && hero.querySelector('.hero-video');
  if (!video) return;
  var copy = hero.querySelector('[data-hero-copy]'), cue = hero.querySelector('[data-scroll-cue]');
  var fade = hero.querySelector('.hero-fade'), speed = hero.querySelector('.hero-speed');

  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var c = navigator.connection || {};
  var saver = !!c.saveData || /(^|slow-)2g$/.test(c.effectiveType || '');

  function fail() { root.classList.add('video-failed', 'no-scrub'); }
  // Static hero (poster only) for reduced motion / Save-Data: nothing is downloaded.
  if (reduce || saver) { root.classList.add('no-scrub'); return; }

  var dur = 0, target = 0, shown = 0, raf = 0, seeking = false, last = -1;
  function clamp(x, a, b) { return Math.min(b, Math.max(a, x)); }

  function progress() {
    var r = hero.getBoundingClientRect(), span = hero.offsetHeight - window.innerHeight;
    return span > 0 ? clamp(-r.top / span, 0, 1) : 0;
  }

  function paintUI(p) {
    if (copy) { var o = 1 - clamp(p / 0.16, 0, 1); copy.style.opacity = o; copy.style.transform = 'translateY(calc(-50% - ' + (p * 60).toFixed(1) + 'px))'; copy.style.pointerEvents = o < .2 ? 'none' : ''; }
    if (cue) cue.style.opacity = 1 - clamp(p / 0.06, 0, 1);
    if (fade) fade.style.opacity = clamp((p - 0.9) / 0.1, 0, 1);   // ease into the dark fixtures section
  }

  function frame() {
    raf = 0;
    var p = progress(); target = p * dur; paintUI(p);
    var d = target - shown;
    if (Math.abs(d) > 0.01) shown += d * 0.18; else shown = target;   // smoothing
    if (!seeking && Math.abs(shown - last) > 0.015) {
      seeking = true; last = shown;
      try { video.currentTime = clamp(shown, 0, Math.max(0, dur - 0.05)); } catch (e) { seeking = false; }
    }
    if (Math.abs(target - shown) > 0.005) raf = requestAnimationFrame(frame);
  }
  function kick() { if (!raf) raf = requestAnimationFrame(frame); }

  video.addEventListener('seeked', function () { seeking = false; if (Math.abs(target - shown) > 0.005) kick(); });
  video.addEventListener('loadedmetadata', function () {
    dur = video.duration || 0;
    if (!dur || !isFinite(dur)) { fail(); return; }
    video.preload = 'auto';
    try { video.currentTime = 0.001; } catch (e) {}
  });
  video.addEventListener('loadeddata', function () { root.classList.add('video-ready'); kick(); });
  video.addEventListener('error', fail);

  window.addEventListener('scroll', kick, { passive: true });
  window.addEventListener('resize', kick);
  // Only touch the network once the page itself is ready.
  function load() { video.src = video.getAttribute('data-src'); video.load(); }
  if (document.readyState === 'complete') load(); else window.addEventListener('load', load);
  paintUI(progress());
})();
