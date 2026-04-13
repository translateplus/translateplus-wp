<?php
/**
 * Front-end main query: list views only show posts with _tp_language = en.
 *
 * Singular requests are unchanged so direct URLs to translated posts still resolve.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @package TranslatePlus
 */
final class TranslatePlus_Query_Main_En {

    /**
     * Canonical English code stored in post meta (matches TranslatePlus default source).
     */
    private const EN_CODE = TranslatePlus_API::DEFAULT_SOURCE;

    public static function init(): void {
        add_action('pre_get_posts', array(self::class, 'pre_get_posts'), 10);
    }

    /**
     * @param WP_Query $query WordPress query.
     */
    public static function pre_get_posts(WP_Query $query): void {
        if (is_admin()) {
            return;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        if (! $query->is_main_query()) {
            return;
        }

        // Language-prefixed permalinks ({lang}/{slug}) set tp_lang; do not merge English-only meta or requests 404.
        if (class_exists('TranslatePlus_Rewrites', false)) {
            $tp_lang = $query->get(TranslatePlus_Rewrites::QUERY_VAR_LANG);
            $tp_slug = $query->get(TranslatePlus_Rewrites::QUERY_VAR_SLUG);
            if ((is_string($tp_lang) && $tp_lang !== '') || (is_string($tp_slug) && $tp_slug !== '')) {
                return;
            }
        }

        if (! apply_filters('translateplus_main_query_show_only_en', true, $query)) {
            return;
        }

        if ($query->is_singular() || $query->is_attachment()) {
            return;
        }

        $translatable = TranslatePlus_Settings::get_translatable_post_types();
        if ($translatable === array()) {
            return;
        }

        $post_type = $query->get('post_type');
        if ($post_type === 'any') {
            return;
        }

        if ($post_type === '' || $post_type === false || $post_type === null) {
            $post_type = 'post';
        }

        if (is_array($post_type)) {
            foreach ($post_type as $slug) {
                if (! is_string($slug) || ! in_array($slug, $translatable, true)) {
                    return;
                }
            }
        } elseif (is_string($post_type)) {
            if (! in_array($post_type, $translatable, true)) {
                return;
            }
        } else {
            return;
        }

        $en_clause = array(
            'key'     => TranslatePlus_Translation_Group::META_LANGUAGE,
            'value'   => self::EN_CODE,
            'compare' => '=',
        );

        $existing = $query->get('meta_query');
        if (! empty($existing) && is_array($existing)) {
            $query->set(
                'meta_query',
                array(
                    'relation' => 'AND',
                    $existing,
                    $en_clause,
                )
            );
        } else {
            $query->set('meta_query', array($en_clause));
        }
    }
}
