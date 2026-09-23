<?php
/**
 * Footer LAT Review (B&W).
 * @package LAT Review
 */
if (!defined('ABSPATH')) {
    exit;
}
$pf_year = (int) current_time('Y');
?>
<footer class="site">
  <div class="wrap">
    <div class="ftop">
      <div>
        <?php echo pf_logo(); // phpcs:ignore WordPress.Security.EscapeOutput -- markup an toàn ?>
        <p class="desc">We review and recommend the best products so you can shop with confidence.</p>
        <?php
        $pf_social = function_exists('acms_get_social_links') ? acms_get_social_links() : [];
        // Chưa cấu hình Customize > Social Links thì hiện demo fallback (link "#"),
        // giống hành vi có sẵn ở theme cha template-parts/footer/footer-minimal.php,
        // để footer không bị trống khi site chưa điền link mạng xã hội thật.
        if (!$pf_social) {
            $pf_social = [
                'facebook'  => ['url' => '#', 'icon' => 'bi-facebook', 'label' => 'Facebook'],
                'twitter'   => ['url' => '#', 'icon' => 'bi-twitter-x', 'label' => 'Twitter'],
                'instagram' => ['url' => '#', 'icon' => 'bi-instagram', 'label' => 'Instagram'],
                'youtube'   => ['url' => '#', 'icon' => 'bi-youtube', 'label' => 'YouTube'],
            ];
        }
        ?>
        <div class="social">
          <?php foreach ($pf_social as $s) : ?>
            <a href="<?php echo esc_url($s['url']); ?>" aria-label="<?php echo esc_attr($s['label']); ?>" rel="noopener" target="_blank"><i class="bi <?php echo esc_attr($s['icon']); ?>"></i></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="cols">
        <?php
        /*
         * Cột footer lấy từ menu ở vị trí `pf-footer` (Appearance > Menus), mỗi mục cấp 1 là một cột.
         * Chưa gán menu thì giữ nguyên bản dựng cứng bên dưới, đúng cách header đang làm, để site
         * mới cài chưa kịp tạo menu vẫn có footer đầy đủ.
         */
        $pf_ft_cols = function_exists('pf_get_footer_menu') ? pf_get_footer_menu() : [];
        if ($pf_ft_cols) :
            foreach ($pf_ft_cols as $pf_ft_col) :
                ?>
          <div>
            <h5><?php echo esc_html($pf_ft_col['title']); ?></h5>
            <ul>
              <?php foreach ($pf_ft_col['children'] as $pf_ft_link) : ?>
                <li><a href="<?php echo esc_url($pf_ft_link['url']); ?>"><?php echo esc_html($pf_ft_link['title']); ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
                <?php
            endforeach;
        else :
            ?>

        <div>
          <h5>Company</h5>
          <ul>
            <li><a href="<?php echo esc_url(home_url('/about/')); ?>">About us</a></li>
            <li><a href="<?php echo esc_url(home_url('/how-we-review/')); ?>">How we review</a></li>
            <li><a href="<?php echo esc_url(home_url('/about/')); ?>">Meet the team</a></li>
            <li><a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact</a></li>
          </ul>
        </div>
        <div>
          <h5>Explore</h5>
          <ul>
            <?php
            // Lấy động 4 nhóm cha đầu của nav, tránh link chết khi taxonomy đổi.
            $pf_ft_groups = array_slice(pf_nav_groups()['primary'], 0, 4);
            foreach ($pf_ft_groups as $pf_ft_g) :
                ?>
              <li><a href="<?php echo esc_url(pf_group_url($pf_ft_g['slug'])); ?>"><?php echo esc_html($pf_ft_g['label']); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <h5>Legal</h5>
          <ul>
            <li><a href="<?php echo esc_url(home_url('/affiliate-disclosure/')); ?>">Affiliate disclosure</a></li>
            <li><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Privacy policy</a></li>
            <li><a href="<?php echo esc_url(home_url('/editorial-policy/')); ?>">Editorial policy</a></li>
            <li><a href="<?php echo esc_url(home_url('/terms/')); ?>">Terms of service</a></li>
          </ul>
        </div>
            <?php
        endif;
        ?>
      </div>
    </div>
    <p class="fine">&copy; <?php echo esc_html((string) $pf_year); ?> LAT Review. All rights reserved.</p>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
