<?php
/**
 * Trang So Sánh sản phẩm ĐỘNG: /compare/?ids=ASIN,ASIN...
 * Dữ liệu do mu-plugin pf-compare.php cung cấp ($GLOBALS['acms_compare_products']).
 * UI bám design token (.btn, .acms-score, .acms-badge remap B&W) + .acms-cmp__* (compare.css).
 * KHÔNG hardcode màu, KHÔNG inline style. Số cột 2-4 xử lý bằng CSS.
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}

$products = isset($GLOBALS['acms_compare_products']) && is_array($GLOBALS['acms_compare_products'])
    ? $GLOBALS['acms_compare_products']
    : [];

get_header();
?>

<main id="content" class="site-main">
  <div class="wrap acms-cmp">

    <?php if (count($products) < ACMS_CMP_MIN) : ?>

      <div class="acms-cmp__empty">
        <h1 class="acms-cmp__empty-title">Compare Products</h1>
        <p><?php printf('Select at least %d products to compare. Open any roundup and tick the "Compare" box on the products you want to line up.', (int) ACMS_CMP_MIN); ?></p>
        <a class="btn btn--primary" href="<?php echo esc_url(home_url('/')); ?>">Browse reviews</a>
      </div>

    <?php
    else :
        $prices = [];
        foreach ($products as $p) {
            $pv = (float) $p['price'];
            if ($pv > 0) { $prices[] = $pv; }
        }
        $minPrice = $prices ? min($prices) : 0;
        $maxScore = max(array_map(static fn($p) => (int) $p['score'], $products));
        $picks    = function_exists('acms_picks_compute') ? acms_picks_compute($products) : [];
        $cols     = count($products);
        $score_label = function_exists('pf_score_label') ? pf_score_label() : 'LAT Score';
        $showUpdate  = (bool) ((get_option('acms_general_settings', [])['show_update']) ?? true);
        // Cùng công tắc "Show price" (Visible Elements) với bảng trong bài roundup: tắt là ẩn cả hàng Price.
        $showPrice   = (bool) ((get_option('acms_general_settings', [])['show_price']) ?? true);
        ?>

        <header class="acms-cmp__header">
          <h1>Compare Products</h1>
          <p class="acms-cmp__subtitle"><?php echo esc_html(implode('  vs  ', array_map('acms_cmp_name', $products))); ?></p>
        </header>

        <div class="acms-cmp__scroll">
          <table class="acms-cmp__table acms-cmp__table--cols-<?php echo (int) $cols; ?>">

            <tr class="acms-cmp__row acms-cmp__row--head">
              <th class="acms-cmp__label" scope="row"></th>
              <?php foreach ($products as $p) :
                  $asin = $p['asin'];
                  $name = acms_cmp_name($p);
                  $detail = 'https://www.amazon.com/dp/' . rawurlencode($asin);
                  $pick = $picks[$asin] ?? null;
                  ?>
                <td class="acms-cmp__cell acms-cmp__cell--head">
                  <?php if ($pick) : ?>
                    <span class="acms-badge acms-badge--<?php echo esc_attr($pick['css']); ?> acms-cmp__pick"><?php echo esc_html($pick['label']); ?></span>
                  <?php endif; ?>
                  <a class="acms-cmp__thumb" href="<?php echo esc_url($detail); ?>">
                    <?php if (!empty($p['image_url'])) : ?><img src="<?php echo esc_url($p['image_url']); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy"><?php endif; ?>
                  </a>
                  <a class="acms-cmp__name" href="<?php echo esc_url($detail); ?>"><?php echo esc_html($name); ?></a>
                </td>
              <?php endforeach; ?>
            </tr>

            <?php if ($showPrice) : ?>
            <tr class="acms-cmp__row">
              <th class="acms-cmp__label" scope="row">Price</th>
              <?php foreach ($products as $p) :
                  $cur   = ('USD' === $p['currency'] || empty($p['currency'])) ? '$' : ($p['currency'] . ' ');
                  $price = (float) $p['price'];
                  $best  = ($price > 0 && $price === $minPrice && $cols > 1);
                  ?>
                <td class="acms-cmp__cell<?php echo $best ? ' is-best' : ''; ?>">
                  <span class="acms-cmp__price"><?php echo esc_html($cur . number_format($price, 2)); ?></span>
                  <?php if ((float) $p['original_price'] > $price && $price > 0) : ?>
                    <span class="acms-cmp__was"><?php echo esc_html($cur . number_format((float) $p['original_price'], 2)); ?></span>
                    <?php if ((int) $p['discount_pct'] > 0) : ?><span class="acms-cmp__off">-<?php echo (int) $p['discount_pct']; ?>%</span><?php endif; ?>
                  <?php endif; ?>
                  <?php if ($best) : ?><span class="acms-cmp__win">Lowest price</span><?php endif; ?>
                  <?php
                  $updDate = ($showUpdate && !empty($p['last_scraped_at'])) ? date_i18n('M j, Y', strtotime((string) $p['last_scraped_at'])) : '';
                  if ($updDate !== '') : ?>
                  <div class="acms-list__update">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <span><?php printf(esc_html__('Updated: %s', 'lat-review'), esc_html($updDate)); ?></span>
                    <?php if (function_exists('pf_update_tooltip')) { echo pf_update_tooltip($updDate); } ?>
                  </div>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
            <?php endif; ?>

            <tr class="acms-cmp__row">
              <th class="acms-cmp__label" scope="row"><?php echo esc_html($score_label); if (function_exists('pf_score_tooltip')) { echo ' ' . pf_score_tooltip(); } ?></th>
              <?php foreach ($products as $p) :
                  $sc = (int) $p['score'];
                  $best = ($sc > 0 && $sc === $maxScore && $cols > 1);
                  ?>
                <td class="acms-cmp__cell<?php echo $best ? ' is-best' : ''; ?>">
                  <?php if ($sc > 0) : ?>
                    <div class="acms-list__score-wrapper">
                      <i class="bi bi-bar-chart-fill acms-list__score-icon"></i>
                      <div class="acms-score acms-score--inline" data-score="<?php echo esc_attr((string) $sc); ?>">
                        <span class="acms-score__value"><?php echo esc_html(number_format($sc / 10, 1)); ?></span>
                        <span class="acms-score__label">/10</span>
                      </div>
                    </div>
                  <?php else : ?><span class="acms-cmp__na">N/A</span><?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>

            <tr class="acms-cmp__row">
              <th class="acms-cmp__label" scope="row">Brand</th>
              <?php foreach ($products as $p) : ?>
                <td class="acms-cmp__cell"><?php echo esc_html($p['brand'] ? $p['brand'] : 'N/A'); ?></td>
              <?php endforeach; ?>
            </tr>

            <tr class="acms-cmp__row">
              <th class="acms-cmp__label" scope="row">Availability</th>
              <?php foreach ($products as $p) : ?>
                <td class="acms-cmp__cell">
                  <?php if (!empty($p['in_stock'])) : ?><span class="acms-cmp__yes">In stock</span><?php else : ?><span class="acms-cmp__no">Out of stock</span><?php endif; ?>
                  <?php if (!empty($p['is_prime'])) : ?><span class="acms-cmp__prime">Prime</span><?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>

            <tr class="acms-cmp__row">
              <th class="acms-cmp__label" scope="row">Pros</th>
              <?php foreach ($products as $p) : $pros = acms_cmp_pros($p); ?>
                <td class="acms-cmp__cell">
                  <?php if ($pros) : ?>
                    <ul class="acms-cmp__list acms-cmp__list--pro">
                      <?php foreach (array_slice($pros, 0, 4) as $x) : ?><li><?php echo esc_html($x); ?></li><?php endforeach; ?>
                    </ul>
                  <?php else : ?><span class="acms-cmp__na">N/A</span><?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>

            <tr class="acms-cmp__row">
              <th class="acms-cmp__label" scope="row">Cons</th>
              <?php foreach ($products as $p) : $cons = acms_cmp_cons($p); ?>
                <td class="acms-cmp__cell">
                  <?php if ($cons) : ?>
                    <ul class="acms-cmp__list acms-cmp__list--con">
                      <?php foreach (array_slice($cons, 0, 4) as $x) : ?><li><?php echo esc_html($x); ?></li><?php endforeach; ?>
                    </ul>
                  <?php else : ?><span class="acms-cmp__na">N/A</span><?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>

            <tr class="acms-cmp__row acms-cmp__row--buy">
              <th class="acms-cmp__label" scope="row"></th>
              <?php foreach ($products as $p) : ?>
                <td class="acms-cmp__cell">
                  <a class="btn btn--primary acms-cmp__buy" href="<?php echo esc_url(acms_cmp_buy_url($p['asin'])); ?>" target="_blank" rel="sponsored nofollow noopener">View on Amazon</a>
                </td>
              <?php endforeach; ?>
            </tr>

          </table>
        </div>

        <p class="acms-cmp__note">Prices and availability are accurate as of the time shown and are subject to change. As an Amazon Associate, LAT Review earns from qualifying purchases.</p>

    <?php endif; ?>

  </div>
</main>

<?php
get_footer();
