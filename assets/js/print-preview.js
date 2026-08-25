(function (window, document) {
  'use strict';

  /*
   * The print preview is deliberately scaled with the existing
   * SIMPPrintPreview API instead of zooming the whole application.  This
   * keeps the paper readable while preserving one-finger scrolling and
   * makes the same gesture work in an iframe or in a standalone report tab.
   */
  var stage = document.querySelector('[data-sheet-stage]');
  var sheet = document.querySelector('[data-print-sheet]');
  var preview = window.SIMPPrintPreview;
  var surface = document.documentElement;

  if (!stage || !sheet || !preview || typeof preview.getZoom !== 'function' || typeof preview.setZoom !== 'function') return;
  if (surface && surface.getAttribute('data-simp-pinch-zoom') === 'true') return;
  if (surface) surface.setAttribute('data-simp-pinch-zoom', 'true');

  // Allow the document to pan with one finger. Two fingers are handled below
  // and mapped to the report's 50–400% zoom range.
  if (surface) surface.style.touchAction = 'pan-x pan-y';
  if (document.body) document.body.style.touchAction = 'pan-x pan-y';
  stage.style.touchAction = 'pan-x pan-y';

  var pinch = null;
  var webkitGestureZoom = null;

  function touchDistance(touches) {
    if (!touches || touches.length < 2) return 0;
    var dx = touches[0].clientX - touches[1].clientX;
    var dy = touches[0].clientY - touches[1].clientY;
    return Math.sqrt((dx * dx) + (dy * dy));
  }

  function updateZoom(percent) {
    // The parent application listens for the same custom event to keep the
    // toolbar percentage in sync when this report is embedded in a modal.
    var detail = { percent: percent };
    try {
      document.dispatchEvent(new CustomEvent('simp:print-zoom', { detail: detail }));
    } catch (error) {
      // CustomEvent is unavailable in a few older WebViews; the report still
      // remains zoomable, only the optional parent label is skipped.
    }
    if (window.parent && window.parent !== window && typeof window.parent.postMessage === 'function') {
      try { window.parent.postMessage({ type: 'simp-print-zoom', percent: percent }, '*'); } catch (error) { /* no-op */ }
    }
  }

  function startPinch(event) {
    if (webkitGestureZoom !== null) return;
    if (!event.touches || event.touches.length !== 2) return;
    var distance = touchDistance(event.touches);
    if (distance <= 0) return;
    pinch = { distance: distance, zoom: preview.getZoom() };
    if (event.cancelable) event.preventDefault();
  }

  function movePinch(event) {
    if (webkitGestureZoom !== null) return;
    if (!pinch || !event.touches || event.touches.length < 2) return;
    var distance = touchDistance(event.touches);
    if (distance <= 0) return;
    var percent = preview.setZoom(pinch.zoom * distance / pinch.distance);
    updateZoom(percent);
    if (event.cancelable) event.preventDefault();
  }

  function endPinch(event) {
    if (!event.touches || event.touches.length < 2) pinch = null;
  }

  // Safari exposes a separate WebKit gesture event stream. Use it when
  // available; Chrome/Android continue to use the standard touch distance.
  function startWebkitGesture(event) {
    if (typeof event.scale !== 'number') return;
    pinch = null;
    webkitGestureZoom = preview.getZoom();
    if (event.cancelable) event.preventDefault();
  }
  function changeWebkitGesture(event) {
    if (webkitGestureZoom === null || typeof event.scale !== 'number') return;
    var percent = preview.setZoom(webkitGestureZoom * event.scale);
    updateZoom(percent);
    if (event.cancelable) event.preventDefault();
  }
  function endWebkitGesture() {
    webkitGestureZoom = null;
  }

  // Capture the events so a table cell, image, or link cannot swallow the
  // second finger.  The listeners are non-passive because preventDefault is
  // needed to stop the browser from applying a second, competing zoom.
  document.addEventListener('touchstart', startPinch, { capture: true, passive: false });
  document.addEventListener('touchmove', movePinch, { capture: true, passive: false });
  document.addEventListener('touchend', endPinch, { capture: true, passive: true });
  document.addEventListener('touchcancel', endPinch, { capture: true, passive: true });
  document.addEventListener('gesturestart', startWebkitGesture, { capture: true, passive: false });
  document.addEventListener('gesturechange', changeWebkitGesture, { capture: true, passive: false });
  document.addEventListener('gestureend', endWebkitGesture, { capture: true, passive: true });
}(window, document));
