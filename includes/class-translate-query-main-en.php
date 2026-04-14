<?php
/**
 * Front-end main query: list views show posts for the active language only.
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

        $active_lang = self::detect_active_language($query);
        if ($active_lang === '') {
            return;
        }

        // Rewritten /{lang}/{path} singular resolution is handled by TranslatePlus_Rewrites.
        // Do not inject list-view language meta constraints for these requests.
        if (class_exists('TranslatePlus_Rewrites', false)) {
            $tp_path = $query->get(TranslatePlus_Rewrites::QUERY_VAR_PATH);
            if (is_string($tp_path) && $tp_path !== '') {
                return;
            }
        }

        if (! apply_filters('translateplus_main_query_show_only_en', true, $query, $active_lang)) {
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

        $lang_clause = self::build_language_clause($active_lang);

        $existing = $query->get('meta_query');
        if (! empty($existing) && is_array($existing)) {
            $query->set(
                'meta_query',
                array(
                    'relation' => 'AND',
                    $existing,
                    $lang_clause,
                )
            );
        } else {
            $query->set('meta_query', array($lang_clause));
        }
    }

    /**
     * Detect active language for the current frontend request.
     */
    private static function detect_active_language(WP_Query $query): string {
        $default = TranslatePlus_Languages::normalize(TranslatePlus_API::DEFAULT_SOURCE);
        if (! is_string($default) || $default === '' || $default === 'auto') {
            $default = 'en';
        }

        if (class_exists('TranslatePlus_Rewrites', false)) {
            $tp_lang = $query->get(TranslatePlus_Rewrites::QUERY_VAR_LANG);
            if (is_string($tp_lang) && $tp_lang !== '') {
                $normalized = TranslatePlus_Languages::normalize($tp_lang);
                if (is_string($normalized) && $normalized !== '' && $normalized !== 'auto') {
                    return $normalized;
                }
            }
        }

        return $default;
    }

    /**
     * Build language meta clause for main list queries.
     *
     * For default language, include legacy content without language meta.
     *
     * @return array<string, mixed>
     */
    private static function build_language_clause(string $active_lang): array {
        $default = TranslatePlus_Languages::normalize(TranslatePlus_API::DEFAULT_SOURCE);
        if (! is_string($default) || $default === '' || $default === 'auto') {
            $default = 'en';
        }

        if ($active_lang !== $default) {
            return array(
                'key'     => TranslatePlus_Translation_Group::META_LANGUAGE,
                'value'   => $active_lang,
                'compare' => '=',
            );
        }

        return array(
            'relation' => 'OR',
            array(
                'key'     => TranslatePlus_Translation_Group::META_LANGUAGE,
                'value'   => $default,
                'compare' => '=',
            ),
            array(
                'key'     => TranslatePlus_Translation_Group::META_LANGUAGE,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => TranslatePlus_Translation_Group::META_LANGUAGE,
                'value'   => '',
                'compare' => '=',
            ),
        );
    }
}
