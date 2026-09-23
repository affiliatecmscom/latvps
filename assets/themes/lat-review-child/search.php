<?php
/**
 * Search results: search box + grid + load more.
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();
$q = get_search_query();
?>
<div class="wrap breadcrumb-wrap">
  <?php echo pf_breadcrumb_nav([['label' => 'Search']]); // phpcs:ignore WordPress.Security.EscapeOutput -- da escape trong ham ?>
</div>
<div class="wrap">
  <div class="cat-hero">
    <h1>Search</h1>
    <form class="results-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <input type="search" name="s" value="<?php echo esc_attr($q); ?>" placeholder="Search reviews and guides" aria-label="Search">
      <button type="submit">Search</button>
    </form>
    <?php if ($q) : ?><p class="cat-intro"><?php echo esc_html(sprintf('%d results for "%s"', (int) $GLOBALS['wp_query']->found_posts, $q)); ?></p><?php endif; ?>
  </div>
</div>
<section class="blk"><div class="wrap">
  <?php pf_render_grid($GLOBALS['wp_query'], ['type' => 'search', 's' => $q]); ?>
</div></section>
<?php get_footer();
