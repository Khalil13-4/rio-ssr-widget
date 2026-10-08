/* Reviews.io SSR widget — media lightbox (progressive enhancement).
   Without this script, thumbnails are plain links that open the file
   directly, so crawlers and no-JS users lose nothing. */
(function () {
  'use strict';
  if (!document.querySelector('[data-rio-media]')) return;

  var box = document.createElement('div');
  box.className = 'rio-lightbox';
  box.hidden = true;
  box.innerHTML =
    '<div class="rio-lightbox__backdrop"></div>' +
    '<figure class="rio-lightbox__stage" role="dialog" aria-modal="true" aria-label="Review media"></figure>' +
    '<button type="button" class="rio-lightbox__close" aria-label="Close">&#215;</button>';
  document.body.appendChild(box);

  var stage = box.querySelector('.rio-lightbox__stage');
  var lastFocus = null;

  function close() {
    box.hidden = true;
    stage.innerHTML = '';                 // stops video playback
    document.removeEventListener('keydown', onKey);
    if (lastFocus) lastFocus.focus();
  }
  function onKey(e) { if (e.key === 'Escape') close(); }

  box.querySelector('.rio-lightbox__backdrop').addEventListener('click', close);
  box.querySelector('.rio-lightbox__close').addEventListener('click', close);

  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-rio-media]') : null;
    if (!a) return;
    e.preventDefault();
    lastFocus = a;
    stage.innerHTML = '';
    if (a.getAttribute('data-rio-media') === 'video') {
      var v = document.createElement('video');
      v.src = a.href;
      v.controls = true;
      v.autoplay = true;
      v.playsInline = true;
      stage.appendChild(v);
    } else {
      var img = document.createElement('img');
      img.src = a.href;
      var t = a.querySelector('img');
      img.alt = t ? t.alt : '';
      stage.appendChild(img);
    }
    box.hidden = false;
    document.addEventListener('keydown', onKey);
    box.querySelector('.rio-lightbox__close').focus();
  });
})();
