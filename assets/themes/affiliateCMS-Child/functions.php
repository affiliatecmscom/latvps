<?php
/**
 * AffiliateCMS Child Theme Functions
 *
 * Add your custom functions, hooks, and overrides here.
 * This file is loaded AFTER the parent theme's functions.php.
 *
 * @package AffiliateCMS_Child
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue parent + child theme styles
 */
function affiliatecms_child_enqueue_styles(): void
{
    // Parent theme styles (already enqueued by parent, just declare dependency)
    $parentHandle = 'acms-main';

    // Child theme styles (loads after parent)
    wp_enqueue_style(
        'affiliatecms-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        [$parentHandle],
        wp_get_theme()->get('Version')
    );
}
add_action('wp_enqueue_scripts', 'affiliatecms_child_enqueue_styles', 20);

/* ==========================================================================
   Custom Functions - Add your code below
   ========================================================================== */

// Theme Settings page (Custom CSS + Code Injection)
require_once get_stylesheet_directory() . '/inc/theme-settings.php';
/**
 * Truncate Rank Math Title and Description
 */
add_filter('rank_math/frontend/title', function ($title) {
    $max_length = 70;
    if (mb_strlen((string) $title) > $max_length) {
        $title = mb_substr((string) $title, 0, $max_length - 3) . '...';
    }
    return $title;
}, 999);

add_filter('rank_math/frontend/description', function ($description) {
    $max_length = 160;
    if (mb_strlen((string) $description) > $max_length) {
        $description = mb_substr((string) $description, 0, $max_length - 3) . '...';
    }
    return $description;
}, 999);

// Thêm field upload avatar vào profile user
add_action('show_user_profile', 'custom_user_avatar_field');
add_action('edit_user_profile', 'custom_user_avatar_field');

function custom_user_avatar_field($user) {
    $avatar = get_user_meta($user->ID, 'custom_avatar', true);
    ?>
    <h3>Custom Avatar</h3>
    <table class="form-table">
        <tr>
            <th><label for="custom_avatar">Upload Avatar</label></th>
            <td>
                <input type="text" name="custom_avatar" id="custom_avatar" value="<?php echo esc_attr($avatar); ?>" class="regular-text" />
                <input type="button" class="button upload-avatar-button" value="Upload Avatar" />
                <br><br>

                <?php if ($avatar) : ?>
                    <img src="<?php echo esc_url($avatar); ?>" width="100" height="100" style="border-radius:50%;" />
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <script>
    jQuery(document).ready(function($){
        $('.upload-avatar-button').click(function(e) {
            e.preventDefault();

            var image = wp.media({
                title: 'Upload Avatar',
                multiple: false
            }).open()
            .on('select', function() {
                var uploaded = image.state().get('selection').first();
                var url = uploaded.toJSON().url;

                $('#custom_avatar').val(url);
            });
        });
    });
    </script>
    <?php
}

// Lưu avatar
add_action('personal_options_update', 'save_custom_user_avatar');
add_action('edit_user_profile_update', 'save_custom_user_avatar');

function save_custom_user_avatar($user_id) {
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'custom_avatar', esc_url($_POST['custom_avatar']));
    }
}

// Thay thế Gravatar bằng avatar upload
add_filter('get_avatar', 'custom_get_avatar', 10, 5);

function custom_get_avatar($avatar, $id_or_email, $size, $default, $alt) {

    if (is_numeric($id_or_email)) {
        $user_id = $id_or_email;
    } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
        $user_id = $id_or_email->user_id;
    } else {
        $user = get_user_by('email', $id_or_email);
        $user_id = $user ? $user->ID : 0;
    }

    if ($user_id) {
        $custom_avatar = get_user_meta($user_id, 'custom_avatar', true);

        if ($custom_avatar) {
            return '<img src="' . esc_url($custom_avatar) . '" width="' . $size . '" height="' . $size . '" alt="' . esc_attr($alt) . '" class="avatar avatar-' . $size . ' photo" style="border-radius:50%;" />';
        }
    }

    return $avatar;
}

// Load media uploader
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_media();
});
