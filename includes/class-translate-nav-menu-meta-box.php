<?php
/**
 * Meta box on Appearance → Menus to add the language switcher as a menu item.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the "TranslatePlus" box with a checklist item wired to the core add-to-menu flow.
 */
final class TranslatePlus_Nav_Menu_Meta_Box {
    public const MENU_ITEM_META_KEY = '_translateplus_menu_item';

    /**
     * @return array<string, int>
     */
    public static function default_switcher_options(): array {
        return array(
            'hide_if_no_translation' => 0,
            'hide_current'           => 0,
            'show_flags'             => 1,
            'show_names'             => 1,
            'dropdown'               => 0,
        );
    }

    public static function init(): void {
        add_action('load-nav-menus.php', array(self::class, 'register_meta_box'));
        add_action('wp_nav_menu_item_custom_fields', array(self::class, 'render_menu_item_fields'), 10, 5);
        add_action('wp_update_nav_menu_item', array(self::class, 'save_menu_item_fields'), 10, 3);
    }

    public static function register_meta_box(): void {
        if (! current_user_can('edit_theme_options')) {
            return;
        }

        add_meta_box(
            'translateplus-add-lang-switcher',
            __('TranslatePlus', 'translateplus'),
            array(self::class, 'render_meta_box'),
            'nav-menus',
            'side',
            'default'
        );
    }

    /**
     * Output checklist + Add to Menu (same pattern as post type meta boxes).
     */
    public static function render_meta_box(): void {
        global $_nav_menu_placeholder, $nav_menu_selected_id;

        require_once ABSPATH . 'wp-admin/includes/class-walker-nav-menu-checklist.php';

        $_nav_menu_placeholder = ( 0 > $_nav_menu_placeholder ) ? (int) $_nav_menu_placeholder - 1 : -1;

        $item = (object) array(
            'ID'               => 0,
            'object_id'        => $_nav_menu_placeholder,
            'object'           => 'custom',
            'menu_item_parent' => 0,
            'type'             => 'custom',
            'post_title'       => __('Language Switcher', 'translateplus'),
            'post_type'        => 'nav_menu_item',
            'post_content'     => '',
            'post_excerpt'     => '',
            'url'              => TranslatePlus_Frontend_Lang_Dropdown::MENU_LANG_SWITCHER_URL,
        );

        $item = wp_setup_nav_menu_item($item);
        if (! is_object($item)) {
            return;
        }

        $item->label = __('Language Switcher', 'translateplus');

        $walker = new Walker_Nav_Menu_Checklist();

        // IDs must match WordPress nav-menu.js: click #submit-posttype-{slug} runs $('#posttype-{slug}').addSelectedToMenu().
        $post_type_box_id = 'translateplus-lang-switcher';
        ?>
        <div id="<?php echo esc_attr('posttype-' . $post_type_box_id); ?>" class="posttypediv">
            <div id="<?php echo esc_attr('tabs-panel-posttype-' . $post_type_box_id); ?>" class="tabs-panel tabs-panel-active" role="region"
                aria-label="<?php esc_attr_e('TranslatePlus', 'translateplus'); ?>" tabindex="0">
                <ul id="<?php echo esc_attr($post_type_box_id . 'checklist'); ?>" class="categorychecklist form-no-clear">
                    <?php
                    echo walk_nav_menu_tree(
                        array($item),
                        0,
                        (object) array('walker' => $walker)
                    );
                    ?>
                </ul>
            </div>
            <p class="button-controls" data-items-type="<?php echo esc_attr('posttype-' . $post_type_box_id); ?>">
                <span class="list-controls hide-if-no-js">
                    <input type="checkbox" id="<?php echo esc_attr($post_type_box_id . '-select-all'); ?>" class="select-all" <?php disabled($nav_menu_selected_id, 0); ?> />
                    <label for="<?php echo esc_attr($post_type_box_id . '-select-all'); ?>"><?php esc_html_e('Select All', 'translateplus'); ?></label>
                </span>
                <span class="add-to-menu">
                    <input type="submit" <?php wp_nav_menu_disabled_check($nav_menu_selected_id); ?>
                        class="button submit-add-to-menu right"
                        value="<?php esc_attr_e('Add to Menu', 'translateplus'); ?>"
                        name="add-post-type-menu-item"
                        id="<?php echo esc_attr('submit-posttype-' . $post_type_box_id); ?>"
                    />
                    <span class="spinner"></span>
                </span>
            </p>
        </div>
        <?php
    }

    /**
     * @param int     $item_id Menu item ID.
     * @param WP_Post $item    Menu item object.
     */
    public static function render_menu_item_fields(int $item_id, $item, int $depth, $args, int $id): void {
        if (! $item instanceof WP_Post) {
            return;
        }
        if (! TranslatePlus_Frontend_Lang_Dropdown::menu_item_is_language_switcher($item)) {
            return;
        }

        $options = self::get_menu_item_options($item_id);
        ?>
        <p class="field-translateplus-switcher description description-wide">
            <strong><?php esc_html_e('TranslatePlus language switcher options', 'translateplus'); ?></strong>
        </p>
        <p class="field-translateplus-switcher description">
            <label>
                <input type="checkbox" name="menu-item-translateplus-hide-current[<?php echo esc_attr((string) $item_id); ?>]" value="1" <?php checked((int) $options['hide_current'], 1); ?> />
                <?php esc_html_e('Hide current language', 'translateplus'); ?>
            </label>
            <br />
            <label>
                <input type="checkbox" name="menu-item-translateplus-hide-no-translation[<?php echo esc_attr((string) $item_id); ?>]" value="1" <?php checked((int) $options['hide_if_no_translation'], 1); ?> />
                <?php esc_html_e('Hide languages without translation', 'translateplus'); ?>
            </label>
            <br />
            <label>
                <input type="checkbox" name="menu-item-translateplus-show-flags[<?php echo esc_attr((string) $item_id); ?>]" value="1" <?php checked((int) $options['show_flags'], 1); ?> />
                <?php esc_html_e('Show flags', 'translateplus'); ?>
            </label>
            <br />
            <label>
                <input type="checkbox" name="menu-item-translateplus-show-names[<?php echo esc_attr((string) $item_id); ?>]" value="1" <?php checked((int) $options['show_names'], 1); ?> />
                <?php esc_html_e('Show language names', 'translateplus'); ?>
            </label>
            <br />
            <label>
                <input type="checkbox" name="menu-item-translateplus-dropdown[<?php echo esc_attr((string) $item_id); ?>]" value="1" <?php checked((int) $options['dropdown'], 1); ?> />
                <?php esc_html_e('Display as dropdown submenu', 'translateplus'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * @param int $menu_id         Menu ID.
     * @param int $menu_item_db_id Menu item DB ID.
     * @param array<string, mixed> $args Args.
     */
    public static function save_menu_item_fields(int $menu_id, int $menu_item_db_id, array $args): void {
        if (! current_user_can('edit_theme_options')) {
            return;
        }
        if (! isset($_REQUEST['update-nav-menu-nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['update-nav-menu-nonce'])), 'update-nav_menu')) {
            return;
        }

        $item = get_post($menu_item_db_id);
        if (! $item instanceof WP_Post || ! TranslatePlus_Frontend_Lang_Dropdown::menu_item_is_language_switcher($item)) {
            return;
        }

        $options = self::default_switcher_options();
        $options['hide_current'] = self::is_checked_for_item('menu-item-translateplus-hide-current', $menu_item_db_id) ? 1 : 0;
        $options['hide_if_no_translation'] = self::is_checked_for_item('menu-item-translateplus-hide-no-translation', $menu_item_db_id) ? 1 : 0;
        $options['show_flags'] = self::is_checked_for_item('menu-item-translateplus-show-flags', $menu_item_db_id) ? 1 : 0;
        $options['show_names'] = self::is_checked_for_item('menu-item-translateplus-show-names', $menu_item_db_id) ? 1 : 0;
        $options['dropdown'] = self::is_checked_for_item('menu-item-translateplus-dropdown', $menu_item_db_id) ? 1 : 0;

        update_post_meta($menu_item_db_id, self::MENU_ITEM_META_KEY, $options);
    }

    /**
     * @return array<string, int>
     */
    public static function get_menu_item_options(int $menu_item_id): array {
        $defaults = self::default_switcher_options();
        $saved = get_post_meta($menu_item_id, self::MENU_ITEM_META_KEY, true);
        if (! is_array($saved)) {
            return $defaults;
        }

        return array(
            'hide_if_no_translation' => ! empty($saved['hide_if_no_translation']) ? 1 : 0,
            'hide_current'           => ! empty($saved['hide_current']) ? 1 : 0,
            'show_flags'             => ! empty($saved['show_flags']) ? 1 : 0,
            'show_names'             => ! empty($saved['show_names']) ? 1 : 0,
            'dropdown'               => ! empty($saved['dropdown']) ? 1 : 0,
        );
    }

    private static function is_checked_for_item(string $field_name, int $menu_item_id): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in save_menu_item_fields().
        if (! isset($_POST[ $field_name ]) || ! is_array($_POST[ $field_name ])) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in save_menu_item_fields().
        $map = wp_unslash($_POST[ $field_name ]);

        return isset($map[ $menu_item_id ]) && (string) $map[ $menu_item_id ] === '1';
    }
}
