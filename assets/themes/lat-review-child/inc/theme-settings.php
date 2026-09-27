<?php
/**
 * LAT Review Child, Theme Settings: Code Injection (Head, Body open, Footer).
 *
 * Port từ affiliateCMS-Child/inc/theme-settings.php, chỉ giữ phần chèn code (Google Tag Manager,
 * Analytics, pixel). Giữ nguyên tên option acmsc_code_* để site chuyển từ child theme cũ sang
 * vẫn còn mã đã dán. Lưu ở wp_options nên cập nhật theme không mất.
 *
 * Khác bản cũ:
 * - Lưu đòi thêm quyền unfiltered_html (bản cũ chỉ kiểm edit_theme_options).
 * - Head code in ở priority 1 (sát đầu <head>) theo khuyến nghị của GTM, bản cũ in gần cuối.
 * - Không có CSS/JS riêng, dùng class có sẵn của wp-admin.
 *
 * @package LAT Review
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const LATRC_CODE_FIELDS = [
    'acmsc_code_head'      => 'Head Code',
    'acmsc_code_body_open' => 'Body Open Code',
    'acmsc_code_footer'    => 'Footer Code',
];

/** Chèn code là chèn script chạy trên mọi trang, nên đòi cả hai quyền. */
function latrc_can_edit_code(): bool
{
    return current_user_can('edit_theme_options') && current_user_can('unfiltered_html');
}

/* ============================================================
 * TRANG ADMIN: Appearance > Theme Settings
 * ============================================================ */
add_action('admin_menu', static function (): void {
    add_theme_page(
        __('Theme Settings', 'lat-review-child'),
        __('Theme Settings', 'lat-review-child'),
        'edit_theme_options',
        'latrc-theme-settings',
        'latrc_render_theme_settings_page'
    );
});

function latrc_render_theme_settings_page(): void
{
    if (!current_user_can('edit_theme_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'lat-review-child'));
    }
    $canEdit = latrc_can_edit_code();

    $hints = [
        'acmsc_code_head'      => [
            'tag'  => '<head>',
            'desc' => __('Printed near the top of <head>. Paste the main Google Tag Manager snippet here, or Google Analytics, site verification meta tags.', 'lat-review-child'),
            'rows' => 10,
        ],
        'acmsc_code_body_open' => [
            'tag'  => '<body>',
            'desc' => __('Printed right after the opening <body> tag. Paste the Google Tag Manager <noscript> snippet here.', 'lat-review-child'),
            'rows' => 6,
        ],
        'acmsc_code_footer'    => [
            'tag'  => '</body>',
            'desc' => __('Printed before </body>. Use for chat widgets, deferred scripts, tracking pixels.', 'lat-review-child'),
            'rows' => 6,
        ],
    ];

    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__('Settings saved.', 'lat-review-child') . '</p></div>';
    }
    if (!$canEdit) {
        echo '<div class="notice notice-warning"><p>'
            . esc_html__('Your account cannot save scripts (missing the unfiltered_html capability). Fields are read-only.', 'lat-review-child')
            . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Theme Settings', 'lat-review-child'); ?></h1>
        <p><?php esc_html_e('Code Injection: add tracking code such as Google Tag Manager to every page of the site. Saved in the database, kept across theme updates.', 'lat-review-child'); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('latrc_save_theme_settings', 'latrc_settings_nonce'); ?>
            <input type="hidden" name="action" value="latrc_save_theme_settings">

            <table class="form-table" role="presentation">
                <?php foreach (LATRC_CODE_FIELDS as $key => $label) : ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                            <p class="description"><code><?php echo esc_html($hints[$key]['tag']); ?></code></p>
                        </th>
                        <td>
                            <textarea id="<?php echo esc_attr($key); ?>"
                                      name="<?php echo esc_attr($key); ?>"
                                      rows="<?php echo (int) $hints[$key]['rows']; ?>"
                                      class="large-text code"
                                      spellcheck="false"
                                      <?php disabled(!$canEdit); ?>><?php echo esc_textarea((string) get_option($key, '')); ?></textarea>
                            <p class="description"><?php echo esc_html($hints[$key]['desc']); ?></p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php if ($canEdit) {
                submit_button(__('Save Settings', 'lat-review-child'));
            } ?>
        </form>
    </div>
    <?php
}

/* ============================================================
 * LƯU
 * ============================================================ */
add_action('admin_post_latrc_save_theme_settings', static function (): void {
    if (!isset($_POST['latrc_settings_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['latrc_settings_nonce'])), 'latrc_save_theme_settings')) {
        wp_die(esc_html__('Security check failed.', 'lat-review-child'));
    }
    if (!latrc_can_edit_code()) {
        wp_die(esc_html__('You do not have permission to save these settings.', 'lat-review-child'));
    }

    foreach (array_keys(LATRC_CODE_FIELDS) as $key) {
        // Cố ý cho phép HTML/script, đó là mục đích của ô này. Chỉ bỏ byte NUL và chuẩn hoá xuống dòng.
        $code = isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : '';
        $code = str_replace([chr(0), "\r\n", "\r"], ['', "\n", "\n"], $code);
        update_option($key, trim($code), false);
    }

    wp_safe_redirect(add_query_arg([
        'page'             => 'latrc-theme-settings',
        'settings-updated' => 'true',
    ], admin_url('themes.php')));
    exit;
});

/* ============================================================
 * IN RA FRONTEND
 * ============================================================ */
function latrc_print_code(string $key): void
{
    $code = (string) get_option($key, '');
    if ($code !== '') {
        echo "\n" . $code . "\n";
    }
}

add_action('wp_head', static fn () => latrc_print_code('acmsc_code_head'), 1);
add_action('wp_body_open', static fn () => latrc_print_code('acmsc_code_body_open'), 1);
add_action('wp_footer', static fn () => latrc_print_code('acmsc_code_footer'), 999);
