/**
 * Tooltip ⓘ: chọn hướng bung theo chỗ trống thật, thay vì luôn bung lên.
 *
 * CSS plugin (frontend.css) đặt cứng .acms-tooltip__content{bottom:calc(100% + ...)},
 * tức luôn mở LÊN TRÊN và không dò biên. Header sticky cao ~117px trên desktop, nên
 * mọi ⓘ nằm trong khoảng trên cùng của màn hình đều làm tooltip trào ra ngoài viewport
 * và mất mấy dòng đầu. Cuộn qua danh sách pick card thì gặp liên tục.
 *
 * Ở đây đo chỗ trống phía trên (đã trừ chiều cao header sticky) rồi gắn/gỡ class
 * .acms-tooltip--flipdown (CSS ở child style.css) để lật xuống khi thiếu chỗ.
 *
 * Chạy cho cả chuột (pointerenter/focusin) lẫn cảm ứng: plugin toggle .is-active khi
 * tap, nên nghe thêm click và theo dõi .is-active để mobile cũng được lật đúng.
 */
(function () {
  'use strict';

  var FLIP = 'acms-tooltip--flipdown';
  var GAP = 8; // khoảng hở giữa icon và hộp tooltip, khớp CSS

  /** Chiều cao phần bị header sticky che ở đỉnh viewport. */
  function headerOffset() {
    var h = document.querySelector('header.site') || document.querySelector('header');
    if (!h) { return 0; }
    if (getComputedStyle(h).position !== 'sticky') { return 0; }
    var r = h.getBoundingClientRect();
    return r.top <= 0 ? r.bottom : 0;
  }

  /**
   * Quyết định hướng cho 1 tooltip.
   * Đo bằng cách tạm cho hộp hiện hình (visibility) để lấy chiều cao thật,
   * vì lúc ẩn nó vẫn có kích thước nhưng ta cần chắc chắn đã layout xong.
   */
  function place(tip) {
    // Bảng compare đã cố định hướng xuống bằng --down, không đụng vào.
    if (tip.classList.contains('acms-tooltip--down')) { return; }

    var box = tip.querySelector('.acms-tooltip__content');
    if (!box) { return; }

    // Đo ở trạng thái CHƯA lật để lấy chiều cao ổn định.
    tip.classList.remove(FLIP);

    var icon = tip.getBoundingClientRect();
    var need = box.offsetHeight + GAP;
    var spaceAbove = icon.top - headerOffset();
    var spaceBelow = window.innerHeight - icon.bottom;

    // Thiếu chỗ phía trên VÀ phía dưới rộng hơn thì mới lật, tránh lật sang chỗ còn tệ hơn.
    if (spaceAbove < need && spaceBelow > spaceAbove) {
      tip.classList.add(FLIP);
    }
  }

  function bind(tip) {
    if (tip.dataset.pfTip === '1') { return; }
    tip.dataset.pfTip = '1';
    ['pointerenter', 'focusin', 'click'].forEach(function (ev) {
      tip.addEventListener(ev, function () { place(tip); }, { passive: true });
    });
  }

  function scan() {
    document.querySelectorAll('.acms-tooltip').forEach(bind);
  }

  document.addEventListener('DOMContentLoaded', scan);
  // Bài chèn thêm qua REST (up-next, load more) -> gắn cho tooltip mới xuất hiện.
  if (window.MutationObserver) {
    new MutationObserver(function () { scan(); })
      .observe(document.documentElement, { childList: true, subtree: true });
  }
  // Cuộn/xoay máy làm chỗ trống đổi -> tooltip đang mở (tap trên mobile) phải tính lại.
  ['scroll', 'resize', 'orientationchange'].forEach(function (ev) {
    window.addEventListener(ev, function () {
      document.querySelectorAll('.acms-tooltip.is-active').forEach(place);
    }, { passive: true });
  });
})();
