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

    public const QUERY_VAR_SLUG = 'tp_post_slug';

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
        $code = apply_filters('translateplus_permalink_default_language', TranslatePlus_API::DEFAULT_SOURCE);

        return is_string($code) && $code !== ''
            ? ( TranslatePlus_Languages::normalize($code) ?? TranslatePlus_API::DEFAULT_SOURCE )
            : TranslatePlus_API::DEFAULT_SOURCE;
    }

    /**
     * When true, default-language posts use /{default}/slug/ instead of plain /slug/.
     *
     * @return bool
     */
    private static function should_prefix_default_language(): bool {
        return (bool) apply_filters('translateplus_permalink_prefix_default_language', false);
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
        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return $permalink;
        }

        $stored = TranslatePlus_Translation_Group::get_post_language($post->ID);
        $lang     = TranslatePlus_Languages::normalize($stored);
        if ($lang === null || $lang === 'auto') {
            $lang = self::get_default_language_code();
        }

        $default = self::get_default_language_code();
        if ($lang === $default && ! self::should_prefix_default_language()) {
            return $permalink;
        }

        return self::insert_language_after_home_path($permalink, $lang);
    }

    /**
     * Insert /{lang}/ after the home path (works for subdirectory installs).
     *
     * @param string $permalink Full URL.
     * @param string $lang      Normalized language code.
     */
    private static function insert_language_after_home_path(string $permalink, string $lang): string {
        $lang = TranslatePlus_Languages::normalize($lang) ?? $lang;
        if (! is_string($lang) || $lang === '' || $lang === 'auto') {
            return $permalink;
        }

        $parts = wp_parse_url($permalink);
        if (! is_array($parts) || empty($parts['host'])) {
            return $permalink;
        }

        $home      = wp_parse_url(home_url());
        $home_path = isset($home['path']) ? trim((string) $home['path'], '/') : '';

        $link_path = isset($parts['path']) ? (string) $parts['path'] : '/';
        $had_trailing = ( substr($link_path, -1) === '/' );

        $link_trim = trim($link_path, '/');

        if ($home_path !== '' && strpos($link_trim . '/', $home_path . '/') === 0) {
            $rest = substr($link_trim, strlen($home_path) + 1);
        } elseif ($home_path === '') {
            $rest = $link_trim;
        } else {
            return $permalink;
        }

        if ($rest !== '' && preg_match('/^' . preg_quote($lang, '/') . '\//', $rest)) {
            return $permalink;
        }

        $prefix   = $home_path !== '' ? $home_path . '/' : '';
        $new_path = '/' . $prefix . $lang . '/' . $rest;
        $new_path = str_replace('//', '/', $new_path);

        if ($had_trailing) {
            $new_path = trailingslashit($new_path);
        } else {
            $new_path = untrailingslashit($new_path);
        }

        $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host     = $parts['host'];
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . $host . $port . $new_path . $query . $fragment;
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
            $pattern,
            'index.php?' . self::QUERY_VAR_LANG . '=$matches[1]&' . self::QUERY_VAR_SLUG . '=$matches[2]',
            'top'
        );
    }

    /**
     * Single rule: only known language codes as the first path segment (avoids stealing /category/post-name).
     *
     * @return string|null PCRE without delimiters, or null if no codes loaded.
     */
    private static function build_language_prefix_rule_pattern(): ?string {
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

        return '^((' . implode('|', $parts) . '))/([^/]+)/?$';
    }

    /**
     * @param list<string> $vars Public query variables.
     * @return list<string>
     */
    public static function register_query_vars(array $vars): array {
        $vars[] = self::QUERY_VAR_LANG;
        $vars[] = self::QUERY_VAR_SLUG;

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
        $slug     = $query->get(self::QUERY_VAR_SLUG);

        if (! is_string($lang_raw) || $lang_raw === '' || ! is_string($slug) || $slug === '') {
            return;
        }

        $lang = TranslatePlus_Languages::normalize($lang_raw);
        if ($lang === null || $lang === 'auto') {
            $query->set_404();

            return;
        }

        $query->set(self::QUERY_VAR_LANG, $lang);

        $slug_sanitized = sanitize_title($slug);
        if ($slug_sanitized === '') {
            $query->set_404();

            return;
        }

        $resolved = self::find_published_post_by_slug_and_language($slug_sanitized, $lang);
        if (! $resolved instanceof WP_Post) {
            $query->set_404();

            return;
        }

        // Resolve as a normal singular request by ID (avoids name + meta_query quirks on the main query).
        $query->set('p', (int) $resolved->ID);
        $query->set('post_type', $resolved->post_type);
        $query->set('name', '');
        $query->set('pagename', '');
        $query->set('meta_query', '');
    }

    /**
     * Find a published translatable post by slug and content language meta.
     */
    private static function find_published_post_by_slug_and_language(string $slug, string $lang): ?WP_Post {
        $types = TranslatePlus_Settings::get_translatable_post_types();
        if ($types === array()) {
            return null;
        }

        $q = new WP_Query(
            array(
                'post_type'              => $types,
                'name'                   => $slug,
                'post_status'            => 'publish',
                'posts_per_page'         => 1,
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

        if (! $q->have_posts() || ! $q->posts[0] instanceof WP_Post) {
            return null;
        }

        return $q->posts[0];
    }
}
