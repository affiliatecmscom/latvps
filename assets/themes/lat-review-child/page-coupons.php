<?php
/**
 * Trang /coupons/ (feed coupon).
 *
 * Vì sao có template riêng: page.php dựng cột chữ hẹp 668px cho trang tĩnh kiểu About/Legal,
 * đúng cho bài đọc nhưng bóp nát feed coupon (card 644px, logo/nút/tiêu đề đều bị nén, đo thật
 * trên DOM ngày 2026-09-15 so với 1176px của trang tham khảo). Ở đây dùng trọn .wrap; H1 nằm
 * trên dải nền trắng, phần feed nằm trên dải nền xám --panel để card trắng nổi lên, đúng
 * tương quan nền/card của layout tham khảo. Màu vẫn là bộ token brand, không lấy màu của họ.
 *
 * WP tự chọn file này theo slug trang (`page-coupons.php` ứng với slug `coupons`), không phải
 * gán Template trong admin.
 *
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<?php while (have_posts()) : the_post(); ?>
  <div class="coupons-head">
    <div class="wrap">
      <h1><?php echo esc_html(pf_coupons_heading()); ?></h1>
    </div>
  </div>
  <div class="coupons-feed-band">
    <div class="wrap">
      <div class="coupons-body"><?php the_content(); ?></div>
    </div>
  </div>
<?php endwhile; ?>
<?php get_footer();
