/*
 * Load more / infinite scroll cho archive LAT Review.
 * Bấm nút .btn-loadmore hoặc kéo tới sentinel -> fetch REST pf/v1/posts, append .acard vào grid.
 * Config: PF_LM.rest. Container: [data-pf-loadmore] với data-type/id/paged/max.
 */
(function () {
  'use strict';
  if (typeof PF_LM === 'undefined' || !PF_LM.rest) { return; }
  var box = document.querySelector('[data-pf-loadmore]');
  var grid = document.querySelector('[data-pf-grid]');
  if (!box || !grid) { return; }
  var btn = box.querySelector('.btn-loadmore');
  var note = box.querySelector('.loadmore-note');
  var paged = parseInt(box.getAttribute('data-paged'), 10) || 1;
  var max = parseInt(box.getAttribute('data-max'), 10) || 1;
  var type = box.getAttribute('data-type') || '';
  var id = box.getAttribute('data-id') || '';
  var s = box.getAttribute('data-s') || '';
  var loading = false;

  function load() {
    if (loading || paged >= max) { return; }
    loading = true;
    if (btn) { btn.disabled = true; btn.textContent = 'Loading...'; }
    var url = PF_LM.rest + '?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id) +
              '&paged=' + (paged + 1) + (s ? '&s=' + encodeURIComponent(s) : '');
    fetch(url).then(function (r) { return r.json(); }).then(function (d) {
      if (d && d.html) {
        grid.insertAdjacentHTML('beforeend', d.html);
        paged += 1;
      }
      loading = false;
      if (paged >= max) {
        if (btn) { btn.style.display = 'none'; }
        if (note) { note.textContent = "You're all caught up."; }
      } else if (btn) {
        btn.disabled = false; btn.textContent = 'Load more';
      }
    }).catch(function () {
      loading = false;
      if (btn) { btn.disabled = false; btn.textContent = 'Load more'; }
    });
  }

  if (btn) { btn.addEventListener('click', load); }

  // Infinite: tự tải khi nút gần viewport.
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting) { load(); } });
    }, { rootMargin: '400px' });
    io.observe(box);
  }
})();
