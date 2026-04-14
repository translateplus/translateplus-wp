<?php
/**
 * Runtime string translation for menus/widgets.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

final class TranslatePlus_String_Translation {

    /**
     * Cache lifetime for translated UI strings.
     */
    private const STRING_CACHE_TTL = 12 * HOUR_IN_SECONDS;

    public static function init(): void {
        add_filter('nav_menu_item_title', array(self::class, 'translate_menu_item_title'), 20, 4);
        add_filter('widget_title', array(self::class, 'translate_widget_text'), 20, 3);
        add_filter('widget_text', array(self::class, 'translate_widget_text'), 20, 3);
        add_filter('widget_text_content', array(self::class, 'translate_widget_text'), 20, 3);
        add_filter('widget_block_content', array(self::class, 'translate_widget_block_content'), 20, 2);
    }

    /**
     * @param mixed $title
     * @return mixed
     */
    public static function translate_menu_item_title($title, $menu_item = null, $args = null, $depth = 0) {
        if (! is_string($title) || $title === '') {
            return $title;
        }
        if (is_admin()) {
            return $title;
        }

        return self::translate_string_if_needed($title);
    }

    /**
     * @param mixed $text
     * @return mixed
     */
    public static function translate_widget_text($text) {
        if (! is_string($text) || $text === '') {
            return $text;
        }
        if (is_admin()) {
            return $text;
        }

        return self::translate_string_if_needed($text);
    }

    /**
     * @param mixed $content
     * @return mixed
     */
    public static function translate_widget_block_content($content, $instance = null) {
        if (! is_string($content) || $content === '') {
            return $content;
        }
        if (is_admin()) {
            return $content;
        }

        return self::translate_string_if_needed($content);
    }

    private static function translate_string_if_needed(string $text): string {
        $target = self::detect_request_language();
        $source = TranslatePlus_API::DEFAULT_SOURCE;

        if ($target === '' || $target === $source) {
            return $text;
        }

        $cached = self::get_cached_translation($text, $target);
        if ($cached !== null) {
            return $cached;
        }

        $translated = TranslatePlus_API::translate_text($text, $target, $source);
        if (is_wp_error($translated)) {
            return $text;
        }

        $translated = trim((string) $translated);
        if ($translated === '') {
            return $text;
        }

        self::cache_translation($text, $target, $translated);

        return $translated;
    }

    private static function detect_request_language(): string {
        $lang = '';
        if (class_exists('TranslatePlus_Rewrites', false)) {
            $q = get_query_var(TranslatePlus_Rewrites::QUERY_VAR_LANG, '');
            if (is_string($q) && $q !== '') {
                $lang = $q;
            }
        }

        if ($lang === '') {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request path parsing.
            $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
            if ($uri !== '') {
                $path = (string) wp_parse_url($uri, PHP_URL_PATH);
                $parts = array_values(array_filter(explode('/', trim($path, '/')), static function ($v) {
                    return is_string($v) && $v !== '';
                }));
                if (! empty($parts[0])) {
                    $lang = (string) $parts[0];
                }
            }
        }

        $normalized = TranslatePlus_Languages::normalize($lang);
        if ($normalized === null || $normalized === 'auto') {
            return TranslatePlus_API::DEFAULT_SOURCE;
        }

        return $normalized;
    }

    private static function cache_key(string $text, string $target): string {
        return 'tp_str_' . md5($target . '|' . $text);
    }

    private static function get_cached_translation(string $text, string $target): ?string {
        $cached = get_transient(self::cache_key($text, $target));
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return null;
    }

    private static function cache_translation(string $text, string $target, string $translated): void {
        set_transient(self::cache_key($text, $target), $translated, self::STRING_CACHE_TTL);
    }
}
