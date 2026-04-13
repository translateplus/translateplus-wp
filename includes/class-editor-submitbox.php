<?php
/**
 * Editor UI: block editor sidebar panel + classic submit box fallback.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Renders the "Translate Now" control for block and classic editors.
 */
final class TranslatePlus_Editor_Submitbox {

    /**
     * Bootstrap hooks.
     */
    public static function init(): void {
        add_action('enqueue_block_editor_assets', array(self::class, 'enqueue_block_editor'));
        add_action('post_submitbox_misc_actions', array(self::class, 'render_translate_button'));
    }

    /**
     * Block editor: document sidebar panel (PluginDocumentSettingPanel).
     */
    public static function enqueue_block_editor(): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (! $screen || empty($screen->post_type)) {
            return;
        }

        if (! in_array($screen->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        if (! TranslatePlus_Settings::is_editor_manual_ui_enabled()) {
            return;
        }

        wp_enqueue_script(
            'translateplus-block-editor',
            plugins_url('assets/js/editor-document-panel.js', TRANSLATEPLUS_FILE),
            array(
                'wp-plugins',
                'wp-edit-post',
                'wp-element',
                'wp-components',
                'wp-data',
                'wp-i18n',
            ),
            TRANSLATEPLUS_VERSION,
            true
        );

        wp_localize_script(
            'translateplus-block-editor',
            'translateplusEditor',
            array(
                'postTypes' => TranslatePlus_Settings::get_translatable_post_types(),
            )
        );
    }

    /**
     * Classic editor: publish box misc section (hidden when block editor handles this post type).
     */
    public static function render_translate_button(): void {
        global $post;

        if (! $post instanceof WP_Post) {
            return;
        }

        if (! in_array($post->post_type, TranslatePlus_Settings::get_translatable_post_types(), true)) {
            return;
        }

        if (! TranslatePlus_Settings::is_editor_manual_ui_enabled()) {
            return;
        }

        if (function_exists('use_block_editor_for_post_type') && use_block_editor_for_post_type($post->post_type)) {
            return;
        }

        ?>
        <div class="misc-pub-section misc-pub-translateplus-translate-now">
            <button type="button" class="button button-large" id="translateplus-translate-now-classic">
                <?php esc_html_e('Translate Now', 'translateplus'); ?>
            </button>
        </div>
        <?php
    }
}
