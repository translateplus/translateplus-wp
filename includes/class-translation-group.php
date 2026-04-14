<?php
/**
 * Translation group: links separate posts per language; editor tabs jump to each post.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Post meta _tp_translation_group + _tp_language; editor nav tabs to linked posts.
 */
final class TranslatePlus_Translation_Group {

    public const META_GROUP = '_tp_translation_group';

    public const META_LANGUAGE = '_tp_language';

    /**
     * @deprecated Legacy meta; read in get_post_language() for migration only.
     */
    private const LEGACY_META_LOCALE = '_tp_content_locale';

    private const META_BOX_ID = 'translateplus_translation_group';

    private const NONCE_ACTION = 'translateplus_save_translation_group';

    private const NONCE_FIELD = 'translateplus_translation_group_nonce';

    public static function init(): void {
        add_action('add_meta_boxes', array(self::class, 'register_meta_box'));
        add_action('save_post', array(self::class, 'save_meta'), 5, 2);
        add_action('save_post', array(self::class, 'maybe_ensure_group_on_save'), 6, 2);
        add_action('edit_form_after_title', array(self::class, 'render_classic_tabs'), 4);
        add_action('admin_enqueue_scripts', array(self::class, 'enqueue_metabox_styles'), 9);
        add_action('admin_enqueue_scripts', array(self::class, 'enqueue_tabs_script'), 20);
    }

    /**
     * Public singular views: language links above post content (same translation group).
     */
    public static function register_frontend_hooks(): void {
        if ((bool) apply_filters('translateplus_auto_prepend_content_switcher', false)) {
            add_filter('the_content', array(self::class, 'prepend_frontend_language_switcher'), 8);
        }
        add_filter('wp_nav_menu_items', array(self::class, 'append_nav_menu_language_items'), 20, 2);
    }

    /**
     * Locales that can be assigned to a post (source + configured targets).
     *
     * @return array<string, string> code => label
     */
    public static function locale_choices(): array {
        $all     = TranslatePlus_Settings::available_languages();
        $targets = TranslatePlus_Settings::get_target_languages();
        $source  = TranslatePlus_API::DEFAULT_SOURCE;
        $full    = TranslatePlus_Languages::get_code_to_label();

        $choices = array();

        // Do not include "auto" — it is API/source-detection only, not a post or linked locale.
        $choices[ $source ] = $full[ $source ] ?? $all[ $source ] ?? __('English', 'translateplus');

        foreach ($targets as $code) {
            if ($code === $source || isset($choices[ $code ])) {
                continue;
            }
            $choices[ $code ] = isset($all[ $code ])
                ? $all[ $code ]
                : ($full[ $code ] ?? strtoupper($code));
        }

        return $choices;
    }

    /**
     * Post language code (new _tp_language, else legacy _tp_content_locale, else default).
     */
    public static function get_post_language(int $post_id): string {
        $v = get_post_meta($post_id, self::META_LANGUAGE, true);
        if (is_string($v) && $v !== '') {
            $n = TranslatePlus_Languages::normalize($v);

            return $n !== null ? $n : TranslatePlus_API::DEFAULT_SOURCE;
        }

        $legacy = get_post_meta($post_id, self::LEGACY_META_LOCALE, true);
        if (is_string($legacy) && $legacy !== '') {
            $n = TranslatePlus_Languages::normalize($legacy);

            return $n !== null ? $n : TranslatePlus_API::DEFAULT_SOURCE;
        }

        return TranslatePlus_API::DEFAULT_SOURCE;
    }

    /**
     * Ensure the post has a translation group ID (creates and saves one if missing).
     */
    public static function ensure_group_for_post(int $post_id): string {
        $existing = get_post_meta($post_id, self::META_GROUP, true);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $uuid = function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : wp_hash(uniqid((string) $post_id, true) . (string) wp_rand());

        update_post_meta($post_id, self::META_GROUP, $uuid);

        return $uuid;
    }

    /**
     * Ensure a translation group meta exists on save when the overview nonce was not submitted.
     *
     * The block editor saves via REST and often omits classic meta box fields from $_POST, so save_meta()
     * never runs and ensure_group_for_post() would otherwise be skipped — breaking automatic sync and linking.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public static function maybe_ensure_group_on_save(int $post_id, WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if (class_exists('TranslatePlus_Auto_Sync', false) && TranslatePlus_Auto_Sync::is_syncing()) {
            return;
        }

        $group = get_post_meta($post_id, self::META_GROUP, true);
        if (is_string($group) && $group !== '') {
            return;
        }

        self::ensure_group_for_post($post_id);
    }

    /**
     * First post ID in the group with the given language (0 if none). Matches _tp_language or legacy _tp_content_locale.
     */
    public static function find_post_in_group_by_language(string $group, string $language, string $post_type): int {
        $n = TranslatePlus_Languages::normalize($language);
        $language = $n !== null ? $n : sanitize_key($language);
        $group    = sanitize_text_field($group);
        if ($group === '' || $language === '') {
            return 0;
        }

        $query = new WP_Query(
            array(
                'post_type'              => $post_type,
                'post_status'            => array('publish', 'draft', 'pending', 'future', 'private'),
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    'relation' => 'AND',
                    array(
                        'key'   => self::META_GROUP,
                        'value' => $group,
                    ),
                    array(
                        'relation' => 'OR',
                        array(
                            'key'   => self::META_LANGUAGE,
                            'value' => $language,
                        ),
                        array(
                            'key'   => self::LEGACY_META_LOCALE,
                            'value' => $language,
                        ),
                    ),
                ),
            )
        );

        if (! empty($query->posts)) {
            return (int) $query->posts[0];
        }

        return 0;
    }

    /**
     * Normalized language of the earliest post (lowest ID) in the translation group — the “parent” entry.
     *
     * @param int $post_id Any post that belongs to the group.
     * @return string|null Canonical code, or null if there is no group or language.
     */
    public static function get_group_root_language(int $post_id): ?string {
        $post = get_post($post_id);
        if (! $post instanceof WP_Post) {
            return null;
        }

        $group = get_post_meta($post_id, self::META_GROUP, true);
        if (! is_string($group) || $group === '') {
            return null;
        }

        $query = new WP_Query(
            array(
                'post_type'              => $post->post_type,
                'post_status'            => array('publish', 'draft', 'pending', 'future', 'private'),
                'posts_per_page'         => 1,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'   => self::META_GROUP,
                        'value' => $group,
                    ),
                ),
            )
        );

        if (empty($query->posts[0])) {
            return null;
        }

        $root_id = (int) $query->posts[0];
        $stored  = self::get_post_language($root_id);
        $n       = TranslatePlus_Languages::normalize($stored);

        if ($n === null || $n === '' || $n === 'auto') {
            return null;
        }

        return $n;
    }

    /**
     * Posts in the same group (same post type), one entry per language; user must be able to edit each.
     *
     * @return list<array{post_id: int, language: string}>
     */
    public static function get_linked_members(WP_Post $post): array {
        $group = get_post_meta($post->ID, self::META_GROUP, true);
        $rows  = array();

        if (! is_string($group) || $group === '') {
            $rows[] = array(
                'post_id' => $post->ID,
                'language' => self::get_post_language($post->ID),
            );

            return $rows;
        }

        $query = new WP_Query(
            array(
                'post_type'              => $post->post_type,
                'post_status'            => array('publish', 'draft', 'pending', 'future', 'private'),
                'posts_per_page'         => -1,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'   => self::META_GROUP,
                        'value' => $group,
                    ),
                ),
            )
        );

        foreach ($query->posts as $p) {
            if (! $p instanceof WP_Post) {
                continue;
            }
            if (! current_user_can('edit_post', $p->ID)) {
                continue;
            }
            $rows[] = array(
                'post_id'  => $p->ID,
                'language' => self::get_post_language($p->ID),
            );
        }

        // One tab per language (smallest ID wins if duplicates).
        $by_lang = array();
        foreach ($rows as $row) {
            $lang = $row['language'];
            if (! isset($by_lang[ $lang ]) || $row['post_id'] < $by_lang[ $lang ]['post_id']) {
                $by_lang[ $lang ] = $row;
            }
        }

        $out = array_values($by_lang);

        $ids = wp_list_pluck($out, 'post_id');
        if (! in_array($post->ID, $ids, true)) {
            $out[] = array(
                'post_id'  => $post->ID,
                'language' => self::get_post_language($post->ID),
            );
        }

        return $out;
    }

    /**
     * Canonical language key for switcher maps (matches {@see self::locale_choices()} keys).
     */
    private static function switcher_language_key(string $code): string {
        $n = TranslatePlus_Languages::normalize($code);
        if ($n !== null && $n !== 'auto') {
            return $n;
        }

        return strtolower(sanitize_key($code));
    }

    /**
     * Whether a linked post should show as a clickable switcher link for the current visitor.
     */
    private static function switcher_target_is_followable(int $post_id): bool {
        $p = get_post($post_id);
        if (! $p instanceof WP_Post) {
            return false;
        }

        if (function_exists('is_post_publicly_viewable') && is_post_publicly_viewable($p)) {
            return true;
        }

        return current_user_can('read_post', $post_id);
    }

    /**
     * Build switcher rows: every locale from Settings ({@see self::locale_choices()}), plus any extra languages
     * found in the group. Missing translations are included with empty URL and missing=true.
     *
     * @return list<array{code: string, label: string, url: string, current: bool, missing: bool}>
     */
    public static function get_frontend_switcher_items(WP_Post $post): array {
        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return array();
        }

        $map   = array();
        $group = get_post_meta($post->ID, self::META_GROUP, true);

        if (is_string($group) && $group !== '') {
            $query = new WP_Query(
                array(
                    'post_type'              => $post->post_type,
                    'post_status'            => array('publish', 'draft', 'pending', 'future', 'private'),
                    'posts_per_page'         => -1,
                    'orderby'                => 'ID',
                    'order'                  => 'ASC',
                    'no_found_rows'          => true,
                    'ignore_sticky_posts'    => true,
                    'update_post_meta_cache' => true,
                    'update_post_term_cache' => false,
                    'meta_query'             => array(
                        array(
                            'key'   => self::META_GROUP,
                            'value' => $group,
                        ),
                    ),
                )
            );

            foreach ($query->posts as $p) {
                if (! $p instanceof WP_Post) {
                    continue;
                }
                $lang = self::get_post_language($p->ID);
                if ($lang === '') {
                    continue;
                }
                $lang_key = self::switcher_language_key($lang);
                if (! isset($map[ $lang_key ]) || $p->ID < $map[ $lang_key ]) {
                    $map[ $lang_key ] = $p->ID;
                }
            }
        }

        $cur_lang = self::get_post_language($post->ID);
        if ($cur_lang !== '') {
            $map[ self::switcher_language_key($cur_lang) ] = $post->ID;
        }

        $choices = self::locale_choices();
        if (count($choices) < 2) {
            return array();
        }

        $items = array();
        $used  = array();

        foreach ($choices as $code => $label) {
            $code_key = self::switcher_language_key($code);
            $pid      = isset($map[ $code_key ]) ? (int) $map[ $code_key ] : 0;
            $url      = '';
            $missing  = true;

            if ($pid > 0 && self::switcher_target_is_followable($pid)) {
                $permalink = get_permalink($pid);
                if (is_string($permalink) && $permalink !== '') {
                    $url     = TranslatePlus_URL_Builder::convert_url($permalink, $code_key);
                    $missing = false;
                }
            }

            $items[] = array(
                'code'    => $code_key,
                'label'   => $label,
                'url'     => $url,
                'current' => $pid > 0 && (int) $post->ID === $pid,
                'missing' => $missing,
            );
            $used[ $code_key ] = true;
        }

        $extra = array_diff_key($map, $used);
        ksort($extra, SORT_STRING);
        foreach ($extra as $code => $pid) {
            $pid = (int) $pid;
            if ($pid <= 0 || ! self::switcher_target_is_followable($pid)) {
                continue;
            }
            $url = get_permalink($pid);
            if (! is_string($url) || $url === '') {
                continue;
            }
            $url = TranslatePlus_URL_Builder::convert_url($url, $code);
            $items[] = array(
                'code'    => $code,
                'label'   => strtoupper($code),
                'url'     => $url,
                'current' => (int) $post->ID === $pid,
                'missing' => false,
            );
        }

        if (! is_singular() && (is_front_page() || is_home())) {
            $current_lang = self::detect_current_request_language();
            foreach ($items as &$item) {
                $code = self::switcher_language_key((string) $item['code']);
                $item['url'] = TranslatePlus_URL_Builder::convert_url(home_url('/'), $code);
                $item['missing'] = false;
                $item['current'] = ($code === $current_lang);
            }
            unset($item);
        }

        return $items;
    }

    /**
     * Detect current request language from rewrite var, falling back to default source language.
     */
    private static function detect_current_request_language(): string {
        $q = get_query_var(TranslatePlus_Rewrites::QUERY_VAR_LANG, '');
        if (is_string($q) && $q !== '') {
            $n = TranslatePlus_Languages::normalize($q);
            if ($n !== null && $n !== 'auto') {
                return $n;
            }
        }

        return TranslatePlus_API::DEFAULT_SOURCE;
    }

    /**
     * @param list<array{code: string, label: string, url: string, current: bool}> $items
     */
    public static function prepend_frontend_language_switcher(string $content): string {
        if (is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query()) {
            return $content;
        }

        $post = get_post();
        if (! $post instanceof WP_Post || ! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return $content;
        }

        $items = self::get_frontend_switcher_items($post);
        if (count($items) < 2) {
            return $content;
        }

        $use_dropdown = apply_filters(
            'translateplus_frontend_use_dropdown',
            TranslatePlus_Settings::is_frontend_switcher_dropdown()
        );

        if ($use_dropdown && class_exists('TranslatePlus_Frontend_Lang_Dropdown')) {
            return TranslatePlus_Frontend_Lang_Dropdown::render((int) $post->ID) . $content;
        }

        if (class_exists('TranslatePlus_Frontend_Lang_Dropdown')) {
            TranslatePlus_Frontend_Lang_Dropdown::enqueue_styles_only();
        }

        $show_flags = TranslatePlus_Settings::is_frontend_switcher_flags_enabled();

        return self::render_language_switcher_nav_inline($post, $show_flags) . $content;
    }

    /**
     * Render a language switcher: links for each translation sharing `_tp_translation_group`, current locale highlighted.
     *
     * When `$post_id` is null, uses the main singular post (`get_queried_object_id()`) or the current post in the loop (`get_the_ID()`).
     *
     * @param int|null $post_id Post ID, or null to detect from the current request.
     * @param array{min_items?: int, nav_class?: string, nav_aria_label?: string, show_flags?: bool} $args Optional. `min_items` defaults to 1 (set to 2 to match the auto-injected switcher threshold). `show_flags` defaults from Settings → TranslatePlus.
     * @return string HTML fragment, or empty string if not applicable.
     */
    public static function render_language_switcher(?int $post_id = null, array $args = array()): string {
        if ($post_id === null || $post_id <= 0) {
            if (is_admin()) {
                return '';
            }
            if (is_singular()) {
                $post_id = (int) get_queried_object_id();
            }
            if ($post_id <= 0) {
                $post_id = (int) get_the_ID();
            }
        }

        if ($post_id <= 0) {
            return '';
        }

        $post = get_post($post_id);
        if (! $post instanceof WP_Post || ! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return '';
        }

        $min_items = isset($args['min_items']) ? max(1, (int) $args['min_items']) : 1;
        $items     = self::get_frontend_switcher_items($post);
        if (count($items) < $min_items) {
            return '';
        }

        $nav_class = ! empty($args['nav_class']) && is_string($args['nav_class'])
            ? $args['nav_class']
            : 'translateplus-frontend-lang-switcher';
        $aria = ! empty($args['nav_aria_label']) && is_string($args['nav_aria_label'])
            ? $args['nav_aria_label']
            : __('Languages', 'translateplus');
        $show_flags = array_key_exists('show_flags', $args)
            ? (bool) $args['show_flags']
            : TranslatePlus_Settings::is_frontend_switcher_flags_enabled();

        return self::render_language_switcher_html($items, $nav_class, $aria, $show_flags);
    }

    /**
     * Inline link row for a singular post (used when layout is “inline” or for themes calling this directly).
     *
     * @param bool $show_flags Whether to prefix labels with flag emoji.
     */
    public static function render_language_switcher_nav_inline(WP_Post $post, bool $show_flags): string {
        $items = self::get_frontend_switcher_items($post);
        if (count($items) < 2) {
            return '';
        }

        return self::render_language_switcher_html(
            $items,
            'translateplus-frontend-lang-switcher translateplus-lang-switcher--inline',
            __('Languages', 'translateplus'),
            $show_flags
        );
    }

    /**
     * Premium dropdown switcher (flag + list). Requires {@see TranslatePlus_Frontend_Lang_Dropdown}.
     *
     * @param int|null $post_id Post ID or null for current post.
     */
    public static function render_language_switcher_dropdown(?int $post_id = null): string {
        if (! class_exists('TranslatePlus_Frontend_Lang_Dropdown')) {
            return '';
        }

        return TranslatePlus_Frontend_Lang_Dropdown::render($post_id);
    }

    /**
     * @param list<array{code: string, label: string, url: string, current: bool, missing?: bool}> $items
     */
    private static function render_language_switcher_html(array $items, string $nav_class, string $aria_label, bool $show_flags = false): string {
        ob_start();
        ?>
        <nav class="<?php echo esc_attr($nav_class); ?> translateplus-lang-switcher" aria-label="<?php echo esc_attr($aria_label); ?>" style="display:flex;flex-wrap:wrap;gap:0.65rem;align-items:center;margin:0 0 1.25em;padding:0;clear:both;">
            <?php
            foreach ($items as $item) :
                $missing = ! empty($item['missing']);
                if (! empty($item['current'])) :
                    ?>
                    <span class="translateplus-frontend-lang-switcher__current" aria-current="page" style="display:inline-flex;align-items:center;gap:0.35rem;font-weight:600;">
                        <?php if ($show_flags && class_exists('TranslatePlus_Frontend_Lang_Dropdown')) : ?>
                            <span class="translateplus-frontend-lang-switcher__flag" aria-hidden="true"><?php echo esc_html(TranslatePlus_Frontend_Lang_Dropdown::flag_emoji($item['code'])); ?></span>
                        <?php endif; ?>
                        <span class="translateplus-frontend-lang-switcher__text"><?php echo esc_html($item['label']); ?></span>
                    </span>
                    <?php
                elseif ($missing) :
                    ?>
                    <span class="translateplus-frontend-lang-switcher__missing" style="display:inline-flex;align-items:center;gap:0.35rem;color:#9ca3af;cursor:not-allowed;" title="<?php echo esc_attr(__('No translation published for this language yet.', 'translateplus')); ?>">
                        <?php if ($show_flags && class_exists('TranslatePlus_Frontend_Lang_Dropdown')) : ?>
                            <span class="translateplus-frontend-lang-switcher__flag" aria-hidden="true"><?php echo esc_html(TranslatePlus_Frontend_Lang_Dropdown::flag_emoji($item['code'])); ?></span>
                        <?php endif; ?>
                        <span class="translateplus-frontend-lang-switcher__text"><?php echo esc_html($item['label']); ?></span>
                    </span>
                    <?php
                else :
                    ?>
                    <a class="translateplus-frontend-lang-switcher__link" style="display:inline-flex;align-items:center;gap:0.35rem;" href="<?php echo esc_url($item['url']); ?>" hreflang="<?php echo esc_attr($item['code']); ?>">
                        <?php if ($show_flags && class_exists('TranslatePlus_Frontend_Lang_Dropdown')) : ?>
                            <span class="translateplus-frontend-lang-switcher__flag" aria-hidden="true"><?php echo esc_html(TranslatePlus_Frontend_Lang_Dropdown::flag_emoji($item['code'])); ?></span>
                        <?php endif; ?>
                        <span class="translateplus-frontend-lang-switcher__text"><?php echo esc_html($item['label']); ?></span>
                    </a>
                    <?php
                endif;
            endforeach;
            ?>
        </nav>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * Append the language dropdown (same markup as {@see TranslatePlus_Frontend_Lang_Dropdown::render()}) inside one menu item.
     *
     * @param string $items The HTML list content for the menu items.
     * @param object $args  An object containing wp_nav_menu() arguments.
     */
    public static function append_nav_menu_language_items(string $items, $args): string {
        if (is_admin() || is_feed()) {
            return $items;
        }

        if (! apply_filters('translateplus_nav_menu_append_languages', true, $args)) {
            return $items;
        }

        if (! is_singular()) {
            return $items;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post || ! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return $items;
        }

        if (! class_exists('TranslatePlus_Frontend_Lang_Dropdown')) {
            return $items;
        }

        $dropdown = TranslatePlus_Frontend_Lang_Dropdown::render((int) $post->ID);
        if ($dropdown === '') {
            return $items;
        }

        $li = '<li class="menu-item menu-item-type-custom translateplus-nav-menu-lang-dropdown">'
            . $dropdown
            . '</li>';

        return $items . $li;
    }

    /**
     * Language switcher: every configured language, link if a linked post exists, else “missing”.
     *
     * @return array{items: list<array{code: string, label: string, title: string, current: bool, url: ?string, missing: bool, missing_aria?: string}>, toolbarId: string, i18n: array{toolbar: string, add: string, addHelp: string}}
     */
    public static function get_language_switcher_config(WP_Post $post): array {
        $choices = self::locale_choices();
        $current = self::get_post_language($post->ID);
        $members = self::get_linked_members($post);
        $map     = array();
        foreach ($members as $row) {
            $map[ $row['language'] ] = (int) $row['post_id'];
        }

        $items = array();
        foreach ($choices as $code => $label) {
            if ($code === $current) {
                $items[] = array(
                    'code'    => $code,
                    'label'   => strtoupper($code),
                    'title'   => $label,
                    'current' => true,
                    'url'     => null,
                    'missing' => false,
                );
                continue;
            }

            $pid = isset($map[ $code ]) ? $map[ $code ] : 0;
            $url = null;
            if ($pid > 0 && current_user_can('edit_post', $pid)) {
                $raw = get_edit_post_link($pid, 'raw');
                if (is_string($raw) && $raw !== '') {
                    $url = $raw;
                }
            }

            if ($url !== null) {
                $items[] = array(
                    'code'    => $code,
                    'label'   => strtoupper($code),
                    'title'   => $label,
                    'current' => false,
                    'url'     => $url,
                    'missing' => false,
                );
            } else {
                $items[] = array(
                    'code'         => $code,
                    'label'        => strtoupper($code),
                    'title'        => $label,
                    'current'      => false,
                    'url'          => null,
                    'missing'      => true,
                    'missing_aria' => sprintf(
                        /* translators: %s: language name */
                        __('Add %s translation', 'translateplus'),
                        $label
                    ),
                );
            }
        }

        return array(
            'items'      => $items,
            'toolbarId'  => 'translateplus-lang-switcher',
            'i18n'       => array(
                'toolbar' => __('Post languages', 'translateplus'),
                'add'     => __('+ Add', 'translateplus'),
                'addHelp' => __('Create a translation in another language', 'translateplus'),
            ),
        );
    }

    public static function register_meta_box(): void {
        foreach (TranslatePlus_Settings::get_translatable_post_types() as $post_type) {
            add_meta_box(
                self::META_BOX_ID,
                __('TranslatePlus — Translation overview', 'translateplus'),
                array(self::class, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Enqueue styles for the translation overview meta box on post edit screens.
     */
    public static function enqueue_metabox_styles(string $hook): void {
        if (! in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || ! in_array($screen->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        wp_enqueue_style(
            'translateplus-translation-overview',
            plugins_url('assets/css/editor-translation-overview.css', TRANSLATEPLUS_FILE),
            array(),
            TRANSLATEPLUS_VERSION
        );
    }

    /**
     * @param WP_Post $post Post object.
     */
    public static function render_meta_box(WP_Post $post): void {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $language = self::get_post_language($post->ID);
        $choices  = self::locale_choices();
        $members  = self::get_linked_members($post);
        $by_lang  = array();
        foreach ($members as $row) {
            $by_lang[ $row['language'] ] = (int) $row['post_id'];
        }

        $missing_count = 0;
        foreach ($choices as $code => $_lbl) {
            if (! isset($by_lang[ $code ])) {
                ++$missing_count;
            }
        }

        ?>
        <div class="translateplus-lang-actions-root translateplus-translation-overview">
            <p class="translateplus-translation-overview__intro">
                <?php esc_html_e('Languages available on this site, this post’s language, and linked translations. Create missing versions with one click.', 'translateplus'); ?>
            </p>

            <div class="translateplus-translation-overview__field">
                <label for="tp_language"><?php esc_html_e('This post’s language', 'translateplus'); ?></label>
                <select name="tp_language" id="tp_language" class="widefat">
                    <?php
                    foreach ($choices as $code => $label) :
                        ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($language, $code); ?>>
                            <?php echo esc_html($label . ' (' . strtoupper($code) . ')'); ?>
                        </option>
                        <?php
                    endforeach;
                    ?>
                </select>
            </div>

            <p class="translateplus-translation-overview__heading"><?php esc_html_e('Available & linked languages', 'translateplus'); ?></p>
            <ul class="translateplus-translation-overview__list" role="list">
                <?php
                foreach ($choices as $code => $label) :
                    $pid = isset($by_lang[ $code ]) ? $by_lang[ $code ] : 0;
                    $add_aria        = sprintf(
                        /* translators: %s: language name */
                        __('Add %s translation', 'translateplus'),
                        $label
                    );
                    ?>
                    <li class="translateplus-translation-overview__item">
                        <span class="translateplus-translation-overview__lang">
                            <?php echo esc_html($label); ?>
                            <code><?php echo esc_html(strtoupper($code)); ?></code>
                        </span>
                        <span class="translateplus-translation-overview__actions">
                            <?php
                            if ($code === $language) :
                                ?>
                                <span class="translateplus-translation-overview__badge"><?php esc_html_e('This post', 'translateplus'); ?></span>
                                <?php
                            elseif ($pid <= 0) :
                                ?>
                                <span class="translateplus-translation-overview__badge translateplus-translation-overview__badge--muted"><?php esc_html_e('Not created', 'translateplus'); ?></span>
                                <button
                                    type="button"
                                    class="button button-small translateplus-lang-missing"
                                    data-tp-pick-lang="<?php echo esc_attr($code); ?>"
                                    aria-label="<?php echo esc_attr($add_aria); ?>"
                                    title="<?php echo esc_attr($add_aria); ?>"
                                >
                                    <?php esc_html_e('Add', 'translateplus'); ?>
                                </button>
                                <?php
                            else :
                                $edit = get_edit_post_link($pid, 'raw');
                                ?>
                                <span class="translateplus-translation-overview__badge translateplus-translation-overview__badge--muted"><?php esc_html_e('Translation exists', 'translateplus'); ?></span>
                                <?php
                                if (is_string($edit) && $edit !== '') :
                                    ?>
                                    <a class="button button-small" href="<?php echo esc_url($edit); ?>"><?php esc_html_e('Edit', 'translateplus'); ?></a>
                                    <?php
                                endif;
                            endif;
                            ?>
                        </span>
                    </li>
                    <?php
                endforeach;
                ?>
            </ul>

            <?php if ($missing_count > 0) : ?>
                <div class="translateplus-translation-overview__footer">
                    <button type="button" class="button button-secondary translateplus-add-translation" title="<?php echo esc_attr(__('Create a translation in another language', 'translateplus')); ?>">
                        <?php esc_html_e('+ Add translation', 'translateplus'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public static function save_meta(int $post_id, WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        if (! isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $choices = self::locale_choices();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
        $lang_raw = isset($_POST['tp_language']) ? wp_unslash($_POST['tp_language']) : '';
        $lang     = is_string($lang_raw) ? (TranslatePlus_Languages::normalize($lang_raw) ?? TranslatePlus_API::DEFAULT_SOURCE) : TranslatePlus_API::DEFAULT_SOURCE;
        if (! isset($choices[ $lang ])) {
            $lang = TranslatePlus_API::DEFAULT_SOURCE;
        }
        update_post_meta($post_id, self::META_LANGUAGE, $lang);
        delete_post_meta($post_id, self::LEGACY_META_LOCALE);

        // Translation group is never read from the editor; assign internally when missing.
        self::ensure_group_for_post($post_id);
    }

    public static function render_classic_tabs(): void {
        global $post;

        if (! $post instanceof WP_Post || ! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        if (! TranslatePlus_Settings::is_editor_manual_ui_enabled()) {
            return;
        }

        if (function_exists('use_block_editor_for_post') && use_block_editor_for_post($post)) {
            return;
        }

        self::output_language_switcher_html($post);
    }

    public static function enqueue_tabs_script(string $hook): void {
        if (! in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        global $post;
        if (! $post instanceof WP_Post || ! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        if (! TranslatePlus_Settings::is_editor_manual_ui_enabled()) {
            return;
        }

        if (! function_exists('use_block_editor_for_post') || ! use_block_editor_for_post($post)) {
            return;
        }

        wp_enqueue_script(
            'translateplus-translation-post-tabs',
            plugins_url('assets/js/editor-translation-post-tabs.js', TRANSLATEPLUS_FILE),
            array('wp-dom-ready'),
            TRANSLATEPLUS_VERSION,
            true
        );

        $cfg = self::get_language_switcher_config($post);
        wp_localize_script(
            'translateplus-translation-post-tabs',
            'translateplusTranslationPostTabs',
            $cfg
        );
    }

    private static function output_language_switcher_html(WP_Post $post): void {
        $cfg = self::get_language_switcher_config($post);
        if ($cfg['items'] === array()) {
            return;
        }

        $add_label = isset($cfg['i18n']['add']) ? $cfg['i18n']['add'] : __('+ Add', 'translateplus');
        ?>
        <div
            id="<?php echo esc_attr($cfg['toolbarId']); ?>"
            class="translateplus-lang-switcher translateplus-lang-actions-root"
            role="toolbar"
            aria-label="<?php echo esc_attr($cfg['i18n']['toolbar']); ?>"
            style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:0 0 16px;padding:10px 12px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;"
        >
            <span class="translateplus-lang-switcher__label" style="font-weight:600;margin-right:4px;">
                <?php echo esc_html($cfg['i18n']['toolbar']); ?>
            </span>
            <span class="translateplus-lang-switcher__buttons" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                <?php
                foreach ($cfg['items'] as $item) :
                    if (! empty($item['current'])) :
                        ?>
                        <button type="button" class="button button-primary" disabled aria-current="true" title="<?php echo esc_attr($item['title']); ?>">
                            <?php echo esc_html($item['label']); ?>
                        </button>
                        <?php
                    elseif (! empty($item['url'])) :
                        ?>
                        <a class="button" href="<?php echo esc_url($item['url']); ?>" title="<?php echo esc_attr($item['title']); ?>">
                            <?php echo esc_html($item['label']); ?>
                        </a>
                        <?php
                    else :
                        $add_title = isset($item['missing_aria']) ? $item['missing_aria'] : $item['title'];
                        ?>
                        <button
                            type="button"
                            class="button translateplus-lang-missing"
                            data-tp-pick-lang="<?php echo esc_attr($item['code']); ?>"
                            title="<?php echo esc_attr($add_title); ?>"
                            aria-label="<?php echo esc_attr($add_title); ?>"
                        >
                            <?php echo esc_html($item['label']); ?>
                        </button>
                        <?php
                    endif;
                endforeach;
                ?>
                <button type="button" class="button button-secondary translateplus-add-translation" title="<?php echo esc_attr($cfg['i18n']['addHelp']); ?>">
                    <?php echo esc_html($add_label); ?>
                </button>
            </span>
        </div>
        <?php
    }
}
