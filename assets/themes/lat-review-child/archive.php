<?php
/**
 * Date/generic archive (kế thừa layout leaf: grid + load more).
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<div class="wrap breadcrumb-wrap">
  <?php echo pf_breadcrumb_nav([['label' => wp_strip_all_tags(get_the_archive_title())]]); // phpcs:ignore WordPress.Security.EscapeOutput -- da escape trong ham ?>
</div>
<div class="wrap">
  <div class="cat-hero"><h1><?php echo esc_html(wp_strip_all_tags(get_the_archive_title())); ?></h1></div>
</div>
<section class="blk"><div class="wrap">
  <?php pf_render_grid($GLOBALS['wp_query']); ?>
</div></section>
<?php get_footer();
