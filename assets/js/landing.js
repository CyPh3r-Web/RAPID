/**
 * RAPID landing — 3D tilt, parallax, and entrance motion.
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

  /* ----- Hero 3D tilt + layered parallax ----- */
  var stage = document.querySelector('[data-lp-stage]');
  if (!stage || reduce) return;

  var cluster = stage.querySelector('[data-lp-cluster]');
  var layers = stage.querySelectorAll('[data-lp-depth]');
  var targetX = 0;
  var targetY = 0;
  var curX = 0;
  var curY = 0;
  var hovering = false;
  var raf = 0;

  function frame() {
    raf = 0;
    curX += (targetX - curX) * 0.08;
    curY += (targetY - curY) * 0.08;

    if (cluster) {
      cluster.style.transform =
        'rotateX(' + (-12 - curY * 14).toFixed(3) + 'deg) rotateY(' + (18 + curX * 22).toFixed(3) + 'deg)';
    }

    layers.forEach(function (layer) {
      var depth = parseFloat(layer.getAttribute('data-lp-depth') || '0');
      layer.style.transform =
        'translate3d(' + (curX * depth * 18).toFixed(2) + 'px,' +
        (curY * depth * -14).toFixed(2) + 'px,' +
        (depth * 24).toFixed(2) + 'px)';
    });

    if (Math.abs(targetX - curX) > 0.001 || Math.abs(targetY - curY) > 0.001 || hovering) {
      raf = window.requestAnimationFrame(frame);
    }
  }

  function kick() {
    if (!raf) raf = window.requestAnimationFrame(frame);
  }

  function onMove(e) {
    var rect = stage.getBoundingClientRect();
    var x = (e.clientX - rect.left) / rect.width;
    var y = (e.clientY - rect.top) / rect.height;
    targetX = Math.max(-1, Math.min(1, x * 2 - 1));
    targetY = Math.max(-1, Math.min(1, y * 2 - 1));
    hovering = true;
    kick();
  }

  function onLeave() {
    targetX = 0;
    targetY = 0;
    hovering = false;
    kick();
  }

  if (finePointer) {
    stage.addEventListener('pointermove', onMove);
    stage.addEventListener('pointerleave', onLeave);
  }
})();
