<?php
/**
 * Supported language codes from assets/js/languages.json (TranslatePlus API).
 *
 * Use source "auto" for automatic source-language detection.
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Loads API language map; normalizes codes for requests and post meta.
 */
final class TranslatePlus_Languages {

    private const JSON_RELATIVE = 'assets/js/languages.json';

    /**
     * @var array<string, string>|null code => English label (API canonical casing)
     */
    private static $by_code = null;

    /**
     * @var array<string, string>|null lowercase key => canonical code
     */
    private static $lower_to_canonical = null;

    /**
     * @return array<string, string> code => label (includes "auto")
     */
    public static function get_code_to_label(): array {
        self::load();

        return self::$by_code;
    }

    /**
     * Labels for Settings checkboxes: all API languages except "auto" (cannot be a translation target).
     *
     * @return array<string, string>
     */
    public static function get_target_codes_with_labels(): array {
        $all = self::get_code_to_label();
        unset($all['auto']);

        return $all;
    }

    /**
     * @return string|null Canonical code or null if unknown.
     */
    public static function normalize(string $code): ?string {
        self::load();

        $key = self::lower_key($code);
        if ($key === 'auto') {
            return 'auto';
        }

        if (isset(self::$lower_to_canonical[ $key ])) {
            return self::$lower_to_canonical[ $key ];
        }

        return null;
    }

    /**
     * Whether the code is allowed as translation target (API language, not auto).
     */
    public static function is_valid_target(string $code): bool {
        $n = self::normalize($code);
        if ($n === null || $n === '' || $n === 'auto') {
            return false;
        }

        return isset(self::get_target_codes_with_labels()[ $n ]);
    }

    /**
     * Whether the code is allowed as API source (any supported language or auto-detect).
     */
    public static function is_valid_source(string $code): bool {
        $n = self::normalize($code);
        if ($n === null || $n === '') {
            return false;
        }

        return isset(self::get_code_to_label()[ $n ]);
    }

    private static function load(): void {
        if (self::$by_code !== null) {
            return;
        }

        $path = TRANSLATEPLUS_PATH . self::JSON_RELATIVE;
        $raw  = array();

        if (is_readable($path)) {
            $json = file_get_contents($path);
            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $label => $code) {
                        if (! is_string($code) || $code === '' || ! is_string($label)) {
                            continue;
                        }
                        $code = trim($code);
                        if ($code === '') {
                            continue;
                        }
                        if (! isset($raw[ $code ])) {
                            $raw[ $code ] = $label;
                        }
                    }
                }
            }
        }

        if ($raw === array()) {
            $raw = self::fallback_map();
        }

        self::$by_code            = $raw;
        self::$lower_to_canonical = array();

        foreach (array_keys(self::$by_code) as $canonical) {
            self::$lower_to_canonical[ self::lower_key($canonical) ] = $canonical;
        }

        // Common legacy / alias codes stored in older sites.
        self::$lower_to_canonical['zh'] = 'zh-CN';
        self::$lower_to_canonical['he'] = 'iw';
    }

    private static function lower_key(string $code): string {
        $code = trim(str_replace('_', '-', $code));

        return strtolower($code);
    }

    /**
     * Minimal map if languages.json is missing.
     *
     * @return array<string, string>
     */
    private static function fallback_map(): array {
        return array(
            'auto' => 'Auto Detect',
            'en'   => 'English',
            'es'   => 'Spanish',
            'fr'   => 'French',
            'de'   => 'German',
            'it'   => 'Italian',
            'pt'   => 'Portuguese',
            'nl'   => 'Dutch',
            'pl'   => 'Polish',
            'ru'   => 'Russian',
            'ja'   => 'Japanese',
            'ko'   => 'Korean',
            'zh-CN' => 'Chinese (Simplified)',
            'zh-TW' => 'Chinese (Traditional)',
            'ar'   => 'Arabic',
        );
    }
}
