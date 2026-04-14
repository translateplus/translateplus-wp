<?php
/**
 * SEO helpers (hreflang links).
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

final class TranslatePlus_SEO {

    public static function init(): void {
        add_action('wp_head', array(self::class, 'print_hreflang_links'), 5);
    }

    public static function print_hreflang_links(): void {
        if (is_admin() || ! is_singular()) {
            return;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return;
        }
        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        $items = TranslatePlus_Translation_Group::get_frontend_switcher_items($post);
        if (count($items) < 2) {
            return;
        }

        $default_url = '';
        foreach ($items as $item) {
            if (! empty($item['missing']) || empty($item['url']) || ! is_string($item['url'])) {
                continue;
            }
            $lang = TranslatePlus_Languages::normalize((string) $item['code']);
            if ($lang === TranslatePlus_API::DEFAULT_SOURCE && $default_url === '') {
                $default_url = $item['url'];
            }
            echo '<link rel="alternate" hreflang="' . esc_attr((string) $item['code']) . '" href="' . esc_url($item['url']) . "\" />\n";
        }

        if ($default_url !== '') {
            echo '<link rel="alternate" hreflang="x-default" href="' . esc_url($default_url) . "\" />\n";
        }
    }
}
