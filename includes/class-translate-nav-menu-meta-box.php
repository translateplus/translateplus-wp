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

    public static function init(): void {
        add_action('load-nav-menus.php', array(self::class, 'register_meta_box'));
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
        <p class="howto"><?php esc_html_e('Select “Language Switcher” below, then click “Add to Menu”.', 'translateplus'); ?></p>
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
}
