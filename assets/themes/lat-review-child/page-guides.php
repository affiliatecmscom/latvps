<?php
/**
 * Guides hub (PRD mục 13): archive cross-category các buying guide / how-to
 * (post KHÔNG phải roundup, tức title không bắt đầu "Best"). Không phải taxonomy.
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();

// Guide = post KHÔNG chứa khối ASIN [acms_list/grid/card] (roundup mới là bài có khối đó).
// Heuristic này ổn định hơn so tên (sau CU title đổi thành "The N Best X").
$all = get_posts(['numberposts' => 60, 'post_status' => 'publish']);
$guides = array_filter($all, static fn($p) => !preg_match('/\[acms_(list|grid|card)\b/', (string) $p->post_content));
?>
<div class="wrap breadcrumb-wrap">
  <nav class="breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>">Home</a><span>&rsaquo;</span><span>Guides</span></nav>
</div>
<div class="wrap">
  <div class="cat-hero">
    <h1>Buying Guides</h1>
    <p class="cat-intro">How-to advice and buying guides to help you choose, across every category we cover.</p>
  </div>
</div>
<section class="blk"><div class="wrap">
  <?php if ($guides) : ?>
    <div class="card-grid">
      <?php foreach ($guides as $p) { pf_acard($p); } ?>
    </div>
  <?php else : ?>
    <p class="date">Buying guides are coming soon.</p>
  <?php endif; ?>
</div></section>
<?php
get_footer();
