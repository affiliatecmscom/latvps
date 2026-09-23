<?php
/**
 * Trang chi tiết store (bài có meta `_acms_brand_id`).
 *
 * WP không tự chọn file này: post type vẫn là `post` nên `single.php` mới là template mặc định.
 * `pf_store_template()` trong functions.php bắt filter `template_include` và đổi sang đây khi bài
 * có brand. Làm vậy để trang store không phải là post type riêng (quyết định đã chốt: bài thật,
 * không phải route ảo) mà vẫn không dính pipeline roundup của `single.php` (up-next infinite,
 * khối ASIN, LAT Score).
 *
 * Bố cục 2 cột theo đúng thứ tự của trang tham khảo: cột chính là coupon đang chạy, coupon hết
 * hạn (gấp lại), bảng tóm tắt, phần biên tập, store cùng danh mục; cột phải là số liệu, FAQ.
 * Mọi con số do plugin đọc thẳng từ CSDL lúc render, KHÔNG đóng băng trong post_content.
 *
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}

use AffiliateCMS\Pro\Frontend\StorePage;

get_header();

while (have_posts()) :
    the_post();
    $pid   = get_the_ID();
    $brand = StorePage::brandForPost($pid);

    if (!$brand) {
        // Meta bị xoá giữa chừng: thà render như bài thường còn hơn trang trắng.
        echo '<div class="wrap"><article class="page"><h1>' . esc_html(get_the_title()) . '</h1>'
            . '<div class="page-body">' . wp_kses_post(apply_filters('the_content', get_the_content())) . '</div></article></div>';
        continue;
    }

    // Qua displayName() để cắt đuôi pháp nhân trong tên lấy từ API ("... International Limited").
    $name     = StorePage::displayName($brand);
    $live     = StorePage::coupons((int) $brand->id);
    $expired  = StorePage::coupons((int) $brand->id, true);
    $stats    = StorePage::stats((int) $brand->id);
    $similar  = StorePage::similarBrands($brand);
    ?>
    <div class="store-head">
      <div class="wrap">
        <?php
        // Breadcrumb của trang store có thêm mắt xích cuối là tên store, khác bài roundup
        // (pf_breadcrumb_html cố ý dừng ở danh mục). Trang store là điểm đến chứ không phải một
        // bài trong danh mục, nên người đọc cần biết mình đang đứng ở đâu, đúng kiểu
        // "Home › Home & Furniture › Aurora Home Goods" của trang tham khảo.
        $crumbs = [];
        $cats   = get_the_category($pid);
        if ($cats) {
            $link = get_term_link($cats[0]);
            $crumbs[] = ['label' => $cats[0]->name, 'url' => is_wp_error($link) ? '' : $link];
        }
        $crumbs[] = ['label' => $name, 'url' => ''];
        // phpcs:ignore WordPress.Security.EscapeOutput -- helper tự escape
        echo pf_breadcrumb_nav($crumbs);
        ?>
        <div class="store-head-row">
          <?php if (!empty($brand->logo_url)) : ?>
            <img class="store-logo" src="<?php echo esc_url((string) $brand->logo_url); ?>" alt="<?php echo esc_attr($name); ?>">
          <?php else : ?>
            <?php
            /*
             * Huy hiệu chữ cái đầu, dùng khi store không có logo.
             *
             * Vì sao cần: 3 store có link logo của mạng tiếp thị đã chết (đo 2026-09-17), đã xoá
             * link để khỏi hiện ảnh vỡ. Nhưng bỏ trống hẳn thì phần đầu trang lệch hẳn so với 25
             * store còn lại, nên lấp bằng huy hiệu cùng kích thước, cùng viền, cùng bo góc.
             *
             * `aria-hidden` vì đây thuần trang trí: tên store đã nằm ngay trong thẻ H1 bên cạnh,
             * đọc lại một chữ cái rời chỉ làm phiền người dùng trình đọc màn hình.
             *
             * mb_substr + mb_strtoupper chứ không dùng bản thường, để tên bắt đầu bằng ký tự nhiều
             * byte không bị cắt vỡ thành dấu hỏi.
             */
            $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
            ?>
            <span class="store-logo store-logo-mono" aria-hidden="true"><?php echo esc_html($initial); ?></span>
          <?php endif; ?>
          <div class="store-head-text">
            <h1><?php echo esc_html(sprintf('%s Coupon Codes', $name)); ?></h1>
            <p class="store-head-sub">
              <?php
              echo esc_html(sprintf(
                  /* translators: 1: số offer, 2: tên store, 3: tháng năm */
                  _n('%1$d offer for %2$s, reviewed for %3$s', '%1$d offers for %2$s, reviewed for %3$s', (int) $stats['total'], 'lat-review-child'),
                  (int) $stats['total'],
                  $name,
                  date_i18n('F Y')
              ));
              ?>
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="store-band">
      <div class="wrap store-grid">
        <main class="store-main">
          <?php if (!empty($live)) : ?>
            <section class="acms-store-section">
              <h2 class="acms-store-section-title">
                <?php echo esc_html(sprintf('Top %1$s Promo Codes for %2$s', $name, date_i18n(get_option('date_format')))); ?>
              </h2>
              <?php
              // phpcs:ignore WordPress.Security.EscapeOutput -- plugin tự escape
              echo StorePage::renderCoupons($live, $brand);
              ?>
            </section>
          <?php else : ?>
            <p class="store-empty"><?php echo esc_html(sprintf('There are no live offers for %s right now. Check back soon.', $name)); ?></p>
          <?php endif; ?>

          <?php if (!empty($expired)) : ?>
            <?php
            // <details> thuần HTML: mặc định đóng, không cần JS, không đẩy coupon còn sống
            // xuống dưới, mà nội dung vẫn nằm trong HTML cho công cụ tìm kiếm.
            ?>
            <details class="store-expired">
              <summary>
                <?php
                echo esc_html(sprintf(
                    /* translators: 1: số offer, 2: tên store */
                    _n('Show %1$d expired %2$s offer', 'Show %1$d expired %2$s offers', count($expired), 'lat-review-child'),
                    count($expired),
                    $name
                ));
                ?>
              </summary>
              <?php
              // phpcs:ignore WordPress.Security.EscapeOutput -- plugin tự escape
              echo StorePage::renderCoupons($expired, $brand, true);
              ?>
            </details>
          <?php endif; ?>

          <?php
          // phpcs:ignore WordPress.Security.EscapeOutput -- plugin tự escape
          echo StorePage::renderTable($brand, $live);

          // Phần biên tập riêng theo hãng (do khâu nội dung có tra cứu điền vào). Bài chưa có
          // nội dung thì khối này rỗng, trang vẫn đứng được bằng các khối dữ liệu.
          $content = trim(get_the_content());
          if ($content !== '') :
              ?>
              <section class="acms-store-section store-editorial">
                <h2 class="acms-store-section-title"><?php echo esc_html(sprintf('More About %s', $name)); ?></h2>
                <div class="page-body"><?php the_content(); ?></div>
              </section>
              <?php
          endif;

          // phpcs:ignore WordPress.Security.EscapeOutput -- plugin tự escape
          echo StorePage::renderHowTo($brand);
          // phpcs:ignore WordPress.Security.EscapeOutput -- plugin tự escape
          echo StorePage::renderSimilar($similar);
          ?>
        </main>

        <aside class="store-side">
          <?php
          // phpcs:ignore WordPress.Security.EscapeOutput -- plugin tự escape
          echo StorePage::renderHighlights($stats);
          // phpcs:ignore WordPress.Security.EscapeOutput -- plugin tự escape
          echo StorePage::renderFaqs($brand, $stats, $live);
          // phpcs:ignore WordPress.Security.EscapeOutput -- plugin tự escape
          echo StorePage::renderHacks($pid, $brand);
          ?>
          <section class="acms-store-card store-visit">
            <h2 class="acms-store-card-title"><?php echo esc_html(sprintf('Visit %s', $name)); ?></h2>
            <p class="store-visit-domain"><?php echo esc_html((string) $brand->domain); ?></p>
            <a class="acms-coupons-btn store-visit-btn"
               href="<?php echo esc_url(StorePage::goUrl((string) ($brand->affiliate_url ?: 'https://' . $brand->domain))); ?>"
               target="_blank" rel="nofollow sponsored noopener">
              <?php echo esc_html(sprintf('Shop at %s', $name)); ?>
            </a>
          </section>
          <?php pf_author_box((int) get_post_field('post_author', $pid)); ?>
        </aside>
      </div>
    </div>
    <?php
endwhile;

get_footer();
