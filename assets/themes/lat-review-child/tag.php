<?php
/**
 * Tag archive (kế thừa layout leaf: grid + load more).
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();
$term = get_queried_object();
?>
<div class="wrap breadcrumb-wrap">
  <?php echo pf_breadcrumb_nav([['label' => 'Tags'], ['label' => $term->name]]); // phpcs:ignore WordPress.Security.EscapeOutput -- da escape trong ham ?>
</div>
<div class="wrap">
  <div class="cat-hero"><h1><?php echo esc_html($term->name); ?></h1>
    <?php if ($term->description) : ?><p class="cat-intro"><?php echo esc_html($term->description); ?></p><?php endif; ?>
  </div>
</div>
<section class="blk"><div class="wrap">
  <?php pf_render_grid($GLOBALS['wp_query'], ['type' => 'tag', 'id' => (int) $term->term_id]); ?>
</div></section>
<?php get_footer();
