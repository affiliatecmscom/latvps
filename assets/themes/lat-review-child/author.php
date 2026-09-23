<?php
/**
 * Author archive: hero bio + grid bài (bỏ byline trong card).
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();
$author = get_queried_object();
$uid    = (int) ($author->ID ?? 0);
?>
<div class="wrap breadcrumb-wrap">
  <?php echo pf_breadcrumb_nav([['label' => 'Authors'], ['label' => get_the_author_meta('display_name', $uid)]]); // phpcs:ignore WordPress.Security.EscapeOutput -- da escape trong ham ?>
</div>
<div class="wrap">
  <div class="author-hero">
    <img class="author-avatar" src="<?php echo esc_url(get_avatar_url($uid, ['size' => 224])); ?>" alt="<?php echo esc_attr(get_the_author_meta('display_name', $uid)); ?>">
    <div class="author-info">
      <h1><?php echo esc_html(get_the_author_meta('display_name', $uid)); ?></h1>
      <?php $role = get_the_author_meta('description', $uid); ?>
      <?php if ($role) : ?><p class="author-role"><?php echo esc_html($role); ?></p><?php endif; ?>
      <div class="author-meta"><span><b><?php echo esc_html((string) count_user_posts($uid, 'post')); ?></b> articles</span></div>
    </div>
  </div>
</div>
<section class="blk"><div class="wrap">
  <?php pf_render_grid($GLOBALS['wp_query'], ['type' => 'author', 'id' => $uid]); ?>
</div></section>
<?php get_footer();
