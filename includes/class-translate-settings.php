<?php
/**
 * Settings: API key for TranslatePlus.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers Settings → TranslatePlus.
 */
final class TranslatePlus_Settings {

    public const MENU_SLUG = 'translateplus';

    public const OPTION_GROUP = 'translateplus_settings_group';

    /**
     * Selected target language codes (array of strings).
     */
    public const OPTION_TARGET_LANGUAGES = 'translateplus_target_languages';

    /**
     * Post types that use TranslatePlus (meta box, editor, Translate Now, front-end switcher).
     */
    public const OPTION_POST_TYPES = 'translateplus_post_types';

    /**
     * How linked posts are updated: manual (editor actions only) or automatic (on save).
     */
    public const OPTION_TRANSLATION_MODE = 'translateplus_translation_mode';

    /**
     * When "0", hide Translate Now, editor language switcher/tabs, and footer translation UI.
     */
    public const OPTION_EDITOR_MANUAL_UI = 'translateplus_editor_manual_ui';

    /**
     * When "1", visitors may be redirected to a matching translation based on browser language (front-end behavior uses this flag).
     */
    public const OPTION_AUTO_LANGUAGE_REDIRECT = 'translateplus_auto_language_redirect';

    /**
     * When "1", newly created translations are published immediately; when "0", they are created as drafts.
     */
    public const OPTION_AUTO_PUBLISH_TRANSLATIONS = 'translateplus_auto_publish_translations';

    /**
     * When "1", translated posts/pages get translated slugs automatically.
     */
    public const OPTION_AUTO_TRANSLATE_SLUGS = 'translateplus_auto_translate_slugs';

    /**
     * Front-end / menu language switcher layout: "dropdown" (toggle + list) or "inline" (horizontal links).
     */
    public const OPTION_FRONTEND_SWITCHER_DISPLAY = 'translateplus_frontend_switcher_display';

    /**
     * When "1", show flag emoji next to language labels in the switcher (dropdown and inline).
     */
    public const OPTION_FRONTEND_SWITCHER_FLAGS = 'translateplus_frontend_switcher_flags';

    /**
     * URL mode: whether default language should have a directory prefix.
     */
    public const OPTION_URL_MODE = 'translateplus_url_mode';

    /**
     * Marketing / billing site (upgrade CTA).
     */
    private const MARKETING_SITE_URL = 'https://translateplus.io/';

    /**
     * Hidden POST field: target language picker is present (compact translateplus_target_languages[] checkboxes).
     */
    private const FIELD_TARGET_LANG_PICKER_SEEN = 'translateplus_lang_picker_seen';

    /**
     * AJAX: load account summary + connection check (async on settings screen).
     */
    private const AJAX_SETTINGS_SUMMARY = 'translateplus_settings_account_summary';

    /**
     * Nonce action for {@see self::AJAX_SETTINGS_SUMMARY}.
     */
    private const NONCE_SETTINGS_SUMMARY = 'translateplus_settings_summary';

    /**
     * AJAX: save Settings → TranslatePlus without full page load ({@see self::ajax_save_settings()}).
     */
    private const AJAX_SAVE_SETTINGS = 'translateplus_save_settings';

    public static function init(): void {
        add_action('admin_init', array(self::class, 'register'));
        add_action('admin_init', array(self::class, 'maybe_add_disconnect_notice'));
        add_action('admin_menu', array(self::class, 'add_menu'));
        add_action('admin_enqueue_scripts', array(self::class, 'enqueue_admin_assets'));
        add_action('admin_post_translateplus_disconnect', array(self::class, 'handle_disconnect'));
        add_action('wp_ajax_' . self::AJAX_SETTINGS_SUMMARY, array(self::class, 'ajax_settings_account_summary'));
        add_action('wp_ajax_' . self::AJAX_SAVE_SETTINGS, array(self::class, 'ajax_save_settings'));
        add_action('admin_notices', array(self::class, 'render_dashboard_credits_notice'), 1);
        $clear_summary = array(TranslatePlus_API::class, 'clear_account_summary_cache');
        add_action('update_option_' . TranslatePlus_API::OPTION_API_KEY, $clear_summary);
        add_action('add_option_' . TranslatePlus_API::OPTION_API_KEY, $clear_summary);
    }

    /**
     * After disconnect redirect, show a one-time success message on the settings screen.
     */
    public static function maybe_add_disconnect_notice(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only query flag after verified redirect.
        if (! isset($_GET['page']) || ! is_string($_GET['page']) || $_GET['page'] !== self::MENU_SLUG) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (! isset($_GET['translateplus_disconnected']) || (string) wp_unslash($_GET['translateplus_disconnected']) !== '1') {
            return;
        }

        add_settings_error(
            'translateplus',
            'translateplus_disconnected',
            __('TranslatePlus has been disconnected. Your API key was removed from this site.', 'translateplus'),
            'success'
        );
    }

    /**
     * Remove API key from wp_options (Disconnect).
     */
    public static function handle_disconnect(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do this.', 'translateplus'));
        }

        check_admin_referer('translateplus_disconnect');

        delete_option(TranslatePlus_API::OPTION_API_KEY);
        TranslatePlus_API::clear_account_summary_cache();
        TranslatePlus_API::clear_last_sync();

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'                         => self::MENU_SLUG,
                    'translateplus_disconnected'   => '1',
                ),
                admin_url('options-general.php')
            )
        );
        exit;
    }

    /**
     * Scoped styles for Settings → TranslatePlus.
     */
    public static function enqueue_admin_assets(string $hook_suffix): void {
        if ($hook_suffix !== 'settings_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'translateplus-admin-settings',
            plugins_url('assets/css/admin-settings.css', TRANSLATEPLUS_FILE),
            array(),
            TRANSLATEPLUS_VERSION
        );

        wp_enqueue_script(
            'translateplus-admin-target-langs',
            plugins_url('assets/js/admin-settings-target-langs.js', TRANSLATEPLUS_FILE),
            array(),
            TRANSLATEPLUS_VERSION,
            true
        );

        wp_localize_script(
            'translateplus-admin-target-langs',
            'translateplusTargetLangPicker',
            array(
                'countNone' => __('No languages selected', 'translateplus'),
                'countOne'  => __('1 language selected', 'translateplus'),
                'countMany' => __('%d languages selected', 'translateplus'),
            )
        );

        wp_enqueue_script(
            'translateplus-admin-settings-tabs',
            plugins_url('assets/js/admin-settings-tabs.js', TRANSLATEPLUS_FILE),
            array(),
            TRANSLATEPLUS_VERSION,
            true
        );

        wp_enqueue_script(
            'translateplus-admin-settings-ajax-save',
            plugins_url('assets/js/admin-settings-ajax-save.js', TRANSLATEPLUS_FILE),
            array(),
            TRANSLATEPLUS_VERSION,
            true
        );
        wp_localize_script(
            'translateplus-admin-settings-ajax-save',
            'translateplusAjaxSave',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'saving'  => __('Saving…', 'translateplus'),
                'error'   => __('Could not save settings. Check your connection and try again.', 'translateplus'),
            )
        );

        if ((string) get_option(TranslatePlus_API::OPTION_API_KEY, '') !== '') {
            wp_enqueue_script(
                'translateplus-admin-settings-summary',
                plugins_url('assets/js/admin-settings-summary.js', TRANSLATEPLUS_FILE),
                array(),
                TRANSLATEPLUS_VERSION,
                true
            );
            wp_localize_script(
                'translateplus-admin-settings-summary',
                'translateplusSettingsSummary',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'action'  => self::AJAX_SETTINGS_SUMMARY,
                    'nonce'   => wp_create_nonce(self::NONCE_SETTINGS_SUMMARY),
                    'strings' => array(
                        'connectionError' => esc_html__(
                            'Could not load connection status. Reload the page or check your network.',
                            'translateplus'
                        ),
                        'statsError'       => esc_html__(
                            'Could not load usage data. Reload the page or try again in a moment.',
                            'translateplus'
                        ),
                        'refreshOk'        => esc_html__(
                            'Usage data was refreshed. The summary cache was cleared and the latest account stats were loaded.',
                            'translateplus'
                        ),
                        'refreshFail'      => esc_html__(
                            'Usage data could not be refreshed. Check your API key and network, then try again.',
                            'translateplus'
                        ),
                        'refreshing'       => esc_html__('Refreshing…', 'translateplus'),
                    ),
                )
            );
        }
    }

    /**
     * AJAX: persist Settings API options (same sanitization as options.php) without redirect.
     */
    public static function ajax_save_settings(): void {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(
                array('message' => __('You do not have permission to save settings.', 'translateplus')),
                403
            );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::OPTION_GROUP . '-options')) {
            wp_send_json_error(
                array('message' => __('Security check failed. Reload the page and try again.', 'translateplus')),
                403
            );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated above.
        if (! isset($_POST['option_page']) || (string) wp_unslash($_POST['option_page']) !== self::OPTION_GROUP) {
            wp_send_json_error(
                array('message' => __('Invalid form submission.', 'translateplus')),
                400
            );
        }

        $option_names = array(
            TranslatePlus_API::OPTION_API_KEY,
            self::OPTION_TARGET_LANGUAGES,
            self::OPTION_POST_TYPES,
            self::OPTION_TRANSLATION_MODE,
            self::OPTION_EDITOR_MANUAL_UI,
            self::OPTION_AUTO_LANGUAGE_REDIRECT,
            self::OPTION_AUTO_PUBLISH_TRANSLATIONS,
            self::OPTION_AUTO_TRANSLATE_SLUGS,
            self::OPTION_URL_MODE,
            self::OPTION_FRONTEND_SWITCHER_DISPLAY,
            self::OPTION_FRONTEND_SWITCHER_FLAGS,
        );

        foreach ($option_names as $option_name) {
            $value = null;
            if (isset($_POST[ $option_name ])) {
                $value = wp_unslash($_POST[ $option_name ]);
                if (! is_array($value)) {
                    $value = trim((string) $value);
                }
            }

            update_option($option_name, $value);
        }

        wp_send_json_success(
            array(
                'message' => __('Settings saved.', 'translateplus'),
            )
        );
    }

    /**
     * AJAX: return HTML fragments for connection check + usage stats (after loading skeleton).
     */
    public static function ajax_settings_account_summary(): void {
        check_ajax_referer(self::NONCE_SETTINGS_SUMMARY, 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'forbidden'), 403);
        }

        $api_key = get_option(TranslatePlus_API::OPTION_API_KEY, '');
        if (! is_string($api_key) || $api_key === '') {
            wp_send_json_error(array('message' => 'no_key'), 400);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $force_refresh = isset($_POST['refresh']) && (string) wp_unslash($_POST['refresh']) === '1';
        if ($force_refresh) {
            TranslatePlus_API::clear_account_summary_cache();
        }

        $summary = TranslatePlus_API::get_account_summary(false);

        ob_start();
        self::render_api_connection_check($summary);
        $connection_html = (string) ob_get_clean();

        ob_start();
        self::render_account_summary_inner($summary);
        $stats_html = (string) ob_get_clean();

        $bar = self::build_status_bar_strings($summary);

        wp_send_json_success(
            array(
                'connection_html'   => $connection_html,
                'stats_html'        => $stats_html,
                'refreshed'         => $force_refresh,
                'refresh_ok'        => ! is_wp_error($summary),
                'connection_is_ok' => $bar['connection_ok'],
                'status_bar_credits' => $bar['credits'],
                'status_bar_updated' => $bar['updated'],
            )
        );
    }

    /**
     * Searchable multi-select for target languages (checkboxes + filter).
     *
     * @param list<string>              $saved_langs   Selected codes.
     * @param array<string, string>     $all_languages code => label.
     */
    private static function render_target_language_picker(array $saved_langs, array $all_languages): void {
        $search_id = 'translateplus-lang-search';
        uasort(
            $all_languages,
            static function (string $a, string $b): int {
                return strcasecmp($a, $b);
            }
        );
        ?>
        <div class="translateplus-lang-picker" data-translateplus-lang-picker>
            <label class="screen-reader-text translateplus-lang-picker__search-label" for="<?php echo esc_attr($search_id); ?>">
                <?php esc_html_e('Search languages', 'translateplus'); ?>
            </label>
            <input
                type="search"
                id="<?php echo esc_attr($search_id); ?>"
                class="translateplus-lang-picker__search"
                placeholder="<?php esc_attr_e('Type to filter…', 'translateplus'); ?>"
                autocomplete="off"
            />
            <p class="translateplus-lang-picker__count-wrap" aria-live="polite">
                <span class="translateplus-lang-picker__count"></span>
            </p>
            <div class="translateplus-lang-picker__list" role="group" aria-label="<?php esc_attr_e('Target languages', 'translateplus'); ?>">
                <?php /* Picker marker so sanitize can tell “no languages posted” from “field omitted”. */ ?>
                <input type="hidden" name="<?php echo esc_attr(self::FIELD_TARGET_LANG_PICKER_SEEN); ?>" value="1" />
                <?php
                foreach ($all_languages as $code => $label) :
                    $checked = in_array($code, $saved_langs, true);
                    // Search: name, code, and code with separators as words (e.g. zh-CN → zh cn).
                    $code_bits    = strtolower(str_replace(array('-', '_'), ' ', $code));
                    $search_index = strtolower($label . ' ' . $code . ' ' . $code_bits);
                    $search_index = preg_replace('/\s+/', ' ', trim($search_index));
                    ?>
                    <label
                        class="translateplus-lang-picker__item"
                        data-search-text="<?php echo esc_attr($search_index); ?>"
                    >
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(self::OPTION_TARGET_LANGUAGES); ?>[]"
                            value="<?php echo esc_attr($code); ?>"
                            <?php checked($checked); ?>
                        />
                        <span class="translateplus-lang-picker__item-text">
                            <span class="translateplus-lang-picker__item-label"><?php echo esc_html($label); ?></span>
                            <span class="translateplus-lang-picker__item-code"><?php echo esc_html($code); ?></span>
                        </span>
                    </label>
                    <?php
                endforeach;
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Allowlist of language codes for translation targets (from assets/js/languages.json; excludes "auto").
     *
     * @return array<string, string>
     */
    public static function available_languages(): array {
        return TranslatePlus_Languages::get_target_codes_with_labels();
    }

    /**
     * Whether the option value is a list of codes (canonical) vs checkbox map { "en": "1", ... }.
     */
    private static function target_languages_option_is_list_of_codes(array $raw): bool {
        if ($raw === array()) {
            return true;
        }
        if (function_exists('array_is_list')) {
            return array_is_list($raw);
        }
        $i = 0;
        foreach (array_keys($raw) as $k) {
            if ($k !== $i) {
                return false;
            }
            ++$i;
        }

        return true;
    }

    /**
     * Active target languages (saved option intersected with allowlist). May be empty until the admin selects languages.
     *
     * Accepts canonical storage (list of codes) and legacy checkbox-shaped maps if those were ever stored.
     *
     * @return list<string>
     */
    public static function get_target_languages(): array {
        $raw     = get_option(self::OPTION_TARGET_LANGUAGES, null);
        $allowed = array_keys(self::available_languages());

        if (! is_array($raw)) {
            return array();
        }

        $codes = array();

        if (self::target_languages_option_is_list_of_codes($raw)) {
            foreach ($raw as $code) {
                if (! is_string($code)) {
                    continue;
                }
                $n = TranslatePlus_Languages::normalize($code);
                if ($n !== null && $n !== 'auto' && in_array($n, $allowed, true)) {
                    $codes[] = $n;
                }
            }
        } else {
            foreach ($allowed as $code) {
                if (isset($raw[ $code ]) && (string) $raw[ $code ] === '1') {
                    $n = TranslatePlus_Languages::normalize($code);
                    if ($n !== null && $n !== 'auto' && in_array($n, $allowed, true)) {
                        $codes[] = $n;
                    }
                }
            }
        }

        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * Post type slugs excluded from the settings UI and from translation features.
     *
     * @return list<string>
     */
    private static function internal_post_types_blacklist(): array {
        return array(
            'attachment',
            'wp_block',
            'wp_template',
            'wp_template_part',
            'wp_global_styles',
            'wp_navigation',
            'wp_font_family',
            'wp_font_face',
        );
    }

    /**
     * Post types admins can enable (show_ui, not internal WP types).
     *
     * @return array<string, string> slug => label
     */
    public static function get_post_types_for_settings_ui(): array {
        $objects = get_post_types(
            array(
                'public'   => true,
                '_builtin' => false,
            ),
            'objects'
        );

        // Polylang-style UX: custom public types list, while keeping core post/page selectable.
        $core_objects = get_post_types(
            array(
                'show_ui' => true,
                '_builtin' => true,
            ),
            'objects'
        );
        foreach (array('post', 'page') as $core_slug) {
            if (isset($core_objects[ $core_slug ]) && $core_objects[ $core_slug ] instanceof WP_Post_Type) {
                $objects[ $core_slug ] = $core_objects[ $core_slug ];
            }
        }

        $skip    = self::internal_post_types_blacklist();
        $out     = array();

        foreach ($objects as $slug => $obj) {
            if (! $obj instanceof WP_Post_Type) {
                continue;
            }
            if (in_array($slug, $skip, true)) {
                continue;
            }
            $out[ $slug ] = $obj->labels->name ?? $slug;
        }

        uksort(
            $out,
            static function (string $a, string $b) use ($out): int {
                $order = array('post' => 0, 'page' => 1);
                $oa    = isset($order[ $a ]) ? $order[ $a ] : 100;
                $ob    = isset($order[ $b ]) ? $order[ $b ] : 100;
                if ($oa !== $ob) {
                    return $oa <=> $ob;
                }

                return strcasecmp($out[ $a ], $out[ $b ]);
            }
        );

        return $out;
    }

    /**
     * Post types with translation UI and API flows enabled.
     *
     * @return list<string>
     */
    public static function get_translatable_post_types(): array {
        $raw        = get_option(self::OPTION_POST_TYPES, null);
        $selectable = array_keys(self::get_post_types_for_settings_ui());

        if (! is_array($raw)) {
            return array('post', 'page');
        }

        $out = array();
        foreach ($raw as $slug) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }
            if (in_array($slug, $selectable, true) && post_type_exists($slug)) {
                $out[] = $slug;
            }
        }

        $out = array_values(array_unique($out));
        sort($out);

        if ($out === array()) {
            return array('post', 'page');
        }

        return $out;
    }

    /**
     * @param mixed $value Raw option value from $_POST (checkbox map).
     * @return list<string>
     */
    public static function sanitize_translatable_post_types($value): array {
        $allowed = array_keys(self::get_post_types_for_settings_ui());

        if (! is_array($value)) {
            return self::get_translatable_post_types();
        }

        $out = array();
        foreach ($allowed as $slug) {
            if (! empty($value[ $slug ]) && (string) $value[ $slug ] === '1') {
                $out[] = $slug;
            }
        }

        $out = array_values(array_unique($out));
        sort($out);

        if ($out === array()) {
            return array('post', 'page');
        }

        return $out;
    }

    /**
     * Checkboxes for post / page / custom post types.
     *
     * @param list<string>         $saved_slugs Selected slugs.
     * @param array<string,string> $ui_types    slug => label from get_post_types_for_settings_ui().
     */
    private static function render_translatable_post_types_fieldset(array $saved_slugs, array $ui_types): void {
        ?>
        <fieldset class="translateplus-card__body">
            <legend class="screen-reader-text">
                <span><?php esc_html_e('Translatable content types', 'translateplus'); ?></span>
            </legend>
            <div class="translateplus-post-type-grid">
                <?php
                foreach ($ui_types as $slug => $label) :
                    $checked = in_array($slug, $saved_slugs, true);
                    ?>
                    <label class="translateplus-post-type-grid__item">
                        <input
                            type="hidden"
                            name="<?php echo esc_attr(self::OPTION_POST_TYPES); ?>[<?php echo esc_attr($slug); ?>]"
                            value="0"
                        />
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(self::OPTION_POST_TYPES); ?>[<?php echo esc_attr($slug); ?>]"
                            value="1"
                            <?php checked($checked); ?>
                        />
                        <span class="translateplus-post-type-grid__text">
                            <span class="translateplus-post-type-grid__label"><?php echo esc_html($label); ?></span>
                            <span class="translateplus-post-type-grid__slug"><?php echo esc_html($slug); ?></span>
                        </span>
                    </label>
                    <?php
                endforeach;
                ?>
            </div>
            <p class="description" style="margin-top:14px;margin-bottom:0;">
                <?php esc_html_e('Language metadata, editor switcher, Translate Now, and the front-end language bar apply only to the types you enable. At least one type must stay selected.', 'translateplus'); ?>
            </p>
        </fieldset>
        <?php
    }

    /**
     * General tab: shortcode help for the front-end language dropdown.
     */
    private static function render_language_switcher_shortcode_help_card(): void {
        ?>
        <div class="translateplus-card translateplus-card--shortcode-help">
            <h2 class="translateplus-card__title"><?php esc_html_e('Front-end language switcher', 'translateplus'); ?></h2>
            <p class="translateplus-card__subtitle">
                <?php esc_html_e('Show the language switcher in navigation menus, shortcodes, and above post content.', 'translateplus'); ?>
            </p>
            <div class="translateplus-card__body">
                <p><strong><?php esc_html_e('Shortcode', 'translateplus'); ?></strong></p>
                <p><code class="translateplus-code-inline">[tp_language_switcher]</code></p>
                <p class="description">
                    <?php esc_html_e('Optional: lock the switcher to a specific post’s translation group (useful on non-singular pages, e.g. the homepage):', 'translateplus'); ?>
                    <code class="translateplus-code-inline">[tp_language_switcher post_id="123"]</code>
                </p>
                <p class="description">
                    <strong><?php esc_html_e('Legacy alias:', 'translateplus'); ?></strong>
                    <code class="translateplus-code-inline">[translateplus_lang_dropdown]</code>
                </p>
                <p><strong><?php esc_html_e('How to add it', 'translateplus'); ?></strong></p>
                <ol class="translateplus-shortcode-steps">
                    <li><?php esc_html_e('Block editor: add a “Shortcode” block (or “Custom HTML”) and paste the shortcode.', 'translateplus'); ?></li>
                    <li><?php esc_html_e('Classic editor or any content area that processes shortcodes: paste the shortcode into the content.', 'translateplus'); ?></li>
                    <li><?php esc_html_e('Appearance → Menus: open the “TranslatePlus” box, check “Language Switcher”, and click “Add to Menu”. You can also add a Custom Link with URL “#tp-lang-switcher” (or label “[tp_language_switcher]” on “#”). If the TranslatePlus box is missing, use Screen Options at the top of the screen to show it.', 'translateplus'); ?></li>
                    <li><?php esc_html_e('Block / Site Editor navigation: put the shortcode in the navigation link label, or add a Shortcode block in the header template.', 'translateplus'); ?></li>
                </ol>
                <p class="description">
                    <?php esc_html_e('Menus appear on every page. On the blog home, archives, and search there is no current post, so use a reference post in your translation group, for example:', 'translateplus'); ?>
                    <code class="translateplus-code-inline">[tp_language_switcher post_id="123"]</code>
                    <?php esc_html_e('Replace 123 with a real post or page ID. On single posts and pages, plain [tp_language_switcher] is enough.', 'translateplus'); ?>
                </p>
                <p class="description" style="margin-bottom:0;">
                    <?php esc_html_e('The switcher lists every language from Target languages above (plus the default source). Missing translations appear disabled until published. On non-post pages, use a post_id shortcode or the translateplus_language_switcher_default_post_id filter.', 'translateplus'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Dropdown (manual vs automatic sync) + checkbox for editor manual controls.
     */
    private static function render_translation_workflow_card(string $translation_mode, string $editor_manual_ui, string $auto_language_redirect, string $auto_publish_translations): void {
        ?>
        <div class="translateplus-card">
            <h2 class="translateplus-card__title"><?php esc_html_e('Translation workflow', 'translateplus'); ?></h2>
            <p class="translateplus-card__subtitle">
                <?php esc_html_e('Choose whether linked language versions update only when you run editor actions, or automatically when you save.', 'translateplus'); ?>
            </p>
            <fieldset class="translateplus-card__body">
                <legend class="screen-reader-text">
                    <span><?php esc_html_e('Translation workflow', 'translateplus'); ?></span>
                </legend>
                <p>
                    <label for="translateplus-translation-mode">
                        <strong><?php esc_html_e('Linked posts', 'translateplus'); ?></strong>
                    </label>
                </p>
                <select
                    name="<?php echo esc_attr(self::OPTION_TRANSLATION_MODE); ?>"
                    id="translateplus-translation-mode"
                    class="regular-text"
                    style="max-width:100%;"
                >
                    <option value="manual" <?php selected($translation_mode, 'manual'); ?>>
                        <?php esc_html_e('Manual — update only when you use Translate Now or add translations in the editor', 'translateplus'); ?>
                    </option>
                    <option value="automatic" <?php selected($translation_mode, 'automatic'); ?>>
                        <?php esc_html_e('Automatic — when you save, translate this post into other languages in the same group (uses API credits)', 'translateplus'); ?>
                    </option>
                </select>
                <p class="description" style="margin-top:12px;margin-bottom:0;">
                    <?php esc_html_e('Automatic mode on each save: creates missing draft posts in the same translation group (matching the parent page when applicable), then translates title and content into those linked posts via the API (uses credits). Translation runs during save; a follow-up cron job can re-sync if WP-Cron runs. Only posts you can edit are created or updated.', 'translateplus'); ?>
                </p>
                <p style="margin-top:18px;margin-bottom:8px;">
                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_AUTO_PUBLISH_TRANSLATIONS); ?>" value="0" />
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(self::OPTION_AUTO_PUBLISH_TRANSLATIONS); ?>"
                            value="1"
                            <?php checked($auto_publish_translations, '1'); ?>
                        />
                        <?php esc_html_e('Auto publish translated posts', 'translateplus'); ?>
                    </label>
                </p>
                <p class="description" style="margin-top:0;margin-bottom:0;">
                    <?php esc_html_e('When enabled, newly created translations are published immediately. When disabled, new translations are saved as drafts until you publish them manually.', 'translateplus'); ?>
                </p>
                <p style="margin-top:18px;margin-bottom:8px;">
                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_AUTO_TRANSLATE_SLUGS); ?>" value="0" />
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(self::OPTION_AUTO_TRANSLATE_SLUGS); ?>"
                            value="1"
                            <?php checked((string) get_option(self::OPTION_AUTO_TRANSLATE_SLUGS, '1'), '1'); ?>
                        />
                        <?php esc_html_e('Translate slugs automatically', 'translateplus'); ?>
                    </label>
                </p>
                <p class="description" style="margin-top:0;margin-bottom:0;">
                    <?php esc_html_e('When enabled, translated pages/posts receive localized URL slugs. Turn off to keep source slugs.', 'translateplus'); ?>
                </p>
                <p style="margin-top:18px;margin-bottom:8px;">
                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_EDITOR_MANUAL_UI); ?>" value="0" />
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(self::OPTION_EDITOR_MANUAL_UI); ?>"
                            value="1"
                            <?php checked($editor_manual_ui, '1'); ?>
                        />
                        <?php esc_html_e('Show Translate Now, the editor language switcher, and “Add translation” controls', 'translateplus'); ?>
                    </label>
                </p>
                <p class="description" style="margin-top:0;margin-bottom:0;">
                    <?php esc_html_e('Turn this off if you rely only on automatic updates or want a cleaner editor. The Translation overview meta box and front-end language bar stay available.', 'translateplus'); ?>
                </p>
                <p style="margin-top:18px;margin-bottom:8px;">
                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_AUTO_LANGUAGE_REDIRECT); ?>" value="0" />
                    <label>
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr(self::OPTION_AUTO_LANGUAGE_REDIRECT); ?>"
                            value="1"
                            <?php checked($auto_language_redirect, '1'); ?>
                        />
                        <?php esc_html_e('Enable auto redirect based on browser language', 'translateplus'); ?>
                    </label>
                </p>
                <p class="description" style="margin-top:0;margin-bottom:0;">
                    <?php esc_html_e('When enabled, visitors can be sent to the best-matching translation for their browser language (implementation uses this setting). Off by default.', 'translateplus'); ?>
                </p>
                <p style="margin-top:18px;margin-bottom:8px;">
                    <label for="translateplus-url-mode">
                        <strong><?php esc_html_e('URL structure', 'translateplus'); ?></strong>
                    </label>
                </p>
                <select
                    name="<?php echo esc_attr(self::OPTION_URL_MODE); ?>"
                    id="translateplus-url-mode"
                    class="regular-text"
                    style="max-width:100%;"
                >
                    <option value="directory_for_translations" <?php selected(self::get_url_mode(), 'directory_for_translations'); ?>>
                        <?php esc_html_e('Use /{lang}/ for translations, keep default language without prefix', 'translateplus'); ?>
                    </option>
                    <option value="directory_for_all" <?php selected(self::get_url_mode(), 'directory_for_all'); ?>>
                        <?php esc_html_e('Use /{lang}/ for all languages, including default language', 'translateplus'); ?>
                    </option>
                </select>
                <p class="description" style="margin-top:8px;margin-bottom:0;">
                    <?php esc_html_e('Changing URL mode may require re-saving permalinks under Settings → Permalinks.', 'translateplus'); ?>
                </p>
            </fieldset>
        </div>
        <?php
    }

    public static function register(): void {
        register_setting(
            self::OPTION_GROUP,
            TranslatePlus_API::OPTION_API_KEY,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_api_key'),
                'default'           => '',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_TARGET_LANGUAGES,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(self::class, 'sanitize_target_languages'),
                'default'           => array(),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_POST_TYPES,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(self::class, 'sanitize_translatable_post_types'),
                'default'           => array('post', 'page'),
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_TRANSLATION_MODE,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_translation_mode'),
                'default'           => 'manual',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_EDITOR_MANUAL_UI,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_editor_manual_ui'),
                'default'           => '1',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_AUTO_LANGUAGE_REDIRECT,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_auto_language_redirect'),
                'default'           => '0',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_AUTO_PUBLISH_TRANSLATIONS,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_auto_publish_translations'),
                'default'           => '1',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_AUTO_TRANSLATE_SLUGS,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_auto_translate_slugs'),
                'default'           => '1',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_FRONTEND_SWITCHER_DISPLAY,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_frontend_switcher_display'),
                'default'           => 'dropdown',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_URL_MODE,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_url_mode'),
                'default'           => 'directory_for_translations',
            )
        );

        register_setting(
            self::OPTION_GROUP,
            self::OPTION_FRONTEND_SWITCHER_FLAGS,
            array(
                'type'              => 'string',
                'sanitize_callback' => array(self::class, 'sanitize_frontend_switcher_flags'),
                'default'           => '1',
            )
        );
    }

    /**
     * Linked posts are re-translated via the API when the author saves (uses credits).
     */
    public static function is_auto_sync_on_save(): bool {
        return (string) get_option(self::OPTION_TRANSLATION_MODE, 'manual') === 'automatic';
    }

    /**
     * Show Translate Now, editor tabs/switcher, and related footer scripts.
     */
    public static function is_editor_manual_ui_enabled(): bool {
        return (string) get_option(self::OPTION_EDITOR_MANUAL_UI, '1') !== '0';
    }

    /**
     * Whether automatic redirect to a translation by browser language is enabled.
     */
    public static function is_auto_language_redirect_enabled(): bool {
        return (string) get_option(self::OPTION_AUTO_LANGUAGE_REDIRECT, '0') === '1';
    }

    /**
     * Whether newly created translations should be published immediately.
     */
    public static function is_auto_publish_translations_enabled(): bool {
        return (string) get_option(self::OPTION_AUTO_PUBLISH_TRANSLATIONS, '1') === '1';
    }

    /**
     * Whether translated post/page slugs should be localized.
     */
    public static function is_auto_translate_slugs_enabled(): bool {
        return (string) get_option(self::OPTION_AUTO_TRANSLATE_SLUGS, '1') === '1';
    }

    /**
     * Front-end language switcher layout: dropdown (button + panel) or inline links.
     *
     * @return string "dropdown"|"inline"
     */
    public static function get_frontend_switcher_display(): string {
        $v = (string) get_option(self::OPTION_FRONTEND_SWITCHER_DISPLAY, 'dropdown');

        return in_array($v, array('dropdown', 'inline'), true) ? $v : 'dropdown';
    }

    /**
     * Whether the switcher uses the dropdown UI (vs inline links).
     */
    public static function is_frontend_switcher_dropdown(): bool {
        return self::get_frontend_switcher_display() === 'dropdown';
    }

    /**
     * Whether flag emoji are shown beside language names in the front-end switcher.
     */
    public static function is_frontend_switcher_flags_enabled(): bool {
        return (string) get_option(self::OPTION_FRONTEND_SWITCHER_FLAGS, '1') === '1';
    }

    /**
     * @return string "directory_for_translations"|"directory_for_all"
     */
    public static function get_url_mode(): string {
        $v = (string) get_option(self::OPTION_URL_MODE, 'directory_for_translations');

        return in_array($v, array('directory_for_translations', 'directory_for_all'), true)
            ? $v
            : 'directory_for_translations';
    }

    /**
     * Whether default language URL should be prefixed.
     */
    public static function is_default_language_prefixed(): bool {
        return self::get_url_mode() === 'directory_for_all';
    }

    /**
     * @param mixed $value Submitted value (hidden "0" or checkbox "1").
     */
    public static function sanitize_auto_language_redirect($value): string {
        if ($value === null) {
            return (string) get_option(self::OPTION_AUTO_LANGUAGE_REDIRECT, '0');
        }

        if ($value === '1' || $value === 1 || $value === true) {
            return '1';
        }

        return '0';
    }

    /**
     * @param mixed $value Submitted value (hidden "0" or checkbox "1").
     */
    public static function sanitize_auto_publish_translations($value): string {
        if ($value === null) {
            return (string) get_option(self::OPTION_AUTO_PUBLISH_TRANSLATIONS, '1');
        }

        if ($value === '1' || $value === 1 || $value === true) {
            return '1';
        }

        return '0';
    }

    /**
     * @param mixed $value Submitted value (hidden "0" or checkbox "1").
     */
    public static function sanitize_auto_translate_slugs($value): string {
        if ($value === null) {
            return (string) get_option(self::OPTION_AUTO_TRANSLATE_SLUGS, '1');
        }

        if ($value === '1' || $value === 1 || $value === true) {
            return '1';
        }

        return '0';
    }

    /**
     * @param mixed $value Submitted value.
     */
    public static function sanitize_frontend_switcher_display($value): string {
        if ($value === null) {
            return (string) get_option(self::OPTION_FRONTEND_SWITCHER_DISPLAY, 'dropdown');
        }

        $v = is_string($value) ? $value : '';

        return in_array($v, array('dropdown', 'inline'), true)
            ? $v
            : (string) get_option(self::OPTION_FRONTEND_SWITCHER_DISPLAY, 'dropdown');
    }

    /**
     * @param mixed $value Submitted value (hidden "0" or checkbox "1").
     */
    public static function sanitize_frontend_switcher_flags($value): string {
        if ($value === null) {
            return (string) get_option(self::OPTION_FRONTEND_SWITCHER_FLAGS, '1');
        }

        if ($value === '1' || $value === 1 || $value === true) {
            return '1';
        }

        return '0';
    }

    /**
     * @param mixed $value Submitted URL mode.
     */
    public static function sanitize_url_mode($value): string {
        if ($value === null) {
            return self::get_url_mode();
        }

        $v = is_string($value) ? $value : '';

        return in_array($v, array('directory_for_translations', 'directory_for_all'), true)
            ? $v
            : self::get_url_mode();
    }

    /**
     * @param mixed $value Submitted value.
     */
    public static function sanitize_translation_mode($value): string {
        // options.php passes null when this field was not in $_POST; keep the saved value.
        if ($value === null) {
            return (string) get_option(self::OPTION_TRANSLATION_MODE, 'manual');
        }

        $v = is_string($value) ? $value : '';

        return in_array($v, array('manual', 'automatic'), true) ? $v : (string) get_option(self::OPTION_TRANSLATION_MODE, 'manual');
    }

    /**
     * @param mixed $value Submitted value (hidden "0" or checkbox "1").
     */
    public static function sanitize_editor_manual_ui($value): string {
        // options.php passes null when the workflow tab fields were not posted; preserve.
        if ($value === null) {
            return (string) get_option(self::OPTION_EDITOR_MANUAL_UI, '1');
        }

        if ($value === '1' || $value === 1 || $value === true) {
            return '1';
        }

        return '0';
    }

    /**
     * Target languages: compact POST (`translateplus_target_languages[]` = selected codes only) or legacy map.
     *
     * Reads from $_POST when the picker marker is present so submission matches what PHP receives (avoids
     * hundreds of fields, hyphenated keys, and max_input_vars truncation).
     *
     * @param mixed $value Raw option value from options.php (may omit nested data).
     * @return list<string>
     */
    public static function sanitize_target_languages($value): array {
        $allowed = array_keys(self::available_languages());

        $raw = null;

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- options.php verifies nonce; saving registered options only.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_unslash + per-code normalization below.
        if (isset($_POST[ self::FIELD_TARGET_LANG_PICKER_SEEN ])) {
            if (isset($_POST[ self::OPTION_TARGET_LANGUAGES ]) && is_array($_POST[ self::OPTION_TARGET_LANGUAGES ])) {
                $raw = wp_unslash($_POST[ self::OPTION_TARGET_LANGUAGES ]);
            } else {
                return array();
            }
        } elseif (is_array($value)) {
            $raw = $value;
        } else {
            return self::get_target_languages();
        }
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $out = array();

        if (self::target_languages_option_is_list_of_codes($raw)) {
            foreach ($raw as $code) {
                if (! is_string($code) || $code === '') {
                    continue;
                }
                $n = TranslatePlus_Languages::normalize($code);
                if ($n !== null && $n !== 'auto' && in_array($n, $allowed, true)) {
                    $out[] = $n;
                }
            }
        } else {
            foreach ($allowed as $code) {
                if (isset($raw[ $code ]) && (string) $raw[ $code ] === '1') {
                    $out[] = $code;
                }
            }
        }

        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);

        return $out;
    }

    /**
     * Keep existing key when the form submits an empty field.
     *
     * @param mixed $value Submitted value.
     */
    public static function sanitize_api_key($value): string {
        if ($value === null) {
            return (string) get_option(TranslatePlus_API::OPTION_API_KEY, '');
        }

        if (! is_string($value)) {
            return (string) get_option(TranslatePlus_API::OPTION_API_KEY, '');
        }

        $value = trim($value);
        if ($value === '') {
            return (string) get_option(TranslatePlus_API::OPTION_API_KEY, '');
        }

        return sanitize_text_field($value);
    }

    public static function add_menu(): void {
        add_options_page(
            __('TranslatePlus', 'translateplus'),
            __('TranslatePlus', 'translateplus'),
            'manage_options',
            self::MENU_SLUG,
            array(self::class, 'render_page')
        );
    }

    /**
     * Top brand bar: optional icon + TranslatePlus title.
     */
    private static function render_settings_page_header(): void {
        $icon_path = TRANSLATEPLUS_PATH . 'assets/images/icon.png';
        $has_icon  = is_file($icon_path);
        $icon_url  = plugins_url('assets/images/icon.png', TRANSLATEPLUS_FILE);
        ?>
        <div class="translateplus-settings-topbar">
            <?php if ($has_icon) : ?>
                <img
                    src="<?php echo esc_url($icon_url); ?>"
                    alt=""
                    width="32"
                    height="32"
                    class="translateplus-settings-topbar__icon"
                    decoding="async"
                />
            <?php endif; ?>
            <h1 class="translateplus-settings-topbar__title"><?php esc_html_e('TranslatePlus', 'translateplus'); ?></h1>
        </div>
        <?php
    }

    /**
     * Status bar line: credits + last updated (from summary or placeholders).
     *
     * @param array<string, mixed>|WP_Error $summary
     * @return array{credits: string, updated: string, connection_ok: bool}
     */
    private static function build_status_bar_strings($summary): array {
        $credits        = '—';
        $connection_ok  = ! is_wp_error($summary);
        if ($connection_ok && is_array($summary)) {
            $rem = isset($summary['credits_remaining']) ? $summary['credits_remaining'] : null;
            if ($rem !== null && is_numeric($rem)) {
                $credits = sprintf(
                    /* translators: %s: formatted credit count */
                    __('%s credits', 'translateplus'),
                    number_format_i18n((int) $rem)
                );
            }
        } elseif (is_wp_error($summary)) {
            $credits = __('Credits unavailable', 'translateplus');
        }

        $ts = self::get_last_sync_timestamp();
        if ($ts <= 0) {
            $updated = __('Last updated —', 'translateplus');
        } else {
            $updated = sprintf(
                /* translators: %s: relative time, e.g. "2 minutes ago" */
                __('Last updated %s', 'translateplus'),
                self::format_human_last_sync($ts)
            );
        }

        return array(
            'credits'       => $credits,
            'updated'       => $updated,
            'connection_ok' => $connection_ok,
        );
    }

    /**
     * Error notice on the main dashboard when credits are depleted.
     */
    public static function render_dashboard_credits_notice(): void {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        global $pagenow;
        if ($pagenow !== 'index.php') {
            return;
        }

        $api_key = get_option(TranslatePlus_API::OPTION_API_KEY, '');
        if (! is_string($api_key) || $api_key === '') {
            return;
        }

        $summary = TranslatePlus_API::get_account_summary(false);
        if (is_wp_error($summary)) {
            return;
        }

        if (! TranslatePlus_API::is_credits_depleted($summary)) {
            return;
        }

        $settings_url = admin_url('options-general.php?page=' . self::MENU_SLUG);
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('TranslatePlus', 'translateplus'); ?>:</strong>
                <?php esc_html_e('Your account has no translation credits remaining. Add credits in your TranslatePlus dashboard or update your API key.', 'translateplus'); ?>
                <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Settings', 'translateplus'); ?></a>
                —
                <a href="https://translateplus.io/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('translateplus.io', 'translateplus'); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Mask stored key for read-only display (never reveal full secret).
     */
    private static function format_api_key_masked(string $key): string {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('•', max(4, $len));
        }
        $head = min(8, $len - 4);
        $tail = 4;

        return substr($key, 0, $head) . str_repeat('•', 12) . substr($key, -$tail);
    }

    /**
     * Unix timestamp from translateplus_last_sync (legacy key fallback once).
     */
    private static function get_last_sync_timestamp(): int {
        $raw = get_option(TranslatePlus_API::OPTION_LAST_SYNC, '');
        if (is_numeric($raw) && (int) $raw > 0) {
            return (int) $raw;
        }
        $legacy = get_option('translateplus_last_connection_verified_at', '');
        if (is_numeric($legacy) && (int) $legacy > 0) {
            return (int) $legacy;
        }

        return 0;
    }

    /**
     * Relative time from translateplus_last_sync: "5 minutes ago", "just now" (empty if timestamp missing).
     */
    private static function format_human_last_sync(int $ts): string {
        if ($ts <= 0) {
            return '';
        }
        if ((time() - $ts) < 90) {
            return __('just now', 'translateplus');
        }

        return sprintf(
            /* translators: %s: time span from human_time_diff(), e.g. "4 minutes" */
            __('%s ago', 'translateplus'),
            human_time_diff($ts, time())
        );
    }

    /**
     * Full connection line: "Never synced" or "Last synced: 5 minutes ago".
     */
    private static function format_connection_sync_line(int $ts): string {
        if ($ts <= 0) {
            return __('Never synced', 'translateplus');
        }

        return sprintf(
            /* translators: %s: relative time, e.g. "just now" or "5 minutes ago" */
            __('Last synced: %s', 'translateplus'),
            self::format_human_last_sync($ts)
        );
    }

    /**
     * Single-line status above tabs when an API key is stored (AJAX refreshes credits / updated / errors).
     */
    private static function render_settings_status_bar_connected(): void {
        $summary = TranslatePlus_API::get_account_summary(false);
        $bar     = self::build_status_bar_strings($summary);
        ?>
        <div
            class="translateplus-settings-status-bar translateplus-settings-status-bar--connected"
            role="status"
            aria-live="polite"
            aria-label="<?php esc_attr_e('TranslatePlus account status', 'translateplus'); ?>"
        >
            <div class="translateplus-settings-status-bar__inner">
                <span class="translateplus-settings-status-bar__emoji" aria-hidden="true">🔑</span>
                <span class="tp-status-badge tp-status-badge--connected" role="status">
                    <span class="tp-status-badge__dot" aria-hidden="true"></span>
                    <?php esc_html_e('Connected', 'translateplus'); ?>
                </span>
                <span class="translateplus-settings-status-bar__sep" aria-hidden="true">•</span>
                <span class="translateplus-settings-status-bar__credits" id="translateplus-status-bar-credits"><?php echo esc_html($bar['credits']); ?></span>
                <span class="translateplus-settings-status-bar__sep" aria-hidden="true">•</span>
                <span class="translateplus-settings-status-bar__updated" id="translateplus-status-bar-updated"><?php echo esc_html($bar['updated']); ?></span>
            </div>
            <div id="translateplus-connection-check-root" class="translateplus-settings-status-bar__messages" hidden></div>
        </div>
        <?php
    }

    /**
     * Single-line status when no API key is stored.
     */
    private static function render_settings_status_bar_disconnected(): void {
        ?>
        <div
            class="translateplus-settings-status-bar translateplus-settings-status-bar--disconnected"
            role="status"
            aria-label="<?php esc_attr_e('TranslatePlus account status', 'translateplus'); ?>"
        >
            <div class="translateplus-settings-status-bar__inner">
                <span class="translateplus-settings-status-bar__emoji" aria-hidden="true">🔑</span>
                <span class="tp-status-badge tp-status-badge--disconnected" role="status">
                    <span class="tp-status-badge__dot" aria-hidden="true"></span>
                    <?php esc_html_e('Not connected', 'translateplus'); ?>
                </span>
                <span class="translateplus-settings-status-bar__sep" aria-hidden="true">•</span>
                <span class="translateplus-settings-status-bar__hint">
                    <?php esc_html_e('Add your API key on the Account tab.', 'translateplus'); ?>
                </span>
            </div>
        </div>
        <?php
    }

    /**
     * Account tab (connected): key display, status, usage, disconnect.
     */
    private static function render_account_tab_panel_connected(string $api_key_masked): void {
        ?>
        <div
            role="tabpanel"
            id="translateplus-panel-account"
            class="translateplus-settings-tabpanel"
            aria-labelledby="translateplus-tab-account"
            hidden
        >
            <div class="translateplus-settings__main translateplus-settings__main--tab-only">
                <div class="translateplus-card translateplus-card--connected translateplus-api-sidebar translateplus-api-account-card">
                    <header class="translateplus-api-sidebar__head">
                        <h2 class="translateplus-card__title"><?php esc_html_e('API connection', 'translateplus'); ?></h2>
                        <p class="translateplus-card__subtitle translateplus-api-sidebar__intro">
                            <?php esc_html_e('Your site is linked to TranslatePlus.', 'translateplus'); ?>
                        </p>
                    </header>

                    <section class="translateplus-api-sidebar__section" aria-labelledby="translateplus-api-status-heading-account">
                        <h3 class="translateplus-api-sidebar__eyebrow" id="translateplus-api-status-heading-account">
                            <?php esc_html_e('Status', 'translateplus'); ?>
                        </h3>
                        <div class="translateplus-api-sidebar__status-row">
                            <span class="tp-status-badge tp-status-badge--connected" role="status">
                                <span class="tp-status-badge__dot" aria-hidden="true"></span>
                                <?php esc_html_e('Connected', 'translateplus'); ?>
                            </span>
                        </div>
                        <p class="translateplus-api-sidebar__muted">
                            <?php esc_html_e('Requests use the key stored for this site.', 'translateplus'); ?>
                        </p>
                    </section>

                    <div class="translateplus-api-sidebar__rule" role="presentation" aria-hidden="true"></div>

                    <section class="translateplus-api-sidebar__section" aria-labelledby="translateplus-api-key-heading-account">
                        <h3 class="translateplus-api-sidebar__eyebrow" id="translateplus-api-key-heading-account">
                            <?php esc_html_e('API key', 'translateplus'); ?>
                        </h3>
                        <input
                            type="text"
                            id="translateplus_api_key_display"
                            class="translateplus-api-sidebar__key-input"
                            value="<?php echo esc_attr($api_key_masked); ?>"
                            readonly
                            disabled
                            autocomplete="off"
                            spellcheck="false"
                            aria-readonly="true"
                            aria-labelledby="translateplus-api-key-heading-account"
                        />
                        <p class="translateplus-api-sidebar__hint">
                            <?php esc_html_e('The full key is not shown. Disconnect to remove it or connect with a different key.', 'translateplus'); ?>
                        </p>
                    </section>

                    <div class="translateplus-api-sidebar__rule" role="presentation" aria-hidden="true"></div>

                    <section class="translateplus-api-sidebar__section translateplus-api-sidebar__section--stats" aria-labelledby="translateplus-api-stats-heading-account">
                        <h3 class="translateplus-api-sidebar__eyebrow" id="translateplus-api-stats-heading-account">
                            <?php esc_html_e('Usage & account', 'translateplus'); ?>
                        </h3>
                        <div
                            class="translateplus-api-stats translateplus-api-stats--loading"
                            id="translateplus-api-stats-root"
                            aria-busy="true"
                            aria-live="polite"
                        >
                            <?php self::render_account_stats_loading_shell(); ?>
                        </div>
                        <div class="translateplus-refresh-stats-form">
                            <button
                                type="button"
                                class="button button-secondary translateplus-refresh-stats-button"
                                id="translateplus-refresh-stats-button"
                            >
                                <?php esc_html_e('Refresh stats', 'translateplus'); ?>
                            </button>
                            <p
                                class="translateplus-refresh-stats-feedback"
                                id="translateplus-refresh-stats-feedback"
                                role="status"
                                aria-live="polite"
                                hidden
                            ></p>
                        </div>
                    </section>

                    <div class="translateplus-api-sidebar__rule" role="presentation" aria-hidden="true"></div>

                    <footer class="translateplus-api-sidebar__footer">
                        <div class="translateplus-disconnect-form">
                            <div class="translateplus-sidebar-actions translateplus-sidebar-actions--disconnect">
                                <?php
                                submit_button(
                                    __('Disconnect', 'translateplus'),
                                    'secondary',
                                    'submit',
                                    false,
                                    array(
                                        'class'   => 'button-large translateplus-disconnect-button',
                                        'form'    => 'translateplus-disconnect-form',
                                    )
                                );
                                ?>
                            </div>
                        </div>
                        <?php self::render_upgrade_cta(); ?>
                    </footer>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Account tab (disconnected): connect form fields and placeholder.
     */
    private static function render_account_tab_panel_disconnected(): void {
        ?>
        <div
            role="tabpanel"
            id="translateplus-panel-account-disconnected"
            class="translateplus-settings-tabpanel"
            aria-labelledby="translateplus-tab-account-disconnected"
            hidden
        >
            <div class="translateplus-settings__main translateplus-settings__main--tab-only">
                <div class="translateplus-card translateplus-api-sidebar translateplus-api-sidebar--disconnected translateplus-api-account-card">
                    <header class="translateplus-api-sidebar__head">
                        <h2 class="translateplus-card__title"><?php esc_html_e('API connection', 'translateplus'); ?></h2>
                        <p class="translateplus-card__subtitle translateplus-api-sidebar__intro">
                            <?php esc_html_e('Your TranslatePlus key authorizes translation requests. It is stored in the WordPress options table (wp_options).', 'translateplus'); ?>
                        </p>
                    </header>

                    <section class="translateplus-api-sidebar__section" aria-labelledby="translateplus-api-status-heading-account-disconnected">
                        <h3 class="translateplus-api-sidebar__eyebrow" id="translateplus-api-status-heading-account-disconnected">
                            <?php esc_html_e('Status', 'translateplus'); ?>
                        </h3>
                        <div class="translateplus-api-sidebar__status-row">
                            <span class="tp-status-badge tp-status-badge--disconnected" role="status">
                                <span class="tp-status-badge__dot" aria-hidden="true"></span>
                                <?php esc_html_e('Not connected', 'translateplus'); ?>
                            </span>
                        </div>
                        <p class="translateplus-api-sidebar__muted">
                            <?php esc_html_e('Paste your API key below, then save changes and use Connect to verify.', 'translateplus'); ?>
                        </p>
                    </section>

                    <div class="translateplus-api-sidebar__rule" role="presentation" aria-hidden="true"></div>

                    <section class="translateplus-api-sidebar__section" aria-labelledby="translateplus-api-key-heading-account-disconnected">
                        <h3 class="translateplus-api-sidebar__eyebrow" id="translateplus-api-key-heading-account-disconnected">
                            <?php esc_html_e('API key', 'translateplus'); ?>
                        </h3>
                        <div class="translateplus-field translateplus-field--saas">
                            <input
                                type="password"
                                name="<?php echo esc_attr(TranslatePlus_API::OPTION_API_KEY); ?>"
                                id="translateplus_api_key"
                                value=""
                                class="translateplus-api-sidebar__key-input translateplus-api-sidebar__key-input--editable"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="<?php esc_attr_e('Paste your API key…', 'translateplus'); ?>"
                                aria-labelledby="translateplus-api-key-heading-account-disconnected"
                            />
                            <p class="translateplus-api-sidebar__hint">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: product name / site */
                                        __('Create or copy a key from your %s dashboard.', 'translateplus'),
                                        'translateplus.io'
                                    )
                                );
                                ?>
                            </p>
                        </div>
                    </section>

                    <footer class="translateplus-api-sidebar__footer translateplus-api-sidebar__footer--connect">
                        <div class="translateplus-sidebar-actions">
                            <?php submit_button(__('Connect', 'translateplus'), 'primary', 'translateplus_connect', false, array('class' => 'button-large')); ?>
                        </div>
                        <?php self::render_upgrade_cta(); ?>
                    </footer>
                </div>

                <div class="translateplus-card translateplus-api-sidebar-account-placeholder">
                    <h2 class="translateplus-card__title"><?php esc_html_e('Account summary', 'translateplus'); ?></h2>
                    <p class="translateplus-card__subtitle translateplus-api-sidebar-account-placeholder__text">
                        <?php esc_html_e('After you connect, credits and usage will load on this tab.', 'translateplus'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Skeleton while usage stats load (replaced via AJAX).
     */
    private static function render_account_stats_loading_shell(): void {
        ?>
        <div class="translateplus-api-stats-skeleton" aria-hidden="true">
            <div class="translateplus-api-stats-skeleton__bar"></div>
            <div class="tp-stats-grid tp-stats-grid--skeleton">
                <?php
                for ($i = 0; $i < 4; $i++) :
                    ?>
                    <div class="tp-stat tp-stat--skeleton">
                        <span class="tp-stat--skeleton__line tp-stat--skeleton__line--short"></span>
                        <span class="tp-stat--skeleton__line tp-stat--skeleton__line--long"></span>
                    </div>
                    <?php
                endfor;
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Short connection check under the status badge (uses account summary fetch result).
     *
     * @param array<string, mixed>|WP_Error|null $summary
     */
    private static function render_api_connection_check($summary): void {
        $ts = self::get_last_sync_timestamp();

        if ($summary !== null && ! is_wp_error($summary)) {
            ?>
            <p class="translateplus-connection-check translateplus-connection-check--ok" role="status">
                <span class="translateplus-connection-check__verified"><?php esc_html_e('Connection verified', 'translateplus'); ?></span>
                <span class="translateplus-connection-check__sync"><?php echo esc_html(self::format_connection_sync_line($ts)); ?></span>
            </p>
            <?php
            return;
        }

        if ($ts > 0) {
            $ago = self::format_human_last_sync($ts);
            $msg = sprintf(
                /* translators: %s: relative time, e.g. "2 hours ago" or "just now" */
                __('Could not refresh usage right now. Last successful check: %s.', 'translateplus'),
                $ago
            );
        } else {
            $msg = __('Could not verify the connection. Check your API key and network.', 'translateplus');
        }
        ?>
        <p class="translateplus-connection-check translateplus-connection-check--warn" role="status"><?php echo esc_html($msg); ?></p>
        <?php
    }

    /**
     * About tab: static marketing / product copy.
     *
     * @param string $tab_id_suffix Empty string, or "disconnected" for unique tab control id.
     */
    private static function render_about_tab_panel(string $tab_id_suffix): void {
        $tab_id = $tab_id_suffix !== '' ? 'translateplus-tab-about-' . $tab_id_suffix : 'translateplus-tab-about';
        ?>
        <div
            role="tabpanel"
            id="tab-about"
            class="tp-tab-content translateplus-settings-tabpanel translateplus-settings__main translateplus-settings__main--tab-only"
            aria-labelledby="<?php echo esc_attr($tab_id); ?>"
            hidden
        >
            <div class="translateplus-card">
                <h2 class="translateplus-card__title"><?php esc_html_e('About TranslatePlus', 'translateplus'); ?></h2>
                <p class="translateplus-card__subtitle">
                    <?php esc_html_e('TranslatePlus is a fast and cost-efficient translation API built for developers and modern applications.', 'translateplus'); ?>
                </p>
                <p class="tp-free-credits-badge">
                    <strong><?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: number of free credits (locale-formatted). */
                            __('🎁 Get %s free credits to start', 'translateplus'),
                            number_format_i18n(5000)
                        )
                    );
                    ?></strong>
                </p>
                <div class="translateplus-card__body tp-about-grid">
                    <div class="tp-about-item">
                        <strong><?php esc_html_e('⚡ Fast', 'translateplus'); ?></strong>
                        <p><?php esc_html_e('Low-latency translation API optimized for real-time usage.', 'translateplus'); ?></p>
                    </div>
                    <div class="tp-about-item">
                        <strong><?php esc_html_e('💰 Cost efficient', 'translateplus'); ?></strong>
                        <p><?php esc_html_e('Save up to 70% compared to traditional translation APIs.', 'translateplus'); ?></p>
                    </div>
                    <div class="tp-about-item">
                        <strong><?php esc_html_e('🌍 Multi-language', 'translateplus'); ?></strong>
                        <p><?php esc_html_e('Support for dozens of languages with consistent quality.', 'translateplus'); ?></p>
                    </div>
                    <div class="tp-about-item">
                        <strong><?php esc_html_e('🔌 Developer friendly', 'translateplus'); ?></strong>
                        <p><?php esc_html_e('Simple API with request-based pricing and easy integration.', 'translateplus'); ?></p>
                    </div>
                </div>
            </div>

            <div class="translateplus-card">
                <h2 class="translateplus-card__title"><?php esc_html_e('How it works', 'translateplus'); ?></h2>
                <div class="translateplus-card__body">
                    <ol class="tp-about-steps">
                        <li><?php esc_html_e('Connect your API key', 'translateplus'); ?></li>
                        <li><?php esc_html_e('Select target languages', 'translateplus'); ?></li>
                        <li><?php esc_html_e('Translate content automatically or manually', 'translateplus'); ?></li>
                    </ol>
                </div>
            </div>

            <div class="translateplus-card">
                <h2 class="translateplus-card__title"><?php esc_html_e('Get started', 'translateplus'); ?></h2>
                <div class="translateplus-card__body">
                    <p class="tp-about-lead">
                        <?php esc_html_e('Start translating your content or integrate the API into your applications.', 'translateplus'); ?>
                    </p>
                    <p class="tp-about-actions">
                        <a href="<?php echo esc_url(self::MARKETING_SITE_URL); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Get API key', 'translateplus'); ?>
                        </a>
                        <a href="<?php echo esc_url('https://app.translateplus.io'); ?>" class="button" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Open dashboard', 'translateplus'); ?>
                        </a>
                        <a href="<?php echo esc_url('mailto:support@translateplus.io'); ?>" class="button">
                            <?php esc_html_e('Contact Support', 'translateplus'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Small upgrade link under disconnect / connect actions.
     */
    private static function render_upgrade_cta(): void {
        ?>
        <p class="translateplus-api-sidebar__upgrade-cta">
            <?php esc_html_e('Need more credits?', 'translateplus'); ?>
            <?php echo ' '; ?>
            <a
                href="<?php echo esc_url(self::MARKETING_SITE_URL); ?>"
                class="translateplus-api-sidebar__upgrade-link"
                target="_blank"
                rel="noopener noreferrer"
            ><?php esc_html_e('Upgrade plan', 'translateplus'); ?></a>
        </p>
        <?php
    }

    /**
     * Account summary body (inside API sidebar card; always fetches fresh when rendered).
     *
     * @param array<string, mixed>|WP_Error $summary
     */
    private static function render_account_summary_inner($summary): void {
        if (is_wp_error($summary)) {
            ?>
            <div class="translateplus-inline-notice translateplus-inline-notice--error" role="alert">
                <p>
                    <strong><?php esc_html_e('Could not load usage data', 'translateplus'); ?></strong>
                </p>
                <p class="translateplus-inline-notice__detail">
                    <?php echo esc_html($summary->get_error_message()); ?>
                </p>
                <p class="translateplus-inline-notice__hint">
                    <?php esc_html_e('Translations may still work if your key is valid. Check your connection or try again in a moment.', 'translateplus'); ?>
                </p>
            </div>
            <?php
            return;
        }

        $email     = isset($summary['email']) && is_string($summary['email']) ? $summary['email'] : '';
        $full_name = isset($summary['full_name']) && is_string($summary['full_name']) ? $summary['full_name'] : '';
        $total     = isset($summary['total_credits']) ? $summary['total_credits'] : null;
        $used      = isset($summary['credits_used']) ? $summary['credits_used'] : null;
        $remaining = isset($summary['credits_remaining']) ? $summary['credits_remaining'] : null;
        $pct_used  = isset($summary['credits_percentage']) ? $summary['credits_percentage'] : null;

        $sub = isset($summary['summary']) && is_array($summary['summary']) ? $summary['summary'] : array();
        $req_total = isset($sub['total_requests']) ? $sub['total_requests'] : null;
        $req_ok    = isset($sub['successful_requests']) ? $sub['successful_requests'] : null;
        $req_fail  = isset($sub['failed_requests']) ? $sub['failed_requests'] : null;
        $req_rate  = isset($sub['success_rate']) ? $sub['success_rate'] : null;

        $depleted = TranslatePlus_API::is_credits_depleted($summary);

        $has_stat_rows = ($email !== '')
            || ($full_name !== '')
            || ($total !== null && is_numeric($total))
            || ($used !== null && is_numeric($used))
            || ($remaining !== null && is_numeric($remaining))
            || ($pct_used !== null && is_numeric($pct_used))
            || ($req_total !== null && is_numeric($req_total))
            || ($req_ok !== null && is_numeric($req_ok))
            || ($req_fail !== null && is_numeric($req_fail))
            || ($req_rate !== null && is_numeric($req_rate));

        if ($depleted) {
            ?>
            <div class="translateplus-card__hint translateplus-card__hint--warn translateplus-api-stats__banner" role="status">
                <?php esc_html_e('No credits remaining. Translation requests will fail until you add credits.', 'translateplus'); ?>
            </div>
            <?php
        }
        ?>
        <div class="translateplus-api-stats__inner">
            <?php if (! $has_stat_rows) : ?>
                <p class="translateplus-summary-empty">
                    <?php esc_html_e('No usage statistics were returned yet. Your connection is active; check back later or verify your account on translateplus.io.', 'translateplus'); ?>
                </p>
            <?php else : ?>
                <div class="tp-stats-grid">
                    <?php if ($email !== '') : ?>
                        <div class="tp-stat tp-stat--full">
                            <span><?php esc_html_e('Email', 'translateplus'); ?></span>
                            <strong><?php echo esc_html($email); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($full_name !== '') : ?>
                        <div class="tp-stat tp-stat--full">
                            <span><?php esc_html_e('Name', 'translateplus'); ?></span>
                            <strong><?php echo esc_html($full_name); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($remaining !== null && is_numeric($remaining)) : ?>
                        <div class="tp-stat">
                            <span><?php esc_html_e('Credits remaining', 'translateplus'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((int) $remaining)); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($total !== null && is_numeric($total)) : ?>
                        <div class="tp-stat">
                            <span><?php esc_html_e('Total credits', 'translateplus'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((int) $total)); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($used !== null && is_numeric($used)) : ?>
                        <div class="tp-stat">
                            <span><?php esc_html_e('Credits used', 'translateplus'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((int) $used)); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($pct_used !== null && is_numeric($pct_used)) : ?>
                        <div class="tp-stat">
                            <span><?php esc_html_e('Credits used (%)', 'translateplus'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((float) $pct_used, 1)); ?>%</strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($req_total !== null && is_numeric($req_total)) : ?>
                        <div class="tp-stat">
                            <span><?php esc_html_e('Total API requests', 'translateplus'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((int) $req_total)); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($req_ok !== null && is_numeric($req_ok)) : ?>
                        <div class="tp-stat">
                            <span><?php esc_html_e('Successful requests', 'translateplus'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((int) $req_ok)); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($req_fail !== null && is_numeric($req_fail)) : ?>
                        <div class="tp-stat">
                            <span><?php esc_html_e('Failed requests', 'translateplus'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((int) $req_fail)); ?></strong>
                        </div>
                    <?php endif; ?>
                    <?php if ($req_rate !== null && is_numeric($req_rate)) : ?>
                        <div class="tp-stat">
                            <span><?php esc_html_e('Success rate', 'translateplus'); ?></span>
                            <strong><?php echo esc_html(number_format_i18n((float) $req_rate, 1)); ?>%</strong>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $api_key_raw       = (string) get_option(TranslatePlus_API::OPTION_API_KEY, '');
        $has_key           = $api_key_raw !== '';
        $api_key_masked    = self::format_api_key_masked($api_key_raw);
        $saved_langs       = self::get_target_languages();
        $all_languages     = self::available_languages();
        $saved_post_types   = self::get_translatable_post_types();
        $post_type_choices  = self::get_post_types_for_settings_ui();
        $translation_mode       = (string) get_option(self::OPTION_TRANSLATION_MODE, 'manual');
        $editor_manual_ui       = (string) get_option(self::OPTION_EDITOR_MANUAL_UI, '1');
        $auto_language_redirect = (string) get_option(self::OPTION_AUTO_LANGUAGE_REDIRECT, '0');
        $auto_publish_translations = (string) get_option(self::OPTION_AUTO_PUBLISH_TRANSLATIONS, '1');
        ?>
        <div class="wrap translateplus-settings">
            <?php self::render_settings_page_header(); ?>
            <?php
            // Not calling settings_errors() here: wp-admin/options-head.php (loaded for Settings
            // submenus) already outputs the queue once; duplicating it showed two identical notices.
            ?>
            <?php if ($has_key) : ?>
                <?php
                /*
                 * Disconnect uses admin-post outside the options form (invalid nested forms).
                 */
                ?>
                <form
                    id="translateplus-disconnect-form"
                    method="post"
                    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    class="translateplus-admin-post-form"
                    tabindex="-1"
                    aria-hidden="true"
                >
                    <?php wp_nonce_field('translateplus_disconnect'); ?>
                    <input type="hidden" name="action" value="translateplus_disconnect" />
                </form>
                <?php self::render_settings_status_bar_connected(); ?>
                <form id="translateplus-options-form" action="options.php" method="post">
                    <?php settings_fields(self::OPTION_GROUP); ?>
                    <div class="translateplus-settings-tabs" data-tp-settings-tabs>
                        <div class="translateplus-settings-tabs__list" role="tablist" aria-label="<?php esc_attr_e('TranslatePlus settings sections', 'translateplus'); ?>">
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab is-active"
                                role="tab"
                                id="translateplus-tab-general"
                                aria-controls="translateplus-panel-general"
                                aria-selected="true"
                            ><?php esc_html_e('General', 'translateplus'); ?></button>
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab"
                                role="tab"
                                id="translateplus-tab-content"
                                aria-controls="translateplus-panel-content"
                                aria-selected="false"
                                tabindex="-1"
                            ><?php esc_html_e('Content', 'translateplus'); ?></button>
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab"
                                role="tab"
                                id="translateplus-tab-workflow"
                                aria-controls="translateplus-panel-workflow"
                                aria-selected="false"
                                tabindex="-1"
                            ><?php esc_html_e('Workflow', 'translateplus'); ?></button>
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab"
                                role="tab"
                                id="translateplus-tab-account"
                                aria-controls="translateplus-panel-account"
                                aria-selected="false"
                                tabindex="-1"
                            ><?php esc_html_e('Account', 'translateplus'); ?></button>
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab"
                                role="tab"
                                id="translateplus-tab-about"
                                aria-controls="tab-about"
                                aria-selected="false"
                                tabindex="-1"
                            ><?php esc_html_e('About', 'translateplus'); ?></button>
                        </div>

                        <div class="translateplus-settings-tabs__panels">
                            <div
                                role="tabpanel"
                                id="translateplus-panel-general"
                                class="translateplus-settings-tabpanel"
                                aria-labelledby="translateplus-tab-general"
                            >
                                <div class="translateplus-settings__main translateplus-settings__main--tab-only">
                                    <div class="translateplus-card">
                                        <h2 class="translateplus-card__title"><?php esc_html_e('Target languages', 'translateplus'); ?></h2>
                                        <p class="translateplus-card__subtitle">
                                            <?php esc_html_e('Languages available for linked posts, editor tabs, the front-end switcher, and “Translate now”.', 'translateplus'); ?>
                                        </p>
                                        <fieldset class="translateplus-card__body">
                                            <legend class="screen-reader-text">
                                                <span><?php esc_html_e('Target languages', 'translateplus'); ?></span>
                                            </legend>
                                            <?php self::render_target_language_picker($saved_langs, $all_languages); ?>
                                            <p class="description" style="margin-top:16px;margin-bottom:0;">
                                                <?php esc_html_e('Nothing is selected until you choose languages. Select at least one to offer those locales in the editor, front-end switcher, and Translate Now.', 'translateplus'); ?>
                                            </p>
                                        </fieldset>
                                    </div>
                                    <?php self::render_language_switcher_shortcode_help_card(); ?>
                                </div>
                            </div>

                            <div
                                role="tabpanel"
                                id="translateplus-panel-content"
                                class="translateplus-settings-tabpanel"
                                aria-labelledby="translateplus-tab-content"
                                hidden
                            >
                                <div class="translateplus-settings__main translateplus-settings__main--tab-only">
                                    <div class="translateplus-card">
                                        <h2 class="translateplus-card__title"><?php esc_html_e('Translatable content types', 'translateplus'); ?></h2>
                                        <p class="translateplus-card__subtitle">
                                            <?php esc_html_e('Enable TranslatePlus for posts, pages, and custom types that use the admin editor (types with “Show UI” enabled).', 'translateplus'); ?>
                                        </p>
                                        <?php self::render_translatable_post_types_fieldset($saved_post_types, $post_type_choices); ?>
                                    </div>
                                </div>
                            </div>

                            <div
                                role="tabpanel"
                                id="translateplus-panel-workflow"
                                class="translateplus-settings-tabpanel"
                                aria-labelledby="translateplus-tab-workflow"
                                hidden
                            >
                                <div class="translateplus-settings__main translateplus-settings__main--tab-only">
                                    <?php self::render_translation_workflow_card($translation_mode, $editor_manual_ui, $auto_language_redirect, $auto_publish_translations); ?>
                                </div>
                            </div>

                            <?php self::render_account_tab_panel_connected($api_key_masked); ?>

                            <?php self::render_about_tab_panel(''); ?>
                        </div>
                    </div>

                    <div class="translateplus-form-actions">
                        <?php submit_button(__('Save changes', 'translateplus')); ?>
                    </div>
                </form>
            <?php else : ?>
                <?php self::render_settings_status_bar_disconnected(); ?>
                <form id="translateplus-options-form" action="options.php" method="post">
                    <?php settings_fields(self::OPTION_GROUP); ?>
                    <div class="translateplus-settings-tabs" data-tp-settings-tabs>
                        <div class="translateplus-settings-tabs__list" role="tablist" aria-label="<?php esc_attr_e('TranslatePlus settings sections', 'translateplus'); ?>">
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab is-active"
                                role="tab"
                                id="translateplus-tab-general-disconnected"
                                aria-controls="translateplus-panel-general-disconnected"
                                aria-selected="true"
                            ><?php esc_html_e('General', 'translateplus'); ?></button>
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab"
                                role="tab"
                                id="translateplus-tab-content-disconnected"
                                aria-controls="translateplus-panel-content-disconnected"
                                aria-selected="false"
                                tabindex="-1"
                            ><?php esc_html_e('Content', 'translateplus'); ?></button>
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab"
                                role="tab"
                                id="translateplus-tab-workflow-disconnected"
                                aria-controls="translateplus-panel-workflow-disconnected"
                                aria-selected="false"
                                tabindex="-1"
                            ><?php esc_html_e('Workflow', 'translateplus'); ?></button>
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab"
                                role="tab"
                                id="translateplus-tab-account-disconnected"
                                aria-controls="translateplus-panel-account-disconnected"
                                aria-selected="false"
                                tabindex="-1"
                            ><?php esc_html_e('Account', 'translateplus'); ?></button>
                            <button
                                type="button"
                                class="translateplus-settings-tabs__tab"
                                role="tab"
                                id="translateplus-tab-about-disconnected"
                                aria-controls="tab-about"
                                aria-selected="false"
                                tabindex="-1"
                            ><?php esc_html_e('About', 'translateplus'); ?></button>
                        </div>

                        <div class="translateplus-settings-tabs__panels">
                            <div
                                role="tabpanel"
                                id="translateplus-panel-general-disconnected"
                                class="translateplus-settings-tabpanel"
                                aria-labelledby="translateplus-tab-general-disconnected"
                            >
                                <div class="translateplus-settings__main translateplus-settings__main--tab-only">
                                    <div class="translateplus-card">
                                        <h2 class="translateplus-card__title"><?php esc_html_e('Target languages', 'translateplus'); ?></h2>
                                        <p class="translateplus-card__subtitle">
                                            <?php esc_html_e('Languages available for linked posts, editor tabs, the front-end switcher, and “Translate now”.', 'translateplus'); ?>
                                        </p>
                                        <fieldset class="translateplus-card__body">
                                            <legend class="screen-reader-text">
                                                <span><?php esc_html_e('Target languages', 'translateplus'); ?></span>
                                            </legend>
                                            <?php self::render_target_language_picker($saved_langs, $all_languages); ?>
                                            <p class="description" style="margin-top:16px;margin-bottom:0;">
                                                <?php esc_html_e('Nothing is selected until you choose languages. Select at least one to offer those locales in the editor, front-end switcher, and Translate Now.', 'translateplus'); ?>
                                            </p>
                                        </fieldset>
                                    </div>
                                    <?php self::render_language_switcher_shortcode_help_card(); ?>
                                </div>
                            </div>

                            <div
                                role="tabpanel"
                                id="translateplus-panel-content-disconnected"
                                class="translateplus-settings-tabpanel"
                                aria-labelledby="translateplus-tab-content-disconnected"
                                hidden
                            >
                                <div class="translateplus-settings__main translateplus-settings__main--tab-only">
                                    <div class="translateplus-card">
                                        <h2 class="translateplus-card__title"><?php esc_html_e('Translatable content types', 'translateplus'); ?></h2>
                                        <p class="translateplus-card__subtitle">
                                            <?php esc_html_e('Enable TranslatePlus for posts, pages, and custom types that use the admin editor (types with “Show UI” enabled).', 'translateplus'); ?>
                                        </p>
                                        <?php self::render_translatable_post_types_fieldset($saved_post_types, $post_type_choices); ?>
                                    </div>
                                </div>
                            </div>

                            <div
                                role="tabpanel"
                                id="translateplus-panel-workflow-disconnected"
                                class="translateplus-settings-tabpanel"
                                aria-labelledby="translateplus-tab-workflow-disconnected"
                                hidden
                            >
                                <div class="translateplus-settings__main translateplus-settings__main--tab-only">
                                    <?php self::render_translation_workflow_card($translation_mode, $editor_manual_ui, $auto_language_redirect, $auto_publish_translations); ?>
                                </div>
                            </div>

                            <?php self::render_account_tab_panel_disconnected(); ?>

                            <?php self::render_about_tab_panel('disconnected'); ?>
                        </div>
                    </div>

                    <div class="translateplus-form-actions">
                        <?php submit_button(__('Save changes', 'translateplus')); ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
