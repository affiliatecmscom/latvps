<?php
/**
 * 404 not found.
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<div class="wrap">
  <div class="err-404">
    <div class="err-inner">
      <div class="err-code">404</div>
      <h1>Page not found</h1>
      <p class="err-msg">The page you are looking for does not exist or has moved. Try a search, or head back home.</p>
      <form class="results-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <input type="search" name="s" placeholder="Search reviews and guides" aria-label="Search">
        <button type="submit">Search</button>
      </form>
      <div class="err-popular">
        <span>Popular:</span>
        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
        <?php
        // Lấy động 3 nhóm cha nhiều bài nhất. Trước đây danh sách này ghi cứng
        // (electronics, home-kitchen, guides) và cả ba đều dẫn tới trang 404.
        $pf_404_cats = get_terms([
            'taxonomy'   => 'category',
            'parent'     => 0,
            'hide_empty' => false,
            'number'     => 12,
        ]);
        $pf_404_shown = 0;
        if (!is_wp_error($pf_404_cats)) {
            foreach ($pf_404_cats as $pf_404_cat) {
                $pf_404_kids = get_terms([
                    'taxonomy'   => 'category',
                    'parent'     => $pf_404_cat->term_id,
                    'hide_empty' => true,
                    'fields'     => 'ids',
                ]);
                if (is_wp_error($pf_404_kids) || !$pf_404_kids) {
                    continue;
                }
                echo '<a href="' . esc_url(get_term_link($pf_404_cat)) . '">' . esc_html($pf_404_cat->name) . '</a>';
                if (++$pf_404_shown >= 3) {
                    break;
                }
            }
        }
        ?>
      </div>
    </div>
  </div>
</div>
<?php get_footer();
