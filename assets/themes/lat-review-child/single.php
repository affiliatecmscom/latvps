<?php
/**
 * Single post LAT Review: dùng chung Roundup "Best X" + Buying guide (PRD mục 3).
 * Bài render qua pf_render_single_article() (functions.php, dùng chung với REST up-next).
 * Up-next infinite: đọc hết bài -> up-next.js tự tải bài kế cùng danh mục vào .pf-article-feed.
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();

while (have_posts()) : the_post();
    $pid = get_the_ID();
    ?>
    <div class="pf-article-feed" data-pf-upnext data-shown="<?php echo (int) $pid; ?>">
        <?php
        // phpcs:ignore WordPress.Security.EscapeOutput -- render nội bộ đã escape
        echo pf_render_single_article(get_post());
        ?>
    </div>
    <div class="up-next-sentinel" aria-hidden="true"></div>
    <?php
endwhile;

get_footer();
