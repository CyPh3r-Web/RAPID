/**
 * RAPID landing — scroll reveal and the exploded 3D phone.
 */
(function () {
  'use strict';

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer = window.matchMedia('(pointer: fine)').matches;

  /* ----- Reveal on scroll ----- */
  var revealNodes = document.querySelectorAll('[data-lp-reveal]');
  if (revealNodes.length) {
    if (reduce || !('IntersectionObserver' in window)) {
      revealNodes.forEach(function (el) { el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        });
      }, { threshold: 0.16, rootMargin: '0px 0px -8% 0px' });
      revealNodes.forEach(function (el) { io.observe(el); });
    }
  }

  /* ----- Exploded phone: view toggle, part focus, pointer rotation ----- */
  var stage = document.querySelector('[data-rig-stage]');
  var rig = stage && stage.querySelector('[data-rig]');
  if (!rig) return;

  var order = ['back', 'battery', 'board', 'frame', 'screen'];
  var layers = {};
  order.forEach(function (key) {
    layers[key] = rig.querySelector('[data-layer="' + key + '"]');
  });
  var modeButtons = stage.querySelectorAll('[data-rig-mode]');
  var focusButtons = stage.querySelectorAll('[data-rig-focus]');

  var exploded = true;
  var focus = '';
  var x = 0;
  var y = 0;
  var raf = 0;

  function apply() {
    raf = 0;
    var gap = exploded ? 78 : 3;
    order.forEach(function (key, i) {
      var layer = layers[key];
      if (!layer) return;
      layer.style.transform = 'translateZ(' + ((i - 2) * gap) + 'px)';
      layer.classList.toggle('is-focus', focus === key);
    });
    rig.style.transform = exploded
      ? 'rotateX(' + (58 - y * 8).toFixed(2) + 'deg) rotateZ(' + (-36 + x * 16).toFixed(2) + 'deg)'
      : 'rotateX(' + (10 - y * 12).toFixed(2) + 'deg) rotateY(' + (-22 + x * 26).toFixed(2) + 'deg)';
    stage.classList.toggle('is-exploded', exploded);
    modeButtons.forEach(function (btn) {
      var on = (btn.getAttribute('data-rig-mode') === 'exploded') === exploded;
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    focusButtons.forEach(function (btn) {
      var on = btn.getAttribute('data-rig-focus') === focus;
      btn.classList.toggle('is-focus', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function schedule() {
    if (!raf) raf = window.requestAnimationFrame(apply);
  }

  /* Pin each label to its part: measure the layer's projected box every frame
     (it moves with rotation, the view toggle and the idle float). */
  var callouts = {};
  focusButtons.forEach(function (btn) {
    callouts[btn.getAttribute('data-rig-focus')] = btn;
  });

  function placeLabels() {
    if (!exploded) return;
    if (window.innerWidth <= 760) return; // labels hidden on narrow screens (see landing.css)
    var s = stage.getBoundingClientRect();
    var column = s.width - 230; // where label text starts
    var items = order.map(function (key) {
      var r = layers[key].getBoundingClientRect();
      return {
        key: key,
        x: r.left + r.width * 0.78 - s.left, // a point on the part's right side
        y: r.top + r.height / 2 - s.top
      };
    }).sort(function (a, b) { return a.y - b.y; });

    var prevY = -Infinity;
    items.forEach(function (it) {
      var btn = callouts[it.key];
      if (!btn) return;
      var y = Math.max(it.y, prevY + 54); // keep labels from overlapping
      prevY = y;
      var lead = Math.max(24, column - it.x);
      btn.style.transform = 'translate(' + it.x.toFixed(1) + 'px,' + y.toFixed(1) + 'px) translateY(-50%)';
      btn.style.setProperty('--lead', lead.toFixed(1) + 'px');
    });
  }

  function track() {
    placeLabels();
    window.requestAnimationFrame(track);
  }

  focusButtons.forEach(function (btn) {
    var key = btn.getAttribute('data-rig-focus');
    btn.addEventListener('mouseenter', function () { layers[key].classList.add('is-hover'); });
    btn.addEventListener('mouseleave', function () { layers[key].classList.remove('is-hover'); });
  });

  modeButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      exploded = btn.getAttribute('data-rig-mode') === 'exploded';
      if (!exploded) focus = '';
      schedule();
    });
  });

  focusButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-rig-focus');
      focus = focus === key ? '' : key;
      schedule();
    });
  });

  if (finePointer && !reduce) {
    stage.addEventListener('pointermove', function (e) {
      var r = stage.getBoundingClientRect();
      x = ((e.clientX - r.left) / r.width) * 2 - 1;
      y = ((e.clientY - r.top) / r.height) * 2 - 1;
      schedule();
    });
    stage.addEventListener('pointerleave', function () {
      x = 0;
      y = 0;
      schedule();
    });
  }

  apply();
  track();
})();
