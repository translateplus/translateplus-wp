<?php
/**
 * TranslatePlus HTTP API client (wp_remote_post).
 *
 * @package TranslatePlus
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * TranslatePlus HTTP API client (translate + account).
 */
final class TranslatePlus_API {

    public const ENDPOINT = 'https://api.translateplus.io/v2/translate/html';

    public const TEXT_ENDPOINT = 'https://api.translateplus.io/v2/translate';

    public const ACCOUNT_SUMMARY_ENDPOINT = 'https://api.translateplus.io/v2/account/summary';

    public const OPTION_API_KEY = 'translateplus_api_key';

    /**
     * Unix time of last successful account summary API fetch.
     */
    public const OPTION_LAST_SYNC = 'translateplus_last_sync';

    private const ACCOUNT_SUMMARY_TRANSIENT = 'translateplus_account_summary';

    /**
     * Account summary transient lifetime (seconds). API runs only when this expires (unless forced).
     */
    private const ACCOUNT_SUMMARY_TTL = 5 * MINUTE_IN_SECONDS;

    /**
     * Default source language for API requests.
     */
    public const DEFAULT_SOURCE = 'en';

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT = 45;

    /**
     * Translate HTML via TranslatePlus API.
     *
     * @param string $html        HTML payload (e.g. post content).
     * @param string $target_lang Target language code (e.g. fr, es).
     * @param string $source_lang Source language code.
     * @return string|WP_Error Translated HTML or error.
     */
    public static function translate_html(string $html, string $target_lang, string $source_lang = self::DEFAULT_SOURCE) {
        $api_key = get_option(self::OPTION_API_KEY, '');
        if (! is_string($api_key) || $api_key === '') {
            return new WP_Error(
                'translateplus_no_api_key',
                __('TranslatePlus API key is not set. Add it under Settings → TranslatePlus.', 'translateplus')
            );
        }

        $target_norm = TranslatePlus_Languages::normalize($target_lang);
        $source_norm = TranslatePlus_Languages::normalize($source_lang);

        if ($target_norm === null || ! TranslatePlus_Languages::is_valid_target($target_norm)) {
            return new WP_Error(
                'translateplus_invalid_lang',
                __('Invalid target language.', 'translateplus')
            );
        }

        if ($source_norm === null || ! TranslatePlus_Languages::is_valid_source($source_norm)) {
            return new WP_Error(
                'translateplus_invalid_lang',
                __('Invalid source language.', 'translateplus')
            );
        }

        $payload = array(
            'html'   => $html,
            'source' => $source_norm,
            'target' => $target_norm,
        );

        $response = wp_remote_post(
            self::ENDPOINT,
            array(
                'timeout' => self::TIMEOUT,
                'headers' => array(
                    'X-API-KEY'     => $api_key,
                    'Content-Type'  => 'application/json; charset=utf-8',
                    'Accept'        => 'application/json',
                ),
                'body'    => wp_json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return new WP_Error(
                'translateplus_bad_json',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('Invalid JSON from TranslatePlus API (HTTP %d).', 'translateplus'),
                    (int) $status
                ),
                array('status' => $status, 'body' => $body)
            );
        }

        if ($status >= 200 && $status < 300 && isset($decoded['html']) && is_string($decoded['html'])) {
            return $decoded['html'];
        }

        $message = self::extract_error_message($decoded, $status, $body);

        return new WP_Error(
            'translateplus_api_error',
            $message,
            array('status' => $status, 'response' => $decoded)
        );
    }

    /**
     * Plain-text translation via POST /v2/translate.
     *
     * @param string $text        Plain text (e.g. title, excerpt).
     * @param string $target_lang Target language code.
     * @param string $source_lang Source language code.
     * @return string|WP_Error Translated string or error.
     */
    public static function translate_text(string $text, string $target_lang, string $source_lang = self::DEFAULT_SOURCE) {
        $api_key = get_option(self::OPTION_API_KEY, '');
        if (! is_string($api_key) || $api_key === '') {
            return new WP_Error(
                'translateplus_no_api_key',
                __('TranslatePlus API key is not set. Add it under Settings → TranslatePlus.', 'translateplus')
            );
        }

        $target_norm = TranslatePlus_Languages::normalize($target_lang);
        $source_norm = TranslatePlus_Languages::normalize($source_lang);

        if ($target_norm === null || ! TranslatePlus_Languages::is_valid_target($target_norm)) {
            return new WP_Error(
                'translateplus_invalid_lang',
                __('Invalid target language.', 'translateplus')
            );
        }

        if ($source_norm === null || ! TranslatePlus_Languages::is_valid_source($source_norm)) {
            return new WP_Error(
                'translateplus_invalid_lang',
                __('Invalid source language.', 'translateplus')
            );
        }

        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        $payload = array(
            'text'   => $text,
            'source' => $source_norm,
            'target' => $target_norm,
        );

        $response = wp_remote_post(
            self::TEXT_ENDPOINT,
            array(
                'timeout' => self::TIMEOUT,
                'headers' => array(
                    'X-API-KEY'     => $api_key,
                    'Content-Type'  => 'application/json; charset=utf-8',
                    'Accept'        => 'application/json',
                ),
                'body'    => wp_json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return new WP_Error(
                'translateplus_bad_json',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('Invalid JSON from TranslatePlus API (HTTP %d).', 'translateplus'),
                    (int) $status
                ),
                array('status' => $status, 'body' => $body)
            );
        }

        if ($status >= 200 && $status < 300) {
            $translation = null;
            if (isset($decoded['translations']) && is_array($decoded['translations'])) {
                $t = $decoded['translations'];
                if (isset($t['translation']) && is_string($t['translation'])) {
                    $translation = $t['translation'];
                }
            }
            if ($translation !== null && $translation !== '') {
                return $translation;
            }
        }

        $message = self::extract_error_message($decoded, $status, $body);

        return new WP_Error(
            'translateplus_api_error',
            $message,
            array('status' => $status, 'response' => $decoded)
        );
    }

    /**
     * Clear cached account summary (e.g. after API key changes).
     */
    public static function clear_account_summary_cache(): void {
        delete_transient(self::ACCOUNT_SUMMARY_TRANSIENT);
    }

    /**
     * GET /v2/account/summary — account stats for the given key.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function fetch_account_summary(string $api_key) {
        $api_key = trim($api_key);
        if ($api_key === '') {
            return new WP_Error(
                'translateplus_no_api_key',
                __('TranslatePlus API key is not set.', 'translateplus')
            );
        }

        $response = wp_remote_get(
            self::ACCOUNT_SUMMARY_ENDPOINT,
            array(
                'timeout' => self::TIMEOUT,
                'headers' => array(
                    'X-API-KEY'    => $api_key,
                    'Accept'       => 'application/json',
                ),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return new WP_Error(
                'translateplus_bad_json',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('Invalid JSON from TranslatePlus API (HTTP %d).', 'translateplus'),
                    (int) $status
                ),
                array('status' => $status, 'body' => $body)
            );
        }

        if ($status >= 200 && $status < 300) {
            return $decoded;
        }

        $message = self::extract_error_message($decoded, $status, $body);

        return new WP_Error(
            'translateplus_api_error',
            $message,
            array('status' => $status, 'response' => $decoded)
        );
    }

    /**
     * Account summary using the saved API key.
     *
     * Uses {@see set_transient()} for {@see self::ACCOUNT_SUMMARY_TTL} (5 minutes). The HTTP API is
     * called only when the transient is missing or expired. Pass $force_refresh true to bypass the cache read.
     *
     * @return array<string, mixed>|WP_Error
     */
    public static function get_account_summary(bool $force_refresh = false) {
        $api_key = get_option(self::OPTION_API_KEY, '');
        if (! is_string($api_key) || $api_key === '') {
            return new WP_Error(
                'translateplus_no_api_key',
                __('TranslatePlus API key is not set.', 'translateplus')
            );
        }

        if (! $force_refresh) {
            $cached = get_transient(self::ACCOUNT_SUMMARY_TRANSIENT);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $data = self::fetch_account_summary($api_key);
        if (! is_wp_error($data)) {
            set_transient(self::ACCOUNT_SUMMARY_TRANSIENT, $data, self::ACCOUNT_SUMMARY_TTL);
            update_option(self::OPTION_LAST_SYNC, time(), false);
        }

        return $data;
    }

    /**
     * Clear last sync timestamp (e.g. on disconnect). Removes legacy option key if present.
     */
    public static function clear_last_sync(): void {
        delete_option(self::OPTION_LAST_SYNC);
        delete_option('translateplus_last_connection_verified_at');
    }

    /**
     * Remaining credits from a summary payload, or null if unknown.
     *
     * @param array<string, mixed> $summary
     */
    public static function credits_remaining_from_summary(array $summary): ?float {
        if (! isset($summary['credits_remaining'])) {
            return null;
        }
        if (is_int($summary['credits_remaining']) || is_float($summary['credits_remaining'])) {
            return (float) $summary['credits_remaining'];
        }
        if (is_string($summary['credits_remaining']) && is_numeric($summary['credits_remaining'])) {
            return (float) $summary['credits_remaining'];
        }

        return null;
    }

    /**
     * True when the account has no credits left (0 or negative).
     *
     * @param array<string, mixed> $summary
     */
    public static function is_credits_depleted(array $summary): bool {
        $n = self::credits_remaining_from_summary($summary);

        return $n !== null && $n <= 0.0;
    }

    /**
     * Best-effort user-facing message from API error payload.
     *
     * @param array  $decoded Decoded JSON (may be error shape).
     * @param int    $status  HTTP status.
     * @param string $body    Raw body (truncated in message).
     */
    private static function extract_error_message(array $decoded, int $status, string $body): string {
        foreach (array('message', 'error', 'detail') as $key) {
            if (isset($decoded[ $key ]) && is_string($decoded[ $key ]) && $decoded[ $key ] !== '') {
                return sanitize_text_field($decoded[ $key ]);
            }
        }

        $snippet = function_exists('mb_substr') ? mb_substr($body, 0, 200) : substr($body, 0, 200);

        return sprintf(
            /* translators: 1: HTTP status, 2: response snippet */
            __('TranslatePlus API error (HTTP %1$d): %2$s', 'translateplus'),
            (int) $status,
            $snippet !== '' ? $snippet : __('No response body.', 'translateplus')
        );
    }
}
