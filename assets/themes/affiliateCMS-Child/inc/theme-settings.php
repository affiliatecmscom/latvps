<?php
/**
 * AffiliateCMS Child - Theme Settings Page
 *
 * Provides Custom CSS editor and Code Injection (Head, Body, Footer).
 * Data stored in wp_options - survives parent theme updates.
 *
 * @package AffiliateCMS_Child
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   Admin Page Registration
   ========================================================================== */

/**
 * Register Theme Settings page under Appearance menu
 */
function acmsc_register_theme_settings_page(): void
{
    add_theme_page(
        __('Theme Settings', 'affiliatecms-child'),
        __('Theme Settings', 'affiliatecms-child'),
        'edit_theme_options',
        'acmsc-theme-settings',
        'acmsc_render_theme_settings_page'
    );
}
add_action('admin_menu', 'acmsc_register_theme_settings_page');

/**
 * Enqueue CodeMirror + admin assets on settings page only
 */
function acmsc_enqueue_settings_assets(string $hook): void
{
    if ($hook !== 'appearance_page_acmsc-theme-settings') {
        return;
    }

    $childUri = get_stylesheet_directory_uri();
    $childVer = wp_get_theme()->get('Version');

    // WordPress built-in CodeMirror (with enhanced settings)
    $cssSettings = wp_enqueue_code_editor([
        'type' => 'text/css',
        'codemirror' => [
            'lineNumbers'       => true,
            'lineWrapping'      => true,
            'autoCloseBrackets' => true,
            'matchBrackets'     => true,
            'styleActiveLine'   => true,
            'indentUnit'        => 4,
            'tabSize'           => 4,
            'indentWithTabs'    => false,
        ],
    ]);
    $htmlSettings = wp_enqueue_code_editor([
        'type' => 'text/html',
        'codemirror' => [
            'lineNumbers'       => true,
            'lineWrapping'      => true,
            'autoCloseBrackets' => true,
            'matchBrackets'     => true,
            'styleActiveLine'   => true,
            'indentUnit'        => 4,
            'tabSize'           => 4,
            'indentWithTabs'    => false,
        ],
    ]);

    // Admin page styles
    wp_enqueue_style(
        'acmsc-theme-settings',
        $childUri . '/assets/css/theme-settings.css',
        [],
        $childVer
    );

    // Admin page script
    wp_enqueue_script(
        'acmsc-theme-settings',
        $childUri . '/assets/js/theme-settings.js',
        ['jquery', 'wp-codemirror'],
        $childVer,
        true
    );

    // Pass CodeMirror settings to JS
    wp_localize_script('acmsc-theme-settings', 'acmscEditorSettings', [
        'css'  => $cssSettings,
        'html' => $htmlSettings,
    ]);
}
add_action('admin_enqueue_scripts', 'acmsc_enqueue_settings_assets');

/* ==========================================================================
   Admin Page Rendering
   ========================================================================== */

/**
 * Render the Theme Settings admin page
 */
function acmsc_render_theme_settings_page(): void
{
    if (!current_user_can('edit_theme_options')) {
        wp_die(__('You do not have permission to access this page.', 'affiliatecms-child'));
    }

    // Get saved values
    $custom_css  = get_option('acmsc_custom_css', '');
    $code_head   = get_option('acmsc_code_head', '');
    $code_body   = get_option('acmsc_code_body_open', '');
    $code_footer = get_option('acmsc_code_footer', '');

    // Display settings
    $theme_mode       = get_option('acmsc_theme_mode', 'system');
    $time_dark_start  = get_option('acmsc_time_dark_start', '18:00');
    $time_dark_end    = get_option('acmsc_time_dark_end', '06:00');

    // Active tab
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'custom-css';
    if (!in_array($active_tab, ['custom-css', 'code-injection', 'display'], true)) {
        $active_tab = 'custom-css';
    }

    // Success notice
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html__('Settings saved successfully.', 'affiliatecms-child');
        echo '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Theme Settings', 'affiliatecms-child'); ?></h1>

        <nav class="nav-tab-wrapper">
            <a href="?page=acmsc-theme-settings&tab=custom-css"
               class="nav-tab <?php echo $active_tab === 'custom-css' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-admin-appearance" style="margin-right: 4px; line-height: 1.4;"></span>
                <?php esc_html_e('Custom CSS', 'affiliatecms-child'); ?>
            </a>
            <a href="?page=acmsc-theme-settings&tab=code-injection"
               class="nav-tab <?php echo $active_tab === 'code-injection' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-editor-code" style="margin-right: 4px; line-height: 1.4;"></span>
                <?php esc_html_e('Code Injection', 'affiliatecms-child'); ?>
            </a>
            <a href="?page=acmsc-theme-settings&tab=display"
               class="nav-tab <?php echo $active_tab === 'display' ? 'nav-tab-active' : ''; ?>">
                <span class="dashicons dashicons-visibility" style="margin-right: 4px; line-height: 1.4;"></span>
                <?php esc_html_e('Display', 'affiliatecms-child'); ?>
            </a>
        </nav>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('acmsc_save_theme_settings', 'acmsc_settings_nonce'); ?>
            <input type="hidden" name="action" value="acmsc_save_theme_settings">
            <input type="hidden" name="active_tab" value="<?php echo esc_attr($active_tab); ?>">

            <!-- Tab 1: Custom CSS -->
            <div id="tab-custom-css" class="acmsc-tab-content" style="<?php echo $active_tab !== 'custom-css' ? 'display:none;' : ''; ?>">
                <div class="acmsc-card">
                    <h2>
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <?php esc_html_e('Custom CSS', 'affiliatecms-child'); ?>
                    </h2>
                    <p class="description">
                        <?php esc_html_e('Add custom CSS that loads after all theme styles. Changes are saved to the database and persist across theme updates.', 'affiliatecms-child'); ?>
                    </p>
                    <textarea id="acmsc_custom_css"
                              name="acmsc_custom_css"
                              rows="20"
                              class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                </div>
            </div>

            <!-- Tab 2: Code Injection -->
            <div id="tab-code-injection" class="acmsc-tab-content" style="<?php echo $active_tab !== 'code-injection' ? 'display:none;' : ''; ?>">

                <!-- Head Code -->
                <div class="acmsc-card">
                    <h2>
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e('Head Code', 'affiliatecms-child'); ?>
                        <code class="acmsc-hook-tag">&lt;/head&gt;</code>
                    </h2>
                    <p class="description">
                        <?php esc_html_e('Injected before </head>. Use for meta tags, analytics (Google Analytics, GTM), custom fonts, Open Graph tags.', 'affiliatecms-child'); ?>
                    </p>
                    <textarea id="acmsc_code_head"
                              name="acmsc_code_head"
                              rows="10"
                              class="large-text code"><?php echo esc_textarea($code_head); ?></textarea>
                </div>

                <!-- Body Open Code -->
                <div class="acmsc-card">
                    <h2>
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e('Body Open Code', 'affiliatecms-child'); ?>
                        <code class="acmsc-hook-tag">&lt;body&gt;</code>
                    </h2>
                    <p class="description">
                        <?php esc_html_e('Injected right after <body>. Use for Google Tag Manager noscript, tracking pixels.', 'affiliatecms-child'); ?>
                    </p>
                    <textarea id="acmsc_code_body_open"
                              name="acmsc_code_body_open"
                              rows="8"
                              class="large-text code"><?php echo esc_textarea($code_body); ?></textarea>
                </div>

                <!-- Footer Code -->
                <div class="acmsc-card">
                    <h2>
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e('Footer Code', 'affiliatecms-child'); ?>
                        <code class="acmsc-hook-tag">&lt;/body&gt;</code>
                    </h2>
                    <p class="description">
                        <?php esc_html_e('Injected before </body>. Use for chat widgets, deferred scripts, tracking pixels.', 'affiliatecms-child'); ?>
                    </p>
                    <textarea id="acmsc_code_footer"
                              name="acmsc_code_footer"
                              rows="8"
                              class="large-text code"><?php echo esc_textarea($code_footer); ?></textarea>
                </div>
            </div>

            <!-- Tab 3: Display Settings -->
            <div id="tab-display" class="acmsc-tab-content" style="<?php echo $active_tab !== 'display' ? 'display:none;' : ''; ?>">
                <div class="acmsc-card">
                    <h2>
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('Dark / Light Mode', 'affiliatecms-child'); ?>
                    </h2>
                    <p class="description">
                        <?php esc_html_e('Control how the theme appearance mode is determined for visitors.', 'affiliatecms-child'); ?>
                    </p>

                    <fieldset class="acmsc-mode-options">
                        <label class="acmsc-mode-option <?php echo $theme_mode === 'light' ? 'acmsc-mode-option--active' : ''; ?>">
                            <input type="radio" name="acmsc_theme_mode" value="light" <?php checked($theme_mode, 'light'); ?>>
                            <span class="acmsc-mode-option__icon dashicons dashicons-admin-appearance"></span>
                            <span class="acmsc-mode-option__content">
                                <strong><?php esc_html_e('Light', 'affiliatecms-child'); ?></strong>
                                <span><?php esc_html_e('Always use light theme. Visitors cannot toggle.', 'affiliatecms-child'); ?></span>
                            </span>
                        </label>

                        <label class="acmsc-mode-option <?php echo $theme_mode === 'dark' ? 'acmsc-mode-option--active' : ''; ?>">
                            <input type="radio" name="acmsc_theme_mode" value="dark" <?php checked($theme_mode, 'dark'); ?>>
                            <span class="acmsc-mode-option__icon dashicons dashicons-admin-customizer"></span>
                            <span class="acmsc-mode-option__content">
                                <strong><?php esc_html_e('Dark', 'affiliatecms-child'); ?></strong>
                                <span><?php esc_html_e('Always use dark theme. Visitors cannot toggle.', 'affiliatecms-child'); ?></span>
                            </span>
                        </label>

                        <label class="acmsc-mode-option <?php echo $theme_mode === 'system' ? 'acmsc-mode-option--active' : ''; ?>">
                            <input type="radio" name="acmsc_theme_mode" value="system" <?php checked($theme_mode, 'system'); ?>>
                            <span class="acmsc-mode-option__icon dashicons dashicons-desktop"></span>
                            <span class="acmsc-mode-option__content">
                                <strong><?php esc_html_e('System Preference', 'affiliatecms-child'); ?></strong>
                                <span><?php esc_html_e('Follow visitor\'s OS setting (prefers-color-scheme). Visitors can override with toggle button.', 'affiliatecms-child'); ?></span>
                            </span>
                        </label>

                        <label class="acmsc-mode-option <?php echo $theme_mode === 'time' ? 'acmsc-mode-option--active' : ''; ?>">
                            <input type="radio" name="acmsc_theme_mode" value="time" <?php checked($theme_mode, 'time'); ?>>
                            <span class="acmsc-mode-option__icon dashicons dashicons-clock"></span>
                            <span class="acmsc-mode-option__content">
                                <strong><?php esc_html_e('Time-based', 'affiliatecms-child'); ?></strong>
                                <span><?php esc_html_e('Auto-switch based on time of day (uses WordPress timezone). Visitors can override with toggle button.', 'affiliatecms-child'); ?></span>
                            </span>
                        </label>
                    </fieldset>

                    <!-- Time-based settings (shown only when time mode selected) -->
                    <div id="acmsc-time-settings" class="acmsc-time-settings" style="<?php echo $theme_mode !== 'time' ? 'display:none;' : ''; ?>">
                        <h3><?php esc_html_e('Dark Mode Schedule', 'affiliatecms-child'); ?></h3>
                        <p class="description">
                            <?php
                            $tz = wp_timezone_string();
                            printf(
                                esc_html__('Based on WordPress timezone: %s', 'affiliatecms-child'),
                                '<code>' . esc_html($tz) . '</code>'
                            );
                            ?>
                        </p>
                        <div class="acmsc-time-fields">
                            <label>
                                <?php esc_html_e('Dark mode starts at:', 'affiliatecms-child'); ?>
                                <input type="time" name="acmsc_time_dark_start" value="<?php echo esc_attr($time_dark_start); ?>">
                            </label>
                            <span class="acmsc-time-arrow dashicons dashicons-arrow-right-alt"></span>
                            <label>
                                <?php esc_html_e('Dark mode ends at:', 'affiliatecms-child'); ?>
                                <input type="time" name="acmsc_time_dark_end" value="<?php echo esc_attr($time_dark_end); ?>">
                            </label>
                        </div>
                        <p class="description" style="margin-top: 8px;">
                            <?php esc_html_e('Example: Start 18:00, End 06:00 = Dark mode from 6 PM to 6 AM.', 'affiliatecms-child'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php submit_button(__('Save Settings', 'affiliatecms-child')); ?>
        </form>
    </div>
    <?php
}

/* ==========================================================================
   Save Handler
   ========================================================================== */

/**
 * Sanitize code injection input
 */
function acmsc_sanitize_code_injection(string $code): string
{
    $code = str_replace(chr(0), '', $code);
    $code = str_replace(["\r\n", "\r"], "\n", $code);
    return trim($code);
}

/**
 * Handle form submission
 */
function acmsc_save_theme_settings(): void
{
    // Verify nonce
    if (!isset($_POST['acmsc_settings_nonce']) ||
        !wp_verify_nonce($_POST['acmsc_settings_nonce'], 'acmsc_save_theme_settings')) {
        wp_die(__('Security check failed.', 'affiliatecms-child'));
    }

    // Verify capability
    if (!current_user_can('edit_theme_options')) {
        wp_die(__('You do not have permission to save these settings.', 'affiliatecms-child'));
    }

    // Custom CSS - strip HTML tags, keep only CSS
    $custom_css = isset($_POST['acmsc_custom_css']) ? wp_strip_all_tags(wp_unslash($_POST['acmsc_custom_css'])) : '';
    update_option('acmsc_custom_css', trim($custom_css), false);

    // Display settings
    $theme_mode = isset($_POST['acmsc_theme_mode']) ? sanitize_key(wp_unslash($_POST['acmsc_theme_mode'])) : 'system';
    if (!in_array($theme_mode, ['light', 'dark', 'system', 'time'], true)) {
        $theme_mode = 'system';
    }
    update_option('acmsc_theme_mode', $theme_mode, true);

    if ($theme_mode === 'time') {
        $time_start = isset($_POST['acmsc_time_dark_start']) ? sanitize_text_field(wp_unslash($_POST['acmsc_time_dark_start'])) : '18:00';
        $time_end   = isset($_POST['acmsc_time_dark_end']) ? sanitize_text_field(wp_unslash($_POST['acmsc_time_dark_end'])) : '06:00';
        update_option('acmsc_time_dark_start', $time_start, true);
        update_option('acmsc_time_dark_end', $time_end, true);
    }

    // Code injection fields - allow HTML/script (that's the purpose)
    // wp_unslash() removes backslashes added by WordPress wp_magic_quotes()
    $code_head = isset($_POST['acmsc_code_head']) ? acmsc_sanitize_code_injection(wp_unslash($_POST['acmsc_code_head'])) : '';
    update_option('acmsc_code_head', $code_head, false);

    $code_body = isset($_POST['acmsc_code_body_open']) ? acmsc_sanitize_code_injection(wp_unslash($_POST['acmsc_code_body_open'])) : '';
    update_option('acmsc_code_body_open', $code_body, false);

    $code_footer = isset($_POST['acmsc_code_footer']) ? acmsc_sanitize_code_injection(wp_unslash($_POST['acmsc_code_footer'])) : '';
    update_option('acmsc_code_footer', $code_footer, false);

    // Redirect back with success
    $active_tab = isset($_POST['active_tab']) ? sanitize_key($_POST['active_tab']) : 'custom-css';
    wp_safe_redirect(add_query_arg([
        'page'             => 'acmsc-theme-settings',
        'tab'              => $active_tab,
        'settings-updated' => 'true',
    ], admin_url('themes.php')));
    exit;
}
add_action('admin_post_acmsc_save_theme_settings', 'acmsc_save_theme_settings');

/* ==========================================================================
   Frontend Output
   ========================================================================== */

/**
 * Output custom CSS in <head> (after all theme styles)
 */
function acmsc_output_custom_css(): void
{
    $css = get_option('acmsc_custom_css', '');
    if (empty($css)) {
        return;
    }
    echo "\n<style id=\"acmsc-custom-css\">\n" . strip_tags($css) . "\n</style>\n";
}
add_action('wp_head', 'acmsc_output_custom_css', 999);

/**
 * Output head code injection
 */
function acmsc_output_code_head(): void
{
    $code = get_option('acmsc_code_head', '');
    if (!empty($code)) {
        echo "\n" . $code . "\n";
    }
}
add_action('wp_head', 'acmsc_output_code_head', 998);

/**
 * Output body open code injection
 */
function acmsc_output_code_body_open(): void
{
    $code = get_option('acmsc_code_body_open', '');
    if (!empty($code)) {
        echo "\n" . $code . "\n";
    }
}
add_action('wp_body_open', 'acmsc_output_code_body_open', 10);

/**
 * Output footer code injection
 */
function acmsc_output_code_footer(): void
{
    $code = get_option('acmsc_code_footer', '');
    if (!empty($code)) {
        echo "\n" . $code . "\n";
    }
}
add_action('wp_footer', 'acmsc_output_code_footer', 999);
