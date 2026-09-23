<?php
/**
 * Static page (About, How We Review, Legal...). Cột chữ hẹp căn giữa, không breadcrumb.
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<div class="wrap">
  <?php while (have_posts()) : the_post(); ?>
    <article class="page">
      <h1><?php the_title(); ?></h1>
      <div class="page-body"><?php the_content(); ?></div>
    </article>
  <?php endwhile; ?>
</div>
<?php get_footer();
