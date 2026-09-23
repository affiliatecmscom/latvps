/*
 * Up-next infinite cho bài đơn LAT Review.
 * Đọc hết bài -> IntersectionObserver ở .up-next-sentinel fetch REST pf/v1/next-post,
 * chèn bài kế (cùng danh mục) vào .pf-article-feed liền mạch. Cập nhật URL + title khi
 * mỗi bài trôi vào vùng đọc. Dừng khi hết bài. Tooltip dùng document-delegation nên
 * content chèn tự hoạt động.
 * Config: PF_UN.rest.
 */
(function () {
  'use strict';
  if (typeof PF_UN === 'undefined' || !PF_UN.rest) { return; }
  var feed = document.querySelector('[data-pf-upnext]');
  var sentinel = document.querySelector('.up-next-sentinel');
  if (!feed || !sentinel) { return; }

  var shown = (feed.getAttribute('data-shown') || '').split(',').filter(Boolean);
  var loading = false;
  var done = false;
  var curUrl = location.href;

  // --- 1) Load bài kế khi sentinel gần vào viewport ---
  var loadIO = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting) { loadNext(); }
  }, { rootMargin: '800px 0px' });
  loadIO.observe(sentinel);

  function loadNext() {
    if (loading || done) { return; }
    loading = true;
    var last = shown[shown.length - 1] || '';
    var url = PF_UN.rest + '?id=' + encodeURIComponent(last) + '&shown=' + encodeURIComponent(shown.join(','));
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || d.done || !d.html) { done = true; loadIO.disconnect(); return; }
        var block = document.createElement('div');
        block.className = 'pf-next-block';
        block.innerHTML = '<div class="wrap"><div class="up-next"><span>↓ Up next</span></div></div>' + d.html;
        feed.appendChild(block);
        shown.push(String(d.id));
        var art = block.querySelector('[data-pf-article]');
        if (art) { titleIO.observe(art); }
        loading = false;
        // sentinel có thể vẫn trong tầm -> IO tự bắn lại; nếu không, chờ scroll.
      })
      .catch(function () { loading = false; });
  }

  // --- 2) Cập nhật URL + document.title theo bài đang đọc ---
  var titleIO = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (!e.isIntersecting) { return; }
      var a = e.target;
      var u = a.getAttribute('data-url');
      var t = a.getAttribute('data-title');
      if (u && u !== curUrl) {
        curUrl = u;
        try { history.replaceState(null, '', u); } catch (err) {}
        if (t) { document.title = t; }
      }
    });
  }, { rootMargin: '-15% 0px -80% 0px' });

  // observe bài đầu (đã có sẵn) + các bài chèn sau
  var first = feed.querySelector('[data-pf-article]');
  if (first) { titleIO.observe(first); }
})();
