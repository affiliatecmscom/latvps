<?php
/**
 * LAT Review Child theme, functions.
 * Chiến lược (như retailcoupons-child): reuse hạ tầng theme cha affiliateCMS-theme,
 * chỉ remap token màu (style.css) + template riêng. KHÔNG viết lại component cha.
 *
 * @package LAT Review
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
 * ENQUEUE: Montserrat + child style (dep acms-main để nạp SAU cha)
 * ============================================================ */
add_action('wp_enqueue_scripts', static function (): void {
    // Montserrat (thay Inter của cha). preconnect + weight cần cho logo 800.
    wp_enqueue_style(
        'lat-review-fonts',
        'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap',
        [],
        null
    );

    $childCss = get_stylesheet_directory() . '/style.css';
    wp_enqueue_style(
        'lat-review-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['acms-main'], // handle CSS chính của theme cha -> child luôn nạp sau
        file_exists($childCss) ? (string) filemtime($childCss) : null
    );

    // Load-more chỉ trên archive (leaf/tag/date/author/search).
    if (is_category() || is_tag() || is_date() || is_author() || is_search()) {
        $js = get_stylesheet_directory() . '/assets/js/loadmore.js';
        wp_enqueue_script('pf-loadmore', get_stylesheet_directory_uri() . '/assets/js/loadmore.js', [], file_exists($js) ? (string) filemtime($js) : '1', true);
        wp_localize_script('pf-loadmore', 'PF_LM', ['rest' => esc_url_raw(rest_url('pf/v1/posts'))]);
    }

    // Up-next infinite: đọc hết bài -> tự tải bài kế cùng danh mục (chỉ single post).
    if (is_singular('post')) {
        $ujs = get_stylesheet_directory() . '/assets/js/up-next.js';
        wp_enqueue_script('pf-upnext', get_stylesheet_directory_uri() . '/assets/js/up-next.js', [], file_exists($ujs) ? (string) filemtime($ujs) : '1', true);
        wp_localize_script('pf-upnext', 'PF_UN', ['rest' => esc_url_raw(rest_url('pf/v1/next-post'))]);
        // TOC "In this guide": scroll offset sticky header.
        $tjs = get_stylesheet_directory() . '/assets/js/pf-toc.js';
        wp_enqueue_script('pf-toc', get_stylesheet_directory_uri() . '/assets/js/pf-toc.js', [], file_exists($tjs) ? (string) filemtime($tjs) : '1', true);
    }

    // Tooltip ⓘ: lật hướng theo chỗ trống (CSS plugin luôn bung lên, bị header sticky
    // và mép viewport cắt). Chỉ nạp nơi có khối ASIN: single post + trang compare.
    if (is_singular('post') || get_query_var('acms_cmp')) {
        $ttjs = get_stylesheet_directory() . '/assets/js/pf-tooltip.js';
        wp_enqueue_script('pf-tooltip', get_stylesheet_directory_uri() . '/assets/js/pf-tooltip.js', [], file_exists($ttjs) ? (string) filemtime($ttjs) : '1', true);
    }
}, 20);

// preconnect Google Fonts (giảm latency font).
add_filter('wp_resource_hints', static function (array $hints, string $relation): array {
    if ($relation === 'preconnect') {
        $hints[] = 'https://fonts.googleapis.com';
        $hints[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin'];
    }
    return $hints;
}, 10, 2);

/* ============================================================
 * MENU locations riêng của LAT Review
 * ============================================================ */
add_action('after_setup_theme', static function (): void {
    register_nav_menus([
        'pf-primary' => 'Primary (mega-menu)',
        'pf-footer'  => 'Footer Links',
    ]);
});

/* ============================================================
 * Affiliate: bơm rel="sponsored" vào nút của shortcode Pro
 * (Pro chỉ xuất rel="nofollow"; FTC + SEO cần thêm sponsored).
 * ============================================================ */
add_filter('do_shortcode_tag', static function ($output, $tag) {
    if (!in_array($tag, ['acms_list', 'acms_grid', 'acms_card'], true)) {
        return $output;
    }
    return preg_replace_callback(
        '/<a\b([^>]*\bclass="[^"]*acms-btn[^"]*"[^>]*)>/i',
        static function (array $m): string {
            $attrs = $m[1];
            if (stripos($attrs, 'rel=') === false) {
                return '<a' . $attrs . ' rel="nofollow sponsored">';
            }
            if (stripos($attrs, 'sponsored') === false) {
                $attrs = preg_replace('/\brel="([^"]*)"/i', 'rel="$1 sponsored"', $attrs);
            }
            return '<a' . $attrs . '>';
        },
        (string) $output
    );
}, 10, 2);

/* ============================================================
 * Không để page-{slug}.php của THEME CHA (vd page-about.php demo) chiếm trang.
 * Nếu template được chọn nằm ở theme cha + là page-{slug}.php + page không gán
 * custom template -> ép dùng page.php của child. Giữ page-{slug}.php của CHILD.
 * ============================================================ */
add_filter('template_include', static function ($template) {
    if (is_page()) {
        $pg = get_queried_object();
        $assigned = $pg instanceof WP_Post ? get_page_template_slug($pg->ID) : '';
        if (empty($assigned)
            && strpos((string) $template, get_template_directory()) === 0
            && preg_match('#/page-[^/]+\.php$#', (string) $template)) {
            $child = locate_template('page.php');
            if ($child) {
                return $child;
            }
        }
    }
    return $template;
}, 99);

/* ============================================================
 * Năm động vào SEO title của ROUNDUP (title bắt đầu "Best"), PRD mục 4.
 * Chỉ đổi meta <title> (SERP/tab), KHÔNG đổi H1. Guide (không "Best") giữ nguyên.
 * ============================================================ */
// Khi RankMath điều khiển title.
add_filter('rank_math/frontend/title', static function ($title) {
    if (is_singular('post')) {
        $pt = get_the_title();
        if ($pt && preg_match('/^best\b/i', $pt)) {
            $year = wp_date('Y');
            if (strpos((string) $title, $pt) !== false && strpos((string) $title, '(' . $year . ')') === false) {
                return str_replace($pt, $pt . ' (' . $year . ')', (string) $title);
            }
        }
    }
    return $title;
}, 20);
// Khi WP core build title (document_title_parts nhận mảng có 'title').
add_filter('document_title_parts', static function (array $parts): array {
    if (is_singular('post') && !empty($parts['title'])) {
        $pt = get_the_title();
        if ($pt && preg_match('/^best\b/i', $pt) && strpos((string) $parts['title'], '(' . wp_date('Y') . ')') === false) {
            $parts['title'] = $pt . ' (' . wp_date('Y') . ')';
        }
    }
    return $parts;
}, 20);

/* ============================================================
 * Tắt module TOC của theme cha (dùng sidebar .rd-toc riêng, tránh 2 TOC)
 * ============================================================ */
add_action('template_redirect', static function (): void {
    add_filter('acms_toc_auto_insert', '__return_false');
    remove_action('wp_footer', 'acms_toc_render_bubble');
});
add_action('wp_enqueue_scripts', static function (): void {
    wp_dequeue_style('acms-toc');
    wp_dequeue_script('acms-toc');
}, 20);

/* ============================================================
 * HELPERS render (dùng trong template)
 * ============================================================ */

/**
 * Info tooltip giải thích LAT Score. Tái dùng component .acms-tooltip của plugin
 * (bi-info-circle + .acms-tooltip__content), modifier --pf để câu dài xuống dòng.
 * Nội dung LẤY ĐỘNG từ cài đặt AffiliateCMS Pro (option acms_general_settings[score_tooltip_text],
 * sửa ở admin -> tự cập nhật). Cho phép HTML an toàn (strong, link) qua wp_kses_post.
 * $down = true: tooltip mở xuống dưới (dùng ở header bảng compare vì .compare-scroll clip overflow trên).
 * Dùng cạnh mọi chỗ hiển thị LAT Score (list.php compare header + p-rating, compare.php).
 */
/**
 * Label điểm PF (mặc định "LAT Score", đổi từ "LAT Review Score" 2026-09-10). LẤY ĐỘNG từ cài đặt
 * AffiliateCMS Pro (option acms_general_settings[score_label_text]) để đồng bộ mọi chỗ + admin sửa tự đổi.
 */
function pf_score_label(): string
{
    $s     = get_option('acms_general_settings', []);
    $label = is_array($s) ? trim((string) ($s['score_label_text'] ?? '')) : '';
    return $label !== '' ? $label : __('LAT Score', 'lat-review');
}

/**
 * Info tooltip cho "Updated: {date}" của ASIN. Nội dung LẤY ĐỘNG từ cài đặt
 * AffiliateCMS Pro (option acms_general_settings[update_tooltip_text]), thay {date}
 * bằng ngày thật ($detailedDate). Tái dùng component .acms-tooltip--pf (tap mobile + B&W).
 */
function pf_update_tooltip(string $detailedDate): string
{
    $s   = get_option('acms_general_settings', []);
    $txt = is_array($s) ? trim((string) ($s['update_tooltip_text'] ?? '')) : '';
    if ($txt === '') {
        $txt = __('Last update on {date}. Affiliate links; prices and availability are subject to change.', 'lat-review');
    }
    $txt = str_replace('{date}', $detailedDate, $txt);
    return '<span class="acms-tooltip acms-tooltip--pf" data-tooltip>'
        . '<i class="bi bi-info-circle" aria-hidden="true"></i>'
        . '<span class="screen-reader-text">' . esc_html__('About this update', 'lat-review') . '</span>'
        . '<span class="acms-tooltip__content">' . wp_kses_post($txt) . '</span>'
        . '</span>';
}

function pf_score_tooltip(bool $down = false): string
{
    $s   = get_option('acms_general_settings', []);
    $txt = is_array($s) ? trim((string) ($s['score_tooltip_text'] ?? '')) : '';
    if ($txt === '') {
        $txt = __('LAT Score (out of 10) is our overall rating based on performance, design and build, ease of use, and value.', 'lat-review');
    }
    $cls = 'acms-tooltip acms-tooltip--pf' . ($down ? ' acms-tooltip--down' : '');
    return '<span class="' . esc_attr($cls) . '" data-tooltip>'
        . '<i class="bi bi-info-circle" aria-hidden="true"></i>'
        . '<span class="screen-reader-text">' . esc_html__('About LAT Score', 'lat-review') . '</span>'
        . '<span class="acms-tooltip__content">' . wp_kses_post($txt) . '</span>'
        . '</span>';
}

/**
 * Nhãn rút gọn cho nav: tên danh mục đầy đủ quá dài thì thanh nav vỡ.
 * Slug nào không có ở đây thì dùng nguyên tên danh mục.
 */
function pf_nav_labels(): array
{
    return [
        'kitchen-dining'          => 'Kitchen',
        'home-furniture'          => 'Home',
        'sleep-bedding'           => 'Sleep',
        'garden-patio'            => 'Garden',
        'tools-home-improvement'  => 'Tools',
        'computers-office'        => 'Computers',
        'beauty-personal-care'    => 'Beauty',
        'sports-outdoors'         => 'Sports',
        'baby-kids'               => 'Baby & Kids',
        'toys-games'              => 'Toys',
        'style-accessories'       => 'Style',
        'travel-luggage'          => 'Travel',
        'hobbies-crafts'          => 'Hobbies',
    ];
}

/**
 * Cấu hình nav nhóm cha (PRD mục 13): 5 nhóm hiện trực tiếp, phần còn lại trong "All Categories".
 * Lấy ĐỘNG từ taxonomy, nên seed thêm hay bớt nhóm cha thì nav tự cập nhật, không phải sửa code.
 * Slug trong $primary lên trước theo đúng thứ tự đó, các nhóm còn lại xếp theo tên.
 * Blog (trước là Uncategorized) là danh mục nội dung, KHÔNG phải nhóm hàng, và không có
 * danh mục con, nên loại khỏi lưới nhóm cha. Nó nằm ở khối phải header, xem header.php.
 */
function pf_nav_groups(): array
{
    $primary = [
        'electronics',
        'kitchen-dining',
        'home-furniture',
        'appliances',
        'beauty-personal-care',
    ];
    $exclude = ['blog'];

    $terms = get_terms([
        'taxonomy'   => 'category',
        'parent'     => 0,
        'hide_empty' => false,
        'orderby'    => 'name',
    ]);
    if (is_wp_error($terms) || empty($terms)) {
        return ['primary' => [], 'more' => []];
    }

    $labels = pf_nav_labels();
    $bySlug = [];
    foreach ($terms as $t) {
        if (in_array($t->slug, $exclude, true)) {
            continue;
        }
        $bySlug[$t->slug] = [
            'label' => $labels[$t->slug] ?? html_entity_decode($t->name, ENT_QUOTES),
            'slug'  => $t->slug,
        ];
    }

    $out = ['primary' => [], 'more' => []];
    foreach ($primary as $slug) {
        if (isset($bySlug[$slug])) {
            $out['primary'][] = $bySlug[$slug];
            unset($bySlug[$slug]);
        }
    }
    $out['more'] = array_values($bySlug);

    return $out;
}

/**
 * Bản đồ chồng lấn danh mục giữa các nhóm cha (CHỐT 2026-09-05, phương án A).
 *
 * Một khái niệm chỉ có MỘT term, không nhân bản, vì WordPress buộc bản sao mang slug
 * hậu tố kiểu `tablets-computers-office` (đã thử nghiệm), URL xấu và hai trang cùng chủ đề
 * sẽ tự cạnh tranh từ khoá. Thay vào đó bài roundup gán nhiều danh mục (tính năng sẵn của WP),
 * còn trang danh mục nối chéo bằng khối "Related categories" này.
 *
 * Khai báo MỘT chiều là đủ, hàm dưới tự suy chiều ngược lại.
 */
function pf_related_map(): array
{
    return [
        'tablets'                 => ['computers-office'],
        'gaming-headsets'         => ['toys-games'],
        'portable-power-stations' => ['sports-outdoors', 'automotive'],
    ];
}

/** Danh mục liên quan của $term, đã suy cả hai chiều. Trả mảng WP_Term, rỗng nếu không có. */
function pf_related_categories(WP_Term $term): array
{
    $map  = pf_related_map();
    $slug = $term->slug;
    $rel  = $map[$slug] ?? [];

    foreach ($map as $nguon => $dich) {
        if (in_array($slug, $dich, true)) {
            $rel[] = $nguon;
        }
    }

    $out = [];
    foreach (array_unique($rel) as $s) {
        if ($s === $slug) { continue; }
        $t = get_term_by('slug', $s, 'category');
        if ($t && !is_wp_error($t)) { $out[$t->term_id] = $t; }
    }
    return array_values($out);
}

/**
 * Lấy cây menu location pf-primary (WP menu do admin tạo, sửa được ở Appearance > Menus).
 * Trả ['groups'=>[{title,url,children:[{title,url}]}], 'right'=>[...]] hoặc [] nếu chưa gán menu
 * (để header fallback về pf_nav_groups config).
 */
function pf_get_primary_menu(): array
{
    $loc = get_nav_menu_locations();
    if (empty($loc['pf-primary'])) {
        return [];
    }
    $items = wp_get_nav_menu_items((int) $loc['pf-primary']);
    if (empty($items)) {
        return [];
    }
    $byParent = [];
    foreach ($items as $it) {
        $byParent[(int) $it->menu_item_parent][] = $it;
    }
    $groups = [];
    $right  = [];
    foreach (($byParent[0] ?? []) as $it) {
        $node = ['title' => $it->title, 'url' => $it->url, 'children' => []];
        foreach (($byParent[(int) $it->ID] ?? []) as $c) {
            $node['children'][] = ['title' => $c->title, 'url' => $c->url];
        }

        /*
         * Mục cấp 1 KHÔNG có con trong menu thì tự lấy con từ danh mục tương ứng.
         *
         * Vì sao: dựng đủ cây vào menu là 17 nhóm cha cộng 216 mục con, tức 233 mục. Màn
         * Appearance > Menus với 233 mục thì nặng và rối, mà tệ hơn là nó biến menu thành ẢNH CHỤP
         * TĨNH: đổi danh mục xong phải nhớ sửa tay menu, quên là link chết.
         *
         * Cách này giữ được cả hai: người vận hành chỉ quản 19 mục cấp 1 (đổi tên, sắp xếp, ẩn bớt,
         * thêm link ngoài), còn mega-menu vẫn tự bám theo danh mục thật.
         *
         * Ai muốn tự quản cấp 2 thì cứ thêm mục con vào menu, nhánh này lập tức nhường chỗ.
         */
        if (!$node['children']) {
            $slug = '';
            if ((string) $it->object === 'category' && (int) $it->object_id > 0) {
                $term = get_term((int) $it->object_id, 'category');
                if ($term && !is_wp_error($term)) {
                    $slug = (string) $term->slug;
                }
            }
            if ($slug === '') {
                // Mục link tự nhập: suy slug từ đoạn cuối đường dẫn.
                $path = trim((string) wp_parse_url($it->url, PHP_URL_PATH), '/');
                if ($path !== '' && strpos($path, '/') === false) {
                    $slug = $path;
                }
            }
            if ($slug !== '' && function_exists('pf_group_children')) {
                foreach (pf_group_children($slug) as $c) {
                    $node['children'][] = ['title' => $c['label'] ?? ($c['title'] ?? ''), 'url' => $c['url'] ?? ''];
                }
            }
        }
        if (in_array('pf-nav-right', (array) $it->classes, true)) {
            $right[] = $node;
        } else {
            $groups[] = $node;
        }
    }
    return ['groups' => $groups, 'right' => $right];
}

/** URL 1 nhóm cha: term link nếu tồn tại, else /{slug}/ (forward-compatible Phase 3). */
function pf_group_url(string $slug): string
{
    $term = get_term_by('slug', $slug, 'category');
    if ($term && !is_wp_error($term)) {
        $link = get_term_link($term);
        if (!is_wp_error($link)) {
            return (string) $link;
        }
    }
    return home_url('/' . $slug . '/');
}

/**
 * Lấy menu footer theo CỘT từ vị trí `pf-footer`.
 *
 * Mỗi mục CẤP 1 của menu là một CỘT (nhãn thành tiêu đề cột), các mục con là link trong cột đó.
 * Ánh xạ thẳng vào bố cục 3 cột sẵn có mà chỉ tốn một vị trí menu, thay vì bắt người vận hành
 * quản ba menu rời.
 *
 * Trả [] khi chưa gán menu, để footer giữ nguyên bản dựng cứng (cùng cách header đang làm), nhờ
 * vậy site mới cài chưa có menu vẫn hiện đủ footer.
 *
 * @return array<int, array{title:string, url:string, children:array<int, array{title:string,url:string}>}>
 */
function pf_get_footer_menu(): array
{
    $loc = get_nav_menu_locations();
    if (empty($loc['pf-footer'])) {
        return [];
    }
    $items = wp_get_nav_menu_items((int) $loc['pf-footer']);
    if (empty($items)) {
        return [];
    }
    $byParent = [];
    foreach ($items as $it) {
        $byParent[(int) $it->menu_item_parent][] = $it;
    }
    $cols = [];
    foreach (($byParent[0] ?? []) as $it) {
        $col = ['title' => $it->title, 'url' => $it->url, 'children' => []];
        foreach (($byParent[(int) $it->ID] ?? []) as $c) {
            $col['children'][] = ['title' => $c->title, 'url' => $c->url];
        }
        $cols[] = $col;
    }

    return $cols;
}

/** Submenu links (child term) của 1 nhóm; [] nếu taxonomy chưa có (Phase 3). */
function pf_group_children(string $slug): array
{
    $term = get_term_by('slug', $slug, 'category');
    if (!$term || is_wp_error($term)) {
        return [];
    }
    $kids = get_terms([
        'taxonomy'   => 'category',
        'parent'     => $term->term_id,
        'hide_empty' => false,
        'number'     => 60,
        'orderby'    => 'name',
    ]);
    if (is_wp_error($kids) || empty($kids)) {
        return [];
    }
    $out = [];
    foreach ($kids as $k) {
        $out[] = ['label' => $k->name, 'url' => get_term_link($k)];
    }
    return $out;
}

/* ============================================================
 * Date placeholders (%year% %month_text% %month% %day%) trong the_content -> evergreen.
 * Safety net (Pro cũng resolve; strtr idempotent). Port từ retailcoupons-child.
 * ============================================================ */
// Priority 99: chạy SAU do_blocks + RankMath FAQ block render, để bắt cả %year% trong FAQ answer.
add_filter('the_content', static function ($content) {
    if (strpos((string) $content, '%') === false) {
        return $content;
    }
    return strtr((string) $content, [
        '%year%'       => wp_date('Y'),
        '%month_text%' => wp_date('F'),
        '%month%'      => wp_date('n'),
        '%day%'        => wp_date('j'),
    ]);
}, 99);

/* ============================================================
 * Media: GIỮ NGUYÊN ảnh gốc, KHÔNG tạo thumbnail/resize + KHÔNG chia thư mục năm/tháng.
 * (port từ retailcoupons-child + yêu cầu user). Ảnh để full-size; template dùng size
 * name sẽ tự fallback về full.
 * ============================================================ */
add_filter('intermediate_image_sizes_advanced', '__return_empty_array'); // không sinh bản resize
add_filter('big_image_size_threshold', '__return_false');                // không sinh bản -scaled
add_filter('option_uploads_use_yearmonth_folders', '__return_zero');     // upload phẳng, không /YYYY/MM/

/* ============================================================
 * Custom avatar: user meta 'custom_avatar' (URL đã upload/sideload) thắng Gravatar.
 * Dùng pre_get_avatar_data để ăn cả get_avatar() lẫn get_avatar_url() (template dùng).
 * Port ý tưởng từ retailcoupons-child.
 * ============================================================ */
add_filter('pre_get_avatar_data', static function (array $args, $id_or_email): array {
    $uid = 0;
    if (is_numeric($id_or_email)) {
        $uid = (int) $id_or_email;
    } elseif ($id_or_email instanceof WP_User) {
        $uid = (int) $id_or_email->ID;
    } elseif ($id_or_email instanceof WP_Post) {
        $uid = (int) $id_or_email->post_author;
    } elseif ($id_or_email instanceof WP_Comment) {
        $uid = (int) ($id_or_email->user_id ?? 0);
    }
    if ($uid) {
        $custom = get_user_meta($uid, 'custom_avatar', true);
        if (is_string($custom) && $custom !== '') {
            $args['url'] = $custom;
            $args['found_avatar'] = true;
        }
    }
    return $args;
}, 10, 2);

/** Breadcrumb ngắn gọn: bỏ "Home" (đầu) + bỏ trang hiện tại (cuối) trên single post
 *  (schema BreadcrumbList vẫn đủ, tránh lặp H1). Category/tag giữ crumb hiện tại. */
add_filter('rank_math/frontend/breadcrumb/items', static function ($crumbs) {
    if (!is_array($crumbs)) {
        return $crumbs;
    }
    if (count($crumbs) > 1) {
        array_shift($crumbs); // Home
    }
    if (is_singular('post') && count($crumbs) > 1) {
        array_pop($crumbs); // tiêu đề bài hiện tại
    }
    return $crumbs;
});

/** Author của 1 leaf category (term meta pf_author_id), fallback null. */
function pf_category_author(int $termId): ?int
{
    $uid = (int) get_term_meta($termId, 'pf_author_id', true);
    return $uid > 0 ? $uid : null;
}

/** Render 1 article card (.acard) từ post. Dùng chung front-page + mọi archive. */
function pf_acard(WP_Post $p, bool $showByline = false): void
{
    $cats = get_the_category($p->ID);
    $cat  = $cats ? $cats[0]->name : '';
    $img  = get_the_post_thumbnail_url($p->ID, 'acms-card') ?: '';
    ?>
    <article class="acard">
      <a href="<?php echo esc_url(get_permalink($p)); ?>">
        <div class="imgwrap">
          <?php if ($img) : ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr(get_the_title($p)); ?>" loading="lazy"><?php endif; ?>
        </div>
      </a>
      <div class="meta">
        <span class="date"><?php echo esc_html(get_the_date('', $p)); ?></span>
      </div>
      <h3><a href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html(get_the_title($p)); ?></a></h3>
      <p><?php echo esc_html(wp_trim_words(get_the_excerpt($p), 20)); ?></p>
      <?php if ($showByline) { pf_byline($p); } ?>
    </article>
    <?php
}

/** Byline "by Author" (link author archive). Dùng cho lead + acard trên home. */
function pf_byline(WP_Post $p): void
{
    $aid = (int) $p->post_author;
    if (!$aid) { return; }
    ?>
    <span class="byline">by <a href="<?php echo esc_url(get_author_posts_url($aid)); ?>"><?php echo esc_html(get_the_author_meta('display_name', $aid)); ?></a></span>
    <?php
}

/** Vài tác giả tiêu biểu cho "Who is LAT Review?" team grid: nhiều bài nhất, kèm avatar + chuyên môn (nhóm cha hay viết). */
function pf_home_authors(int $limit = 12): array
{
    // 1 danh mục con flagship / nhóm cha nổi bật -> author phụ trách (mapping lat_review_author).
    // 12 danh mục con flagship, mỗi cái thuộc 1 nhóm cha khác nhau, để lưới team hiện đủ mảng chuyên môn.
    $featured = [
        'air-fryers', 'refrigerators', 'vacuum-cleaners', 'mattresses',
        'lawn-mowers', 'cordless-drills', 'wireless-earbuds', 'laptops',
        'skincare-moisturizers', 'running-shoes', 'tires', 'dog-food',
    ];
    $out = [];
    foreach ($featured as $slug) {
        if (count($out) >= $limit) { break; }
        $t = get_term_by('slug', $slug, 'category');
        if (!$t) { continue; }
        $uid = (int) get_term_meta($t->term_id, 'lat_review_author', true);
        if (!$uid || !get_user_by('id', $uid)) { continue; }
        $out[] = ['id' => $uid, 'name' => get_the_author_meta('display_name', $uid), 'spec' => html_entity_decode($t->name)];
    }
    return $out;
}

/** Shortcode [lat_review_team] -> lưới author (dùng ở trang About). team-light = scope màu nền sáng. */
function pf_team_shortcode($atts): string
{
    $a = shortcode_atts(['limit' => 12], $atts);
    $team = pf_home_authors((int) $a['limit']);
    if (!$team) { return ''; }
    $h = '<div class="team team-light">';
    foreach ($team as $m) {
        $url = get_author_posts_url((int) $m['id']);
        $h .= '<div class="m"><a href="' . esc_url($url) . '"><img src="' . esc_url(get_avatar_url((int) $m['id'], ['size' => 156])) . '" alt="' . esc_attr($m['name']) . '" loading="lazy"></a>'
            . '<b><a href="' . esc_url($url) . '">' . esc_html($m['name']) . '</a></b>'
            . '<span>' . esc_html($m['spec']) . '</span></div>';
    }
    return $h . '</div>';
}
add_shortcode('lat_review_team', 'pf_team_shortcode');

/** Grid card + nút Load more (infinite). $q = WP_Query. Dùng cho leaf/tag/archive/author/search. */
function pf_render_grid(WP_Query $q, array $ctx = []): void
{
    if (!$q->have_posts()) {
        echo '<p class="date">No articles yet.</p>';
        return;
    }
    echo '<div class="card-grid" data-pf-grid="1">';
    while ($q->have_posts()) { $q->the_post(); pf_acard(get_post()); }
    wp_reset_postdata();
    echo '</div>';
    if ((int) $q->max_num_pages > 1) {
        $attrs = 'data-pf-loadmore="1" data-max="' . (int) $q->max_num_pages . '" data-paged="1"';
        foreach ($ctx as $k => $v) { $attrs .= ' data-' . esc_attr($k) . '="' . esc_attr((string) $v) . '"'; }
        echo '<div class="loadmore" ' . $attrs . '><button type="button" class="btn-loadmore">Load more</button><p class="loadmore-note"></p></div>';
    }
}

/** REST: trả HTML card trang kế (load-more). GET /wp-json/pf/v1/posts?type=&id=&paged= */
add_action('rest_api_init', static function (): void {
    register_rest_route('pf/v1', '/posts', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => static function (WP_REST_Request $req) {
            $type  = sanitize_key((string) $req->get_param('type'));
            $id    = (int) $req->get_param('id');
            $paged = max(2, (int) $req->get_param('paged'));
            $args  = ['post_status' => 'publish', 'posts_per_page' => 12, 'paged' => $paged];
            if ($type === 'category') { $args['cat'] = $id; }
            elseif ($type === 'tag') { $args['tag_id'] = $id; }
            elseif ($type === 'author') { $args['author'] = $id; }
            elseif ($type === 'search') { $args['s'] = (string) $req->get_param('s'); }
            $q = new WP_Query($args);
            ob_start();
            while ($q->have_posts()) { $q->the_post(); pf_acard(get_post()); }
            wp_reset_postdata();
            return ['html' => ob_get_clean(), 'max' => (int) $q->max_num_pages];
        },
    ]);
});

/* ============================================================
 * SINGLE POST: render dùng chung (single.php + REST up-next) + infinite next-post
 * ============================================================ */

/** Gắn id vào H2/H3 trong content + trích TOC (chỉ H2). Trả [html, toc]. */
function pf_content_with_toc(string $html): array
{
    $toc = [];
    $used = [];
    $html = preg_replace_callback(
        '/<(h2|h3)\b([^>]*)>(.*?)<\/\1>/is',
        function (array $m) use (&$toc, &$used): string {
            $tag = strtolower($m[1]);
            $attrs = $m[2];
            $text = trim(wp_strip_all_tags($m[3]));
            if (preg_match('/\bid="([^"]+)"/i', $attrs, $idm)) {
                $id = $idm[1];
            } else {
                $base = sanitize_title($text) ?: 'section';
                $id = $base; $i = 2;
                while (isset($used[$id])) { $id = $base . '-' . $i++; }
                $attrs .= ' id="' . esc_attr($id) . '"';
            }
            $used[$id] = true;
            if ($tag === 'h2' && $text !== '') { $toc[] = ['id' => $id, 'text' => $text]; }
            return '<' . $tag . $attrs . '>' . $m[3] . '</' . $tag . '>';
        },
        $html
    );
    return [$html, $toc];
}

/**
 * Render MỘT dạng breadcrumb duy nhất cho toàn site. Mọi template phải đi qua hàm này
 * để trail luôn cùng markup, cùng thứ tự, cùng dấu phân tách.
 * $items: mảng ['label' => string, 'url' => string|null]. url rỗng = mắt xích hiện tại (không link).
 * Home luôn được chèn sẵn ở đầu, các template không tự thêm.
 *
 * Build THỦ CÔNG (không dùng rank_math_the_breadcrumbs): RankMath phụ thuộc query context
 * nên sai trong REST up-next, và hàm đó im lặng không in gì khi setting breadcrumbs = off
 * (đúng lỗi đã làm 109 trang danh mục mất breadcrumb). Schema BreadcrumbList ở <head>
 * vẫn do RankMath lo, không ảnh hưởng.
 */
function pf_breadcrumb_nav(array $items): string
{
    array_unshift($items, ['label' => 'Home', 'url' => home_url('/')]);
    $parts = [];
    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') { continue; }
        $url = (string) ($item['url'] ?? '');
        $parts[] = $url !== ''
            ? '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>'
            : '<span>' . esc_html($label) . '</span>';
    }
    if (count($parts) < 2) { return ''; }
    return '<nav class="breadcrumb" aria-label="breadcrumbs">'
        . implode(' <span>&rsaquo;</span> ', $parts)
        . '</nav>';
}

/**
 * Mắt xích breadcrumb của 1 term: các danh mục cha theo thứ tự gốc → lá.
 * Mắt xích cuối (chính term đang xem) không link.
 */
function pf_breadcrumb_term_items($term): array
{
    if (!$term instanceof WP_Term) { return []; }
    $chain = array_reverse(get_ancestors($term->term_id, $term->taxonomy));
    $chain[] = (int) $term->term_id;

    $items = [];
    $last  = end($chain);
    foreach ($chain as $tid) {
        $t = get_term((int) $tid, $term->taxonomy);
        if (!$t || is_wp_error($t)) { continue; }
        $url = ((int) $tid === (int) $last) ? '' : get_term_link($t);
        $items[] = ['label' => $t->name, 'url' => is_wp_error($url) ? '' : $url];
    }
    return $items;
}

/**
 * Breadcrumb hiển thị cho 1 bài: Home › trail category ancestors (parent › leaf), bỏ post title.
 * Danh mục lá VẪN link được (khác archive, vì đây không phải trang của chính nó).
 */
function pf_breadcrumb_html(int $pid): string
{
    $cats = get_the_category($pid);
    if (!$cats) { return ''; }
    $primaryId = (int) get_post_meta($pid, 'rank_math_primary_category', true);
    $primary = null;
    foreach ($cats as $c) { if ((int) $c->term_id === $primaryId) { $primary = $c; break; } }
    if (!$primary) { $primary = $cats[0]; }

    $chain = array_reverse(get_ancestors($primary->term_id, 'category'));
    $chain[] = (int) $primary->term_id;
    $items = [];
    foreach ($chain as $tid) {
        $t = get_term((int) $tid, 'category');
        if (!$t || is_wp_error($t)) { continue; }
        $link = get_term_link($t);
        if (is_wp_error($link)) { continue; }
        $items[] = ['label' => $t->name, 'url' => $link];
    }
    if (!$items) { return ''; }
    return pf_breadcrumb_nav($items);
}

/** Author box (1 tác giả) từ ID user. */
function pf_author_box(int $uid): void
{
    $name = get_the_author_meta('display_name', $uid);
    $bio  = get_the_author_meta('description', $uid);
    $av   = get_avatar_url($uid, ['size' => 144]);
    $url  = get_author_posts_url($uid);
    ?>
    <div class="author-box">
      <img src="<?php echo esc_url($av); ?>" alt="<?php echo esc_attr($name); ?>">
      <div>
        <b><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($name); ?></a></b>
        <p><?php echo esc_html($bio ?: ($name . ' writes reviews and buying guides for LAT Review.')); ?></p>
      </div>
    </div>
    <?php
}

/**
 * Render FULL 1 bài (breadcrumb + hero + section post-layout) -> HTML string.
 * Dùng cho single.php (bài đầu) VÀ REST up-next (bài kế). Set global $post tạm rồi trả về.
 */
function pf_render_single_article($the_post): string
{
    global $post;
    $prev = $post;
    $post = get_post($the_post);
    if (!$post) { $post = $prev; return ''; }
    setup_postdata($post);

    $pid = (int) $post->ID;
    $uid = (int) $post->post_author;
    $dek = get_the_excerpt($post);
    $raw = apply_filters('the_content', get_the_content(null, false, $post));
    [$body, $toc] = pf_content_with_toc((string) $raw);

    ob_start();
    ?>
    <article class="pf-article" data-pf-article data-id="<?php echo $pid; ?>" data-url="<?php echo esc_url(get_permalink($pid)); ?>" data-title="<?php echo esc_attr(get_the_title($pid)); ?>">
      <div class="wrap breadcrumb-wrap">
        <?php echo pf_breadcrumb_html($pid); // phpcs:ignore WordPress.Security.EscapeOutput -- đã escape trong hàm ?>
      </div>

      <div class="wrap post-hero">
        <h1><?php echo esc_html(get_the_title($pid)); ?></h1>
        <?php if ($dek) : ?><p class="post-dek"><?php echo esc_html($dek); ?></p><?php endif; ?>
        <div class="post-meta">
          <span class="author">
            <span class="author-avs">
              <a href="<?php echo esc_url(get_author_posts_url($uid)); ?>"><img src="<?php echo esc_url(get_avatar_url($uid, ['size' => 64])); ?>" alt="<?php echo esc_attr(get_the_author_meta('display_name', $uid)); ?>"></a>
            </span>
            By <a href="<?php echo esc_url(get_author_posts_url($uid)); ?>"><?php echo esc_html(get_the_author_meta('display_name', $uid)); ?></a>
          </span>
          <span class="m-item">Updated <?php echo esc_html(get_the_modified_date('', $pid)); ?></span>
        </div>
        <?php
        // Ảnh nguồn "product_fallback" (1 sản phẩm trong roundup, KHÔNG phải lifestyle photo thật)
        // KHÔNG hiện to làm hero trên chính trang chi tiết (mờ khi phóng to + không công bằng đại
        // diện cho cả roundup nhiều sản phẩm, quyết định user 2026-09-13). Vẫn dùng bình thường cho
        // card/thumbnail ở nơi khác (home, related, category) vì những chỗ đó không đọc meta này.
        $pf_hero_id = get_post_thumbnail_id($pid);
        $pf_hero_ok = $pf_hero_id && get_post_meta($pf_hero_id, '_pf_hero_source', true) !== 'product_fallback';
        if ($pf_hero_ok) :
            // Credit đúng photographer đã lưu trên attachment (post_excerpt "Photo by ...").
            // Không có credit thì KHÔNG hiện gì. Trước đây fallback là "Photo: LAT Review",
            // tức nhận công ảnh của người khác, sai cả về minh bạch lẫn bản quyền.
            $pf_cap = trim((string) wp_get_attachment_caption(get_post_thumbnail_id($pid)));
        ?>
          <figure class="post-figure">
            <?php
            $pf_halt = trim((string) get_post_meta(get_post_thumbnail_id($pid), '_wp_attachment_image_alt', true)); // alt attachment (pipeline set)
            if ($pf_halt === '') { $pf_halt = (string) get_post_meta($pid, '_acms_card_title', true) ?: get_the_title($pid); } // fallback: KHÔNG bao giờ rỗng
            if (function_exists('acms_ct_replace_vars')) { $pf_halt = acms_ct_replace_vars($pf_halt); } // không để %year% trong alt
            echo get_the_post_thumbnail($pid, 'acms-hero', ['class' => 'post-feat', 'loading' => 'lazy', 'alt' => $pf_halt]);
            ?>
            <?php if ($pf_cap !== '') : ?><figcaption class="post-cap"><?php echo esc_html($pf_cap); ?></figcaption><?php endif; ?>
          </figure>
        <?php endif; ?>
      </div>

      <section class="blk">
        <div class="wrap">
          <div class="post-layout">
            <main class="post-main">
              <div class="page-body">
                <?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- the_content đã lọc ?>
                <h2 id="author-<?php echo $pid; ?>">About the author</h2>
                <?php pf_author_box($uid); ?>
              </div>
            </main>
            <aside class="post-aside">
              <?php if ($toc) : ?>
                <div class="rd-toc">
                  <h4>In this guide</h4>
                  <?php foreach ($toc as $t) : ?>
                    <a href="#<?php echo esc_attr($t['id']); ?>"><?php echo esc_html($t['text']); ?></a>
                  <?php endforeach; ?>
                  <a href="#author-<?php echo $pid; ?>">About the author</a>
                </div>
              <?php endif; ?>
              <?php
              $catId = (function () use ($pid) { $c = get_the_category($pid); return $c ? $c[0]->term_id : 0; })();
              // 5 bài MỚI NHẤT cùng danh mục (get_posts mặc định sắp theo post_date DESC).
              // Chốt 2026-09-16, trước đó là 3.
              $related = get_posts(['numberposts' => 5, 'post__not_in' => [$pid], 'category' => $catId]);
              if ($related) : ?>
                <div class="rd-related">
                  <h4>Related articles</h4>
                  <?php foreach ($related as $r) :
                      $rimg   = get_the_post_thumbnail_url($r->ID, 'acms-card-small') ?: '';
                      $rtitle = (string) get_post_meta($r->ID, '_acms_card_title', true);
                      if ($rtitle === '') { $rtitle = get_the_title($r); } // fallback post title
                      if (function_exists('acms_ct_replace_vars')) { $rtitle = acms_ct_replace_vars($rtitle); } // %year%/%product_count% -> giá trị thật ?>
                    <a class="rel-item" href="<?php echo esc_url(get_permalink($r)); ?>">
                      <?php if ($rimg) : ?><img src="<?php echo esc_url($rimg); ?>" alt="<?php echo esc_attr($rtitle); ?>" loading="lazy"><?php endif; ?>
                      <span><?php echo esc_html($rtitle); ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </aside>
          </div>
        </div>
      </section>
    </article>
    <?php
    $html = ob_get_clean();
    $post = $prev;
    wp_reset_postdata();
    return (string) $html;
}

/** REST: bài KẾ cùng danh mục (up-next infinite). GET /wp-json/pf/v1/next-post?id=&shown= */
add_action('rest_api_init', static function (): void {
    register_rest_route('pf/v1', '/next-post', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => static function (WP_REST_Request $req) {
            $id    = (int) $req->get_param('id');
            $shown = array_values(array_filter(array_map('intval', explode(',', (string) $req->get_param('shown')))));
            if (!$shown) { $shown = [$id]; }
            $cat   = get_the_category($id);
            $catId = $cat ? $cat[0]->term_id : 0;
            $next  = get_posts([
                'numberposts'  => 1,
                'post_status'  => 'publish',
                'post__not_in' => $shown,
                'category'     => $catId,
                'orderby'      => 'date',
                'order'        => 'DESC',
            ]);
            if (!$next) { return ['done' => true]; }
            $np = $next[0];
            return [
                'done'  => false,
                'id'    => (int) $np->ID,
                'title' => get_the_title($np),
                'url'   => get_permalink($np),
                'html'  => pf_render_single_article($np),
            ];
        },
    ]);
});

/**
 * Logo wordmark: chữ "Best" trong khối đỏ bo lệch hai góc chéo nhau, cộng dải năm sao
 * điểm số ở dưới. Toàn bộ trình bày nằm ở .logo trong style.css của child theme.
 * Kiểu nhấp nháy của dải sao đổi bằng filter 'pf_logo_star_anim':
 * 'blink-wave' (mặc định, sóng chạy), 'blink-last', 'blink-breath', hoặc chuỗi rỗng để tắt.
 */
function pf_logo(?string $class = null): string
{
    $star = '<svg viewBox="0 0 24 24" aria-hidden="true">'
        . '<path d="M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5-5.8-3-5.8 3 1.1-6.5L2.6 9.4l6.5-.9z"/>'
        . '</svg>';

    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $kind    = ($i === 5) ? 'lse' : 'lsf';   // sao thứ năm để trống có chủ ý
        $stars  .= '<span class="' . $kind . ' d' . $i . '">' . $star . '</span>';
    }

    $anim  = (string) apply_filters('pf_logo_star_anim', 'blink-wave');
    $score = '<span class="logo-score' . ($anim ? ' ' . $anim : '') . '" aria-hidden="true">' . $stars . '</span>';

    $cls = 'logo' . ($class ? ' ' . $class : '');

    return '<a href="' . esc_url(home_url('/')) . '" class="' . esc_attr($cls) . '" aria-label="LAT Review">'
        . '<span class="logo-row"><span class="logo-bx">LAT</span>Review</span>'
        . $score
        . '</a>';
}

/**
 * Favicon SVG cùng ngôn ngữ hình với logo: khối đỏ bo lệch, chữ B trắng.
 * Chỉ thêm khi WordPress chưa có Site Icon, để cấu hình trong admin luôn thắng.
 */
add_action('wp_head', function (): void {
    if (has_site_icon()) {
        return;
    }
    $svg = get_stylesheet_directory() . '/assets/img/favicon.svg';
    if (!file_exists($svg)) {
        return;
    }
    printf(
        '<link rel="icon" type="image/svg+xml" href="%s">' . "\n",
        esc_url(get_stylesheet_directory_uri() . '/assets/img/favicon.svg?ver=' . filemtime($svg))
    );
}, 5);

/**
 * Tiêu đề H1 của trang /coupons/, có thêm số trang khi đang xem trang 2 trở đi.
 *
 * Ghi "(Page 2 of 10)" trong ngoặc đơn chứ không dùng dấu gạch làm dấu phân tách (rule B2
 * trong CLAUDE.md). Tổng số trang hỏi thẳng plugin để không nhân bản logic đếm/lọc ở 2 nơi,
 * và có class_exists bọc ngoài để trang vẫn chạy nếu plugin bị tắt.
 */
function pf_coupons_heading(): string
{
    $title = get_the_title();
    $cls   = '\AffiliateCMS\Pro\Frontend\Shortcodes';

    if (!class_exists($cls) || !method_exists($cls, 'couponsCurrentPage')) {
        return $title;
    }

    $paged = $cls::couponsCurrentPage();
    if ($paged < 2) {
        return $title;
    }

    $total = $cls::couponsTotalPages();

    return $title . sprintf(' (Page %d of %d)', min($paged, $total), $total);
}

/**
 * Trang tĩnh có phân trang riêng (hiện là /coupons/): sửa canonical + title cho trang 2 trở đi.
 *
 * RankMath coi mọi URL của một Page là cùng một trang nên trả canonical trỏ hết về /coupons/,
 * tức tự khai báo /coupons/page/2/ ... /page/10/ là bản trùng của trang 1 và mất luôn cơ hội
 * index. Trang tham khảo (couponfollow.com/featured) tự canonical từng trang, chỉ /featured/1
 * mới gộp về /featured, và đây làm y vậy: trang 1 giữ URL gốc, trang N tự trỏ về chính nó.
 */
function pf_is_coupons_feed_page(): bool
{
    if (!is_page() || !class_exists('\AffiliateCMS\Pro\Frontend\Shortcodes')) {
        return false;
    }

    $post = get_post();

    return $post && has_shortcode((string) $post->post_content, 'acms_coupons');
}

function pf_paged_shortcode_page(): int
{
    if (!pf_is_coupons_feed_page()) {
        return 0;
    }

    $paged = \AffiliateCMS\Pro\Frontend\Shortcodes::couponsCurrentPage();

    return $paged > 1 ? $paged : 0;
}

/**
 * Meta description cho trang feed coupon `/coupons/`.
 *
 * Trang này chỉ chứa mỗi shortcode nên RankMath không có chữ nào để rút ra, kết quả là trang ra
 * KHÔNG có meta description lẫn og:description. Sinh động từ CSDL, cùng lý do với trang store:
 * số ưu đãi và số store đổi mỗi ngày, viết tay rồi để đó là sai dần.
 *
 * Trang 2 trở đi ghi thêm số trang, để 10 trang không cùng đúng một câu mô tả.
 */
function pf_coupons_feed_description(): string
{
    $stats = \AffiliateCMS\Pro\Frontend\Shortcodes::couponsFeedStats();
    $month = date_i18n('F Y');

    if ($stats['total'] < 1) {
        return sprintf('Coupon codes and deals, updated for %s.', $month);
    }

    $desc = sprintf(
        /* translators: 1: số ưu đãi, 2: số store, 3: tháng năm */
        _n(
            'Browse %1$d coupon code and deal from %2$d stores, updated for %3$s.',
            'Browse %1$d coupon codes and deals from %2$d stores, updated for %3$s.',
            $stats['total'],
            'lat-review-child'
        ),
        $stats['total'],
        $stats['stores'],
        $month
    );

    $paged = pf_paged_shortcode_page();
    if ($paged > 1) {
        $total = \AffiliateCMS\Pro\Frontend\Shortcodes::couponsTotalPages();
        $desc .= ' ' . sprintf('Page %d of %d.', min($paged, $total), $total);
    }

    return $desc;
}

/**
 * Gắn câu mô tả đó vào cả thẻ meta lẫn OpenGraph.
 *
 * Phải khai riêng 3 chỗ: OpenGraph của RankMath có đường lấy dữ liệu riêng, không tự ăn theo
 * filter `rank_math/frontend/description`.
 */
foreach ([
    'rank_math/frontend/description',
    'rank_math/opengraph/facebook/og_description',
    'rank_math/opengraph/twitter/twitter_description',
] as $pf_desc_filter) {
    add_filter($pf_desc_filter, static function ($desc) {
        return pf_is_coupons_feed_page() ? pf_coupons_feed_description() : $desc;
    }, 20);
}
unset($pf_desc_filter);

add_filter('rank_math/frontend/canonical', static function ($canonical) {
    $paged = pf_paged_shortcode_page();
    if ($paged < 1) {
        return $canonical;
    }

    // Số trang vượt tổng số trang thật (vd /coupons/page/99/) thì shortcode kẹp về trang cuối
    // và in đúng nội dung trang cuối, nên canonical cũng phải trỏ về trang cuối, đừng tự khai
    // báo một URL rỗng nghĩa là bản gốc.
    $total = \AffiliateCMS\Pro\Frontend\Shortcodes::couponsTotalPages();
    $paged = min($paged, $total);

    return $paged > 1 ? get_pagenum_link($paged) : get_permalink();
});

add_filter('rank_math/frontend/title', static function ($title) {
    $paged = pf_paged_shortcode_page();
    if ($paged < 1) {
        return $title;
    }

    $total  = \AffiliateCMS\Pro\Frontend\Shortcodes::couponsTotalPages();
    // Ngoặc đơn chứ không phải dấu gạch, rule B2.
    $suffix = sprintf(' (Page %d of %d)', min($paged, $total), $total);

    // Chèn ngay sau tên trang chứ không nối vào đuôi chuỗi: RankMath đã ghép sẵn tên site vào
    // title, nối đuôi sẽ ra "Coupons | LAT Review (Page 4 of 10)". Bám theo tên trang thay vì
    // đoán dấu phân tách nên đổi separator trong RankMath cũng không vỡ.
    $pageTitle = get_the_title();
    $at        = $pageTitle !== '' ? mb_strpos($title, $pageTitle) : false;

    if ($at === false) {
        return $title . $suffix;
    }

    return mb_substr($title, 0, $at + mb_strlen($pageTitle)) . $suffix . mb_substr($title, $at + mb_strlen($pageTitle));
});

/**
 * ===== Trang store (coupon theo brand) =========================================
 *
 * Trang store là BÀI THẬT (post type `post`, quyết định đã chốt), nhận diện bằng post meta
 * `_acms_brand_id` trỏ về `wp_acms_coupon_brands.id`.
 */

/** Bài này có phải trang store không. */
function pf_is_store_post(?int $pid = null): bool
{
    $pid = $pid ?: (int) get_the_ID();

    return $pid > 0 && (int) get_post_meta($pid, '_acms_brand_id', true) > 0;
}

/**
 * Đổi template sang `single-store.php`.
 *
 * Dùng `template_include` chứ không đặt tên file theo quy ước của WP, vì WP chỉ chọn template
 * theo post type/slug/id, không chọn theo post meta. Nếu để `single.php` render thì bài store sẽ
 * chạy nguyên pipeline roundup (up-next infinite, khối ASIN, LAT Score), sai hoàn toàn.
 */
add_filter('template_include', static function ($template) {
    if (!is_singular('post') || !pf_is_store_post()) {
        return $template;
    }
    $store = get_stylesheet_directory() . '/single-store.php';

    return file_exists($store) ? $store : $template;
});

/**
 * Bài store CÓ nằm chung lưới với bài roundup trong trang danh mục (chốt 2026-09-15).
 *
 * Bản đầu có một `pre_get_posts` giấu bài store khỏi danh mục/tác giả/tìm kiếm/feed, để trang
 * danh mục thuần bài review. User chọn ngược lại: cho trộn thẳng vào lưới. Hệ quả đã biết và
 * chấp nhận: lưới sắp theo ngày nên bài store mới tạo sẽ chiếm ô đầu tiên của trang danh mục cha
 * cho tới khi có bài review mới hơn. Muốn tách ra lại thì thêm `pre_get_posts` loại theo meta
 * `_acms_brand_id` (compare NOT EXISTS), không chỗ nào khác phụ thuộc.
 */

/**
 * Nạp CSS/JS của plugin trên trang store.
 *
 * Trang store không chứa shortcode nào của plugin (template gọi thẳng các hàm render), nên
 * `enqueueFrontendStyles()` với phép quét `has_shortcode()` của plugin không bắt được, và card
 * coupon sẽ ra trơ không style. Phải enqueue ở `wp_enqueue_scripts`, KHÔNG gọi trong template:
 * lúc template chạy thì `wp_head()` đã in xong, enqueue muộn sẽ rơi xuống footer hoặc mất hẳn.
 */
add_action('wp_enqueue_scripts', static function (): void {
    if (!is_singular('post') || !pf_is_store_post((int) get_queried_object_id())) {
        return;
    }
    if (class_exists('\AffiliateCMS\Pro\Core\Assets')) {
        \AffiliateCMS\Pro\Core\Assets::forceEnqueueFrontendAssets();
    }
});

/**
 * Nhóm cha (danh mục cấp 1) của một term bất kỳ, dùng khi gán danh mục cho bài store.
 * Term đã là cấp 1 thì trả về chính nó.
 */
function pf_top_level_category(int $termId): int
{
    $ancestors = get_ancestors($termId, 'category');

    return $ancestors ? (int) end($ancestors) : $termId;
}

/**
 * Icon coupon (SVG inline) cho mục "Coupons" trên thanh điều hướng.
 *
 * Vẽ inline chứ không dùng font icon: nav xuất hiện trên MỌI trang, còn bộ Bootstrap Icons chỉ
 * được nạp ở những trang có plugin chạy, nên dùng nó là gặp cảnh icon lúc có lúc không. SVG tô
 * theo `currentColor` để đổi màu theo trạng thái của nút mà không cần thêm rule màu.
 *
 * Hình: tem giảm giá có khuyết tròn hai bên (dáng vé) và dấu phần trăm ở giữa.
 */
function pf_coupon_icon(): string
{
    return '<svg class="nav-coupons-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . '<path d="M3 8.5V6.8A1.8 1.8 0 0 1 4.8 5h14.4A1.8 1.8 0 0 1 21 6.8v1.7a2.6 2.6 0 0 0 0 7v1.7a1.8 1.8 0 0 1-1.8 1.8H4.8A1.8 1.8 0 0 1 3 17.2v-1.7a2.6 2.6 0 0 0 0-7Z"/>'
        . '<path d="m14.5 9.5-5 5"/>'
        . '<circle cx="9.9" cy="9.9" r="1"/>'
        . '<circle cx="14.1" cy="14.1" r="1"/>'
        . '</svg>';
}

/**
 * Mục điều hướng này có phải "Coupons" không (dựa vào URL, không dựa vào chữ hiển thị).
 *
 * Bám URL để khi nào user tự dựng menu trong Appearance > Menus thì mục Coupons vẫn có icon và
 * vẫn nổi bật, không phụ thuộc việc đặt tên mục là gì.
 */
function pf_is_coupons_nav_item(string $url): bool
{
    return (bool) preg_match('#/coupons/?$#', (string) wp_parse_url($url, PHP_URL_PATH));
}

/**
 * Dòng mô tả của bài store trong lưới danh mục / tìm kiếm.
 *
 * Bài store để `post_content` rỗng cho tới khi có khâu biên tập, nên `get_the_excerpt()` trả về
 * chuỗi rỗng và card trong lưới bị khuyết mất dòng mô tả trong khi mọi card khác đều có. Dùng lại
 * đúng câu meta description đã sinh động từ CSDL, không viết thêm một câu thứ hai để rồi lệch nhau.
 *
 * Lưu ý: KHÔNG gọi được pf_store_seo() ở đây, hàm đó chỉ chạy trên chính trang store đang xem,
 * còn chỗ này là vòng lặp nhiều bài trong trang danh mục.
 */
add_filter('get_the_excerpt', static function ($excerpt, $post = null) {
    if (trim((string) $excerpt) !== '' || !$post instanceof WP_Post) {
        return $excerpt;
    }
    if (!pf_is_store_post((int) $post->ID) || !class_exists('\AffiliateCMS\Pro\Frontend\StorePage')) {
        return $excerpt;
    }

    $brand = \AffiliateCMS\Pro\Frontend\StorePage::brandForPost((int) $post->ID);
    if (!$brand) {
        return $excerpt;
    }

    $stats = \AffiliateCMS\Pro\Frontend\StorePage::stats((int) $brand->id);

    return \AffiliateCMS\Pro\Frontend\StorePage::seoDescription($brand, $stats);
}, 10, 2);

/**
 * Title + meta description của trang store, sinh ĐỘNG lúc render.
 *
 * Vì sao không lưu cứng vào bài: cả hai đều mang số coupon, mức giảm cao nhất và tháng hiện tại.
 * Viết một lần rồi để đó thì tháng sau sai tháng, tuần sau sai số. Cùng nguyên tắc với các khối
 * số liệu trên trang (đọc thẳng từ CSDL) và với phân trang `/coupons/page/N/`.
 *
 * Bài store đang để `post_content` rỗng cho tới khi có khâu biên tập, nên nếu không có mấy filter
 * này thì RankMath không có gì để lấy: trang ra KHÔNG có meta description lẫn og:description.
 */
function pf_store_seo(): ?array
{
    if (!is_singular('post') || !pf_is_store_post((int) get_queried_object_id())) {
        return null;
    }
    if (!class_exists('\AffiliateCMS\Pro\Frontend\StorePage')) {
        return null;
    }

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $brand = \AffiliateCMS\Pro\Frontend\StorePage::brandForPost((int) get_queried_object_id());
    if (!$brand) {
        return null;
    }

    $stats = \AffiliateCMS\Pro\Frontend\StorePage::stats((int) $brand->id);
    $cache = [
        'title' => \AffiliateCMS\Pro\Frontend\StorePage::seoTitle($brand, $stats),
        'desc'  => \AffiliateCMS\Pro\Frontend\StorePage::seoDescription($brand, $stats),
    ];

    return $cache;
}

add_filter('rank_math/frontend/title', static function ($title) {
    $seo = pf_store_seo();

    return $seo ? $seo['title'] : $title;
}, 20);

add_filter('rank_math/frontend/description', static function ($desc) {
    $seo = pf_store_seo();

    return $seo ? $seo['desc'] : $desc;
}, 20);

// OpenGraph có đường lấy dữ liệu riêng của RankMath, không tự ăn theo 2 filter trên, nên phải
// khai cả 3 chỗ; thiếu thì link chia sẻ ra tiêu đề mặc định của WP.
add_filter('rank_math/opengraph/facebook/og_description', static function ($desc) {
    $seo = pf_store_seo();

    return $seo ? $seo['desc'] : $desc;
}, 20);

add_filter('rank_math/opengraph/twitter/twitter_description', static function ($desc) {
    $seo = pf_store_seo();

    return $seo ? $seo['desc'] : $desc;
}, 20);

add_filter('rank_math/opengraph/facebook/og_title', static function ($t) {
    $seo = pf_store_seo();

    return $seo ? $seo['title'] : $t;
}, 20);

add_filter('rank_math/opengraph/twitter/twitter_title', static function ($t) {
    $seo = pf_store_seo();

    return $seo ? $seo['title'] : $t;
}, 20);
