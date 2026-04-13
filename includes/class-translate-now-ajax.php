<?php
/**
 * AJAX: translateplus_translate_now, tp_translate_post, and admin_footer click handler.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles translate-now AJAX and injects fetch() handler via admin_footer.
 */
final class TranslatePlus_Translate_Now_Ajax {

    public const ACTION = 'translateplus_translate_now';

    public const NONCE_ACTION = 'translateplus_translate_now';

    public const ACTION_TP_TRANSLATE_POST = 'tp_translate_post';

    public const NONCE_TP_TRANSLATE_POST = 'tp_translate_post';

    /**
     * Meta keys not copied when creating a translated post.
     *
     * @var list<string>
     */
    private const META_SKIP_ON_TRANSLATE_COPY = array(
        TranslatePlus_Translation_Group::META_GROUP,
        TranslatePlus_Translation_Group::META_LANGUAGE,
        '_tp_translations',
        '_tp_manual',
        '_tp_content_locale',
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_customize_changeset_uuid',
    );

    public static function init(): void {
        add_action('wp_ajax_' . self::ACTION, array(self::class, 'handle_ajax'));
        add_action('wp_ajax_' . self::ACTION_TP_TRANSLATE_POST, array(self::class, 'handle_tp_translate_post'));
        add_action('admin_footer', array(self::class, 'print_footer_script'));
    }

    /**
     * AJAX handler (logged-in admin only).
     */
    public static function handle_ajax(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(
                array('message' => __('Permission denied.', 'translateplus')),
                403
            );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;

        if ($post_id <= 0) {
            wp_send_json_error(
                array('message' => __('Invalid post ID.', 'translateplus')),
                400
            );
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(
                array('message' => __('You cannot edit this post.', 'translateplus')),
                403
            );
        }

        $post = get_post($post_id);
        if (! $post instanceof WP_Post || ! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            wp_send_json_error(
                array('message' => __('Unsupported post type.', 'translateplus')),
                400
            );
        }

        wp_send_json_success(
            array(
                'message' => __('Translate request received.', 'translateplus'),
                'post_id' => $post_id,
            )
        );
    }

    /**
     * AJAX: open existing translation post or create one (translate, copy tax/meta, link group, redirect).
     */
    public static function handle_tp_translate_post(): void {
        check_ajax_referer(self::NONCE_TP_TRANSLATE_POST, 'nonce');

        if (! current_user_can('edit_posts')) {
            wp_send_json_error(
                array('message' => __('Permission denied.', 'translateplus')),
                403
            );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $target_raw = isset($_POST['target_lang']) ? wp_unslash($_POST['target_lang']) : '';
        $target     = is_string($target_raw) ? (TranslatePlus_Languages::normalize($target_raw) ?? '') : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $content_mode_raw = isset($_POST['content_mode']) ? wp_unslash($_POST['content_mode']) : '';
        $content_mode     = is_string($content_mode_raw) && $content_mode_raw === 'copy' ? 'copy' : 'translate';

        if ($post_id <= 0) {
            wp_send_json_error(
                array('message' => __('Save the post first, then use Translate Now.', 'translateplus')),
                400
            );
        }

        if (! current_user_can('edit_post', $post_id)) {
            wp_send_json_error(
                array('message' => __('You cannot edit this post.', 'translateplus')),
                403
            );
        }

        $post = get_post($post_id);
        if (! $post instanceof WP_Post) {
            wp_send_json_error(
                array('message' => __('Post not found.', 'translateplus')),
                404
            );
        }

        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            wp_send_json_error(
                array('message' => __('Unsupported post type.', 'translateplus')),
                400
            );
        }

        $pto = get_post_type_object($post->post_type);
        if (! $pto || ! current_user_can($pto->cap->create_posts)) {
            wp_send_json_error(
                array('message' => __('You cannot create posts of this type.', 'translateplus')),
                403
            );
        }

        $allowed = TranslatePlus_Translation_Group::locale_choices();
        if ($target === '' || $target === 'auto' || ! isset($allowed[ $target ])) {
            wp_send_json_error(
                array('message' => __('Invalid target language.', 'translateplus')),
                400
            );
        }

        $source_lang = TranslatePlus_Translation_Group::get_post_language($post_id);
        if ($source_lang !== 'auto' && $target === $source_lang) {
            wp_send_json_error(
                array('message' => __('Choose a different language than this post’s content language.', 'translateplus')),
                400
            );
        }

        $group_id = TranslatePlus_Translation_Group::ensure_group_for_post($post_id);

        $existing_id = TranslatePlus_Translation_Group::find_post_in_group_by_language(
            $group_id,
            $target,
            $post->post_type
        );

        if ($existing_id > 0) {
            if (! current_user_can('edit_post', $existing_id)) {
                wp_send_json_error(
                    array('message' => __('A translation for this language already exists, but you cannot edit it.', 'translateplus')),
                    403
                );
            }
            $url = get_edit_post_link($existing_id, 'raw');
            if (! is_string($url) || $url === '') {
                wp_send_json_error(
                    array('message' => __('Could not build edit URL.', 'translateplus')),
                    500
                );
            }
            wp_send_json_success(
                array(
                    'redirect' => $url,
                    'message'  => __('Opening existing translation.', 'translateplus'),
                    'post_id'  => $existing_id,
                )
            );
        }

        if ($content_mode === 'copy') {
            $title_t   = is_string($post->post_title) ? $post->post_title : '';
            $html      = $post->post_content;
            $body_use  = ! is_string($html) ? '' : wp_kses_post($html);
            $excerpt_t = is_string($post->post_excerpt) ? $post->post_excerpt : '';
        } else {
            $html = $post->post_content;
            if (! is_string($html)) {
                $html = '';
            }

            $translated_body = TranslatePlus_API::translate_html($html, $target, $source_lang);
            if (is_wp_error($translated_body)) {
                wp_send_json_error(
                    array('message' => $translated_body->get_error_message()),
                    502
                );
            }

            $title_t = self::translate_plain_text($post->post_title, $target, $source_lang);
            $excerpt = is_string($post->post_excerpt) ? $post->post_excerpt : '';
            $excerpt_t = $excerpt !== ''
                ? self::translate_plain_text($excerpt, $target, $source_lang)
                : '';
            $body_use = wp_kses_post($translated_body);
        }

        $new_id = wp_insert_post(
            array(
                'post_type'    => $post->post_type,
                'post_status'  => 'draft',
                'post_title'   => $title_t,
                'post_name'    => sanitize_title(is_string($title_t) ? $title_t : ''),
                'post_content' => $body_use,
                'post_excerpt' => $excerpt_t,
                'post_author'  => get_current_user_id(),
                'post_parent'  => (int) $post->post_parent,
                'menu_order'   => (int) $post->menu_order,
            ),
            true
        );

        if (is_wp_error($new_id)) {
            wp_send_json_error(
                array('message' => $new_id->get_error_message()),
                500
            );
        }

        self::copy_taxonomies($post_id, (int) $new_id, $post->post_type);
        self::copy_post_meta_skipping((int) $post_id, (int) $new_id);

        update_post_meta((int) $new_id, TranslatePlus_Translation_Group::META_GROUP, $group_id);
        update_post_meta((int) $new_id, TranslatePlus_Translation_Group::META_LANGUAGE, $target);
        delete_post_meta((int) $new_id, '_tp_content_locale');

        $url = get_edit_post_link((int) $new_id, 'raw');
        if (! is_string($url) || $url === '') {
            wp_send_json_error(
                array('message' => __('Translation was created but the editor URL could not be built.', 'translateplus')),
                500
            );
        }

        wp_send_json_success(
            array(
                'redirect' => $url,
                'message'  => __('Translation created. Opening editor…', 'translateplus'),
                'post_id'  => (int) $new_id,
            )
        );
    }

    /**
     * Translate plain text via POST /v2/translate (title, excerpt, slug source).
     */
    private static function translate_plain_text(string $text, string $target, string $source): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $out = TranslatePlus_API::translate_text($text, $target, $source);
        if (is_wp_error($out)) {
            return $text;
        }

        $out = trim((string) $out);

        return $out !== '' ? $out : $text;
    }

    /**
     * @param int    $from_id Source post ID.
     * @param int    $to_id   New post ID.
     * @param string $post_type Post type.
     */
    /**
     * Used when automatic sync creates new linked drafts (same rules as manual “Add translation”).
     *
     * @param int    $from_id   Source post ID.
     * @param int    $to_id     New post ID.
     * @param string $post_type Post type slug.
     */
    public static function copy_assets_for_new_translation(int $from_id, int $to_id, string $post_type): void {
        self::copy_taxonomies($from_id, $to_id, $post_type);
        self::copy_post_meta_skipping($from_id, $to_id);
    }

    private static function copy_taxonomies(int $from_id, int $to_id, string $post_type): void {
        $taxonomies = get_object_taxonomies($post_type, 'names');
        foreach ($taxonomies as $tax) {
            if (! is_string($tax) || $tax === '') {
                continue;
            }
            $terms = wp_get_object_terms($from_id, $tax, array('fields' => 'ids'));
            if (is_wp_error($terms) || $terms === array()) {
                continue;
            }
            wp_set_object_terms($to_id, array_map('intval', $terms), $tax);
        }
    }

    /**
     * Copy all post meta except internal / translation fields.
     */
    private static function copy_post_meta_skipping(int $from_id, int $to_id): void {
        $all = get_post_custom($from_id);
        if (! is_array($all)) {
            return;
        }

        foreach ($all as $meta_key => $values) {
            if (! is_string($meta_key) || in_array($meta_key, self::META_SKIP_ON_TRANSLATE_COPY, true)) {
                continue;
            }
            if (! is_array($values)) {
                continue;
            }
            foreach ($values as $meta_value) {
                add_post_meta($to_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
    }

    /**
     * Inject fetch() click handler on post / page edit screens only.
     */
    public static function print_footer_script(): void {
        if (! function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen || ! in_array($screen->base, array('post', 'post-new'), true)) {
            return;
        }

        if (! in_array($screen->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        // Meta box “Available & linked languages” is always registered; buttons need this script even when
        // the optional toolbar / classic tabs are hidden via editor manual UI setting.
        global $post;
        $edited = null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen context.
        if (isset($_GET['post'])) {
            $maybe = get_post(absint(wp_unslash($_GET['post'])));
            if ($maybe instanceof WP_Post && $maybe->post_type === $screen->post_type) {
                $edited = $maybe;
            }
        }
        if ($edited === null && $post instanceof WP_Post && $post->post_type === $screen->post_type) {
            $edited = $post;
        }
        if ($edited === null || (int) $edited->ID <= 0) {
            return;
        }

        $post_id = (int) $edited->ID;

        $switcher_cfg   = TranslatePlus_Translation_Group::get_language_switcher_config($edited);
        $missing_codes  = array();
        foreach ($switcher_cfg['items'] as $item) {
            if (! empty($item['missing'])) {
                $missing_codes[] = $item['code'];
            }
        }

        $src_norm = TranslatePlus_Languages::normalize(TranslatePlus_Translation_Group::get_post_language($post_id));
        $root     = TranslatePlus_Translation_Group::get_group_root_language($post_id);
        $exclude_translate_now = array();
        if ($src_norm !== null && $src_norm !== '' && $src_norm !== 'auto') {
            $exclude_translate_now[] = $src_norm;
        }
        if ($root !== null && $root !== '' && ! in_array($root, $exclude_translate_now, true)) {
            $exclude_translate_now[] = $root;
        }

        $config = array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce(self::NONCE_TP_TRANSLATE_POST),
            'postId'         => $post_id,
            'action'         => self::ACTION_TP_TRANSLATE_POST,
            'sourceLanguage' => TranslatePlus_Translation_Group::get_post_language($post_id),
            'excludeLanguagesTranslateNow' => array_values(array_unique($exclude_translate_now)),
            'languages'      => TranslatePlus_Translation_Group::locale_choices(),
            'switcherMissingLangs' => $missing_codes,
            'i18n'           => array(
                'modalTitle'     => __('Translate to…', 'translateplus'),
                'modalTitleAdd'  => __('Add translation', 'translateplus'),
                'selectLabel'    => __('Target language', 'translateplus'),
                'submit'         => __('Continue', 'translateplus'),
                'submitCreate'   => __('Create translation', 'translateplus'),
                'cancel'         => __('Cancel', 'translateplus'),
                'pickOne'        => __('Select a language.', 'translateplus'),
                'needSave'       => __('Save the post first, then use Translate Now.', 'translateplus'),
                'noTargets'      => __('No other languages are available. Add languages under Settings → TranslatePlus.', 'translateplus'),
                'noMissingLangs' => __('Every configured language already has a linked post for this group.', 'translateplus'),
            ),
        );

        ?>
        <div id="translateplus-translate-modal" class="translateplus-modal-overlay" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;padding:20px;box-sizing:border-box;" aria-hidden="true">
            <div class="translateplus-modal-card" role="dialog" aria-modal="true" aria-labelledby="translateplus-translate-modal-title" style="background:#fff;max-width:420px;width:100%;border-radius:4px;box-shadow:0 4px 24px rgba(0,0,0,0.2);padding:20px 22px;">
                <h2 id="translateplus-translate-modal-title" style="margin:0 0 14px;font-size:1.15em;"><?php esc_html_e('Translate to…', 'translateplus'); ?></h2>
                <p style="margin:0 0 8px;">
                    <label for="translateplus-target-lang-select" style="display:block;font-weight:600;margin-bottom:6px;"><?php esc_html_e('Target language', 'translateplus'); ?></label>
                    <select id="translateplus-target-lang-select" class="widefat" style="width:100%;max-width:100%;box-sizing:border-box;"></select>
                </p>
                <p class="translateplus-modal-actions" style="margin:18px 0 0;display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" class="button" id="translateplus-translate-cancel"><?php esc_html_e('Cancel', 'translateplus'); ?></button>
                    <button type="button" class="button button-primary" id="translateplus-translate-submit"><?php esc_html_e('Continue', 'translateplus'); ?></button>
                </p>
            </div>
        </div>
        <script id="translateplus-translate-now-handler">
        (function () {
            var cfg = <?php echo wp_json_encode($config); ?>;

            var overlay = document.getElementById('translateplus-translate-modal');
            var select = document.getElementById('translateplus-target-lang-select');
            var btnSubmit = document.getElementById('translateplus-translate-submit');
            var btnCancel = document.getElementById('translateplus-translate-cancel');
            var modalTitleEl = document.getElementById('translateplus-translate-modal-title');
            var modalContext = 'translate_now';
            var requestBusy = false;

            function getPostId() {
                if (window.wp && wp.data && wp.data.select) {
                    try {
                        var id = wp.data.select('core/editor').getCurrentPostId();
                        if (id && id > 0) {
                            return id;
                        }
                    } catch (e) {}
                }
                return cfg.postId > 0 ? cfg.postId : 0;
            }

            function fillSelect(context) {
                if (!select) {
                    return 0;
                }
                select.innerHTML = '';
                var src = (cfg.sourceLanguage || 'en').toLowerCase();
                var langs = cfg.languages || {};
                var added = 0;
                var allow = null;
                var exclude = {};
                if (context === 'translate_now') {
                    (cfg.excludeLanguagesTranslateNow || []).forEach(function (c) {
                        exclude[String(c).toLowerCase()] = true;
                    });
                } else {
                    exclude[src] = true;
                }
                if (context === 'switcher') {
                    allow = {};
                    (cfg.switcherMissingLangs || []).forEach(function (c) {
                        allow[String(c).toLowerCase()] = true;
                    });
                }
                Object.keys(langs).forEach(function (code) {
                    if (exclude[code.toLowerCase()]) {
                        return;
                    }
                    if (allow && !allow[code.toLowerCase()]) {
                        return;
                    }
                    var opt = document.createElement('option');
                    opt.value = code;
                    opt.textContent = langs[code] ? langs[code] + ' (' + code.toUpperCase() + ')' : code.toUpperCase();
                    select.appendChild(opt);
                    added++;
                });
                return added;
            }

            function openTranslateModal(preselectCode, context) {
                if (!overlay || !select) {
                    return;
                }
                context = context || 'translate_now';
                modalContext = context;
                var n = fillSelect(context);
                if (n < 1) {
                    if (context === 'switcher') {
                        window.alert(
                            cfg.i18n && cfg.i18n.noMissingLangs ? cfg.i18n.noMissingLangs : 'No languages to add'
                        );
                    } else {
                        window.alert(cfg.i18n && cfg.i18n.noTargets ? cfg.i18n.noTargets : 'No languages');
                    }
                    return;
                }
                if (modalTitleEl && cfg.i18n) {
                    modalTitleEl.textContent =
                        context === 'switcher' && cfg.i18n.modalTitleAdd
                            ? cfg.i18n.modalTitleAdd
                            : cfg.i18n.modalTitle || '';
                }
                if (btnSubmit && cfg.i18n) {
                    btnSubmit.textContent =
                        context === 'switcher' && cfg.i18n.submitCreate
                            ? cfg.i18n.submitCreate
                            : cfg.i18n.submit || '';
                }
                if (preselectCode) {
                    select.value = preselectCode;
                    if (select.value !== preselectCode) {
                        var i;
                        for (i = 0; i < select.options.length; i++) {
                            if (select.options[i].value === preselectCode) {
                                select.selectedIndex = i;
                                break;
                            }
                        }
                    }
                }
                overlay.style.display = 'flex';
                overlay.setAttribute('aria-hidden', 'false');
                select.focus();
            }

            window.translateplusOpenTranslateModal = function (preselectCode, context) {
                openTranslateModal(preselectCode || '', context || 'translate_now');
            };

            function closeModal() {
                if (!overlay) {
                    return;
                }
                overlay.style.display = 'none';
                overlay.setAttribute('aria-hidden', 'true');
                if (btnSubmit && cfg.i18n && cfg.i18n.submit) {
                    btnSubmit.textContent = cfg.i18n.submit;
                }
            }

            /**
             * Create or open translation: server ensures group ID, new draft, meta (no extra prompts).
             * @param {number} postId
             * @param {string} targetLang
             */
            function submitTranslation(postId, targetLang) {
                if (requestBusy) {
                    return;
                }
                if (postId <= 0) {
                    window.alert(cfg.i18n && cfg.i18n.needSave ? cfg.i18n.needSave : 'Save first');
                    return;
                }
                if (!targetLang) {
                    window.alert(cfg.i18n && cfg.i18n.pickOne ? cfg.i18n.pickOne : 'Pick one');
                    return;
                }
                requestBusy = true;
                if (btnSubmit) {
                    btnSubmit.disabled = true;
                }
                document.body.style.cursor = 'wait';

                var body = new URLSearchParams();
                body.set('action', cfg.action);
                body.set('nonce', cfg.nonce);
                body.set('post_id', String(postId));
                body.set('target_lang', String(targetLang));
                body.set('content_mode', 'translate');

                fetch(cfg.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: body.toString(),
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (payload) {
                        if (payload && payload.success === true && payload.data && payload.data.redirect) {
                            closeModal();
                            window.location.href = payload.data.redirect;
                            return;
                        }
                        var err =
                            payload && payload.data && payload.data.message
                                ? payload.data.message
                                : '<?php echo esc_js(__('Translation request failed.', 'translateplus')); ?>';
                        window.alert(err);
                        console.error('TranslatePlus AJAX:', payload);
                    })
                    .catch(function () {
                        window.alert('<?php echo esc_js(__('Network error.', 'translateplus')); ?>');
                    })
                    .finally(function () {
                        requestBusy = false;
                        if (btnSubmit) {
                            btnSubmit.disabled = false;
                        }
                        document.body.style.cursor = '';
                    });
            }

            function runTranslate() {
                if (!select) {
                    return;
                }
                submitTranslation(getPostId(), select.value);
            }

            document.addEventListener('click', function (event) {
                var target = event.target;
                if (!target || !target.closest) {
                    return;
                }
                var sw = target.closest(
                    '#translateplus-lang-switcher, .translateplus-lang-actions-root, .translateplus-translation-overview'
                );
                if (sw) {
                    var addT = target.closest('.translateplus-add-translation');
                    var miss = target.closest('.translateplus-lang-missing');
                    if (addT) {
                        event.preventDefault();
                        var pidAdd = getPostId();
                        if (pidAdd <= 0) {
                            window.alert(cfg.i18n && cfg.i18n.needSave ? cfg.i18n.needSave : 'Save first');
                            return;
                        }
                        var nMissing = fillSelect('switcher');
                        if (nMissing < 1) {
                            window.alert(
                                cfg.i18n && cfg.i18n.noMissingLangs ? cfg.i18n.noMissingLangs : 'No languages to add'
                            );
                            return;
                        }
                        if (nMissing === 1 && select.options[0]) {
                            submitTranslation(pidAdd, select.options[0].value);
                            return;
                        }
                        openTranslateModal('', 'switcher');
                        return;
                    }
                    if (miss) {
                        event.preventDefault();
                        var langCode = (miss.getAttribute('data-tp-pick-lang') || '').trim();
                        if (!langCode) {
                            return;
                        }
                        submitTranslation(getPostId(), langCode);
                        return;
                    }
                }
                var classic = target.closest('#translateplus-translate-now-classic');
                var blockBtn = target.closest('.translateplus-translate-now-button');
                if (!classic && !blockBtn) {
                    return;
                }
                event.preventDefault();
                openTranslateModal();
            });

            if (btnCancel) {
                btnCancel.addEventListener('click', closeModal);
            }
            if (btnSubmit) {
                btnSubmit.addEventListener('click', runTranslate);
            }
            if (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay) {
                        closeModal();
                    }
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay && overlay.style.display === 'flex') {
                    closeModal();
                }
            });
        })();
        </script>
        <?php
    }
}
