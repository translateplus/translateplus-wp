<?php
/**
 * Admin post/page list: one row per translation group + Languages column with edit links per locale.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers list table column and optional SQL deduplication for linked translations.
 */
final class TranslatePlus_Admin_List {

    public const COLUMN_ID = 'translateplus_lang_map';

    public static function init(): void {
        add_action('admin_init', array(self::class, 'register_column_hooks'));
        add_filter('posts_where', array(self::class, 'filter_posts_where_dedupe_translation_rows'), 10, 2);
        add_action('admin_enqueue_scripts', array(self::class, 'enqueue_list_styles'));
    }

    public static function register_column_hooks(): void {
        foreach (TranslatePlus_Settings::get_translatable_post_types() as $post_type) {
            if (! is_string($post_type) || $post_type === '') {
                continue;
            }
            add_filter("manage_{$post_type}_posts_columns", array(self::class, 'filter_add_languages_column'));
            add_action("manage_{$post_type}_posts_custom_column", array(self::class, 'render_languages_column'), 10, 2);
        }
    }

    /**
     * Insert “Languages” column after the title.
     *
     * @param string[] $columns Column key => label.
     * @return string[]
     */
    public static function filter_add_languages_column(array $columns): array {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[ $key ] = $label;
            if ($key === 'title') {
                $new[ self::COLUMN_ID ] = __('Languages', 'translateplus');
            }
        }
        if (! isset($new[ self::COLUMN_ID ])) {
            $new[ self::COLUMN_ID ] = __('Languages', 'translateplus');
        }

        return $new;
    }

    /**
     * @param string $column Column key.
     * @param int    $post_id Post ID.
     */
    public static function render_languages_column($column, $post_id): void {
        if ($column !== self::COLUMN_ID) {
            return;
        }

        $post = get_post((int) $post_id);
        if (! $post instanceof WP_Post) {
            echo '—';

            return;
        }

        $members = TranslatePlus_Translation_Group::get_linked_members($post);
        $by_lang = array();
        foreach ($members as $row) {
            if (! isset($row['language'], $row['post_id'])) {
                continue;
            }
            $lang = $row['language'];
            $by_lang[ $lang ] = (int) $row['post_id'];
        }

        $choices = TranslatePlus_Translation_Group::locale_choices();
        if ($choices === array()) {
            echo '—';

            return;
        }

        echo '<div class="translateplus-admin-lang-map" role="group" aria-label="' . esc_attr__('Translations in this group', 'translateplus') . '">';
        foreach ($choices as $code => $label) {
            $pid = isset($by_lang[ $code ]) ? $by_lang[ $code ] : 0;
            $code_u = strtoupper($code);
            if ($pid > 0 && current_user_can('edit_post', $pid)) {
                $link = get_edit_post_link($pid, 'raw');
                if (! is_string($link) || $link === '') {
                    echo '<span class="translateplus-admin-lang-map__cell translateplus-admin-lang-map__cell--missing" title="' . esc_attr($label) . '">' . esc_html($code_u) . '</span>';
                    continue;
                }
                $is_current = (int) $post_id === $pid;
                $class = 'translateplus-admin-lang-map__link' . ($is_current ? ' is-current' : '');
                printf(
                    '<a class="%s" href="%s" title="%s">%s</a>',
                    esc_attr($class),
                    esc_url($link),
                    esc_attr(sprintf(
                        /* translators: 1: language label, 2: language code */
                        __('Edit %1$s (%2$s)', 'translateplus'),
                        $label,
                        $code_u
                    )),
                    esc_html($code_u)
                );
            } else {
                printf(
                    '<span class="translateplus-admin-lang-map__cell translateplus-admin-lang-map__cell--missing" title="%s">%s</span>',
                    esc_attr(sprintf(
                        /* translators: %s: language label */
                        __('Not created: %s', 'translateplus'),
                        $label
                    )),
                    esc_html($code_u)
                );
            }
        }
        echo '</div>';
    }

    /**
     * Hide extra rows for posts that share a translation group so the list shows one row per group (lowest ID kept).
     *
     * @param string    $where The WHERE clause.
     * @param WP_Query  $query Query object.
     */
    public static function filter_posts_where_dedupe_translation_rows($where, $query): string {
        if (! is_string($where) || $where === '') {
            return $where;
        }
        if (! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query()) {
            return $where;
        }

        if (! apply_filters('translateplus_admin_list_dedupe_translation_rows', true)) {
            return $where;
        }

        if (! function_exists('get_current_screen')) {
            return $where;
        }

        $screen = get_current_screen();
        if (! $screen || $screen->base !== 'edit') {
            return $where;
        }

        $pt = $query->get('post_type');
        if (is_array($pt)) {
            if (count($pt) !== 1) {
                return $where;
            }
            $post_type = (string) $pt[0];
        } else {
            $post_type = (string) $pt;
        }

        if ($post_type === '') {
            $post_type = 'post';
        }

        if (! in_array($post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return $where;
        }

        global $wpdb;
        $meta_key = TranslatePlus_Translation_Group::META_GROUP;

        $where .= $wpdb->prepare(
            " AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_self
                INNER JOIN {$wpdb->postmeta} pm_lower
                    ON pm_lower.meta_key = %s
                    AND pm_lower.meta_value = pm_self.meta_value
                    AND pm_lower.post_id < {$wpdb->posts}.ID
                INNER JOIN {$wpdb->posts} p_lower ON p_lower.ID = pm_lower.post_id AND p_lower.post_type = {$wpdb->posts}.post_type
                WHERE pm_self.post_id = {$wpdb->posts}.ID
                AND pm_self.meta_key = %s
                AND pm_self.meta_value != ''
            )",
            $meta_key,
            $meta_key
        );

        return $where;
    }

    /**
     * Styles for the language map cells in the list table.
     */
    public static function enqueue_list_styles(string $hook): void {
        if ($hook !== 'edit.php') {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || $screen->base !== 'edit') {
            return;
        }

        if (! in_array($screen->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        wp_enqueue_style(
            'translateplus-admin-list',
            plugins_url('assets/css/admin-list-table.css', TRANSLATEPLUS_FILE),
            array(),
            TRANSLATEPLUS_VERSION
        );
    }
}
