<?php
/**
 * Category LAT Review. MỘT kiểu hiển thị duy nhất cho mọi cấp danh mục (đổi 2026-08-30):
 * lưới bài mới nhất + load more, đúng cách WordPress xử lý archive danh mục.
 * Danh mục cha hiện luôn bài của các danh mục con, vì query archive của WordPress
 * đã bao gồm danh mục con sẵn (include_children).
 * Trước đây cha và con dùng hai layout khác nhau (catblock xen kẽ 4 variant), đã bỏ.
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();

$term     = get_queried_object();
$children = get_terms(['taxonomy' => 'category', 'parent' => (int) $term->term_id, 'hide_empty' => false]);
$children = is_wp_error($children) ? [] : $children;
?>

<div class="wrap breadcrumb-wrap">
  <?php echo pf_breadcrumb_nav(pf_breadcrumb_term_items($term)); // phpcs:ignore WordPress.Security.EscapeOutput -- da escape trong ham ?>
</div>

<div class="wrap">
  <div class="cat-hero">
    <h1><?php echo esc_html($term->name); ?></h1>
    <?php if ($term->description) : ?><p class="cat-intro"><?php echo esc_html($term->description); ?></p><?php endif; ?>
    <?php if ($children) : ?>
      <nav class="cat-subnav">
        <?php foreach ($children as $c) : ?>
          <a href="<?php echo esc_url(get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </div>
</div>

<section class="blk">
  <div class="wrap">
    <?php pf_render_grid($GLOBALS['wp_query'], ['type' => 'category', 'id' => (int) $term->term_id]); ?>
  </div>
</section>

<?php
// Nối chéo sang nhóm cha khác cho các danh mục chồng lấn (Tablets, Gaming Headsets,
// Portable Power Stations...). Xem pf_related_map() trong functions.php.
$pf_lienquan = pf_related_categories($term);
if ($pf_lienquan) :
?>
<section class="blk cat-related">
  <div class="wrap">
    <h2 class="cat-related-title">Related categories</h2>
    <nav class="cat-related-list">
      <?php foreach ($pf_lienquan as $pf_r) : ?>
        <a href="<?php echo esc_url(get_term_link($pf_r)); ?>"><?php echo esc_html(html_entity_decode($pf_r->name, ENT_QUOTES)); ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</section>
<?php endif; ?>

<?php
get_footer();
