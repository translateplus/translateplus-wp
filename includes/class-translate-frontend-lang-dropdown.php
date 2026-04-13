<?php
/**
 * Premium dropdown language switcher (HTML + enqueued CSS/JS).
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders accessible dropdown; uses get_frontend_switcher_items() for links.
 */
final class TranslatePlus_Frontend_Lang_Dropdown {

    /**
     * Custom menu item URL that triggers the front-end language switcher (fragment id).
     */
    public const MENU_LANG_SWITCHER_FRAGMENT = 'tp-lang-switcher';

    /**
     * Stored menu URL for the language switcher (custom link target).
     */
    public const MENU_LANG_SWITCHER_URL = '#tp-lang-switcher';

    /**
     * Language code → flag emoji (approximate locale → region).
     *
     * @var array<string, string>
     */
    private static $flag_by_code = array(
        'af' => '🇿🇦',
        'sq' => '🇦🇱',
        'am' => '🇪🇹',
        'ar' => '🇸🇦',
        'hy' => '🇦🇲',
        'az' => '🇦🇿',
        'eu' => '🇪🇸',
        'be' => '🇧🇾',
        'bn' => '🇧🇩',
        'bs' => '🇧🇦',
        'bg' => '🇧🇬',
        'ca' => '🇪🇸',
        'ceb' => '🇵🇭',
        'zh-CN' => '🇨🇳',
        'zh-TW' => '🇹🇼',
        'co' => '🇫🇷',
        'hr' => '🇭🇷',
        'cs' => '🇨🇿',
        'da' => '🇩🇰',
        'nl' => '🇳🇱',
        'en' => '🇬🇧',
        'eo' => '🌐',
        'et' => '🇪🇪',
        'fi' => '🇫🇮',
        'fr' => '🇫🇷',
        'fy' => '🇳🇱',
        'gl' => '🇪🇸',
        'ka' => '🇬🇪',
        'de' => '🇩🇪',
        'el' => '🇬🇷',
        'gu' => '🇮🇳',
        'ht' => '🇭🇹',
        'ha' => '🇳🇬',
        'haw' => '🇺🇸',
        'iw' => '🇮🇱',
        'hi' => '🇮🇳',
        'hmn' => '🌐',
        'hu' => '🇭🇺',
        'is' => '🇮🇸',
        'ig' => '🇳🇬',
        'id' => '🇮🇩',
        'ga' => '🇮🇪',
        'it' => '🇮🇹',
        'ja' => '🇯🇵',
        'jv' => '🇮🇩',
        'kn' => '🇮🇳',
        'kk' => '🇰🇿',
        'km' => '🇰🇭',
        'rw' => '🇷🇼',
        'ko' => '🇰🇷',
        'ku' => '🇮🇶',
        'ckb' => '🇮🇶',
        'ky' => '🇰🇬',
        'lo' => '🇱🇦',
        'la' => '🇻🇦',
        'lv' => '🇱🇻',
        'lt' => '🇱🇹',
        'lb' => '🇱🇺',
        'mk' => '🇲🇰',
        'mg' => '🇲🇬',
        'ms' => '🇲🇾',
        'ml' => '🇮🇳',
        'mt' => '🇲🇹',
        'mi' => '🇳🇿',
        'mr' => '🇮🇳',
        'mn' => '🇲🇳',
        'my' => '🇲🇲',
        'ne' => '🇳🇵',
        'no' => '🇳🇴',
        'ny' => '🇲🇼',
        'or' => '🇮🇳',
        'ps' => '🇦🇫',
        'fa' => '🇮🇷',
        'pl' => '🇵🇱',
        'pt' => '🇵🇹',
        'pa' => '🇮🇳',
        'ro' => '🇷🇴',
        'ru' => '🇷🇺',
        'sm' => '🇼🇸',
        'gd' => '🌐',
        'sr' => '🇷🇸',
        'st' => '🇱🇸',
        'sn' => '🇿🇼',
        'sd' => '🇵🇰',
        'si' => '🇱🇰',
        'sk' => '🇸🇰',
        'sl' => '🇸🇮',
        'so' => '🇸🇴',
        'es' => '🇪🇸',
        'su' => '🇮🇩',
        'sw' => '🇹🇿',
        'sv' => '🇸🇪',
        'tl' => '🇵🇭',
        'tg' => '🇹🇯',
        'ta' => '🇮🇳',
        'tt' => '🇷🇺',
        'te' => '🇮🇳',
        'th' => '🇹🇭',
        'tr' => '🇹🇷',
        'tk' => '🇹🇲',
        'uk' => '🇺🇦',
        'ur' => '🇵🇰',
        'ug' => '🇨🇳',
        'uz' => '🇺🇿',
        'vi' => '🇻🇳',
        'cy' => '🌐',
        'xh' => '🇿🇦',
        'yi' => '🌐',
        'yo' => '🇳🇬',
        'zu' => '🇿🇦',
    );

    public static function init(): void {
        add_action('wp_enqueue_scripts', array(self::class, 'maybe_enqueue_for_singular'), 25);
        add_shortcode('tp_language_switcher', array(self::class, 'shortcode_tp_language_switcher'));
        add_shortcode('translateplus_lang_dropdown', array(self::class, 'shortcode_translateplus_lang_dropdown'));
        add_filter('nav_menu_item_title', array(self::class, 'filter_nav_menu_item_title_shortcode'), 1000, 4);
        add_filter('walker_nav_menu_start_el', array(self::class, 'filter_walker_nav_menu_start_el'), 10, 4);
        add_filter('wp_nav_menu_items', array(self::class, 'filter_wp_nav_menu_items_replace_switcher_anchor'), 25, 2);
        add_filter('nav_menu_css_class', array(self::class, 'filter_nav_menu_css_class'), 10, 4);
        add_filter('render_block', array(self::class, 'filter_render_block_shortcodes'), 20, 2);
    }

    /**
     * True when a menu item URL is the TranslatePlus language switcher placeholder.
     *
     * @param mixed $url Menu item URL (usually string).
     */
    public static function is_lang_switcher_menu_item_url($url): bool {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $url = trim($url);
        if ($url === self::MENU_LANG_SWITCHER_URL) {
            return true;
        }

        $fragment = wp_parse_url($url, PHP_URL_FRAGMENT);
        if (is_string($fragment) && $fragment === self::MENU_LANG_SWITCHER_FRAGMENT) {
            return true;
        }

        return false;
    }

    /**
     * Detect the language switcher menu item using {@see _menu_item_url} or the setup object (some themes omit -&gt;url).
     *
     * @param WP_Post|null $menu_item Nav menu item post.
     */
    public static function menu_item_is_language_switcher($menu_item): bool {
        if (! $menu_item instanceof WP_Post) {
            return false;
        }

        $url = '';
        if ((int) $menu_item->ID > 0) {
            $meta = get_post_meta($menu_item->ID, '_menu_item_url', true);
            if (is_string($meta) && $meta !== '') {
                $url = $meta;
            }
        }
        if ($url === '' && isset($menu_item->url)) {
            $url = (string) $menu_item->url;
        }

        return self::is_lang_switcher_menu_item_url($url);
    }

    /**
     * Themes that do not run {@see 'walker_nav_menu_start_el'} still output &lt;a href="#tp-lang-switcher"&gt;; replace that anchor with the dropdown.
     *
     * @param string $items HTML list items.
     * @param object $args  {@see wp_nav_menu()} arguments.
     */
    public static function filter_wp_nav_menu_items_replace_switcher_anchor(string $items, $args): string {
        if (is_admin()) {
            return $items;
        }
        if (stripos($items, 'tp-lang-switcher') === false) {
            return $items;
        }

        $dropdown = self::render(null);
        if ($dropdown === '') {
            return $items;
        }

        $wrapped = self::wrap_nav_menu_lang_switcher_inner($dropdown);

        $pattern = '/<a\b[^>]*\bhref=(["\'])(?:[^"\']*#tp-lang-switcher|#tp-lang-switcher)\1[^>]*>.*?<\/a>/is';

        return (string) preg_replace($pattern, $wrapped, $items);
    }

    /**
     * Replace the link markup for this item with the dropdown, wrapped for valid structure inside &lt;li&gt;.
     *
     * Core outputs: &lt;li&gt; … {@see Walker_Nav_Menu::start_el()} … &lt;/li&gt;. This filter only replaces the
     * inner segment (before + &lt;a&gt;…&lt;/a&gt; + after), so the dropdown ends up as direct flow content
     * under &lt;li&gt; inside {@see self::wrap_nav_menu_lang_switcher_inner()}.
     *
     * @param string   $item_output HTML (before, opening a, title, closing a, after).
     * @param WP_Post  $menu_item   Menu item.
     * @param int      $depth       Nesting depth.
     * @param stdClass $args        wp_nav_menu() args.
     */
    public static function filter_walker_nav_menu_start_el($item_output, $menu_item, $depth, $args): string {
        if (is_admin()) {
            return $item_output;
        }
        if (! $menu_item instanceof WP_Post) {
            return $item_output;
        }
        if (! self::menu_item_is_language_switcher($menu_item)) {
            return $item_output;
        }

        $dropdown = self::render(null);
        if ($dropdown === '') {
            return $item_output;
        }

        $inner  = self::wrap_nav_menu_lang_switcher_inner($dropdown);
        $before = is_object($args) && isset($args->before) ? (string) $args->before : '';
        $after  = is_object($args) && isset($args->after) ? (string) $args->after : '';

        return $before . $inner . $after;
    }

    /**
     * Single block wrapper inside &lt;li&gt; so themes expecting a container (not a bare widget root) get valid HTML.
     *
     * @param string $dropdown_html Output of {@see self::render()} (may be empty).
     */
    private static function wrap_nav_menu_lang_switcher_inner(string $dropdown_html): string {
        if ($dropdown_html === '') {
            return '<div class="translateplus-nav-menu-item translateplus-nav-menu-item--lang-switcher is-empty" aria-hidden="true"></div>';
        }

        return '<div class="translateplus-nav-menu-item translateplus-nav-menu-item--lang-switcher">'
            . $dropdown_html
            . '</div>';
    }

    /**
     * @param string[] $classes   Class names.
     * @param WP_Post  $menu_item Menu item.
     * @param stdClass $args      wp_nav_menu() args.
     * @param int      $depth     Depth.
     * @return string[]
     */
    public static function filter_nav_menu_css_class(array $classes, $menu_item, $args, $depth): array {
        if (! $menu_item instanceof WP_Post) {
            return $classes;
        }
        if (! self::menu_item_is_language_switcher($menu_item)) {
            return $classes;
        }

        $classes[] = 'menu-item-translateplus-lang-switcher';
        $classes[] = 'translateplus-nav-menu-li';

        return $classes;
    }

    /**
     * Enqueue assets when singular translatable post has 2+ language versions (for early paint).
     */
    public static function maybe_enqueue_for_singular(): void {
        if (! apply_filters('translateplus_frontend_dropdown_assets', true)) {
            return;
        }
        if (! is_singular()) {
            return;
        }
        $post = get_queried_object();
        if (! $post instanceof WP_Post || ! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }
        $items = TranslatePlus_Translation_Group::get_frontend_switcher_items($post);
        if (count($items) < 2) {
            return;
        }
        self::enqueue_styles_only();
    }

    /**
     * Styles + front-end script (dropdown toggle, localStorage preference) for any switcher layout.
     */
    public static function enqueue_styles_only(): void {
        wp_enqueue_style(
            'translateplus-lang-dropdown',
            plugins_url('assets/css/frontend-lang-dropdown.css', TRANSLATEPLUS_FILE),
            array(),
            TRANSLATEPLUS_VERSION
        );
        wp_enqueue_script(
            'translateplus-lang-dropdown',
            plugins_url('assets/js/frontend-lang-dropdown.js', TRANSLATEPLUS_FILE),
            array(),
            TRANSLATEPLUS_VERSION,
            true
        );
    }

    public static function enqueue_assets(): void {
        self::enqueue_styles_only();
    }

    /**
     * @param array|string $atts Attributes.
     */
    public static function shortcode_tp_language_switcher($atts = array()): string {
        return self::shortcode_output(is_array($atts) ? $atts : array(), 'tp_language_switcher');
    }

    /**
     * Legacy alias for {@see self::shortcode_tp_language_switcher()}.
     *
     * @param array|string $atts Attributes.
     */
    public static function shortcode_translateplus_lang_dropdown($atts = array()): string {
        return self::shortcode_output(is_array($atts) ? $atts : array(), 'translateplus_lang_dropdown');
    }

    /**
     * @param array<string, string> $atts Shortcode attributes.
     */
    private static function shortcode_output(array $atts, string $tag): string {
        $atts = shortcode_atts(
            array(
                'post_id' => '',
            ),
            $atts,
            $tag
        );

        $pid = absint($atts['post_id']);
        if ($pid > 0) {
            return self::render($pid);
        }

        return self::render(null);
    }

    /**
     * Run shortcodes in menu item titles on the front end (e.g. label <code>[tp_language_switcher]</code>).
     *
     * Decodes entities (some setups store brackets as &#91;…&#93;) and runs late so other filters do not break the tag.
     *
     * @param string       $title Menu item title.
     * @param WP_Post|null $item  Menu item object.
     */
    public static function filter_nav_menu_item_title_shortcode($title, $item = null, $args = null, $depth = 0): string {
        if (! is_string($title)) {
            return (string) $title;
        }
        if (is_admin()) {
            return $title;
        }

        if ($item instanceof WP_Post && self::menu_item_is_language_switcher($item)) {
            return $title;
        }

        $raw = '';
        if ($item instanceof WP_Post) {
            $raw = (string) $item->post_title;
        } elseif (is_object($item) && isset($item->post_title)) {
            $raw = (string) $item->post_title;
        }

        $decoded_title = wp_specialchars_decode($title, ENT_QUOTES);
        $decoded_raw   = wp_specialchars_decode($raw, ENT_QUOTES);

        // Themes/plugins often run strip_shortcodes on the_title before this filter, which removes the
        // whole label and yields an empty <a>. The nav item object still has the raw post_title from DB.
        $candidate = $decoded_title;
        if (
            $raw !== ''
            && (
                strpos($candidate, '[') === false
                || ! preg_match('/\[(tp_language_switcher|translateplus_lang_dropdown)\b/', $candidate)
            )
            && preg_match('/\[(tp_language_switcher|translateplus_lang_dropdown)\b/', $decoded_raw)
        ) {
            $candidate = $decoded_raw;
        }

        if (strpos($candidate, '[') === false) {
            return $title;
        }
        if (! preg_match('/\[(tp_language_switcher|translateplus_lang_dropdown)\b/', $candidate)) {
            return $title;
        }

        return do_shortcode($candidate);
    }

    /**
     * Block themes: Navigation blocks output HTML that may contain the shortcode as plain text — process it.
     *
     * @param string               $block_content Block HTML.
     * @param array<string, mixed> $block         Block data.
     */
    public static function filter_render_block_shortcodes(string $block_content, array $block): string {
        if (is_admin()) {
            return $block_content;
        }
        if (strpos($block_content, '[') === false) {
            return $block_content;
        }
        if (! preg_match('/\[(tp_language_switcher|translateplus_lang_dropdown)\b/', $block_content)) {
            return $block_content;
        }

        return preg_replace_callback(
            '/\[(tp_language_switcher|translateplus_lang_dropdown)(\s[^\]]*)?\]/',
            static function (array $m): string {
                return do_shortcode($m[0]);
            },
            $block_content
        );
    }

    /**
     * @param int|null $post_id Optional explicit post ID from shortcode; null resolves from context.
     */
    public static function render(?int $post_id = null): string {
        $explicit = ($post_id !== null && $post_id > 0) ? $post_id : null;
        $resolved = self::resolve_post_id_for_render($explicit);
        if ($resolved <= 0) {
            return '';
        }
        $post = get_post($resolved);
        if (! $post instanceof WP_Post) {
            return '';
        }

        return self::render_for_post($post);
    }

    /**
     * Resolve which post defines the translation group for the switcher.
     *
     * @param int|null $explicit Positive ID from [tp_language_switcher post_id="n"], or null.
     */
    private static function resolve_post_id_for_render(?int $explicit): int {
        if ($explicit !== null && $explicit > 0) {
            return $explicit;
        }

        if (is_singular()) {
            $id = (int) get_queried_object_id();
            if ($id > 0) {
                return $id;
            }
        }

        $id = (int) get_the_ID();
        if ($id > 0) {
            return $id;
        }

        // Blog index when a static page is set as “Posts page”.
        if (is_home() && ! is_front_page()) {
            $posts_page = (int) get_option('page_for_posts');
            if ($posts_page > 0) {
                return $posts_page;
            }
        }

        $filtered = (int) apply_filters('translateplus_language_switcher_default_post_id', 0);
        if ($filtered > 0) {
            return $filtered;
        }

        // No singular / blog context (e.g. “Your latest posts” home): use any post that has 2+ linked translations.
        $from_group = self::resolve_post_id_fallback_any_translated_group();

        return $from_group > 0 ? $from_group : 0;
    }

    /**
     * When the current URL has no reference post, pick a published post that belongs to a group with multiple languages.
     * Cached via transient to avoid scanning on every request.
     */
    private static function resolve_post_id_fallback_any_translated_group(): int {
        $tid = 'translateplus_switcher_ref_post_v2';
        $cached = get_transient($tid);
        if ($cached !== false) {
            $cached = (int) $cached;
            if ($cached === 0) {
                return 0;
            }
            return get_post($cached) instanceof WP_Post ? $cached : 0;
        }

        $post_types = TranslatePlus_Settings::get_translatable_post_types();
        if ($post_types === array()) {
            set_transient($tid, 0, HOUR_IN_SECONDS);
            return 0;
        }

        $q = new WP_Query(
            array(
                'post_type'              => $post_types,
                'post_status'            => 'publish',
                'posts_per_page'         => 80,
                'orderby'                => 'modified',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => true,
                'meta_query'             => array(
                    array(
                        'key'     => TranslatePlus_Translation_Group::META_GROUP,
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        foreach ($q->posts as $p) {
            if (! $p instanceof WP_Post) {
                continue;
            }
            $items = TranslatePlus_Translation_Group::get_frontend_switcher_items($p);
            if (count($items) >= 2) {
                set_transient($tid, (int) $p->ID, 12 * HOUR_IN_SECONDS);
                return (int) $p->ID;
            }
        }

        set_transient($tid, 0, HOUR_IN_SECONDS);

        return 0;
    }

    /**
     * @param WP_Post $post Post.
     */
    private static function render_for_post(WP_Post $post): string {
        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return '';
        }

        $items = TranslatePlus_Translation_Group::get_frontend_switcher_items($post);
        if (count($items) < 2) {
            return '';
        }

        $use_dropdown = apply_filters(
            'translateplus_frontend_use_dropdown',
            TranslatePlus_Settings::is_frontend_switcher_dropdown()
        );

        if (! $use_dropdown) {
            self::enqueue_styles_only();
            $show_flags = TranslatePlus_Settings::is_frontend_switcher_flags_enabled();

            return TranslatePlus_Translation_Group::render_language_switcher_nav_inline($post, $show_flags);
        }

        self::enqueue_assets();

        $show_flags = TranslatePlus_Settings::is_frontend_switcher_flags_enabled();

        $current = null;
        foreach ($items as $row) {
            if (! empty($row['current'])) {
                $current = $row;
                break;
            }
        }
        if ($current === null) {
            $current = $items[0];
        }

        $uid = function_exists('wp_unique_id') ? wp_unique_id('tp-lang-dd-') : 'tp-lang-dd-' . wp_rand(1000, 999999);
        $btn_id = $uid . '-btn';
        $list_id = $uid . '-list';

        $aria_label = sprintf(
            /* translators: %s: Current language name. */
            __('Language menu, current: %s', 'translateplus'),
            $current['label']
        );

        $root_class = 'translateplus-lang-dd' . ($show_flags ? '' : ' translateplus-lang-dd--no-flags');

        ob_start();
        ?>
        <div class="<?php echo esc_attr($root_class); ?>" data-translateplus-lang-dd id="<?php echo esc_attr($uid); ?>" data-translateplus-dd-init="0">
            <button
                type="button"
                class="translateplus-lang-dd__toggle"
                id="<?php echo esc_attr($btn_id); ?>"
                aria-expanded="false"
                aria-haspopup="listbox"
                aria-controls="<?php echo esc_attr($list_id); ?>"
                aria-label="<?php echo esc_attr($aria_label); ?>"
            >
                <?php if ($show_flags) : ?>
                    <span class="translateplus-lang-dd__flag" aria-hidden="true"><?php echo esc_html(self::flag_emoji($current['code'])); ?></span>
                <?php endif; ?>
                <span class="translateplus-lang-dd__label"><?php echo esc_html($current['label']); ?></span>
                <span class="translateplus-lang-dd__chev" aria-hidden="true">▼</span>
            </button>
            <ul class="translateplus-lang-dd__list" id="<?php echo esc_attr($list_id); ?>" role="listbox" hidden aria-label="<?php echo esc_attr(__('Available languages', 'translateplus')); ?>">
                <?php
                foreach ($items as $item) :
                    $is_active = ! empty($item['current']);
                    $is_miss   = ! empty($item['missing']);
                    $li_class  = 'translateplus-lang-dd__item' . ($is_active ? ' is-active' : '') . ($is_miss ? ' is-missing' : '');
                    ?>
                    <li class="<?php echo esc_attr($li_class); ?>" role="none">
                        <?php if ($is_active) : ?>
                            <span class="translateplus-lang-dd__link" role="option" aria-selected="true" tabindex="-1">
                                <?php if ($show_flags) : ?>
                                    <span class="translateplus-lang-dd__item-flag" aria-hidden="true"><?php echo esc_html(self::flag_emoji($item['code'])); ?></span>
                                <?php endif; ?>
                                <span class="translateplus-lang-dd__item-text"><?php echo esc_html($item['label']); ?></span>
                                <span class="translateplus-lang-dd__item-code"><?php echo esc_html(strtoupper($item['code'])); ?></span>
                            </span>
                        <?php elseif ($is_miss) : ?>
                            <span class="translateplus-lang-dd__link translateplus-lang-dd__link--missing" role="option" aria-disabled="true" tabindex="-1" title="<?php echo esc_attr(__('No translation published for this language yet.', 'translateplus')); ?>">
                                <?php if ($show_flags) : ?>
                                    <span class="translateplus-lang-dd__item-flag" aria-hidden="true"><?php echo esc_html(self::flag_emoji($item['code'])); ?></span>
                                <?php endif; ?>
                                <span class="translateplus-lang-dd__item-text"><?php echo esc_html($item['label']); ?></span>
                                <span class="translateplus-lang-dd__item-code"><?php echo esc_html(strtoupper($item['code'])); ?></span>
                            </span>
                        <?php else : ?>
                            <a class="translateplus-lang-dd__link" role="option" href="<?php echo esc_url($item['url']); ?>" hreflang="<?php echo esc_attr($item['code']); ?>" aria-selected="false">
                                <?php if ($show_flags) : ?>
                                    <span class="translateplus-lang-dd__item-flag" aria-hidden="true"><?php echo esc_html(self::flag_emoji($item['code'])); ?></span>
                                <?php endif; ?>
                                <span class="translateplus-lang-dd__item-text"><?php echo esc_html($item['label']); ?></span>
                                <span class="translateplus-lang-dd__item-code"><?php echo esc_html(strtoupper($item['code'])); ?></span>
                            </a>
                        <?php endif; ?>
                    </li>
                    <?php
                endforeach;
                ?>
            </ul>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Display flag emoji for a language code (fallback 🌐).
     */
    public static function flag_emoji(string $code): string {
        $n = TranslatePlus_Languages::normalize($code);
        $key = $n !== null ? $n : $code;

        if (isset(self::$flag_by_code[ $key ])) {
            return self::$flag_by_code[ $key ];
        }

        $lower = strtolower(str_replace('_', '-', $code));
        if (isset(self::$flag_by_code[ $lower ])) {
            return self::$flag_by_code[ $lower ];
        }

        return '🌐';
    }
}
