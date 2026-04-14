<?php
/**
 * Language-prefixed permalinks: /{lang}/{post-slug} → main query + query vars.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers rewrite rules, query vars, and maps requests to the correct singular post.
 */
final class TranslatePlus_Rewrites {

    public const QUERY_VAR_LANG = 'tp_lang';

    public const QUERY_VAR_PATH = 'tp_post_path';

    public static function init(): void {
        add_action('init', array(self::class, 'register_rewrite_rules'), 11);
        add_filter('query_vars', array(self::class, 'register_query_vars'));
        add_action('pre_get_posts', array(self::class, 'map_query_vars_to_post'), 1);
        add_filter('post_link', array(self::class, 'filter_post_link'), 10, 3);
        add_filter('page_link', array(self::class, 'filter_page_link'), 10, 3);
        add_filter('post_type_link', array(self::class, 'filter_post_type_link'), 10, 4);
    }

    /**
     * Canonical “site default” language for URLs: no prefix when it matches (unless filtered).
     *
     * @return string Normalized language code (e.g. en).
     */
    public static function get_default_language_code(): string {
        return TranslatePlus_URL_Builder::get_default_language_code();
    }

    /**
     * When true, default-language posts use /{default}/slug/ instead of plain /slug/.
     *
     * @return bool
     */
    private static function should_prefix_default_language(): bool {
        return TranslatePlus_URL_Builder::should_prefix_default_language();
    }

    /**
     * @param string  $permalink Permalink.
     * @param WP_Post $post      Post object.
     */
    public static function filter_post_link(string $permalink, $post, bool $leavename): string {
        if ($leavename || false !== strpos($permalink, '%')) {
            return $permalink;
        }
        if (! $post instanceof WP_Post) {
            return $permalink;
        }

        return self::maybe_prefix_permalink($permalink, $post);
    }

    /**
     * @param string $link    Page permalink.
     * @param int    $post_id Post ID.
     * @param bool   $sample  Whether this is a sample permalink.
     */
    public static function filter_page_link(string $link, int $post_id, $sample): string {
        if ($sample) {
            return $link;
        }
        $post = get_post($post_id);
        if (! $post instanceof WP_Post) {
            return $link;
        }

        return self::maybe_prefix_permalink($link, $post);
    }

    /**
     * @param string  $post_link Post permalink.
     * @param WP_Post $post      Post object.
     * @param bool    $leavename Leave name placeholder.
     * @param bool    $sample    Sample permalink.
     */
    public static function filter_post_type_link(string $post_link, $post, bool $leavename, bool $sample): string {
        if ($sample || $leavename || false !== strpos($post_link, '%')) {
            return $post_link;
        }
        if (! $post instanceof WP_Post) {
            return $post_link;
        }

        return self::maybe_prefix_permalink($post_link, $post);
    }

    /**
     * Prefix path with /{lang}/ using _tp_language (via get_post_language).
     *
     * @param string  $permalink Full URL from WordPress.
     * @param WP_Post $post      Post.
     */
    private static function maybe_prefix_permalink(string $permalink, WP_Post $post): string {
        return TranslatePlus_URL_Builder::build_permalink_for_post($permalink, $post);
    }

    /**
     * Call on plugin activation so rules are stored and flushed.
     */
    public static function activate(): void {
        self::register_rewrite_rules();
        flush_rewrite_rules(false);
    }

    /**
     * Call on deactivation to drop generated rules from the rewrite rules option.
     */
    public static function deactivate(): void {
        flush_rewrite_rules(false);
    }

    public static function register_rewrite_rules(): void {
        $pattern = self::build_language_prefix_rule_pattern();
        if ($pattern === null) {
            return;
        }

        add_rewrite_rule(
            '^((' . self::build_language_codes_pattern() . '))/?$',
            'index.php?' . self::QUERY_VAR_LANG . '=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            $pattern,
            'index.php?' . self::QUERY_VAR_LANG . '=$matches[1]&' . self::QUERY_VAR_PATH . '=$matches[2]',
            'top'
        );
    }

    /**
     * Single rule: only known language codes as the first path segment (avoids stealing /category/post-name).
     *
     * @return string|null PCRE without delimiters, or null if no codes loaded.
     */
    private static function build_language_prefix_rule_pattern(): ?string {
        $codes_pattern = self::build_language_codes_pattern();
        if ($codes_pattern === null) {
            return null;
        }

        return '^((' . $codes_pattern . '))/(.+?)/?$';
    }

    /**
     * Build alternation segment for supported language codes.
     */
    private static function build_language_codes_pattern(): ?string {
        $codes = array_keys(TranslatePlus_Languages::get_code_to_label());
        $parts = array();
        foreach ($codes as $code) {
            if (! is_string($code) || $code === '' || $code === 'auto') {
                continue;
            }
            $parts[] = preg_quote($code, '/');
        }

        if ($parts === array()) {
            return null;
        }

        return implode('|', $parts);
    }

    /**
     * @param list<string> $vars Public query variables.
     * @return list<string>
     */
    public static function register_query_vars(array $vars): array {
        $vars[] = self::QUERY_VAR_LANG;
        $vars[] = self::QUERY_VAR_PATH;

        return $vars;
    }

    /**
     * When tp_lang + tp_post_slug are set, resolve the singular post by slug and _tp_language (not “first post with this slug”).
     *
     * @param WP_Query $query Main query.
     */
    public static function map_query_vars_to_post(WP_Query $query): void {
        if (is_admin() || ! $query->is_main_query()) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return;
        }

        if ($query->is_feed() || $query->is_trackback() || $query->is_embed()) {
            return;
        }

        $lang_raw = $query->get(self::QUERY_VAR_LANG);
        $path     = $query->get(self::QUERY_VAR_PATH);

        if (! is_string($lang_raw) || $lang_raw === '' || ! is_string($path) || $path === '') {
            return;
        }

        $lang = TranslatePlus_Languages::normalize($lang_raw);
        if ($lang === null || $lang === 'auto') {
            $query->set_404();

            return;
        }

        $query->set(self::QUERY_VAR_LANG, $lang);

        $requested_path = trim((string) wp_unslash($path), '/');
        if ($requested_path === '') {
            $query->set_404();

            return;
        }

        $resolved = self::find_published_post_by_path_and_language($requested_path, $lang);
        if (! $resolved instanceof WP_Post) {
            $query->set_404();

            return;
        }

        // Resolve as a normal singular request by ID (avoids name + meta_query quirks on the main query).
        // Pages need page_id; posts/custom post types use p.
        if ($resolved->post_type === 'page') {
            $query->set('page_id', (int) $resolved->ID);
            $query->set('p', 0);
        } else {
            $query->set('p', (int) $resolved->ID);
            $query->set('page_id', 0);
        }
        $query->set('post_type', $resolved->post_type);
        $query->set('name', '');
        $query->set('pagename', '');
        $query->set('meta_query', '');
    }

    /**
     * Find a published translatable post by slug and content language meta.
     */
    private static function find_published_post_by_path_and_language(string $path, string $lang): ?WP_Post {
        $types = TranslatePlus_Settings::get_translatable_post_types();
        if ($types === array()) {
            return null;
        }

        $normalized_path = trim($path, '/');
        if ($normalized_path === '') {
            return null;
        }

        // First try direct path resolution (works for pages and hierarchical types).
        $direct = get_page_by_path($normalized_path, OBJECT, $types);
        if ($direct instanceof WP_Post && $direct->post_status === 'publish') {
            $direct_lang = TranslatePlus_Languages::normalize(TranslatePlus_Translation_Group::get_post_language((int) $direct->ID));
            if ($direct_lang === $lang) {
                return $direct;
            }
        }

        $segments = explode('/', $normalized_path);
        $leaf     = sanitize_title((string) end($segments));
        if ($leaf === '') {
            return null;
        }

        $q = new WP_Query(
            array(
                'post_type'              => $types,
                'name'                   => $leaf,
                'post_status'            => 'publish',
                'posts_per_page'         => 20,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => true,
                'meta_query'             => array(
                    'relation' => 'OR',
                    array(
                        'key'     => TranslatePlus_Translation_Group::META_LANGUAGE,
                        'value'   => $lang,
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_tp_content_locale',
                        'value'   => $lang,
                        'compare' => '=',
                    ),
                ),
            )
        );

        if (! $q->have_posts()) {
            return null;
        }

        foreach ($q->posts as $candidate) {
            if (! $candidate instanceof WP_Post) {
                continue;
            }
            $permalink = get_permalink($candidate);
            if (! is_string($permalink) || $permalink === '') {
                continue;
            }

            $url_path = (string) wp_parse_url($permalink, PHP_URL_PATH);
            $url_path = trim($url_path, '/');
            $home     = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
            $home     = trim($home, '/');

            if ($home !== '' && strpos($url_path . '/', $home . '/') === 0) {
                $url_path = substr($url_path, strlen($home) + 1);
            }

            // Remove leading language segment from permalink path.
            if (preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,4})?\//', $url_path)) {
                $url_path = preg_replace('/^[^\/]+\/?/', '', $url_path) ?? $url_path;
            }

            if (trim($url_path, '/') === $normalized_path) {
                return $candidate;
            }
        }

        // Fallback: incoming path may use a source-language slug. Resolve by group and switch to requested language.
        $any_lang = new WP_Query(
            array(
                'post_type'              => $types,
                'name'                   => $leaf,
                'post_status'            => 'publish',
                'posts_per_page'         => 20,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => true,
            )
        );

        foreach ($any_lang->posts as $source_candidate) {
            if (! $source_candidate instanceof WP_Post) {
                continue;
            }

            $group = get_post_meta((int) $source_candidate->ID, TranslatePlus_Translation_Group::META_GROUP, true);
            if (! is_string($group) || $group === '') {
                continue;
            }

            $translated_id = TranslatePlus_Translation_Group::find_post_in_group_by_language($group, $lang, $source_candidate->post_type);
            if ($translated_id <= 0) {
                continue;
            }

            $translated = get_post($translated_id);
            if ($translated instanceof WP_Post && $translated->post_status === 'publish') {
                return $translated;
            }
        }

        return null;
    }
}
