<?php
/**
 * Header LAT Review (B&W). Logo wordmark + mega-menu + search + mobile nav.
 * Nav lấy từ WP menu location pf-primary (Appearance > Menus, sửa được). Chưa gán -> fallback pf_nav_groups.
 * Item có class "pf-nav-right" -> khối phải (Blog, Coupons). 5 nhóm đầu inline, còn lại trong "More".
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}

$pf_menu = pf_get_primary_menu();
if ($pf_menu) {
    $pf_grp  = $pf_menu['groups'];
    $pf_rgt  = $pf_menu['right'];
} else {
    // Fallback: dựng từ config tĩnh khi chưa gán menu.
    $pf_grp = [];
    foreach (array_merge(pf_nav_groups()['primary'], pf_nav_groups()['more']) as $g) {
        $kids = pf_group_children($g['slug']);
        $pf_grp[] = [
            'title'    => $g['label'],
            'url'      => pf_group_url($g['slug']),
            'children' => array_map(static fn($k) => ['title' => $k['label'], 'url' => $k['url']], $kids),
        ];
    }
    // Coupons thay chỗ "How We Review" trên thanh điều hướng (yêu cầu user 2026-09-15).
    // "How We Review" vẫn còn link ở footer (cột Company), nên trang tin cậy không bị mất lối vào.
    $pf_rgt = [
        ['title' => 'Blog', 'url' => home_url('/blog/')],
        ['title' => 'Coupons', 'url' => home_url('/coupons/')],
    ];
}
$pf_inline = array_slice($pf_grp, 0, 5);
$pf_more   = array_slice($pf_grp, 5);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="https://gmpg.org/xfn/11">
<script>window.acmsThemeConfig={mode:'light'};</script>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site">
  <input type="checkbox" id="mnav" class="mnav-toggle" hidden>
  <input type="checkbox" id="msearch" class="msearch-toggle" hidden>

  <div class="topbar">
    <div class="wrap">
      <label for="mnav" class="hamburger" aria-label="Menu"><i class="bi bi-list ic-open"></i><i class="bi bi-x-lg ic-close"></i></label>
      <?php echo pf_logo(); // phpcs:ignore WordPress.Security.EscapeOutput -- markup an toàn ?>
      <form class="searchpill" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <i class="bi bi-search"></i>
        <input type="search" name="s" placeholder="What are you looking for today?" aria-label="Search" value="<?php echo esc_attr(get_search_query()); ?>">
        <button type="submit" aria-label="Search"><i class="bi bi-arrow-right"></i></button>
      </form>
      <label for="msearch" class="m-searchbtn" aria-label="Search"><i class="bi bi-search"></i></label>
    </div>
  </div>

  <div class="mobile-search">
    <form class="m-search" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
      <i class="bi bi-search"></i>
      <input type="search" name="s" placeholder="What are you looking for today?" aria-label="Search">
    </form>
  </div>

  <div class="navbar">
    <div class="wrap">
      <nav aria-label="Primary">
        <?php foreach ($pf_inline as $g) : ?>
          <div class="ni">
            <a href="<?php echo esc_url($g['url']); ?>"><?php echo esc_html($g['title']); ?></a>
            <?php if (!empty($g['children'])) : ?>
              <div class="submenu">
                <?php foreach ($g['children'] as $c) : ?>
                  <a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['title']); ?></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($pf_grp) : ?>
          <div class="ni">
            <a href="#">All Categories <i class="bi bi-chevron-down" aria-hidden="true"></i></a>
            <div class="submenu allcats">
              <?php foreach ($pf_grp as $g) : ?>
                <a href="<?php echo esc_url($g['url']); ?>"><?php echo esc_html($g['title']); ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </nav>
      <div class="right">
        <?php foreach ($pf_rgt as $r) :
            $pf_is_coupons = pf_is_coupons_nav_item((string) $r['url']);
            ?>
          <a class="<?php echo $pf_is_coupons ? 'nav-coupons' : ''; ?>" href="<?php echo esc_url($r['url']); ?>">
            <?php
            if ($pf_is_coupons) {
                // phpcs:ignore WordPress.Security.EscapeOutput -- SVG tĩnh viết trong theme
                echo pf_coupon_icon();
            }
            echo esc_html($r['title']);
            ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <nav class="mobile-nav" aria-label="Mobile">
    <?php foreach ($pf_grp as $g) :
        if (!empty($g['children'])) : ?>
        <details>
          <summary><?php echo esc_html($g['title']); ?></summary>
          <div class="sub">
            <a href="<?php echo esc_url($g['url']); ?>">All <?php echo esc_html($g['title']); ?></a>
            <?php foreach ($g['children'] as $c) : ?>
              <a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['title']); ?></a>
            <?php endforeach; ?>
          </div>
        </details>
    <?php else : ?>
        <a href="<?php echo esc_url($g['url']); ?>"><?php echo esc_html($g['title']); ?></a>
    <?php endif; endforeach; ?>
    <div class="m-sep">More</div>
    <?php foreach ($pf_rgt as $r) :
        $pf_is_coupons = pf_is_coupons_nav_item((string) $r['url']);
        ?>
      <a class="gold<?php echo $pf_is_coupons ? ' nav-coupons' : ''; ?>" href="<?php echo esc_url($r['url']); ?>">
        <?php
        if ($pf_is_coupons) {
            // phpcs:ignore WordPress.Security.EscapeOutput -- SVG tĩnh viết trong theme
            echo pf_coupon_icon();
        }
        echo esc_html($r['title']);
        ?>
      </a>
    <?php endforeach; ?>
  </nav>
</header>

<div class="ftc-bar">
  <p>We independently review everything we recommend. When you buy through our links, we may earn a commission. <a aria-label="Learn more about LAT Review" data-gtm-trigger="ftc_link" href="<?php echo esc_url(home_url('/affiliate-disclosure/')); ?>">Learn more<span>&rsaquo;</span></a></p>
</div>
