/* rd-toc "In this guide": scroll tới heading, chừa chỗ cho sticky header (+ admin bar nếu có).
   Capture-phase + stopImmediatePropagation: chặn native anchor + handler khác (tránh race).
   Cập nhật URL #id (replaceState: không scroll, không rác history). */
(function () {
  function offsetTop() {
    var h = document.querySelector('header.site');
    var hh = h ? h.getBoundingClientRect().height : 116;
    var ab = document.getElementById('wpadminbar');
    var abh = (ab && getComputedStyle(ab).position === 'fixed') ? ab.getBoundingClientRect().height : 0;
    return hh + abh + 16;
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('.rd-toc a[href^="#"]');
    if (!a) return;
    var id = a.getAttribute('href').slice(1);
    var t = document.getElementById(id) || document.getElementsByName(id)[0];
    if (!t) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    var y = t.getBoundingClientRect().top + window.pageYOffset - offsetTop();
    window.scrollTo({ top: y < 0 ? 0 : y, behavior: 'smooth' });
    if (history.replaceState) { history.replaceState(null, '', '#' + id); }
  }, true);
})();
