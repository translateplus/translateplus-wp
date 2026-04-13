<?php
/**
 * When translation mode is automatic, ensure drafts exist for each configured locale and push content
 * from the saved post to other posts in the same group. Runs in the background via WP-Cron by default.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * save_post handler: queue or run sync; cron processes API translation and sibling updates.
 */
final class TranslatePlus_Auto_Sync {

    private const META_FINGERPRINT = '_translateplus_sync_fingerprint';

    /**
     * Post meta: Unix time when a background sync was queued (cleared when the job runs).
     */
    private const META_SYNC_QUEUED = '_translateplus_sync_queued';

    /**
     * Post meta: user ID to use for capability checks in the cron job (last editor).
     */
    private const META_SYNC_TRIGGER_USER = '_translateplus_sync_trigger_user';

    /**
     * WP-Cron single-event hook (one scheduled event per post ID).
     */
    private const CRON_HOOK = 'translateplus_auto_sync_post';

    /**
     * @var bool
     */
    private static $syncing = false;

    public static function init(): void {
        add_action('save_post', array(self::class, 'on_save_post'), 25, 2);
        add_action(self::CRON_HOOK, array(self::class, 'process_scheduled_sync'), 10, 1);
    }

    /**
     * Remove pending auto-sync cron events when the plugin is deactivated.
     */
    public static function on_deactivate(): void {
        if (function_exists('wp_unschedule_hook')) {
            wp_unschedule_hook(self::CRON_HOOK);
        }
    }

    /**
     * True while automatic sync runs (nested save_post handlers should not recurse or assign a new group).
     */
    public static function is_syncing(): bool {
        return self::$syncing;
    }

    /**
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public static function on_save_post(int $post_id, WP_Post $post): void {
        if (self::$syncing) {
            return;
        }

        if (! TranslatePlus_Settings::is_auto_sync_on_save()) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        if (! in_array($post->post_status, array('publish', 'draft', 'pending', 'future', 'private'), true)) {
            return;
        }

        $api_key = get_option(TranslatePlus_API::OPTION_API_KEY, '');
        if (! is_string($api_key) || $api_key === '') {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        if (apply_filters('translateplus_auto_sync_background', true, $post_id, $post)) {
            TranslatePlus_Translation_Group::ensure_group_for_post($post_id);
            self::schedule_sync($post_id, $post);
            return;
        }

        self::$syncing = true;
        try {
            self::run_sync_for_post($post);
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Debounced WP-Cron job: same post re-saved soon only runs one sync (latest content).
     *
     * @param int $post_id Post ID.
     */
    public static function process_scheduled_sync(int $post_id): void {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return;
        }

        delete_post_meta($post_id, self::META_SYNC_QUEUED);

        if (! TranslatePlus_Settings::is_auto_sync_on_save()) {
            delete_post_meta($post_id, self::META_SYNC_TRIGGER_USER);
            return;
        }

        $api_key = get_option(TranslatePlus_API::OPTION_API_KEY, '');
        if (! is_string($api_key) || $api_key === '') {
            delete_post_meta($post_id, self::META_SYNC_TRIGGER_USER);
            return;
        }

        $post = get_post($post_id);
        if (! $post instanceof WP_Post) {
            delete_post_meta($post_id, self::META_SYNC_TRIGGER_USER);
            return;
        }

        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            delete_post_meta($post_id, self::META_SYNC_TRIGGER_USER);
            return;
        }

        $uid = (int) get_post_meta($post_id, self::META_SYNC_TRIGGER_USER, true);
        if ($uid <= 0) {
            $uid = (int) $post->post_author;
        }
        $uid = (int) apply_filters('translateplus_auto_sync_cron_user_id', $uid, $post_id, $post);
        if ($uid > 0) {
            wp_set_current_user($uid);
        }

        delete_post_meta($post_id, self::META_SYNC_TRIGGER_USER);

        if (! current_user_can('edit_post', $post_id)) {
            wp_set_current_user(0);
            return;
        }

        self::$syncing = true;
        try {
            self::run_sync_for_post($post);
        } finally {
            self::$syncing = false;
            wp_set_current_user(0);
        }
    }

    /**
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    private static function schedule_sync(int $post_id, WP_Post $post): void {
        $delay = (int) apply_filters('translateplus_auto_sync_background_delay', 20, $post_id, $post);
        $delay = max(5, min(300, $delay));

        update_post_meta($post_id, self::META_SYNC_TRIGGER_USER, get_current_user_id());
        update_post_meta($post_id, self::META_SYNC_QUEUED, time());

        $timestamp = wp_next_scheduled(self::CRON_HOOK, array($post_id));
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK, array($post_id));
        }

        wp_schedule_single_event(time() + $delay, self::CRON_HOOK, array($post_id));

        if (function_exists('spawn_cron')) {
            spawn_cron();
        }
    }

    /**
     * Create missing linked drafts (same translation group / copied parent as source) and translate into siblings.
     */
    private static function run_sync_for_post(WP_Post $post): void {
        $post_id = (int) $post->ID;

        $group = TranslatePlus_Translation_Group::ensure_group_for_post($post_id);

        $created_missing = false;
        if (apply_filters('translateplus_auto_sync_create_missing', true, $post, $group)) {
            $created_missing = self::ensure_missing_translation_posts($post, $group);
        }

        $fingerprint = self::hash_content_parts($post->post_title, $post->post_excerpt, $post->post_content);
        $stored      = get_post_meta($post_id, self::META_FINGERPRINT, true);
        $unchanged   = is_string($stored) && $stored !== '' && hash_equals($stored, $fingerprint);

        if ($unchanged && ! $created_missing) {
            return;
        }

        // Fresh post object after possible draft inserts.
        $post = get_post($post_id);
        if (! $post instanceof WP_Post) {
            return;
        }

        $siblings = self::query_sibling_posts($group, $post->post_type, $post_id);
        if ($siblings === array()) {
            update_post_meta($post_id, self::META_FINGERPRINT, $fingerprint);

            return;
        }

        $src_stored = TranslatePlus_Translation_Group::get_post_language($post_id);
        $src_norm   = TranslatePlus_Languages::normalize($src_stored);
        $api_source = self::api_source_lang($src_stored);

        foreach ($siblings as $sibling) {
            if (! $sibling instanceof WP_Post) {
                continue;
            }
            if (! current_user_can('edit_post', $sibling->ID)) {
                continue;
            }

            $tgt_stored = TranslatePlus_Translation_Group::get_post_language($sibling->ID);
            $tgt_norm   = TranslatePlus_Languages::normalize($tgt_stored);
            if ($tgt_norm === null || $tgt_norm === 'auto' || ! TranslatePlus_Languages::is_valid_target($tgt_norm)) {
                continue;
            }
            if ($src_norm !== null && $tgt_norm === $src_norm) {
                continue;
            }

            $html = TranslatePlus_API::translate_html($post->post_content, $tgt_norm, $api_source);
            if (is_wp_error($html)) {
                continue;
            }

            $title = self::translate_plain($post->post_title, $tgt_norm, $api_source);
            if (is_wp_error($title)) {
                continue;
            }

            $excerpt = self::translate_plain($post->post_excerpt, $tgt_norm, $api_source);
            if (is_wp_error($excerpt)) {
                continue;
            }

            wp_update_post(
                array(
                    'ID'           => $sibling->ID,
                    'post_title'   => $title,
                    'post_excerpt' => $excerpt,
                    'post_content' => $html,
                )
            );
            update_post_meta($sibling->ID, self::META_FINGERPRINT, self::hash_content_parts($title, $excerpt, $html));
        }

        update_post_meta($post_id, self::META_FINGERPRINT, $fingerprint);
    }

    /**
     * Create draft posts for each site language (Settings → TranslatePlus) missing from this group.
     * New posts share the source post’s hierarchical parent (`post_parent`) and translation group meta.
     *
     * @return bool Whether any new post was created.
     */
    private static function ensure_missing_translation_posts(WP_Post $post, string $group): bool {
        $pto = get_post_type_object($post->post_type);
        if (! $pto || ! current_user_can($pto->cap->create_posts)) {
            return false;
        }

        $choices = TranslatePlus_Translation_Group::locale_choices();
        if ($choices === array()) {
            return false;
        }

        $post_id  = (int) $post->ID;
        $src_raw  = TranslatePlus_Translation_Group::get_post_language($post_id);
        $src_norm = TranslatePlus_Languages::normalize($src_raw);
        if ($src_norm === null || $src_norm === 'auto') {
            $src_norm = TranslatePlus_API::DEFAULT_SOURCE;
        }

        $created = false;

        foreach (array_keys($choices) as $lang_code) {
            $lang_norm = TranslatePlus_Languages::normalize($lang_code);
            if ($lang_norm === null || $lang_norm === 'auto' || ! TranslatePlus_Languages::is_valid_target($lang_norm)) {
                continue;
            }

            if ($lang_norm === $src_norm) {
                continue;
            }

            if (TranslatePlus_Translation_Group::find_post_in_group_by_language($group, $lang_norm, $post->post_type) > 0) {
                continue;
            }

            $title = is_string($post->post_title) ? $post->post_title : '';

            $new_id = wp_insert_post(
                array(
                    'post_type'    => $post->post_type,
                    'post_status'  => 'draft',
                    'post_title'   => $title,
                    'post_name'    => '',
                    'post_content' => '',
                    'post_excerpt' => '',
                    'post_author'  => (int) get_current_user_id() > 0 ? (int) get_current_user_id() : (int) $post->post_author,
                    'post_parent'  => (int) $post->post_parent,
                    'menu_order'   => (int) $post->menu_order,
                    'meta_input'   => array(
                        TranslatePlus_Translation_Group::META_GROUP    => $group,
                        TranslatePlus_Translation_Group::META_LANGUAGE => $lang_norm,
                    ),
                ),
                true
            );

            if (is_wp_error($new_id) || ! $new_id) {
                continue;
            }

            $new_id = (int) $new_id;
            delete_post_meta($new_id, '_tp_content_locale');

            TranslatePlus_Translate_Now_Ajax::copy_assets_for_new_translation($post_id, $new_id, $post->post_type);

            update_post_meta($new_id, TranslatePlus_Translation_Group::META_GROUP, $group);
            update_post_meta($new_id, TranslatePlus_Translation_Group::META_LANGUAGE, $lang_norm);

            $created = true;
        }

        return $created;
    }

    private static function hash_content_parts(string $title, string $excerpt, string $content): string {
        $payload = wp_json_encode(
            array(
                't' => $title,
                'e' => $excerpt,
                'c' => $content,
            ),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return hash('sha256', is_string($payload) ? $payload : '');
    }

    /**
     * @return list<WP_Post>
     */
    private static function query_sibling_posts(string $group, string $post_type, int $exclude_id): array {
        $query = new WP_Query(
            array(
                'post_type'              => $post_type,
                'post_status'            => array('publish', 'draft', 'pending', 'future', 'private'),
                'posts_per_page'         => -1,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query'             => array(
                    array(
                        'key'   => TranslatePlus_Translation_Group::META_GROUP,
                        'value' => $group,
                    ),
                ),
            )
        );

        $out = array();
        foreach ($query->posts as $p) {
            if (! $p instanceof WP_Post) {
                continue;
            }
            if ((int) $p->ID === $exclude_id) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }

    private static function api_source_lang(string $stored): string {
        $n = TranslatePlus_Languages::normalize($stored);
        if ($n !== null && $n !== 'auto' && TranslatePlus_Languages::is_valid_source($n)) {
            return $n;
        }

        return TranslatePlus_API::DEFAULT_SOURCE;
    }

    /**
     * @return string|WP_Error
     */
    private static function translate_plain(string $text, string $target_lang, string $source_lang) {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $out = TranslatePlus_API::translate_text($text, $target_lang, $source_lang);
        if (is_wp_error($out)) {
            return $out;
        }

        $out = trim((string) $out);

        return $out !== '' ? $out : $text;
    }
}
