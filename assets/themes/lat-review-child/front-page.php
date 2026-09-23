<?php
/**
 * Front page LAT Review: hero 3 cột + Browse categories + newsletter + Who.
 * (Đã bỏ khối tagline "Independent reviews. Confident choices." và section "Trending
 * Reviews" theo yêu cầu 2026-09-07, xem docs/LICH-SU-PHIEN.md.)
 * Query WP posts (roundup/guide). Browse categories từ taxonomy (Phase 3), fallback nav groups.
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();

$recent = get_posts(['numberposts' => 12, 'post_status' => 'publish']);
$feat   = $recent ? $recent[0] : null;
$latest = array_slice($recent, 1, 5);
?>

<div class="wrap">
  <div class="hero">
    <!-- Left: The Latest -->
    <div class="latest">
      <div class="col-h accent-h">The Latest</div>
      <?php foreach ($latest as $p) : ?>
        <div class="it">
          <div class="meta">
            <span class="date"><?php echo esc_html(get_the_date('', $p)); ?></span>
          </div>
          <a class="h" href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html(get_the_title($p)); ?></a>
        </div>
      <?php endforeach; ?>
      <?php if (!$latest) : ?><p class="date">No posts yet.</p><?php endif; ?>
    </div>

    <!-- Center: Featured -->
    <div class="feat">
      <div class="col-h accent-h">Featured</div>
      <?php if ($feat) :
          $fimg = get_the_post_thumbnail_url($feat->ID, 'acms-hero') ?: ''; ?>
        <a href="<?php echo esc_url(get_permalink($feat)); ?>">
          <?php if ($fimg) : ?><img src="<?php echo esc_url($fimg); ?>" alt="<?php echo esc_attr(get_the_title($feat)); ?>"><?php endif; ?>
        </a>
        <h3><a href="<?php echo esc_url(get_permalink($feat)); ?>"><?php echo esc_html(get_the_title($feat)); ?></a></h3>
        <p><?php echo esc_html(wp_trim_words(get_the_excerpt($feat), 28)); ?></p>
        <div class="byline feat-meta">By <a href="<?php echo esc_url(get_author_posts_url((int) $feat->post_author)); ?>"><?php echo esc_html(get_the_author_meta('display_name', (int) $feat->post_author)); ?></a></div>
      <?php else : ?>
        <p class="date">No featured post yet.</p>
      <?php endif; ?>
    </div>

    <!-- Right: secondary list -->
    <div class="latest">
      <div class="col-h accent-h">Trending</div>
      <?php foreach (array_slice($recent, 6, 4) as $p) : ?>
        <div class="it">
          <div class="meta"><span class="date"><?php echo esc_html(get_the_date('', $p)); ?></span></div>
          <a class="h" href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html(get_the_title($p)); ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Browse categories: xoay 4 variant card (Lead+List / Card Grid / Compact List / Carousel) -->
<?php
// Blog (default_category) là danh mục nội dung, không phải nhóm hàng -> loại khỏi lưới box,
// khớp cách pf_nav_groups() (functions.php) đã loại nó khỏi nav. Nếu không loại, box Blog tự
// lọt vào đây ngay khi có đủ >=3 bài publish (điều kiện ở dưới), lẫn với box danh mục sản phẩm.
$top      = get_terms(['taxonomy' => 'category', 'parent' => 0, 'hide_empty' => true, 'number' => 11, 'exclude' => [(int) get_option('default_category')]]);
$variants = ['lead', 'grid', 'compact', 'carousel'];
if (!is_wp_error($top) && $top) : ?>
<div class="wrap">
  <?php $vi = 0; foreach ($top as $term) :
      $posts = get_posts(['numberposts' => 8, 'category' => $term->term_id, 'post_status' => 'publish']);
      if (count($posts) < 3) { continue; }                 // cần đủ card cho variant
      $variant = $variants[$vi % count($variants)];
      $vi++;
      $kids = get_terms(['taxonomy' => 'category', 'parent' => $term->term_id, 'hide_empty' => true, 'number' => 3]);
      ?>
    <section class="catblock">
      <div class="ch">
        <h2 class="cat-title"><a href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a></h2>
        <?php if (!is_wp_error($kids) && $kids) :
            $parts = [];
            foreach ($kids as $k) { $parts[] = '<a href="' . esc_url(get_term_link($k)) . '">' . esc_html($k->name) . '</a>'; } ?>
        <div class="subs"><?php echo implode(', ', $parts); ?>, or view all in <a href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a></div>
        <?php endif; ?>
      </div>

      <?php if ($variant === 'lead') :
          $lead = $posts[0];
          $rest = array_slice($posts, 1, 6);
          $limg = get_the_post_thumbnail_url($lead->ID, 'acms-card') ?: ''; ?>
      <div class="cols">
        <div class="lead">
          <a href="<?php echo esc_url(get_permalink($lead)); ?>"><?php if ($limg) : ?><img src="<?php echo esc_url($limg); ?>" alt="<?php echo esc_attr(get_the_title($lead)); ?>" loading="lazy"><?php endif; ?></a>
          <span class="date"><?php echo esc_html(get_the_date('', $lead)); ?></span>
          <h3><a href="<?php echo esc_url(get_permalink($lead)); ?>"><?php echo esc_html(get_the_title($lead)); ?></a></h3>
          <?php pf_byline($lead); ?>
          <p><?php echo esc_html(wp_trim_words(get_the_excerpt($lead), 22)); ?></p>
        </div>
        <div class="clist">
          <?php foreach ($rest as $p) : ?>
            <div class="ci">
              <span class="date"><?php echo esc_html(get_the_date('', $p)); ?></span>
              <a class="ci-title" href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html(get_the_title($p)); ?></a>
            </div>
          <?php endforeach; ?>
                  </div>
      </div>

      <?php elseif ($variant === 'grid') : ?>
      <div class="card-grid">
        <?php foreach (array_slice($posts, 0, 3) as $p) { pf_acard($p, true); } ?>
      </div>
      
      <?php elseif ($variant === 'compact') : ?>
      <div class="compact-list">
        <?php foreach (array_slice($posts, 0, 6) as $p) :
            $img = get_the_post_thumbnail_url($p->ID, 'acms-card') ?: ''; ?>
          <a class="crow" href="<?php echo esc_url(get_permalink($p)); ?>">
            <?php if ($img) : ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title($p)); ?>" loading="lazy"><?php endif; ?>
            <div><span class="date"><?php echo esc_html(get_the_date('', $p)); ?></span><h4><?php echo esc_html(get_the_title($p)); ?></h4></div>
          </a>
        <?php endforeach; ?>
      </div>
      
      <?php else : /* carousel */ ?>
      <div class="carousel">
        <?php foreach ($posts as $p) { pf_acard($p, true); } ?>
      </div>
            <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Newsletter -->
<div class="wrap news-wrap">
  <div class="news">
    <div class="l">
      <div class="news-title"><span class="wk">The Weekly</span><br><b>Sign up for our newsletter</b></div>
      <p>Our best reviews and buying advice, delivered to your inbox.</p>
    </div>
    <form method="post" action="#">
      <input type="email" name="email" placeholder="Your email address" aria-label="Email">
      <button type="submit">Subscribe</button>
    </form>
  </div>
</div>

<!-- Who is LAT Review? -->
<?php
$pub_n    = (int) wp_count_posts('post')->publish;
$reviews  = $pub_n >= 100 ? number_format((int) floor($pub_n / 50) * 50) . '+' : (string) $pub_n;
$default  = (int) get_option('default_category');                // loại "Uncategorized" khỏi thống kê
$cat_par  = get_terms(['taxonomy' => 'category', 'parent' => 0, 'hide_empty' => false, 'exclude' => [$default], 'fields' => 'ids']);
$cat_all  = get_terms(['taxonomy' => 'category', 'hide_empty' => false, 'exclude' => [$default], 'fields' => 'ids']);
$n_parent = is_wp_error($cat_par) ? 0 : count($cat_par);
$n_sub    = is_wp_error($cat_all) ? 0 : max(0, count($cat_all) - $n_parent);
$team     = pf_home_authors(12);
?>
<section class="who">
  <div class="wrap">
    <h2 class="who-title">Who is LAT Review?</h2>
    <p class="sub">Our team of reviewers and category experts research and recommend the best products so you can find the right one, the first time.</p>
    <p class="sub">We review independently. Every pick is chosen after deep research, comparing specifications, professional review consensus, and verified owner feedback across thousands of products.</p>
    <div class="stats">
      <div class="stat"><b><?php echo esc_html($reviews); ?></b><span>Reviews</span></div>
      <div class="stat"><b><?php echo esc_html((string) $n_parent); ?></b><span>Categories</span></div>
      <div class="stat"><b><?php echo esc_html((string) $n_sub); ?></b><span>Subcategories</span></div>
    </div>
    <div class="pillars">
      <div class="pillar"><div class="pillar-title">Who we are</div><p>A team of experienced reviewers, researchers, writers, and editors dedicated to honest recommendations.</p></div>
      <div class="pillar"><div class="pillar-title">What we do</div><p>We research, compare, and scrutinize thousands of products to narrow them to the top choices.</p></div>
      <div class="pillar"><div class="pillar-title">How we do it</div><p>We weigh professional review consensus and verified owner feedback, never spec sheets alone.</p></div>
    </div>
    <?php if ($team) : ?>
    <div class="teamlbl">Meet a few of our reviewers</div>
    <div class="team">
      <?php foreach ($team as $m) : ?>
        <div class="m">
          <img src="<?php echo esc_url(get_avatar_url((int) $m['id'], ['size' => 156])); ?>" alt="<?php echo esc_attr($m['name']); ?>" loading="lazy">
          <b><?php echo esc_html($m['name']); ?></b>
          <?php if ($m['spec']) : ?><span><?php echo esc_html($m['spec']); ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php
get_footer();
