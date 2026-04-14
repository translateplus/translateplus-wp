<?php
/**
 * TranslatePlus plugin bootstrap kernel.
 *
 * @package TranslatePlus
 */

namespace TranslatePlus\Core;

use TranslatePlus\Core\Module\ModuleLoader;
use TranslatePlus\Core\Module\ModuleRegistry;
use TranslatePlus\Modules\Admin\AdminModule;
use TranslatePlus\Modules\Frontend\FrontendModule;
use TranslatePlus\Modules\Translation\TranslationModule;

final class Bootstrap {

    private static ?self $instance = null;

    private Container $container;

    private ModuleLoader $module_loader;

    private bool $bootstrapped = false;

    /**
     * @var list<string>
     */
    private array $legacy_include_files = array(
        'includes/class-translate-languages.php',
        'includes/class-translate-api.php',
        'includes/class-translate-url-builder.php',
        'includes/class-translate-settings.php',
        'includes/class-editor-submitbox.php',
        'includes/class-translate-now-ajax.php',
        'includes/class-translation-group.php',
        'includes/class-translate-frontend-lang-dropdown.php',
        'includes/class-translate-seo.php',
        'includes/class-translate-string-translation.php',
        'includes/class-translate-nav-menu-meta-box.php',
        'includes/class-translate-admin-list.php',
        'includes/class-translate-auto-sync.php',
        'includes/class-translate-rewrites.php',
        'includes/class-translate-browser-lang-redirect.php',
        'includes/class-translate-query-main-en.php',
    );

    public static function run(string $plugin_file): void {
        $kernel = self::instance($plugin_file);
        $kernel->bootstrap();
    }

    public static function activate(): void {
        $kernel = self::instance(defined('TRANSLATEPLUS_FILE') ? TRANSLATEPLUS_FILE : __FILE__);
        $kernel->ensure_initialized();
        $kernel->module_loader->activate_all();
    }

    public static function deactivate(): void {
        $kernel = self::instance(defined('TRANSLATEPLUS_FILE') ? TRANSLATEPLUS_FILE : __FILE__);
        $kernel->ensure_initialized();
        $kernel->module_loader->deactivate_all();
    }

    private static function instance(string $plugin_file): self {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
        }

        return self::$instance;
    }

    private function __construct(string $plugin_file) {
        $this->define_constants($plugin_file);
        $this->container = new Container();
        $this->container->set('plugin_file', TRANSLATEPLUS_FILE);
        $this->container->set('plugin_path', TRANSLATEPLUS_PATH);
        $this->container->set('plugin_url', TRANSLATEPLUS_URL);
        $this->container->set('plugin_version', TRANSLATEPLUS_VERSION);
    }

    private function define_constants(string $plugin_file): void {
        if (! defined('TRANSLATEPLUS_FILE')) {
            define('TRANSLATEPLUS_FILE', $plugin_file);
        }
        if (! defined('TRANSLATEPLUS_PATH')) {
            define('TRANSLATEPLUS_PATH', plugin_dir_path(TRANSLATEPLUS_FILE));
        }
        if (! defined('TRANSLATEPLUS_URL')) {
            define('TRANSLATEPLUS_URL', plugin_dir_url(TRANSLATEPLUS_FILE));
        }
        if (! defined('TRANSLATEPLUS_VERSION')) {
            define('TRANSLATEPLUS_VERSION', '2.0.0');
        }
    }

    private function bootstrap(): void {
        if ($this->bootstrapped) {
            return;
        }

        $this->ensure_initialized();

        add_action(
            'plugins_loaded',
            function (): void {
                $this->module_loader->boot_all();
            }
        );

        $this->bootstrapped = true;
    }

    private function ensure_initialized(): void {
        if (isset($this->module_loader)) {
            return;
        }

        $this->load_kernel_files();
        $this->load_legacy_features();

        $registry = new ModuleRegistry();
        $registry->add(new FrontendModule());
        $registry->add(new TranslationModule());
        $registry->add(new AdminModule());

        $this->module_loader = new ModuleLoader($registry, $this->container);
        $this->module_loader->register_all();
    }

    private function load_kernel_files(): void {
        require_once TRANSLATEPLUS_PATH . 'src/Core/Module/ModuleInterface.php';
        require_once TRANSLATEPLUS_PATH . 'src/Core/Module/ModuleRegistry.php';
        require_once TRANSLATEPLUS_PATH . 'src/Core/Module/ModuleLoader.php';
        require_once TRANSLATEPLUS_PATH . 'src/Modules/Admin/Settings/SettingsHooks.php';
        require_once TRANSLATEPLUS_PATH . 'src/Modules/Admin/AdminModule.php';
        require_once TRANSLATEPLUS_PATH . 'src/Modules/Translation/TranslationModule.php';
        require_once TRANSLATEPLUS_PATH . 'src/Modules/Frontend/FrontendModule.php';
    }

    private function load_legacy_features(): void {
        foreach ($this->legacy_include_files as $file) {
            require_once TRANSLATEPLUS_PATH . $file;
        }
    }
}
