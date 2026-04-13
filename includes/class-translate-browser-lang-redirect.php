<?php
/**
 * Redirect singular views to a matching translation when browser language prefers it.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Uses Accept-Language, {@see TranslatePlus_Settings::is_auto_language_redirect_enabled()}, and translation group links.
 */
final class TranslatePlus_Browser_Lang_Redirect {

    /**
     * Query arg to skip redirect for this request (e.g. debugging).
     */
    private const QUERY_BYPASS = 'tp_no_lang_redirect';

    public static function init(): void {
        add_action('template_redirect', array(self::class, 'maybe_redirect'), 5);
    }

    public static function maybe_redirect(): void {
        if (! TranslatePlus_Settings::is_auto_language_redirect_enabled()) {
            return;
        }

        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return;
        }

        if (function_exists('is_customize_preview') && is_customize_preview()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bypass flag.
        if (isset($_GET[ self::QUERY_BYPASS ]) && (string) wp_unslash($_GET[ self::QUERY_BYPASS ]) === '1') {
            return;
        }

        if (! is_singular()) {
            return;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post || ! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        if (! apply_filters('translateplus_should_browser_lang_redirect', true, $post)) {
            return;
        }

        $prefs = self::preferred_browser_codes();
        if ($prefs === array()) {
            return;
        }

        $items = TranslatePlus_Translation_Group::get_frontend_switcher_items($post);
        if (count($items) < 2) {
            return;
        }

        $by_code = array();
        foreach ($items as $item) {
            $by_code[ $item['code'] ] = $item;
        }

        foreach ($prefs as $code) {
            if (! isset($by_code[ $code ])) {
                continue;
            }
            $item = $by_code[ $code ];
            if (! empty($item['current'])) {
                return;
            }

            if (! empty($item['missing'])) {
                continue;
            }

            $url = $item['url'];
            if (! is_string($url) || $url === '') {
                continue;
            }

            if (self::current_request_url_matches($url)) {
                return;
            }

            $url = apply_filters('translateplus_browser_redirect_url', $url, $post, $code);
            if (! is_string($url) || $url === '') {
                return;
            }

            wp_safe_redirect($url, 302);
            exit;
        }
    }

    /**
     * @return list<string> Preferred normalized codes (highest q-value first, unique).
     */
    private static function preferred_browser_codes(): array {
        if (! isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) || ! is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return array();
        }

        $raw = sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']));
        if ($raw === '') {
            return array();
        }

        $segments = preg_split('/\s*,\s*/', $raw);
        if (! is_array($segments)) {
            return array();
        }

        $scored = array();
        foreach ($segments as $seg) {
            $seg = trim((string) $seg);
            if ($seg === '') {
                continue;
            }

            $q   = 1.0;
            $tag = $seg;
            if (preg_match('/^(.*?)\s*;\s*q\s*=\s*([\d.]+)\s*$/i', $seg, $m)) {
                $tag = trim($m[1]);
                $q   = (float) $m[2];
                if ($q > 1.0) {
                    $q = 1.0;
                }
                if ($q < 0.0) {
                    $q = 0.0;
                }
            }

            $code = self::map_accept_tag_to_code($tag);
            if ($code === null) {
                continue;
            }

            $scored[] = array(
                'code' => $code,
                'q'    => $q,
            );
        }

        if ($scored === array()) {
            return array();
        }

        usort(
            $scored,
            static function (array $a, array $b): int {
                if ($a['q'] === $b['q']) {
                    return 0;
                }

                return ($a['q'] < $b['q']) ? 1 : -1;
            }
        );

        $out  = array();
        $seen = array();
        foreach ($scored as $row) {
            $c = $row['code'];
            if (isset($seen[ $c ])) {
                continue;
            }
            $seen[ $c ] = true;
            $out[]      = $c;
        }

        return $out;
    }

    private static function map_accept_tag_to_code(string $tag): ?string {
        $tag = trim(str_replace('_', '-', $tag));
        if ($tag === '') {
            return null;
        }

        $n = TranslatePlus_Languages::normalize($tag);
        if ($n !== null && $n !== 'auto') {
            return $n;
        }

        if (preg_match('/^([a-z]{2,3})(?:-|$)/i', $tag, $m)) {
            $primary = strtolower($m[1]);
            $n2      = TranslatePlus_Languages::normalize($primary);
            if ($n2 !== null && $n2 !== 'auto') {
                return $n2;
            }
        }

        return null;
    }

    /**
     * Avoid redirecting to the same URL (loop guard if permalinks or filters align oddly).
     */
    private static function current_request_url_matches(string $target_url): bool {
        $target = wp_parse_url($target_url);
        $here   = wp_parse_url(self::get_current_request_url());
        if (! is_array($target) || ! is_array($here)) {
            return false;
        }

        $t_path = isset($target['path']) ? untrailingslashit($target['path']) : '';
        $h_path = isset($here['path']) ? untrailingslashit($here['path']) : '';
        $t_host = isset($target['host']) ? strtolower($target['host']) : '';
        $h_host = isset($here['host']) ? strtolower($here['host']) : '';

        if ($t_host !== '' && $h_host !== '' && $t_host !== $h_host) {
            return false;
        }

        return $t_path === $h_path;
    }

    private static function get_current_request_url(): string {
        if (! isset($_SERVER['HTTP_HOST']) || ! is_string($_SERVER['HTTP_HOST'])) {
            return '';
        }

        $scheme = is_ssl() ? 'https' : 'http';
        $host   = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
        $uri    = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';

        return $scheme . '://' . $host . $uri;
    }
}
