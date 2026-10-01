<?php

if (!defined('ABSPATH')) {
    exit;
}

// -------------------------------------
// インクルード
// -------------------------------------
foreach (array(
             // 'Page', このプロジェクトでは不要
             // 'Json', このプロジェクトでは不要
             // 'Batch', このプロジェクトでは不要
             'Pagination',
             'Util',
             'TermModelHelper',
             'PostModelHelper',
             'PostViewHelper',
             ) as $class_file) {
    require_once get_theme_file_path("class/{$class_file}.php");
}

require_once get_theme_file_path('class/call_bridge_api.php');

foreach (array(
            'global',// テンプレートで共通利用するグローバル変数
             'lutwiyo_util',
             'gutenberg',
             'acf_comment_policy',
             'acf_paid_article_policy',
             'weather',
             'coupon',
             'memberblog') as $include_file) {
    require_once get_theme_file_path("inc/{$include_file}.php");
}



// -------------------------------------
// 定数
// -------------------------------------
define('JSON_DIR',rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'). '/assets/json');
const DEFAULT_STAFF_IMAGE_URL = 'default_staff_image.png';
const DEFAULT_PRESENT_LIST_URL = '/present-entry/';
const RANKING_RANGE = 'last7days';
const POSTS_PER_PAGE = 20;
const NEW_ARRIVAL_DAYS = 3; // 新着記事とみなす日数
const PLACEHOLDER_IMAGE_URL = '/assets/img/common/tokk--noimage.jpg';
const FAVORITE_LIST_COOKIE_NAME = 'favlist';
const ARTICLE_LIST_DISPLAY_LIMIT = 5; // 記事一覧の表示上限数

/**
 * 定数または環境変数から feature flag を解決する。
 *
 * @param string $flagName
 * @return mixed
 */
function tokk_member_service_config_value(string $flagName)
{
    if (defined($flagName)) {
        return constant($flagName);
    }

    $envValue = getenv($flagName);
    if ($envValue !== false) {
        return $envValue;
    }

    return null;
}

/**
 * 定数または環境変数から feature flag を解決する。
 *
 * @param string $flagName
 * @return bool
 */
function tokk_member_service_flag_enabled(string $flagName): bool
{
    $rawValue = tokk_member_service_config_value($flagName);

    if (is_bool($rawValue)) {
        return $rawValue;
    }

    if (is_int($rawValue) || is_float($rawValue)) {
        return (int) $rawValue === 1;
    }

    if (!is_string($rawValue)) {
        return false;
    }

    return in_array(strtolower(trim($rawValue)), ['1', 'true', 'on', 'yes'], true);
}

/**
 * 定数または環境変数から member-service 用の文字列設定を解決する。
 *
 * @param string $settingName
 * @return string
 */
function tokk_member_service_string_config(string $settingName): string
{
    $rawValue = tokk_member_service_config_value($settingName);

    if (!is_scalar($rawValue) || is_bool($rawValue)) {
        return '';
    }

    return trim((string) $rawValue);
}

/**
 * member-service 呼び出しメトリクスログの有効状態を返す。
 */
function tokk_member_service_request_metrics_enabled(): bool
{
    return tokk_member_service_flag_enabled('TOKK_MEMBER_SERVICE_REQUEST_METRICS_ENABLED');
}

/**
 * member-service 呼び出しメトリクスの専用ログファイル出力先を返す。
 *
 * - `TOKK_MEMBER_SERVICE_METRICS_LOG_PATH` 定数または環境変数で指定する。
 * - `{date}` は `Y-m-d`、`{host}` は現在ホストへ展開する。
 * - 相対パス指定時は `ABSPATH` 基準で解決する。
 */
function tokk_member_service_metrics_log_path(): string
{
    $path = tokk_member_service_string_config('TOKK_MEMBER_SERVICE_METRICS_LOG_PATH');
    if ($path === '') {
        return '';
    }

    $host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])
        ? strtolower(trim((string) $_SERVER['HTTP_HOST']))
        : '';
    $normalizedHost = preg_replace('/[^a-z0-9._-]+/i', '-', $host);
    if (!is_string($normalizedHost) || $normalizedHost === '') {
        $normalizedHost = 'unknown-host';
    }

    $formattedDate = function_exists('wp_date')
        ? wp_date('Y-m-d')
        : date('Y-m-d');

    $path = strtr($path, [
        '{date}' => $formattedDate,
        '{host}' => $normalizedHost,
    ]);

    if ($path === '') {
        return '';
    }

    if ($path[0] !== '/' && defined('ABSPATH')) {
        $path = rtrim((string) ABSPATH, '/') . '/' . ltrim($path, '/');
    }

    return $path;
}

/**
 * お気に入り件数取得停止フラグの有効状態を返す。
 */
function tokk_member_service_disable_favorite_count(): bool
{
    return tokk_member_service_flag_enabled('TOKK_MEMBER_SERVICE_DISABLE_FAVORITE_COUNT');
}

/**
 * 記事一覧 favorite 状態取得停止フラグの有効状態を返す。
 */
function tokk_member_service_disable_list_favorite_state(): bool
{
    return tokk_member_service_flag_enabled('TOKK_MEMBER_SERVICE_DISABLE_LIST_FAVORITE_STATE');
}

/**
 * 記事一覧 favorite ボタンの描画可否を返す。
 */
function tokk_member_service_should_render_list_favorite_button(): bool
{
    return !tokk_member_service_disable_list_favorite_state();
}

/**
 * 会員ブログ公開一覧 favorite 状態取得停止フラグの有効状態を返す。
 */
function tokk_member_service_disable_memberblog_list_favorite_state(): bool
{
    return tokk_member_service_flag_enabled('TOKK_MEMBER_SERVICE_DISABLE_MEMBERBLOG_LIST_FAVORITE_STATE');
}

/**
 * ゲスト向け favorite 系 short-circuit フラグの有効状態を返す。
 */
function tokk_member_service_short_circuit_guest_favorites(): bool
{
    return tokk_member_service_flag_enabled('TOKK_MEMBER_SERVICE_SHORT_CIRCUIT_GUEST_FAVORITES');
}

/**
 * member-context 統合APIを利用するかどうかを返す。
 * - wp-config.php の `TOKK_MEMBER_CONTEXT_ENABLED=true` で有効化する。
 */
function tokk_member_context_enabled(): bool
{
    return tokk_member_service_flag_enabled('TOKK_MEMBER_CONTEXT_ENABLED');
}

/**
 * 記事詳細閲覧コンテキスト統合APIを利用するかどうかを返す。
 * - `TOKK_ARTICLE_VIEWER_CONTEXT_ENABLED` が未指定の場合は member-context フラグへ追従する。
 */
function tokk_article_viewer_context_enabled(): bool
{
    if (tokk_member_service_config_value('TOKK_ARTICLE_VIEWER_CONTEXT_ENABLED') !== null) {
        return tokk_member_service_flag_enabled('TOKK_ARTICLE_VIEWER_CONTEXT_ENABLED');
    }

    return tokk_member_context_enabled();
}

/**
 * member-service 計測チェック固定ページを自動用意してよい dev ホストかを返す。
 */
function tokk_should_prepare_member_service_metrics_check_page(): bool
{
    $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    $normalizedHost = strtolower(trim($host));

    if ($normalizedHost === '') {
        return false;
    }

    return str_starts_with($normalizedHost, 'dev-')
        || str_contains($normalizedHost, '.local')
        || $normalizedHost === 'localhost';
}

/**
 * dev 環境向けに member-service 計測チェック固定ページを自動作成・補正する。
 */
function tokk_ensure_member_service_metrics_check_page(): void
{
    if (!is_admin()) {
        return;
    }

    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!tokk_should_prepare_member_service_metrics_check_page()) {
        return;
    }

    $pageSlug = 'member-service-metrics-check';
    $pageTitle = 'member-service 計測チェック';
    $pageTemplate = 'page-member-service-metrics-check.php';

    $existingPage = get_page_by_path($pageSlug, OBJECT, 'page');
    if ($existingPage instanceof WP_Post) {
        $currentTemplate = (string) get_post_meta($existingPage->ID, '_wp_page_template', true);
        if ($currentTemplate !== $pageTemplate) {
            update_post_meta($existingPage->ID, '_wp_page_template', $pageTemplate);
        }

        if ((string) $existingPage->post_title !== $pageTitle) {
            wp_update_post([
                'ID' => $existingPage->ID,
                'post_title' => $pageTitle,
            ]);
        }

        return;
    }

    $pageId = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $pageTitle,
        'post_name' => $pageSlug,
        'post_content' => '',
    ], true);

    if (is_wp_error($pageId) || $pageId <= 0) {
        return;
    }

    update_post_meta($pageId, '_wp_page_template', $pageTemplate);
}

add_action('admin_init', 'tokk_ensure_member_service_metrics_check_page');

/**
 * テーマ内で利用する Cookie の統一オプションを返す。
 *
 * - Secure=true を固定し、HTTPS 経由でのみ送信する。
 * - SameSite=Lax を固定し、外部サイト起点の不正送信リスクを下げる。
 *
 * @return array<string, mixed>
 */
function lutwiyo_cookie_options(int $expiresAt): array
{
    return [
        'expires' => $expiresAt,
        'path' => COOKIEPATH ?: '/',
        'domain' => COOKIE_DOMAIN,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/**
 * 公開向けHTMLのコメントを除去する。
 *
 * - 管理画面・ログイン画面・REST/feed では適用しない。
 * - IE条件付きコメントは除外して保持する。
 */
function lutwiyo_strip_public_html_comments(string $html): string
{
    return (string) preg_replace('/<!--(?!\\[if|\\s*<!|\\s*\\/?ko)(?:(?!-->).)*-->/s', '', $html);
}

add_action('template_redirect', function (): void {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed()) {
        return;
    }

    ob_start('lutwiyo_strip_public_html_comments');
}, 0);

add_action('template_redirect', function (): void {
    if (!function_exists('lutwiyo_register_member_service_request_metrics_shutdown_logger')) {
        return;
    }

    if (!tokk_member_service_request_metrics_enabled()) {
        return;
    }

    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || is_feed()) {
        return;
    }

    lutwiyo_register_member_service_request_metrics_shutdown_logger();
}, 0);

/**
 * OneTrust を有効化するホストかを返す。
 *
 * staging での一時確認のため、stage-tokk-kansai.jp を暫定許可している。
 */
function lutwiyo_is_onetrust_enabled_host(): bool
{
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    if (!is_string($currentHost) || $currentHost === '') {
        $currentHost = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    }

    $normalizedHost = strtolower(preg_replace('/:\d+$/', '', trim($currentHost)));
    return $normalizedHost === 'tokk-kansai.jp'
        || $normalizedHost === 'stage-tokk-kansai.jp';
}

// -------------------------------------
// Vite manifest helpers
// -------------------------------------
function lutwiyo_get_vite_manifest_path()
{
    if (defined('ABSPATH')) {
        return ABSPATH . '.vite/manifest.json';
    }
    return dirname(__DIR__, 3) . '/.vite/manifest.json';
}

/**
 * Vite manifest から CSS を取得
 * @param string $base "style" などのベース名
 * @return array CSS href の配列
 */
function get_css_file($base)
{
    $default = "/assets/css/{$base}.css";
    $manifest_path = lutwiyo_get_vite_manifest_path();

    if (!is_readable($manifest_path)) {
        return [$default];
    }

    $manifest_json = file_get_contents($manifest_path);
    $manifest = json_decode($manifest_json, true);
    if (!is_array($manifest)) {
        return [$default];
    }

    $pattern = '#^assets/css/' . preg_quote($base, '#') . '(?:-[^/]+)?\\.css$#';
    $css_files = [];

    foreach ($manifest as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if (isset($entry['file']) && is_string($entry['file']) && preg_match($pattern, $entry['file'])) {
            $css_files[] = '/' . ltrim($entry['file'], '/');
        }
        if (isset($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $css) {
                if (is_string($css) && preg_match($pattern, $css)) {
                    $css_files[] = '/' . ltrim($css, '/');
                }
            }
        }
    }

    $css_files = array_values(array_unique($css_files));
    return !empty($css_files) ? $css_files : [$default];
}


// -------------------------------------
// 管理画面の拡張
// -------------------------------------
add_filter('post_type_link', function ($permalink, $post) {
    if ($post->post_type === 'articles') {
        error_log('[articles_permalink] ' . print_r($permalink, true));
    }
    return $permalink;
}, 9, 2);


/**
 * taxonomy=area のターム編集画面では「削除」リンクを非表示にする
 */
add_action('admin_enqueue_scripts', function ($hook) {
    // term 編集画面以外は何もしない
    if ($hook !== 'term.php') {
        return;
    }

    // taxonomy=area のときだけ適用
    if (
        isset($_GET['taxonomy']) &&
        $_GET['taxonomy'] === 'area'
    ) {
        wp_add_inline_style(
            'wp-admin',
            '#delete-link { display: none !important; }'
        );
    }
});


// 管理画面メニューから「投稿」を非表示
add_action( 'admin_menu', function () {
    remove_menu_page( 'edit.php' ); // 「投稿」メニュー
} );

/**
 * 会員ブログ編集で利用可能なタグ判定に使うterm metaキー。
 */
function tokk_memberblog_allowed_tag_meta_key(): string
{
    return 'tokk_memberblog_allowed';
}

/**
 * post_tag 追加フォームに「会員ブログ利用可」チェックを追加する。
 */
add_action('post_tag_add_form_fields', function (): void {
    wp_nonce_field('tokk_memberblog_tag_allowed', 'tokk_memberblog_tag_allowed_nonce');
    ?>
    <div class="form-field term-group">
        <label for="tokk_memberblog_allowed"><?php echo esc_html('会員ブログ利用可'); ?></label>
        <input type="checkbox" id="tokk_memberblog_allowed" name="tokk_memberblog_allowed" value="1" />
        <p class="description"><?php echo esc_html('ONのタグのみ会員ブログ投稿画面で選択できます。'); ?></p>
    </div>
    <?php
});

/**
 * post_tag 編集フォームに「会員ブログ利用可」チェックを追加する。
 *
 * @param WP_Term $term
 */
add_action('post_tag_edit_form_fields', function ($term): void {
    if (!($term instanceof WP_Term)) {
        return;
    }

    $allowed = (string) get_term_meta($term->term_id, tokk_memberblog_allowed_tag_meta_key(), true) === '1';
    wp_nonce_field('tokk_memberblog_tag_allowed', 'tokk_memberblog_tag_allowed_nonce');
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="tokk_memberblog_allowed"><?php echo esc_html('会員ブログ利用可'); ?></label></th>
        <td>
            <label>
                <input type="checkbox" id="tokk_memberblog_allowed" name="tokk_memberblog_allowed" value="1" <?php checked($allowed); ?> />
                <?php echo esc_html('ONのタグのみ会員ブログ投稿画面で選択できます。'); ?>
            </label>
        </td>
    </tr>
    <?php
});

/**
 * post_tag の会員ブログ利用可フラグを保存する。
 */
function tokk_save_memberblog_allowed_tag_term_meta(int $termId): void
{
    if (!current_user_can('manage_categories')) {
        return;
    }

    $nonce = isset($_POST['tokk_memberblog_tag_allowed_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['tokk_memberblog_tag_allowed_nonce']))
        : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'tokk_memberblog_tag_allowed')) {
        return;
    }

    $allowedRaw = isset($_POST['tokk_memberblog_allowed'])
        ? sanitize_text_field(wp_unslash($_POST['tokk_memberblog_allowed']))
        : '0';
    $allowed = $allowedRaw === '1' ? '1' : '0';

    update_term_meta($termId, tokk_memberblog_allowed_tag_meta_key(), $allowed);
}

add_action('created_post_tag', 'tokk_save_memberblog_allowed_tag_term_meta');
add_action('edited_post_tag', 'tokk_save_memberblog_allowed_tag_term_meta');

/**
 * 会員ブログ許可タグ一覧を返す内部RESTエンドポイント。
 */
add_action('rest_api_init', function (): void {
    register_rest_route('tokk/v1', '/memberblog/allowed-tags', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (): WP_REST_Response {
            $options = function_exists('tokk_memberblog_get_allowed_tag_options')
                ? tokk_memberblog_get_allowed_tag_options()
                : [];
            $tagSlugs = [];
            foreach ($options as $option) {
                $slug = trim((string) ($option['slug'] ?? ''));
                if ($slug === '' || !preg_match('/^[a-z0-9_-]{1,120}$/', $slug)) {
                    continue;
                }
                $tagSlugs[] = $slug;
            }
            $tagSlugs = array_values(array_unique($tagSlugs));

            return rest_ensure_response([
                'result' => 'ok',
                'data' => [
                    'tag_slugs' => $tagSlugs,
                ],
            ]);
        },
    ]);
});


// -------------------------------------
// 初期化フック
// -------------------------------------
/**
 * テーマ設定の初期化
 *
 * @return void
 */
function lutwiyo_setup_theme()
{
    add_theme_support('post-thumbnails');
    add_editor_style('assets/css/style.css');
}

add_action('after_setup_theme', 'lutwiyo_setup_theme');

/**
 * リクエスト内で事前取得したログイン状態判定APIレスポンスを保持する。
 *
 * @param mixed $response
 * @return void
 */
function lutwiyo_set_prefetched_login_status_response($response)
{
    $GLOBALS['lutwiyo_prefetched_login_status_response'] = $response;
}

/**
 * リクエスト内で保持したログイン状態判定APIレスポンスを返す。
 *
 * @return mixed
 */
function lutwiyo_get_prefetched_login_status_response()
{
    return $GLOBALS['lutwiyo_prefetched_login_status_response'] ?? null;
}

/**
 * リクエスト内で事前取得した会員情報APIレスポンスを保持する。
 *
 * @param mixed $response
 * @return void
 */
function lutwiyo_set_prefetched_member_info_response($response)
{
    $GLOBALS['lutwiyo_prefetched_member_info_response'] = $response;
}

/**
 * リクエスト内で保持した会員情報APIレスポンスを返す。
 *
 * @return mixed
 */
function lutwiyo_get_prefetched_member_info_response()
{
    return $GLOBALS['lutwiyo_prefetched_member_info_response'] ?? null;
}

/**
 * リクエスト内で事前取得した会員コンテキストAPIレスポンスを保持する。
 *
 * @param mixed $response
 * @return void
 */
function lutwiyo_set_prefetched_member_context_response($response)
{
    $GLOBALS['lutwiyo_prefetched_member_context_response'] = $response;
}

/**
 * リクエスト内で保持した会員コンテキストAPIレスポンスを返す。
 *
 * @return mixed
 */
function lutwiyo_get_prefetched_member_context_response()
{
    return $GLOBALS['lutwiyo_prefetched_member_context_response'] ?? null;
}

/**
 * リクエスト内で事前取得した会員プロフィールAPIレスポンスを保持する。
 *
 * @param mixed $response
 * @return void
 */
function lutwiyo_set_prefetched_member_profile_response($response)
{
    $GLOBALS['lutwiyo_prefetched_member_profile_response'] = $response;
}

/**
 * リクエスト内で保持した会員プロフィールAPIレスポンスを返す。
 *
 * @return mixed
 */
function lutwiyo_get_prefetched_member_profile_response()
{
    return $GLOBALS['lutwiyo_prefetched_member_profile_response'] ?? null;
}

/**
 * リクエスト内で事前取得した記事詳細閲覧コンテキストAPIレスポンスを保持する。
 *
 * @param string $resourceType
 * @param string $resourceId
 * @param mixed $response
 * @return void
 */
function lutwiyo_set_prefetched_article_viewer_context_response(string $resourceType, string $resourceId, $response)
{
    $cacheKey = trim($resourceType) . '::' . trim($resourceId);
    if ($cacheKey === '::') {
        return;
    }

    $responses = is_array($GLOBALS['lutwiyo_prefetched_article_viewer_context_responses'] ?? null)
        ? $GLOBALS['lutwiyo_prefetched_article_viewer_context_responses']
        : [];
    $responses[$cacheKey] = $response;
    $GLOBALS['lutwiyo_prefetched_article_viewer_context_responses'] = $responses;
}

/**
 * リクエスト内で保持した記事詳細閲覧コンテキストAPIレスポンスを返す。
 *
 * @param string $resourceType
 * @param string $resourceId
 * @return mixed
 */
function lutwiyo_get_prefetched_article_viewer_context_response(string $resourceType, string $resourceId)
{
    $cacheKey = trim($resourceType) . '::' . trim($resourceId);
    if ($cacheKey === '::') {
        return null;
    }

    $responses = is_array($GLOBALS['lutwiyo_prefetched_article_viewer_context_responses'] ?? null)
        ? $GLOBALS['lutwiyo_prefetched_article_viewer_context_responses']
        : [];

    return $responses[$cacheKey] ?? null;
}

/**
 * member-context 判定・フォールバックの診断情報を記録する。
 *
 * @param array<string,mixed> $diagnostic
 * @return void
 */
function lutwiyo_record_member_context_diagnostic(array $diagnostic): void
{
    $logs = is_array($GLOBALS['lutwiyo_member_context_diagnostics'] ?? null)
        ? $GLOBALS['lutwiyo_member_context_diagnostics']
        : [];
    $logs[] = $diagnostic;
    $GLOBALS['lutwiyo_member_context_diagnostics'] = $logs;
}

/**
 * member-context 診断情報を返す。
 *
 * @return array<int,array<string,mixed>>
 */
function lutwiyo_get_member_context_diagnostics(): array
{
    return is_array($GLOBALS['lutwiyo_member_context_diagnostics'] ?? null)
        ? $GLOBALS['lutwiyo_member_context_diagnostics']
        : [];
}

/**
 * ログ出力向けに機微情報をマスクした値へ変換する。
 *
 * @param mixed $value
 * @return mixed
 */
function lutwiyo_mask_member_context_debug_value($value)
{
    if (is_array($value)) {
        $masked = [];
        foreach ($value as $key => $child) {
            $normalizedKey = is_string($key) ? strtolower($key) : '';
            if (in_array($normalizedKey, ['access_token', 'refresh_token', 'token', 'authorization', 'cookie'], true)) {
                $masked[$key] = '[masked]';
                continue;
            }

            if ($normalizedKey === 'uid' && is_scalar($child)) {
                $uid = trim((string) $child);
                if ($uid !== '') {
                    $masked[$key] = strlen($uid) <= 4
                        ? str_repeat('*', strlen($uid))
                        : substr($uid, 0, 2) . '***' . substr($uid, -2);
                    continue;
                }
            }

            $masked[$key] = lutwiyo_mask_member_context_debug_value($child);
        }

        return $masked;
    }

    if (is_object($value)) {
        return lutwiyo_mask_member_context_debug_value((array) $value);
    }

    return $value;
}

/**
 * member-context 生レスポンスを 1 回だけデバッグログへ出力する。
 *
 * 有効化条件:
 * - `?mc_debug_once=1` が付与されている
 * - まだ1回も出力されていない
 *
 * 出力後は transient でロックし、自動的に無効化する。
 *
 * @param mixed $response
 * @return void
 */
function lutwiyo_maybe_log_member_context_debug_once($response): void
{
    if (!isset($_GET['mc_debug_once']) || (string) $_GET['mc_debug_once'] !== '1') {
        return;
    }

    $transientKey = 'tokk_member_context_debug_once_logged';
    if (function_exists('get_transient') && get_transient($transientKey)) {
        return;
    }

    $payload = [
        'request' => isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '',
        'host' => isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '',
        'response' => lutwiyo_mask_member_context_debug_value($response),
    ];

    error_log('[tokk_member_context_debug_once] ' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    if (function_exists('set_transient')) {
        set_transient($transientKey, 1, DAY_IN_SECONDS);
    }
}

/**
 * member-context 応答を解析し、利用可否と理由を返す。
 *
 * @param array{status:int,body:array<string,mixed>}|null $response
 * @return array<string,mixed>
 */
function lutwiyo_analyze_member_context_response($response): array
{
    if (!is_array($response)) {
        return [
            'is_valid' => false,
            'reason' => 'response_not_array',
            'result' => '',
            'fallback_required' => false,
            'auth_result' => '',
            'member_info_result' => '',
            'has_auth' => false,
            'has_member_info' => false,
        ];
    }

    $body = is_array($response['body'] ?? null) ? $response['body'] : null;
    if (!is_array($body)) {
        return [
            'is_valid' => false,
            'reason' => 'body_not_array',
            'result' => '',
            'fallback_required' => false,
            'auth_result' => '',
            'member_info_result' => '',
            'has_auth' => false,
            'has_member_info' => false,
        ];
    }

    $result = (string) ($body['result'] ?? '');
    $fallbackRequired = (bool) ($body['fallback_required'] ?? false);
    if ($result === 'partial') {
        return [
            'is_valid' => false,
            'reason' => 'partial',
            'result' => $result,
            'fallback_required' => $fallbackRequired,
            'auth_result' => '',
            'member_info_result' => '',
            'has_auth' => false,
            'has_member_info' => false,
        ];
    }

    if ($fallbackRequired) {
        return [
            'is_valid' => false,
            'reason' => 'fallback_required',
            'result' => $result,
            'fallback_required' => true,
            'auth_result' => '',
            'member_info_result' => '',
            'has_auth' => false,
            'has_member_info' => false,
        ];
    }

    $authResponse = lutwiyo_extract_login_status_from_member_context($response);
    if (!is_array($authResponse)) {
        return [
            'is_valid' => false,
            'reason' => 'auth_missing',
            'result' => $result,
            'fallback_required' => $fallbackRequired,
            'auth_result' => '',
            'member_info_result' => '',
            'has_auth' => false,
            'has_member_info' => false,
        ];
    }

    $authResult = (string) ($authResponse['body']['result'] ?? '');
    if ($authResult !== 'logged_in') {
        return [
            'is_valid' => true,
            'reason' => 'ok',
            'result' => $result,
            'fallback_required' => $fallbackRequired,
            'auth_result' => $authResult,
            'member_info_result' => '',
            'has_auth' => true,
            'has_member_info' => false,
        ];
    }

    $memberInfoResponse = lutwiyo_extract_member_info_from_member_context($response);
    if (!is_array($memberInfoResponse)) {
        return [
            'is_valid' => false,
            'reason' => 'member_info_missing',
            'result' => $result,
            'fallback_required' => $fallbackRequired,
            'auth_result' => $authResult,
            'member_info_result' => '',
            'has_auth' => true,
            'has_member_info' => false,
        ];
    }

    $memberInfoResult = (string) ($memberInfoResponse['body']['result'] ?? '');
    if ($memberInfoResult !== 'ok') {
        return [
            'is_valid' => false,
            'reason' => 'member_info_not_ok',
            'result' => $result,
            'fallback_required' => $fallbackRequired,
            'auth_result' => $authResult,
            'member_info_result' => $memberInfoResult,
            'has_auth' => true,
            'has_member_info' => true,
        ];
    }

    return [
        'is_valid' => true,
        'reason' => 'ok',
        'result' => $result,
        'fallback_required' => $fallbackRequired,
        'auth_result' => $authResult,
        'member_info_result' => $memberInfoResult,
        'has_auth' => true,
        'has_member_info' => true,
    ];
}

/**
 * member-context レスポンスを login-status 相当の形式へ写像する。
 *
 * @param array<string,mixed>|null $memberContextResponse
 * @return array{status:int,body:array<string,mixed>}|null
 */
function lutwiyo_extract_login_status_from_member_context($memberContextResponse)
{
    if (!is_array($memberContextResponse)) {
        return null;
    }

    $body = is_array($memberContextResponse['body'] ?? null)
        ? $memberContextResponse['body']
        : null;
    if (!is_array($body)) {
        return null;
    }

    // member-context の login-status 内包キーは `auth` を正とするが、
    // 過渡期レスポンス（`login_status`）も許容して追加フォールバックを抑止する。
    $auth = $body['auth'] ?? null;
    if (!is_array($auth)) {
        $auth = $body['login_status'] ?? null;
    }
    if (!is_array($auth)) {
        return null;
    }

    $status = (int) ($auth['status'] ?? 200);
    $body = is_array($auth['body'] ?? null) ? $auth['body'] : null;
    if (!is_array($body)) {
        return null;
    }

    return [
        'status' => $status,
        'body' => $body,
    ];
}

/**
 * member-context レスポンスを member-info 相当の形式へ写像する。
 *
 * @param array<string,mixed>|null $memberContextResponse
 * @return array{status:int,body:array<string,mixed>}|null
 */
function lutwiyo_extract_member_info_from_member_context($memberContextResponse)
{
    if (!is_array($memberContextResponse)) {
        return null;
    }

    $memberInfo = $memberContextResponse['body']['member_info'] ?? null;
    if (!is_array($memberInfo)) {
        return null;
    }

    $status = (int) ($memberInfo['status'] ?? 200);
    $body = is_array($memberInfo['body'] ?? null) ? $memberInfo['body'] : null;
    if (!is_array($body)) {
        return null;
    }

    return [
        'status' => $status,
        'body' => $body,
    ];
}

/**
 * member-context レスポンスを member-profile 相当の形式へ写像する。
 *
 * @param array<string,mixed>|null $memberContextResponse
 * @return array{status:int,body:array<string,mixed>}|null
 */
function lutwiyo_extract_member_profile_from_member_context($memberContextResponse)
{
    if (!is_array($memberContextResponse)) {
        return null;
    }

    $memberProfile = $memberContextResponse['body']['member_profile'] ?? null;
    if (!is_array($memberProfile)) {
        return null;
    }

    $status = (int) ($memberProfile['status'] ?? 200);
    $body = is_array($memberProfile['body'] ?? null) ? $memberProfile['body'] : null;
    if (!is_array($body)) {
        return null;
    }

    return [
        'status' => $status,
        'body' => $body,
    ];
}

/**
 * article-viewer-context レスポンスから閲覧者情報を返す。
 *
 * @param array<string,mixed>|null $articleViewerContextResponse
 * @return array{is_logged_in:bool,plan:string,uid:string,nickname:string,profile_image_url:string}|null
 */
function lutwiyo_extract_article_viewer_context_viewer($articleViewerContextResponse)
{
    if (!is_array($articleViewerContextResponse)) {
        return null;
    }

    $viewer = $articleViewerContextResponse['body']['viewer'] ?? null;
    if (!is_array($viewer)) {
        return null;
    }

    return [
        'is_logged_in' => ($viewer['is_logged_in'] ?? false) === true,
        'plan' => trim((string) ($viewer['plan'] ?? 'guest')),
        'uid' => trim((string) ($viewer['uid'] ?? '')),
        'nickname' => trim((string) ($viewer['nickname'] ?? '')),
        'profile_image_url' => trim((string) ($viewer['profile_image_url'] ?? '')),
    ];
}

/**
 * article-viewer-context レスポンスからお気に入り状態を返す。
 *
 * @param array<string,mixed>|null $articleViewerContextResponse
 * @return bool|null
 */
function lutwiyo_extract_article_viewer_context_favorite_state($articleViewerContextResponse)
{
    if (!is_array($articleViewerContextResponse)) {
        return null;
    }

    $favorite = $articleViewerContextResponse['body']['favorite'] ?? null;
    if (!is_array($favorite)) {
        return null;
    }

    $body = is_array($favorite['body'] ?? null) ? $favorite['body'] : null;
    if (!is_array($body) || ($body['result'] ?? '') !== 'ok' || !array_key_exists('favorited', $body)) {
        return null;
    }

    return (bool) $body['favorited'];
}

/**
 * article-viewer-context レスポンスからいいね状態を返す。
 *
 * @param array<string,mixed>|null $articleViewerContextResponse
 * @return bool|null
 */
function lutwiyo_extract_article_viewer_context_like_state($articleViewerContextResponse)
{
    if (!is_array($articleViewerContextResponse)) {
        return null;
    }

    $like = $articleViewerContextResponse['body']['like'] ?? null;
    if (!is_array($like)) {
        return null;
    }

    $body = is_array($like['body'] ?? null) ? $like['body'] : null;
    if (!is_array($body) || ($body['result'] ?? '') !== 'ok' || !array_key_exists('liked', $body)) {
        return null;
    }

    return (bool) $body['liked'];
}

/**
 * article-viewer-context レスポンスからコメント閲覧・投稿可否を返す。
 *
 * @param array<string,mixed>|null $articleViewerContextResponse
 * @return array{can_view:bool,can_post:bool}|null
 */
function lutwiyo_extract_article_viewer_context_comment($articleViewerContextResponse)
{
    if (!is_array($articleViewerContextResponse)) {
        return null;
    }

    $comment = $articleViewerContextResponse['body']['comment'] ?? null;
    if (!is_array($comment)) {
        return null;
    }

    return [
        'can_view' => ($comment['can_view'] ?? false) === true,
        'can_post' => ($comment['can_post'] ?? false) === true,
    ];
}

/**
 * article-viewer-context 応答が安全に利用できるかを返す。
 *
 * @param array{status:int,body:array<string,mixed>}|null $response
 */
function lutwiyo_is_valid_article_viewer_context_response($response): bool
{
    if (!is_array($response) || (int) ($response['status'] ?? 0) !== 200) {
        return false;
    }

    $body = is_array($response['body'] ?? null) ? $response['body'] : null;
    if (!is_array($body)) {
        return false;
    }

    if (($body['result'] ?? '') !== 'ok' || ($body['fallback_required'] ?? false) === true) {
        return false;
    }

    $loginStatusResponse = lutwiyo_extract_login_status_from_member_context($response);
    if (!is_array($loginStatusResponse)) {
        return false;
    }

    $authResult = (string) ($loginStatusResponse['body']['result'] ?? '');
    if ($authResult === 'logged_out') {
        return true;
    }

    $memberInfoResponse = lutwiyo_extract_member_info_from_member_context($response);
    if (!is_array($memberInfoResponse) || ($memberInfoResponse['body']['result'] ?? '') !== 'ok') {
        return false;
    }

    return is_array(lutwiyo_extract_article_viewer_context_viewer($response));
}

/**
 * 指定 article resource が現在の単一記事本文と一致するかを返す。
 */
function lutwiyo_is_current_single_article_resource_id(string $resourceId): bool
{
    $normalizedResourceId = trim($resourceId);
    if ($normalizedResourceId === '') {
        return false;
    }

    if (!function_exists('is_singular') || !is_singular('articles')) {
        return false;
    }

    $queriedObjectId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;

    return $queriedObjectId > 0 && $normalizedResourceId === (string) $queriedObjectId;
}

/**
 * 記事詳細閲覧コンテキスト統合APIレスポンスを取得する（フラグ無効時は null）。
 *
 * @param string $resourceType
 * @param string $resourceId
 * @param array<string,mixed> $options
 * @return array{status:int,body:array<string,mixed>}|null
 */
function lutwiyo_get_or_fetch_article_viewer_context_response(string $resourceType, string $resourceId, array $options = [])
{
    if (!tokk_article_viewer_context_enabled()) {
        return null;
    }

    $normalizedResourceType = trim($resourceType);
    $normalizedResourceId = trim($resourceId);
    if ($normalizedResourceType === '' || $normalizedResourceId === '') {
        return null;
    }

    $prefetched = lutwiyo_get_prefetched_article_viewer_context_response($normalizedResourceType, $normalizedResourceId);
    if (is_array($prefetched)) {
        return lutwiyo_is_valid_article_viewer_context_response($prefetched)
            ? $prefetched
            : null;
    }

    $payload = [
        'resource_type' => $normalizedResourceType,
        'resource_id' => $normalizedResourceId,
        'content' => 'sub,data',
        'include_reactions' => true,
    ];

    if (isset($options['comment_view_acl'])) {
        $payload['comment_view_acl'] = (string) $options['comment_view_acl'];
    }
    if (isset($options['comment_post_acl'])) {
        $payload['comment_post_acl'] = (string) $options['comment_post_acl'];
    }

    $response = lutwiyo_call_bridge_api('article_viewer_context', $payload);
    if (!is_array($response)) {
        $response = [
            'status' => 0,
            'body' => [
                'result' => 'error',
                'reason' => 'transport_error',
                'fallback_required' => true,
            ],
        ];
    }
    lutwiyo_set_prefetched_article_viewer_context_response($normalizedResourceType, $normalizedResourceId, $response);

    if (!lutwiyo_is_valid_article_viewer_context_response($response)) {
        return null;
    }

    $loginStatusResponse = lutwiyo_extract_login_status_from_member_context($response);
    if (is_array($loginStatusResponse)) {
        lutwiyo_set_prefetched_login_status_response($loginStatusResponse);
    }

    $memberInfoResponse = lutwiyo_extract_member_info_from_member_context($response);
    if (is_array($memberInfoResponse)) {
        lutwiyo_set_prefetched_member_info_response($memberInfoResponse);
    }

    return $response;
}

/**
 * member-context 応答が安全に利用できるかを返す。
 *
 * @param array{status:int,body:array<string,mixed>}|null $response
 */
function lutwiyo_is_valid_member_context_response($response): bool
{
    $analysis = lutwiyo_analyze_member_context_response($response);

    return (bool) ($analysis['is_valid'] ?? false);
}

/**
 * 会員コンテキスト統合APIレスポンスを取得する（フラグ無効時は null）。
 *
 * @return array{status:int,body:array<string,mixed>}|null
 */
function lutwiyo_get_or_fetch_member_context_response()
{
    if (!tokk_member_context_enabled()) {
        return null;
    }

    $prefetched = lutwiyo_get_prefetched_member_context_response();
    if (is_array($prefetched)) {
        return $prefetched;
    }

    $response = lutwiyo_call_bridge_api('member_context', [
        'content' => 'sub,data',
        'include_member_info' => true,
        'include_member_profile' => true,
    ]);
    lutwiyo_maybe_log_member_context_debug_once($response);
    lutwiyo_set_prefetched_member_context_response($response);

    $analysis = lutwiyo_analyze_member_context_response($response);
    lutwiyo_record_member_context_diagnostic([
        'source' => 'lutwiyo_get_or_fetch_member_context_response',
        'is_valid' => (bool) ($analysis['is_valid'] ?? false),
        'reason' => (string) ($analysis['reason'] ?? 'unknown'),
        'result' => (string) ($analysis['result'] ?? ''),
        'fallback_required' => (bool) ($analysis['fallback_required'] ?? false),
        'auth_result' => (string) ($analysis['auth_result'] ?? ''),
        'member_info_result' => (string) ($analysis['member_info_result'] ?? ''),
        'has_auth' => (bool) ($analysis['has_auth'] ?? false),
        'has_member_info' => (bool) ($analysis['has_member_info'] ?? false),
    ]);

    $loginStatusResponse = lutwiyo_extract_login_status_from_member_context($response);
    if (is_array($loginStatusResponse)) {
        lutwiyo_set_prefetched_login_status_response($loginStatusResponse);
    }

    $memberInfoResponse = lutwiyo_extract_member_info_from_member_context($response);
    if (is_array($memberInfoResponse)) {
        lutwiyo_set_prefetched_member_info_response($memberInfoResponse);
    }

    $memberProfileResponse = lutwiyo_extract_member_profile_from_member_context($response);
    if (is_array($memberProfileResponse)) {
        lutwiyo_set_prefetched_member_profile_response($memberProfileResponse);
    }

    if (!((bool) ($analysis['is_valid'] ?? false))) {
        $reason = (string) ($analysis['reason'] ?? 'unknown');
        $strictGuestReasons = [
            // member-context が「構造上は有効」かつログイン済み member_info まで評価できた場合のみ strict guest に倒す。
            // auth/member_info 自体が欠落したケースは契約不整合の可能性があるため、従来 API フォールバックを優先する。
            'partial',
            'fallback_required',
            'member_info_not_ok',
        ];
        if (!in_array($reason, $strictGuestReasons, true)) {
            // 通信失敗などで会員コンテキスト応答自体が不完全な場合は従来どおり個別APIへフォールバックする。
            return null;
        }

        // 会員コンテキストの必要構造不足時は strict guest 扱いとし、追加HTTP呼び出しを抑止する。
        lutwiyo_set_prefetched_login_status_response([
            'status' => 200,
            'body' => [
                'result' => 'logged_out',
                'reason' => 'member_context_' . $reason,
            ],
        ]);

        lutwiyo_set_prefetched_member_info_response([
            'status' => 401,
            'body' => [
                'result' => 'error',
                'reason' => 'no_local_member',
                'member_context_reason' => $reason,
            ],
        ]);

        return null;
    }

    return $response;
}

/**
 * ログイン状態判定APIレスポンスを取得する（未取得時のみAPI実行）。
 *
 * @return mixed
 */
function lutwiyo_get_or_fetch_login_status_response()
{
    $prefetched = lutwiyo_get_prefetched_login_status_response();
    if (is_array($prefetched)) {
        return $prefetched;
    }

    lutwiyo_get_or_fetch_member_context_response();

    $prefetched = lutwiyo_get_prefetched_login_status_response();
    if (is_array($prefetched)) {
        return $prefetched;
    }

    $response = lutwiyo_call_bridge_api('login_status');
    lutwiyo_set_prefetched_login_status_response($response);

    return $response;
}

/**
 * login_status レスポンスが明示的なログアウト状態かを返す。
 *
 * @param mixed $loginStatusResponse
 * @return bool
 */
function lutwiyo_is_logged_out_login_status_response($loginStatusResponse): bool
{
    if (!is_array($loginStatusResponse)) {
        return false;
    }

    return ($loginStatusResponse['body']['result'] ?? '') === 'logged_out';
}

/**
 * ゲスト時に favorite 系 SSR 呼び出しを short-circuit すべきかを返す。
 *
 * @return bool
 */
function lutwiyo_should_short_circuit_guest_favorite_requests(): bool
{
    static $resolved = false;
    static $shouldShortCircuit = false;

    if ($resolved) {
        return $shouldShortCircuit;
    }

    $resolved = true;

    if (!tokk_member_service_short_circuit_guest_favorites()) {
        return false;
    }

    if ((function_exists('is_admin') && is_admin())
        || (function_exists('wp_doing_ajax') && wp_doing_ajax())
        || (function_exists('wp_doing_cron') && wp_doing_cron())
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('WP_CLI') && WP_CLI)) {
        return false;
    }

    if ((function_exists('is_feed') && is_feed())
        || (function_exists('is_robots') && is_robots())
        || (function_exists('is_trackback') && is_trackback())) {
        return false;
    }

    $loginStatusResponse = lutwiyo_get_or_fetch_login_status_response();
    $shouldShortCircuit = lutwiyo_is_logged_out_login_status_response($loginStatusResponse);

    return $shouldShortCircuit;
}

/**
 * member_info レスポンスが「即時ログアウト扱い」かどうかを返す。
 *
 * @param mixed $memberInfoResponse
 * @return bool
 */
function lutwiyo_is_hard_logout_member_info_response($memberInfoResponse): bool
{
    if (!is_array($memberInfoResponse)) {
        return false;
    }

    if (($memberInfoResponse['body']['result'] ?? '') === 'ok') {
        return false;
    }

    return ($memberInfoResponse['body']['reason'] ?? '') === 'no_local_member';
}

/**
 * member_info 呼び出し前に login_status を 1 回だけ確認して、
 * refresh_token によるセッション延命の機会を確保する。
 *
 * @return void
 */
function lutwiyo_maybe_probe_login_status_before_member_info()
{
    if (($GLOBALS['lutwiyo_login_status_probed_before_member_info'] ?? false) === true) {
        return;
    }

    $GLOBALS['lutwiyo_login_status_probed_before_member_info'] = true;

    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (is_feed() || is_robots() || is_trackback()) {
        return;
    }

    lutwiyo_get_or_fetch_login_status_response();
}

/**
 * 会員情報APIレスポンスを取得する（同一ペイロードはブリッジ呼び出し側で1リクエスト内キャッシュされる）。
 *
 * @return array{status:int,body:array<string,mixed>}|null
 */
function lutwiyo_get_or_fetch_member_info_response()
{
    $prefetched = lutwiyo_get_prefetched_member_info_response();
    if (is_array($prefetched)) {
        return $prefetched;
    }

    lutwiyo_get_or_fetch_member_context_response();

    $prefetched = lutwiyo_get_prefetched_member_info_response();
    if (is_array($prefetched)) {
        return $prefetched;
    }

    lutwiyo_maybe_probe_login_status_before_member_info();

    $response = lutwiyo_call_bridge_api('member_info', [
        'content' => 'sub,data',
    ]);
    lutwiyo_set_prefetched_member_info_response($response);

    if (lutwiyo_is_hard_logout_member_info_response($response)) {
        lutwiyo_set_prefetched_login_status_response([
            'status' => 200,
            'body' => [
                'result' => 'logged_out',
                'reason' => 'no_local_member',
            ],
        ]);
    }

    return $response;
}

/**
 * member_info が失敗した場合に login_status を再実行したうえで member_info を再試行する。
 *
 * @return array{status:int,body:array<string,mixed>}|null
 */
function lutwiyo_get_member_info_response_with_login_status_retry()
{
    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();
    if (is_array($memberInfoResponse) && ($memberInfoResponse['body']['result'] ?? '') === 'ok') {
        return $memberInfoResponse;
    }

    if (lutwiyo_is_hard_logout_member_info_response($memberInfoResponse)) {
        return $memberInfoResponse;
    }

    $loginStatusResponse = function_exists('lutwiyo_call_bridge_api_uncached')
        ? lutwiyo_call_bridge_api_uncached('login_status')
        : lutwiyo_call_bridge_api('login_status');
    lutwiyo_set_prefetched_login_status_response($loginStatusResponse);

    $retriedMemberInfoResponse = function_exists('lutwiyo_call_bridge_api_uncached')
        ? lutwiyo_call_bridge_api_uncached('member_info', ['content' => 'sub,data'])
        : lutwiyo_call_bridge_api('member_info', ['content' => 'sub,data']);
    lutwiyo_set_prefetched_member_info_response($retriedMemberInfoResponse);

    if (is_array($retriedMemberInfoResponse) && ($retriedMemberInfoResponse['body']['result'] ?? '') === 'ok') {
        return $retriedMemberInfoResponse;
    }

    if (lutwiyo_is_hard_logout_member_info_response($retriedMemberInfoResponse)) {
        lutwiyo_set_prefetched_login_status_response([
            'status' => 200,
            'body' => [
                'result' => 'logged_out',
                'reason' => 'no_local_member',
            ],
        ]);

        return $retriedMemberInfoResponse;
    }

    lutwiyo_set_prefetched_member_info_response($memberInfoResponse);

    return $memberInfoResponse;
}

/**
 * 現在ログイン中会員のTOKK独自プロフィールを取得する。
 *
 * @return array{status:int,body:array<string,mixed>}|null
 */
function lutwiyo_get_or_fetch_current_member_profile_response()
{
    static $resolved = false;
    static $cachedResponse = null;

    if ($resolved) {
        return $cachedResponse;
    }

    $resolved = true;

    lutwiyo_get_or_fetch_member_context_response();

    $prefetched = lutwiyo_get_prefetched_member_profile_response();
    if (is_array($prefetched) && ($prefetched['body']['result'] ?? '') === 'ok') {
        $cachedResponse = $prefetched;
        return $cachedResponse;
    }

    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();
    if (!is_array($memberInfoResponse) || ($memberInfoResponse['body']['result'] ?? '') !== 'ok') {
        $cachedResponse = null;
        return $cachedResponse;
    }

    $memberUid = trim((string) ($memberInfoResponse['body']['uid'] ?? ''));
    if ($memberUid === '') {
        $cachedResponse = null;
        return $cachedResponse;
    }

    $cachedResponse = lutwiyo_call_bridge_api('member_profile', [
        'uid' => $memberUid,
        'operation' => 'get',
    ]);

    if (!is_array($cachedResponse) || ($cachedResponse['body']['result'] ?? '') !== 'ok') {
        $cachedResponse = function_exists('lutwiyo_call_bridge_api_uncached')
            ? lutwiyo_call_bridge_api_uncached('member_profile', [
                'uid' => $memberUid,
                'operation' => 'get',
            ])
            : lutwiyo_call_bridge_api('member_profile', [
                'uid' => $memberUid,
                'operation' => 'get',
            ]);
    }

    return $cachedResponse;
}

/**
 * 現在ログイン中会員のTOKK独自プロフィール配列を返す。
 *
 * @return array<string,mixed>
 */
function lutwiyo_get_current_member_profile_member(): array
{
    $response = lutwiyo_get_or_fetch_current_member_profile_response();

    if (!is_array($response) || ($response['body']['result'] ?? '') !== 'ok') {
        return [];
    }

    return is_array($response['body']['member'] ?? null)
        ? $response['body']['member']
        : [];
}

/**
 * member_profile レスポンスが未登録会員を示すかを返す。
 *
 * @param mixed $memberProfileResponse
 * @return bool
 */
function lutwiyo_is_unregistered_member_profile_response($memberProfileResponse): bool
{
    if (!is_array($memberProfileResponse)) {
        return false;
    }

    $memberProfileResult = strtolower(trim((string) ($memberProfileResponse['body']['result'] ?? '')));

    return (int) ($memberProfileResponse['status'] ?? 0) === 404
        || in_array($memberProfileResult, ['not_found', 'notfound', 'unregistered'], true);
}

/**
 * 文字列が「Unicode空白のみ」ではないか判定する。
 *
 * @param mixed $value
 * @return bool
 */
function lutwiyo_has_non_whitespace_text($value): bool
{
    $text = (string) $value;

    return preg_match('/\S/u', $text) === 1;
}

/**
 * 現在ログイン中会員の表示用プロフィール要約を返す。
 *
 * @return array{nickname:string,member_slug:string,profile_image_type:string,profile_image_id:string,profile_image_url:string,favorite_area:string}
 */
function lutwiyo_get_current_member_profile_summary()
{
    $member = lutwiyo_get_current_member_profile_member();

    return [
        'nickname' => trim((string) ($member['nickname'] ?? '')),
        'member_slug' => trim((string) ($member['member_slug'] ?? '')),
        'profile_image_type' => trim((string) ($member['profile_image_type'] ?? '')),
        'profile_image_id' => trim((string) ($member['profile_image_id'] ?? '')),
        'profile_image_url' => trim((string) ($member['profile_image_url'] ?? '')),
        'favorite_area' => trim((string) ($member['favorite_area'] ?? '')),
    ];
}

/**
 * 現在ログイン中会員の表示名を member_info.local_member から返す。
 */
function lutwiyo_get_current_member_nickname_from_member_info_response(): string
{
    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();
    if (!is_array($memberInfoResponse) || ($memberInfoResponse['body']['result'] ?? '') !== 'ok') {
        return '';
    }

    $localMember = is_array($memberInfoResponse['body']['local_member'] ?? null)
        ? $memberInfoResponse['body']['local_member']
        : [];

    return trim((string) ($localMember['nickname'] ?? ''));
}

/**
 * お気に入りエリア向けのおすすめエリア記事セクション情報を返す。
 *
 * @param array<int,string> $areaSlugs
 * @return array<int,array<string,mixed>>
 */
function lutwiyo_get_member_favorite_area_sections(array $areaSlugs, int $maxAreas = 1, int $postsPerArea = 3): array
{
    if (!class_exists('TermModelHelper') || !class_exists('PostModelHelper')) {
        return [];
    }

    $normalizedAreaSlugs = array_values(array_unique(array_filter(array_map(static function ($slug): string {
        return sanitize_title((string) $slug);
    }, $areaSlugs), static function (string $slug): bool {
        return $slug !== '';
    })));

    if ($normalizedAreaSlugs === []) {
        return [];
    }

    if ($maxAreas > 0) {
        $normalizedAreaSlugs = array_slice($normalizedAreaSlugs, 0, $maxAreas);
    }

    $sections = [];
    foreach ($normalizedAreaSlugs as $areaSlug) {
        $areaInfoList = TermModelHelper::get_terms_payload(
            'area',
            ['slug' => $areaSlug],
            [],
            []
        );
        $areaInfo = is_array($areaInfoList[0] ?? null) ? $areaInfoList[0] : [];
        if ($areaInfo === []) {
            continue;
        }

        $pinnedCount = is_countable($areaInfo['pinned_list'] ?? null) ? count($areaInfo['pinned_list']) : 0;
        $additionalArticlesInfoList = PostModelHelper::get_posts_payload(
            [
                'post_type' => 'articles',
                'posts_per_page' => max(0, $postsPerArea - $pinnedCount),
                'tax_query' => [
                    [
                        'taxonomy' => 'area',
                        'field' => 'slug',
                        'terms' => $areaSlug,
                    ],
                ],
                'post__not_in' => wp_list_pluck((array) ($areaInfo['pinned_list'] ?? []), 'ID'),
            ],
            [],
            []
        );
        $areaInfo['pinned_articles_info_list'] = array_map(
            static fn ($item) => is_array($item) ? $item + ['is_pinned' => true] : $item,
            (array) ($areaInfo['pinned_articles_info_list'] ?? [])
        );
        $areaInfo['integrated_articles_info_list'] = array_merge(
            (array) ($areaInfo['pinned_articles_info_list'] ?? []),
            is_array($additionalArticlesInfoList) ? $additionalArticlesInfoList : []
        );
        $sections[] = $areaInfo;
    }

    return $sections;
}

/**
 * 会員プロフィール画像のプリセット一覧を返す。
 *
 * @return array<string,array{id:string,label:string,url:string}>
 */
function lutwiyo_get_member_profile_image_presets()
{
    static $presetMap = null;

    if (is_array($presetMap)) {
        return $presetMap;
    }

    $baseUri = trailingslashit(home_url('/assets/img/common'));
    $presetMap = [];
    for ($index = 1; $index <= 4; $index++) {
        $id = sprintf('preset_avatar_%02d', $index);
        $presetMap[$id] = [
            'id' => $id,
            'label' => sprintf('%02d', $index),
            'accessible_label' => sprintf('プロフィール画像 %02d', $index),
            'url' => $baseUri . sprintf('memberAvatarPreset-%02d.svg', $index),
        ];
    }

    return $presetMap;
}

/**
 * 会員プロフィール画像アップロードを処理し、正方形画像URLを返す。
 *
 * @param array<string,mixed> $file
 * @return array{ok:bool,url:string,id:string,error:string}
 */
function lutwiyo_process_member_profile_uploaded_image(array $file)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [
            'ok' => false,
            'url' => '',
            'id' => '',
            'error' => '画像ファイルを選択してください。',
        ];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [
            'ok' => false,
            'url' => '',
            'id' => '',
            'error' => '画像のアップロードに失敗しました。',
        ];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = [
        'test_form' => false,
        'mimes' => [
            'jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ],
        'unique_filename_callback' => static function ($dir, $name, $ext) {
            return 'member-profile-' . wp_generate_password(12, false, false) . $ext;
        },
    ];

    $uploaded = wp_handle_upload($file, $overrides);
    if (!is_array($uploaded) || !empty($uploaded['error']) || empty($uploaded['file']) || empty($uploaded['url'])) {
        return [
            'ok' => false,
            'url' => '',
            'id' => '',
            'error' => '画像のアップロードに失敗しました。',
        ];
    }

    $uploadedFile = (string) $uploaded['file'];
    $editor = wp_get_image_editor($uploadedFile);
    if (is_wp_error($editor)) {
        return [
            'ok' => false,
            'url' => '',
            'id' => '',
            'error' => '画像の加工に失敗しました。',
        ];
    }

    $size = $editor->get_size();
    $width = max(0, (int) ($size['width'] ?? 0));
    $height = max(0, (int) ($size['height'] ?? 0));
    if ($width <= 0 || $height <= 0) {
        return [
            'ok' => false,
            'url' => '',
            'id' => '',
            'error' => '画像サイズを取得できませんでした。',
        ];
    }

    $side = min($width, $height);
    $x = (int) floor(($width - $side) / 2);
    $y = (int) floor(($height - $side) / 2);
    $cropped = $editor->crop($x, $y, $side, $side, 512, 512);
    if (is_wp_error($cropped)) {
        return [
            'ok' => false,
            'url' => '',
            'id' => '',
            'error' => '画像の切り抜きに失敗しました。',
        ];
    }

    $saved = $editor->save();
    if (!is_array($saved) || empty($saved['path'])) {
        return [
            'ok' => false,
            'url' => '',
            'id' => '',
            'error' => '画像の保存に失敗しました。',
        ];
    }

    $uploadDir = wp_get_upload_dir();
    $savedPath = wp_normalize_path((string) $saved['path']);
    $baseDir = wp_normalize_path((string) ($uploadDir['basedir'] ?? ''));
    $baseUrl = (string) ($uploadDir['baseurl'] ?? '');
    $relativePath = $baseDir !== '' ? ltrim(str_replace($baseDir, '', $savedPath), '/') : basename($savedPath);
    $publicUrl = $baseUrl !== '' ? trailingslashit($baseUrl) . str_replace('\\', '/', $relativePath) : (string) $uploaded['url'];

    return [
        'ok' => true,
        'url' => $publicUrl,
        'id' => basename($savedPath),
        'error' => '',
    ];
}

/**
 * 会員情報APIレスポンスからログイン状態を判定する。
 */
function lutwiyo_is_logged_in_via_member_info(): bool
{
    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();

    return is_array($memberInfoResponse)
        && ($memberInfoResponse['body']['result'] ?? '') === 'ok';
}

/**
 * 通常画面ではログイン状態を先に取得して、同一リクエスト中の重複呼び出しを抑止する。
 */
function lutwiyo_prefetch_login_status_response_on_frontend()
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (is_feed() || is_robots() || is_trackback()) {
        return;
    }

    // 初期表示はmember-infoを事実源として扱うため、login-statusの先行呼び出しは行わない。
}

add_action('template_redirect', 'lutwiyo_prefetch_login_status_response_on_frontend', 0);

/**
 * ログイン済みユーザーがログイン案内/新規登録案内へ直アクセスした場合は
 * マイページへリダイレクトする。
 */
function lutwiyo_redirect_logged_in_user_from_auth_guide_pages()
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (!is_page(['login', 'regist'])) {
        return;
    }

    if (!lutwiyo_is_logged_in_via_member_info()) {
        return;
    }

    wp_safe_redirect(home_url('/mypage/'));
    exit;
}

add_action('template_redirect', 'lutwiyo_redirect_logged_in_user_from_auth_guide_pages', 1);

/**
 * スタンダード会員で継続課金カードmain未設定のユーザーを
 * 継続課金カード設定必須ページへ強制遷移させる。
 */
function lutwiyo_force_redirect_standard_member_without_recurring_card()
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (is_feed() || is_robots() || is_trackback()) {
        return;
    }

    // 設定必須ページ自身と会員情報入力ページは許可してループを回避する。
    if (is_page(['paid-payment-method-required', 'mypage-edit'])) {
        return;
    }

    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();
    if (!is_array($memberInfoResponse) || ($memberInfoResponse['body']['result'] ?? '') !== 'ok') {
        return;
    }

    $localMember = is_array($memberInfoResponse['body']['local_member'] ?? null)
        ? $memberInfoResponse['body']['local_member']
        : [];
    $isStandardMember = (int) ($localMember['member_rank_id'] ?? 0) === 2;

    if (!$isStandardMember) {
        return;
    }

    $recurringBillingReadyRaw = $localMember['recurring_billing_ready'] ?? false;
    $isRecurringBillingReady = in_array($recurringBillingReadyRaw, [true, 1, '1', 'true'], true);

    if ($isRecurringBillingReady) {
        return;
    }

    wp_safe_redirect(home_url('/paid-payment-method-required/'));
    exit;
}

add_action('template_redirect', 'lutwiyo_force_redirect_standard_member_without_recurring_card', 1);

/**
 * ログイン済みだがTOKK独自会員情報が未登録のユーザーを
 * 会員情報入力ページへ誘導する。
 *
 * - 外部認証基盤には会員が存在するが、tokk_members に未登録なケースを想定
 * - /mypage-edit 自身ではリダイレクトしない（入力完了の導線を確保）
 */
function lutwiyo_redirect_incomplete_member_profile()
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (is_feed() || is_robots() || is_trackback()) {
        return;
    }

    if (is_page('mypage-edit')) {
        return;
    }

    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();

    if (!is_array($memberInfoResponse) || ($memberInfoResponse['body']['result'] ?? '') !== 'ok') {
        return;
    }

    $memberUid = trim((string) ($memberInfoResponse['body']['uid'] ?? ''));
    if ($memberUid === '') {
        return;
    }

    if (lutwiyo_should_skip_profile_required_redirect_once($memberInfoResponse)) {
        return;
    }

    $memberProfileResponse = lutwiyo_get_or_fetch_current_member_profile_response();

    $memberProfileResult = is_array($memberProfileResponse)
        ? strtolower(trim((string) ($memberProfileResponse['body']['result'] ?? '')))
        : '';

    $hasMemberProfile = is_array($memberProfileResponse)
        && $memberProfileResult === 'ok'
        && is_array($memberProfileResponse['body']['member'] ?? null);

    $isUnregisteredMember = lutwiyo_is_unregistered_member_profile_response($memberProfileResponse);

    // レコード未作成は従来どおり入力ページへ誘導する。
    if ($isUnregisteredMember) {
        wp_safe_redirect(add_query_arg('profile_required', '1', home_url('/mypage-edit/')));
        exit;
    }

    // 想定外応答は通常表示を優先し、リダイレクトを抑止する。
    if (!$hasMemberProfile) {
        return;
    }

    $memberProfile = $memberProfileResponse['body']['member'];
    $nickname = (string) ($memberProfile['nickname'] ?? '');
    $hasNickname = lutwiyo_has_non_whitespace_text($nickname);
    $mailmagazineOptIn = $memberProfile['is_mailmagazine_opt_in'] ?? null;
    $hasMailmagazineOptIn = $mailmagazineOptIn !== null
        && in_array((string) $mailmagazineOptIn, ['0', '1'], true);

    // nickname または is_mailmagazine_opt_in が未設定時は入力を強制する。
    if (!$hasNickname || !$hasMailmagazineOptIn) {
        wp_safe_redirect(add_query_arg('profile_required', '1', home_url('/mypage-edit/')));
        exit;
    }
}

add_action('template_redirect', 'lutwiyo_redirect_incomplete_member_profile', 1);

/**
 * 有料会員登録フロー完了直後の1回だけ、プロフィール未完了リダイレクトを抑止する。
 *
 * 許可条件:
 * - 会員ランクがスタンダード
 * - 抑止cookieに保持されたURLの path と現在 path が一致
 * - mypage / mypage-edit 系ページではない
 */
function lutwiyo_should_skip_profile_required_redirect_once(array $memberInfoResponse): bool
{
    $cookieName = 'tokk_paid_profile_redirect_bypass_once';
    if (!isset($_COOKIE[$cookieName])) {
        return false;
    }

    $memberInfoBody = is_array($memberInfoResponse['body'] ?? null)
        ? $memberInfoResponse['body']
        : [];
    $localMember = is_array($memberInfoBody['local_member'] ?? null)
        ? $memberInfoBody['local_member']
        : [];
    $isStandardMember = (int) ($localMember['member_rank_id'] ?? 0) === 2;
    if (!$isStandardMember) {
        return false;
    }

    $cookieValue = sanitize_text_field(wp_unslash($_COOKIE[$cookieName]));
    $validatedReturnTo = wp_validate_redirect($cookieValue, '');
    $returnToPath = $validatedReturnTo !== ''
        ? (string) wp_parse_url($validatedReturnTo, PHP_URL_PATH)
        : '';
    if ($returnToPath === '' || preg_match('#^/mypage(?:-edit)?(?:/|$)#', $returnToPath) === 1) {
        setcookie($cookieName, '', lutwiyo_cookie_options(time() - 3600));
        return false;
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $currentPath = $requestUri !== '' ? (string) wp_parse_url($requestUri, PHP_URL_PATH) : '';
    if ($currentPath === '' || $currentPath !== $returnToPath) {
        return false;
    }

    setcookie($cookieName, '', lutwiyo_cookie_options(time() - 3600));

    return true;
}

/**
 * 未ログイン向けのログイン案内（/login と同内容）を現在ページ内へ表示して終了する。
 *
 * @param string $currentUrlForReturn
 * @return void
 */
function lutwiyo_render_inline_login_guide_and_exit(string $currentUrlForReturn)
{
    $loginGuideBaseUrl = remove_query_arg('tokk_member_login', $currentUrlForReturn);
    $loginStartUrl = add_query_arg('tokk_member_login', '1', $loginGuideBaseUrl);

    get_header();
    ?>
    <main class="main wrapper" role="main">
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <div class="gridWide">
                <style>
                    .tokkSimplePage {
                        margin: 0 auto;
                        max-width: 560px;
                        font-size: 16px;
                    }
                    .tokkSimplePageTitle {
                        text-align: center;
                    }
                    .tokkSimplePageLead {
                        margin-top: 16px;
                        line-height: 1.8;
                    }
                    .tokkSimplePageLead a {
                        display: inline;
                        height: auto;
                        text-decoration: underline;
                    }
                    .tokkSimplePageActions {
                        margin-top: 24px;
                        display: flex;
                        justify-content: center;
                    }
                    .tokkSimplePageActions .tokkLoginGuideBtn,
                    .tokkSimplePageActions .privateUserBtn {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 50px;
                        border: 1px solid rgba(58, 58, 58, 0.75);
                        border-radius: 50px;
                        padding: 0 44px;
                        font-size: 17px;
                        background: #fff;
                        white-space: nowrap;
                        text-decoration: none;
                        color: #3a3a3a;
                    }
                    .tokkSimplePageActions .tokkLoginGuideBtn__label,
                    .tokkSimplePageActions .privateUserBtn__title {
                        display: inline-block;
                        color: #3a3a3a !important;
                        -webkit-text-fill-color: #3a3a3a !important;
                        font-size: 16px;
                        line-height: 1.4;
                        opacity: 1 !important;
                        text-indent: 0;
                    }
                    @media only screen and (max-width: 767px) {
                        .tokkSimplePageActions .tokkLoginGuideBtn {
                            width: 100%;
                            padding: 0 20px;
                        }
                        .tokkSimplePageActions .tokkLoginGuideBtn__label {
                            width: 100%;
                            text-align: center;
                        }
                    }
                </style>

                <div class="tokkSimplePage">
                    <h1 class="title fs--22 tokkSimplePageTitle">ログインのご案内</h1>
                    <p class="tokkSimplePageLead">「TOKK関西の会員サービス」のご利用には、阪急阪神ホールディングスグループの各種サービスで使えるグループ共通ID「<a href="https://www.hhcross.hankyu-hanshin.jp/about/" target="_blank" rel="noopener noreferrer">HH cross ID</a>」へのログインが必要です。</p>
                    <p class="tokkSimplePageActions">
                        <a href="<?php echo esc_url($loginStartUrl); ?>" class="tokkLoginGuideBtn" role="link" title="HH cross IDでログイン">
                            <span class="tokkLoginGuideBtn__label">HH cross IDでログイン</span>
                        </a>
                    </p>
                </div>
            </div>
        </article>
    </main>
    <?php
    get_footer();
    exit;
}

/**
 * 有料会員導線の戻り先URLを、同一オリジンの相対パスへ正規化する。
 *
 * - 外部オリジン/不正値は fallback へフォールバック
 * - 戻り先は callback 側の安全判定と整合するよう、`/path?query` 形式を優先する
 */
function lutwiyo_normalize_paid_return_to(string $candidate, string $fallback = '/'): string
{
    $fallbackValidated = wp_validate_redirect($fallback, home_url('/'));
    $fallbackPath = '/';
    if ($fallbackValidated !== '') {
        if (str_starts_with($fallbackValidated, '/')) {
            $fallbackPath = str_starts_with($fallbackValidated, '//') ? '/' : $fallbackValidated;
        } else {
            $fallbackParsed = wp_parse_url($fallbackValidated);
            if (is_array($fallbackParsed)) {
                $fallbackPathOnly = (string) ($fallbackParsed['path'] ?? '/');
                $fallbackQuery = isset($fallbackParsed['query']) && (string) $fallbackParsed['query'] !== ''
                    ? '?'.(string) $fallbackParsed['query']
                    : '';
                $fallbackPath = ($fallbackPathOnly !== '' ? $fallbackPathOnly : '/').$fallbackQuery;
            }
        }
    }

    $validated = wp_validate_redirect($candidate, '');
    if ($validated === '') {
        return $fallbackPath;
    }

    if (str_starts_with($validated, '/')) {
        return str_starts_with($validated, '//') ? $fallbackPath : $validated;
    }

    $parsed = wp_parse_url($validated);
    $homeParsed = wp_parse_url(home_url('/'));
    if (!is_array($parsed) || !is_array($homeParsed)) {
        return $fallbackPath;
    }

    $candidateHost = strtolower((string) ($parsed['host'] ?? ''));
    $candidateScheme = strtolower((string) ($parsed['scheme'] ?? ''));
    $candidatePort = isset($parsed['port']) ? (int) $parsed['port'] : 0;

    $homeHost = strtolower((string) ($homeParsed['host'] ?? ''));
    $homeScheme = strtolower((string) ($homeParsed['scheme'] ?? ''));
    $homePort = isset($homeParsed['port']) ? (int) $homeParsed['port'] : 0;

    if ($candidateHost === '' || $candidateHost !== $homeHost || $candidateScheme !== $homeScheme) {
        return $fallbackPath;
    }

    if ($candidatePort !== 0 && $homePort !== 0 && $candidatePort !== $homePort) {
        return $fallbackPath;
    }

    $path = (string) ($parsed['path'] ?? '/');
    if ($path === '' || str_starts_with($path, '//')) {
        $path = '/';
    }

    $query = isset($parsed['query']) && (string) $parsed['query'] !== ''
        ? '?'.(string) $parsed['query']
        : '';

    return $path.$query;
}

/**
 * 現在リクエストのフロントURLを返す（ログイン導線の戻り先用）。
 */
function lutwiyo_get_current_frontend_url_for_return(): string
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestUri = is_string($requestUri) ? wp_unslash($requestUri) : '/';
    $requestUri = trim($requestUri) !== '' ? $requestUri : '/';

    return home_url($requestUri);
}

/**
 * 初回有料決済完了後のサンクスモーダル表示データを返す（1回のみ）。
 *
 * @return array{should_show:bool,membership_expired_at:string,next_renewal_at:string,mypage_edit_url:string,message:string,note:string}
 */
function lutwiyo_get_paid_upgrade_thanks_modal_data(): array
{
    $default = [
        'should_show' => false,
        'membership_expired_at' => '',
        'next_renewal_at' => '',
        'mypage_edit_url' => home_url('/mypage-edit/'),
        'message' => 'スタンダード会員の登録ありがとうございます。引き続きTOKKをお楽しみください。',
        'note' => '上記の情報は、マイページ編集画面でもご確認いただけます。',
    ];

    $cookieName = 'tokk_paid_upgrade_thanks_once';
    if (!isset($_COOKIE[$cookieName])) {
        return $default;
    }

    $cookieRaw = wp_unslash((string) $_COOKIE[$cookieName]);
    setcookie($cookieName, '', lutwiyo_cookie_options(time() - 3600));

    parse_str($cookieRaw, $payload);
    if (!is_array($payload)) {
        return $default;
    }

    $issuedAt = isset($payload['issued_at']) ? (int) $payload['issued_at'] : 0;
    if ($issuedAt <= 0 || (time() - $issuedAt) > 600) {
        return $default;
    }

    $membershipExpiredAtRaw = trim((string) ($payload['membership_expired_at'] ?? ''));
    $nextRenewalAtRaw = trim((string) ($payload['next_renewal_at'] ?? ''));
    if ($membershipExpiredAtRaw === '' || $nextRenewalAtRaw === '') {
        return $default;
    }

    $formatDateOnly = static function (string $dateRaw): string {
        $timestamp = strtotime($dateRaw);
        if ($timestamp === false) {
            return '';
        }

        return wp_date('Y年m月d日', $timestamp);
    };

    $membershipExpiredAt = $formatDateOnly($membershipExpiredAtRaw);
    $nextRenewalAt = $formatDateOnly($nextRenewalAtRaw);
    if ($membershipExpiredAt === '' || $nextRenewalAt === '') {
        return $default;
    }

    $default['should_show'] = true;
    $default['membership_expired_at'] = $membershipExpiredAt;
    $default['next_renewal_at'] = $nextRenewalAt;

    return $default;
}

add_action('wp_footer', static function (): void {
    if (is_admin()) {
        return;
    }

    $modalData = lutwiyo_get_paid_upgrade_thanks_modal_data();
    if (($modalData['should_show'] ?? false) !== true) {
        return;
    }
    ?>
    <style>
        .tokkPaidUpgradeThanksOverlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .tokkPaidUpgradeThanksDialog { width: min(560px, 100%); background: #fff; border-radius: 12px; padding: 24px 20px; box-shadow: 0 16px 40px rgba(0,0,0,.2); color: #3a3a3a; }
        .tokkPaidUpgradeThanksDialog h2 { font-size: 22px; margin: 0 0 12px; }
        .tokkPaidUpgradeThanksDialog p { margin: 0 0 8px; line-height: 1.7; }
        .tokkPaidUpgradeThanksDialog ul { margin: 12px 0; padding-left: 1.2em; }
        .tokkPaidUpgradeThanksDialog li { margin: 6px 0; }
        .tokkPaidUpgradeThanksDialog a { color: #005ea8; text-decoration: underline; }
        .tokkPaidUpgradeThanksDialog__close { margin-top: 12px; min-width: 180px; min-height: 44px; border: 1px solid rgba(58,58,58,.75); border-radius: 999px; background: #fff; color: #3a3a3a; cursor: pointer; }
    </style>
    <div class="tokkPaidUpgradeThanksOverlay" id="tokkPaidUpgradeThanksOverlay" role="dialog" aria-modal="true" aria-labelledby="tokkPaidUpgradeThanksTitle">
        <div class="tokkPaidUpgradeThanksDialog">
            <h2 id="tokkPaidUpgradeThanksTitle">スタンダード会員登録完了</h2>
            <p><?php echo esc_html((string) $modalData['message']); ?></p>
            <ul>
                <li>会員有効期限：<?php echo esc_html((string) $modalData['membership_expired_at']); ?></li>
                <li>次回更新予定日：<?php echo esc_html((string) $modalData['next_renewal_at']); ?></li>
            </ul>
            <p>※<a href="<?php echo esc_url((string) $modalData['mypage_edit_url']); ?>">マイページ編集画面</a>でもご確認いただけます。</p>
            <button type="button" class="tokkPaidUpgradeThanksDialog__close" id="tokkPaidUpgradeThanksClose">閉じる</button>
        </div>
    </div>
    <script>
        (function () {
            var overlay = document.getElementById('tokkPaidUpgradeThanksOverlay');
            var closeButton = document.getElementById('tokkPaidUpgradeThanksClose');
            if (!overlay || !closeButton) {
                return;
            }
            closeButton.addEventListener('click', function () {
                overlay.remove();
            });
        })();
    </script>
    <?php
}, 99);

/**
 * 記事の会員公開プランを返す。
 *
 * @param int $postId
 * @return string public|paid_member
 */
function lutwiyo_get_article_member_access_plan(int $postId): string
{
    $rawPlan = get_field('member_access_plan', $postId);
    $plan = is_string($rawPlan) ? trim(strtolower($rawPlan)) : '';

    return in_array($plan, ['public', 'paid_member'], true)
        ? $plan
        : 'public';
}

/**
 * 会員アクセスコンテキストを返す。
 *
 * @return array{is_logged_in:bool,plan:string,uid:string}
 */
function lutwiyo_get_member_access_context(): array
{
    $context = [
        'is_logged_in' => false,
        'plan' => 'guest',
        'uid' => '',
    ];

    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();
    if (!is_array($memberInfoResponse) || ($memberInfoResponse['body']['result'] ?? '') !== 'ok') {
        if (lutwiyo_is_hard_logout_member_info_response($memberInfoResponse)) {
            return $context;
        }

        // 導線によっては member-info が一時的に取得失敗する場合があるため、
        // login-status が logged_in であれば guest へ誤判定しない。
        $loginStatusResponse = lutwiyo_get_or_fetch_login_status_response();
        $isLoggedInViaStatus = is_array($loginStatusResponse)
            && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';

        if ($isLoggedInViaStatus) {
            $context['is_logged_in'] = true;
            $context['plan'] = 'unknown';
        }

        return $context;
    }

    $context['is_logged_in'] = true;
    $context['uid'] = trim((string) ($memberInfoResponse['body']['uid'] ?? ''));

    $localMember = $memberInfoResponse['body']['local_member'] ?? null;
    if (!is_array($localMember)) {
        $context['plan'] = 'unknown';
        return $context;
    }

    $rankId = (int) ($localMember['member_rank_id'] ?? 0);
    if ($rankId === 2) {
        // STANDARD会員は会員ランク反映（バッチ完了）まで paid として扱う。
        // 期限日時の先行比較で free へ降格させないことで、深夜バッチ実行時間の変更時も
        // 期限日 + システム更新完了までのログイン継続を保証する。
        $context['plan'] = 'paid';
        return $context;
    }

    $context['plan'] = 'free';
    return $context;
}

/**
 * コメント一覧を閲覧できる会員コンテキストかを返す。
 *
 * @param array{is_logged_in?:bool,plan?:string,uid?:string} $memberContext
 */
function lutwiyo_can_member_context_view_comments(array $memberContext, string $commentViewAcl): bool
{
    $plan = (string) ($memberContext['plan'] ?? 'guest');

    return $commentViewAcl !== 'private'
        && ($memberContext['is_logged_in'] ?? false) === true
        && in_array($plan, ['free', 'paid'], true);
}

/**
 * コメントを投稿できる会員コンテキストかを返す。
 *
 * @param array{is_logged_in?:bool,plan?:string,uid?:string} $memberContext
 */
function lutwiyo_can_member_context_post_comments(array $memberContext, string $commentViewAcl, string $commentPostAcl): bool
{
    return lutwiyo_can_member_context_view_comments($memberContext, $commentViewAcl)
        && (string) ($memberContext['plan'] ?? 'guest') === 'paid'
        && $commentPostAcl === 'enabled';
}

/**
 * コメント投稿不可時の利用者向け文言を返す。
 *
 * @param array{is_logged_in?:bool,plan?:string,uid?:string} $memberContext
 */
function lutwiyo_get_comment_post_unavailable_message(array $memberContext, string $commentPostAcl): string
{
    if ($commentPostAcl !== 'enabled') {
        return '現在コメント投稿は停止中です。';
    }

    $plan = (string) ($memberContext['plan'] ?? 'guest');
    if (($memberContext['is_logged_in'] ?? false) !== true || $plan === 'guest') {
        return '会員にログインするとコメント投稿できます。';
    }

    if ($plan === 'free') {
        return 'コメント投稿は有料会員限定です。';
    }

    return 'コメント投稿は現在ご利用いただけません。';
}

/**
 * 会員UIDアサーション文字列を生成する。
 * 形式: base64url(payload_json).base64url(hmac_sha256)
 */
function lutwiyo_build_member_uid_assertion(string $uid, int $ttlSeconds = 300): string
{
    $normalizedUid = trim($uid);
    if ($normalizedUid === '') {
        return '';
    }

    $secret = '';
    if (defined('TOKK_MEMBER_SERVICE_AUTHORIZATION')) {
        $secret = trim((string) constant('TOKK_MEMBER_SERVICE_AUTHORIZATION'));
    }
    if ($secret === '') {
        return '';
    }

    $issuedAt = time();
    $expiresAt = $issuedAt + max(60, $ttlSeconds);
    $payload = wp_json_encode([
        'uid' => $normalizedUid,
        'iat' => $issuedAt,
        'exp' => $expiresAt,
    ]);
    if (! is_string($payload) || $payload === '') {
        return '';
    }

    $payloadB64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $payloadB64, $secret, true);
    $signatureB64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return $payloadB64 . '.' . $signatureB64;
}

/**
 * 会員特典導線で扱うURLをフロントURLとして正規化する。
 */
function lutwiyo_normalize_frontend_url(string $targetUrl): string
{
    $normalizedUrl = trim($targetUrl);
    if ($normalizedUrl === '') {
        return home_url('/');
    }

    if (str_starts_with($normalizedUrl, '/')) {
        return home_url($normalizedUrl);
    }

    return $normalizedUrl;
}

/**
 * 会員特典導線としてログイン必須化する対象URLかを返す。
 */
function lutwiyo_should_gate_member_benefit_url(string $targetUrl): bool
{
    $normalizedUrl = lutwiyo_normalize_frontend_url($targetUrl);
    $targetHost = (string) wp_parse_url($normalizedUrl, PHP_URL_HOST);
    $siteHost = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);

    if ($targetHost !== '' && $siteHost !== '' && strtolower($targetHost) !== strtolower($siteHost)) {
        return false;
    }

    $path = '/' . ltrim((string) wp_parse_url($normalizedUrl, PHP_URL_PATH), '/');
    // クーポン/プレゼントはページ本体を公開し、ギャラリーだけ入口で会員制御する。
    foreach (['/gallery'] as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return true;
        }
    }

    return false;
}

/**
 * 会員特典導線用URLを返す。非会員はログイン開始付きURLへ変換する。
 */
function lutwiyo_get_member_benefit_entry_url(string $targetUrl): string
{
    $normalizedUrl = lutwiyo_normalize_frontend_url($targetUrl);
    if (!lutwiyo_should_gate_member_benefit_url($normalizedUrl)) {
        return $normalizedUrl;
    }

    static $isLoggedIn = null;
    if ($isLoggedIn === null) {
        $isLoggedIn = lutwiyo_is_logged_in_via_member_info();
    }

    if ($isLoggedIn) {
        return $normalizedUrl;
    }

    return add_query_arg('tokk_member_login', '1', remove_query_arg('tokk_member_login', $normalizedUrl));
}

/**
 * `howto` / `privacy` の iframe 埋め込み向け裸出力を判定する。
 */
function lutwiyo_is_static_page_naked_mode(string $expectedSlug): bool
{
    if (!is_page($expectedSlug)) {
        return false;
    }

    $mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : '';

    return $mode === 'naked';
}

/**
 * 会員特典の実行リンクに対する会員状態別ガード情報を返す。
 *
 * @return array{
 *   mode:string,
 *   href:string,
 *   message:string,
 *   primary_label:string,
 *   primary_url:string,
 *   secondary_label:string,
 *   secondary_url:string
 * }
 */
function lutwiyo_get_member_benefit_action_gate(string $targetUrl, string $benefitKind = 'benefit'): array
{
    $normalizedUrl = lutwiyo_normalize_frontend_url($targetUrl);
    $memberContext = lutwiyo_get_member_access_context();
    $plan = (string) ($memberContext['plan'] ?? 'guest');
    $isLoggedIn = (bool) ($memberContext['is_logged_in'] ?? false);

    $benefitLabel = match ($benefitKind) {
        'coupon' => 'クーポン',
        'present' => 'プレゼント',
        default => '会員特典',
    };

    if ($isLoggedIn && ($plan === 'paid' || $plan === 'free')) {
        return [
            'mode' => 'direct',
            'href' => $normalizedUrl,
            'message' => '',
            'primary_label' => '',
            'primary_url' => '',
            'secondary_label' => '',
            'secondary_url' => '',
        ];
    }

    if ($isLoggedIn && $plan === 'unknown') {
        return [
            'mode' => 'unknown',
            'href' => '#',
            'message' => '会員状態を確認できないため、時間をおいて再度お試しください。',
            'primary_label' => '',
            'primary_url' => '',
            'secondary_label' => '',
            'secondary_url' => '',
        ];
    }

    return [
        'mode' => 'login',
        'href' => home_url('/login/'),
        'message' => sprintf('この%sは会員登録で利用できます。会員の方はログインしてください。', $benefitLabel),
        'primary_label' => 'ログイン',
        'primary_url' => home_url('/login/'),
        'secondary_label' => '新規会員登録',
        'secondary_url' => home_url('/regist/'),
    ];
}

/**
 * 会員特典の実行リンクに付与するHTML属性を返す。
 */
function lutwiyo_get_member_benefit_action_attrs(array $gate): string
{
    $mode = trim((string) ($gate['mode'] ?? 'direct'));
    if ($mode === '' || $mode === 'direct') {
        return '';
    }

    $attrs = [
        'data-benefit-gate-mode="' . esc_attr($mode) . '"',
        'data-benefit-gate-message="' . esc_attr((string) ($gate['message'] ?? '')) . '"',
        'data-benefit-primary-label="' . esc_attr((string) ($gate['primary_label'] ?? '')) . '"',
        'data-benefit-primary-url="' . esc_attr((string) ($gate['primary_url'] ?? '')) . '"',
        'data-benefit-secondary-label="' . esc_attr((string) ($gate['secondary_label'] ?? '')) . '"',
        'data-benefit-secondary-url="' . esc_attr((string) ($gate['secondary_url'] ?? '')) . '"',
    ];

    return implode(' ', $attrs);
}

/**
 * 有料会員限定記事の閲覧可否を判定する。
 *
 * @param array{is_logged_in:bool,plan:string,uid:string} $memberContext
 * @param int $postId
 * @return bool
 */
function lutwiyo_can_view_paid_limited_article(array $memberContext, int $postId): bool
{
    if (lutwiyo_get_article_member_access_plan($postId) !== 'paid_member') {
        return true;
    }

    return ($memberContext['is_logged_in'] ?? false) === true
        && ($memberContext['plan'] ?? 'guest') === 'paid';
}

/**
 * paywallゲートブロック名かどうかを返す。
 *
 * @param string $blockName
 * @return bool
 */
function lutwiyo_is_paywall_gate_block_name(string $blockName): bool
{
    $normalized = trim(strtolower($blockName));
    if ($normalized === '') {
        return false;
    }

    return in_array($normalized, [
        'acf/tokk-paywall-gate',
        'tokk/paywall-gate',
        'tokk/paywall-break',
    ], true);
}

/**
 * 投稿本文内の paywall ゲート配置を解析する。
 *
 * @param array<int,array<string,mixed>> $blocks
 * @return array{
 *   count:int,
 *   first_index:int|null,
 *   is_valid_position:bool,
 *   fallback_mode:string
 * }
 */
function lutwiyo_analyze_paywall_gate_blocks(array $blocks): array
{
    $count = 0;
    $firstIndex = null;
    $lastIndex = max(0, count($blocks) - 1);

    foreach ($blocks as $index => $block) {
        if (!is_array($block)) {
            continue;
        }

        $blockName = (string) ($block['blockName'] ?? '');
        if (!lutwiyo_is_paywall_gate_block_name($blockName)) {
            continue;
        }

        $count++;
        if ($firstIndex === null) {
            $firstIndex = (int) $index;
        }
    }

    $isValidPosition = $count === 1
        && $firstIndex !== null
        && $firstIndex > 0
        && $firstIndex < $lastIndex;

    return [
        'count' => $count,
        'first_index' => $firstIndex,
        'is_valid_position' => $isValidPosition,
        // 不正配置は安全側で全文を有料制限する。
        'fallback_mode' => $isValidPosition ? 'restrict_after_break' : 'all_paid',
    ];
}

/**
 * paywall ゲートのプレースホルダを除去する。
 *
 * @param string $content
 * @return string
 */
function lutwiyo_strip_paywall_gate_placeholder(string $content): string
{
    return str_replace('<!-- tokk-paywall-gate -->', '', $content);
}

/**
 * 現在のリクエストで有料記事本文をマスクすべきかを返す。
 *
 * @param WP_Post $post
 * @param array{is_logged_in:bool,plan:string,uid:string}|null $memberContext
 * @return bool
 */
function lutwiyo_should_mask_paid_article_content(WP_Post $post, ?array $memberContext = null): bool
{
    if ($post->post_type !== 'articles') {
        return false;
    }

    if (current_user_can('edit_post', $post->ID)) {
        return false;
    }

    if (lutwiyo_get_article_member_access_plan((int) $post->ID) !== 'paid_member') {
        return false;
    }

    $memberContext = $memberContext ?? lutwiyo_get_member_access_context();

    return !lutwiyo_can_view_paid_limited_article($memberContext, (int) $post->ID);
}

/**
 * 有料記事マスク時の案内文HTMLを返す。
 *
 * @param array{is_logged_in:bool,plan:string,uid:string}|null $memberContext
 * @return string
 */
function lutwiyo_get_paid_article_mask_notice_html(?array $memberContext = null): string
{
    $memberContext = $memberContext ?? lutwiyo_get_member_access_context();
    $isGuest = ($memberContext['is_logged_in'] ?? false) !== true;
    $message = $isGuest
        ? 'この記事は有料会員限定です。ログイン後に有料会員登録すると続きを閲覧できます。'
        : 'この記事は有料会員限定です。続きを読むには有料会員登録が必要です。';

    return '<p>' . esc_html($message) . '</p>';
}

/**
 * 有料記事の非公開側本文を、境界前のみ公開する安全側HTMLへ整形する。
 *
 * @param WP_Post $post
 * @param array{is_logged_in:bool,plan:string,uid:string}|null $memberContext
 * @return string
 */
function lutwiyo_build_paid_article_masked_content(WP_Post $post, ?array $memberContext = null): string
{
    $memberContext = $memberContext ?? lutwiyo_get_member_access_context();
    $blocks = parse_blocks((string) $post->post_content);
    $analysis = lutwiyo_analyze_paywall_gate_blocks(is_array($blocks) ? $blocks : []);
    $segments = [];

    if (($analysis['is_valid_position'] ?? false) === true) {
        $firstIndex = (int) ($analysis['first_index'] ?? -1);
        $visibleHtml = '';

        foreach ($blocks as $index => $block) {
            if (!is_array($block)) {
                continue;
            }

            if ((int) $index >= $firstIndex) {
                break;
            }

            $blockName = (string) ($block['blockName'] ?? '');
            if (lutwiyo_is_paywall_gate_block_name($blockName)) {
                break;
            }

            $visibleHtml .= render_block($block);
        }

        $visibleHtml = lutwiyo_strip_paywall_gate_placeholder((string) $visibleHtml);
        if (trim((string) wp_strip_all_tags($visibleHtml)) !== '') {
            $segments[] = $visibleHtml;
        }
    }

    if ($segments === []) {
        $excerpt = trim((string) get_the_excerpt($post));
        if ($excerpt !== '') {
            $segments[] = '<p>' . esc_html($excerpt) . '</p>';
        }
    }

    $segments[] = lutwiyo_get_paid_article_mask_notice_html($memberContext);

    return implode('', $segments);
}

/**
 * REST / 配信 / メタ用の安全側説明文を返す。
 *
 * @param WP_Post $post
 * @param array{is_logged_in:bool,plan:string,uid:string}|null $memberContext
 * @return string
 */
function lutwiyo_get_paid_article_safe_description(WP_Post $post, ?array $memberContext = null): string
{
    $excerpt = trim((string) wp_strip_all_tags((string) get_the_excerpt($post)));
    if ($excerpt !== '') {
        return $excerpt;
    }

    return wp_strip_all_tags(lutwiyo_get_paid_article_mask_notice_html($memberContext));
}

/**
 * 有料会員の広告非表示判定。
 *
 * @return bool true=広告表示可 / false=広告非表示
 */
function lutwiyo_can_view_advertisement(): bool
{
    if (is_admin() || wp_doing_ajax()) {
        return true;
    }

    $memberContext = lutwiyo_get_member_access_context();
    $plan = (string) ($memberContext['plan'] ?? 'guest');

    // 有料会員は広告を表示しない。
    return $plan !== 'paid';
}

/**
 * 有料会員向けに記事本文中の AdSense マークアップを除去する。
 *
 * @param string $content
 * @return string
 */
function lutwiyo_strip_article_adsense_markup_for_paid_members(string $content): string
{
    if ($content === '') {
        return $content;
    }

    if (is_admin() || wp_doing_ajax()) {
        return $content;
    }

    if (!is_singular('articles')) {
        return $content;
    }

    if (lutwiyo_can_view_advertisement()) {
        return $content;
    }

    $patterns = [
        '#<ins\b[^>]*class=(["\'])[^"\']*adsbygoogle[^"\']*\1[^>]*>.*?</ins>\s*<script>\s*\(adsbygoogle\s*=\s*window\.adsbygoogle\s*\|\|\s*\[\]\)\.push\(\{\}\);\s*</script>#is',
        '#<ins\b[^>]*class=(["\'])[^"\']*adsbygoogle[^"\']*\1[^>]*>.*?</ins>#is',
        '#<script>\s*\(adsbygoogle\s*=\s*window\.adsbygoogle\s*\|\|\s*\[\]\)\.push\(\{\}\);\s*</script>#is',
    ];

    $strippedContent = preg_replace($patterns, '', $content);
    if (!is_string($strippedContent)) {
        return $content;
    }

    return $strippedContent;
}

/**
 * 記事詳細本文に含まれる adsense1 系 shortcode を判定する。
 *
 * Post Snippets 側の実装差分により、`[adsense1]` と
 * `[postsnippet id="2"]` / `[post_snippet ...]` の両方を許容する。
 *
 * @param string $shortcodeTag
 * @param mixed $attr
 * @param mixed $m
 * @return bool
 */
function lutwiyo_is_article_adsense_shortcode_reference(string $shortcodeTag, $attr = [], $m = []): bool
{
    $normalizedTag = strtolower(trim($shortcodeTag));
    if ($normalizedTag === 'adsense1') {
        return true;
    }

    if (!in_array($normalizedTag, ['postsnippet', 'post_snippet'], true)) {
        return false;
    }

    $haystacks = [];
    if (is_array($attr)) {
        foreach ($attr as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $normalizedValue = is_scalar($value) ? strtolower((string) $value) : '';
            $haystacks[] = $normalizedKey;
            if ($normalizedValue !== '') {
                $haystacks[] = $normalizedValue;
                $haystacks[] = $normalizedKey . '=' . $normalizedValue;
            }
        }
    }

    if (is_array($m) && isset($m[0]) && is_string($m[0])) {
        $haystacks[] = strtolower($m[0]);
    }

    $combined = implode(' ', $haystacks);
    if ($combined === '') {
        return false;
    }

    return (bool) preg_match('/\badsense1\b/i', $combined)
        || (bool) preg_match("/\bid\s*=\s*[\"']?2(?:[\"']|\b)/i", $combined);
}

/**
 * 記事詳細本文で Post Snippets 経由の adsense1 を非表示にするか判定する。
 *
 * @param string $shortcodeTag
 * @param mixed $attr
 * @param mixed $m
 * @return bool
 */
function lutwiyo_should_hide_article_adsense_snippet(string $shortcodeTag, $attr = [], $m = []): bool
{
    if (is_admin() || wp_doing_ajax()) {
        return false;
    }

    if (!is_singular('articles')) {
        return false;
    }

    return lutwiyo_is_article_adsense_shortcode_reference($shortcodeTag, $attr, $m)
        && !lutwiyo_can_view_advertisement();
}

add_filter('pre_do_shortcode_tag', function ($return, $tag, $attr, $m) {
    if (!is_string($tag) || $tag === '') {
        return $return;
    }

    if (!lutwiyo_should_hide_article_adsense_snippet($tag, $attr, $m)) {
        return $return;
    }

    return '';
}, 10, 4);

/**
 * 有料会員限定記事の未権限時に、課金導線つきの案内を表示して終了する。
 *
 * @param int $postId
 * @param array{is_logged_in:bool,plan:string,uid:string} $memberContext
 * @return void
 */
function lutwiyo_render_inline_paid_member_guide_and_exit(int $postId, array $memberContext): void
{
    $title = trim((string) get_the_title($postId));
    $excerpt = trim((string) get_the_excerpt($postId));
    $loginUrl = home_url('/login/');
    $signupUrl = home_url('/regist/');
    $upgradeUrl = home_url('/paid-exp/');
    $memberPlan = (string) ($memberContext['plan'] ?? 'guest');
    $isGuestViewer = ($memberContext['is_logged_in'] ?? false) !== true;
    $isFreeViewer = $memberPlan === 'free';
    $isUnknownPlan = $memberPlan === 'unknown';

    get_header();
    ?>
    <main class="main wrapper" role="main">
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <div class="gridWide">
                <style>
                    .tokkSimplePage {
                        margin: 0 auto;
                        max-width: 640px;
                        font-size: 16px;
                    }
                    .tokkSimplePageTitle {
                        text-align: center;
                    }
                    .tokkSimplePageLead {
                        margin-top: 16px;
                        line-height: 1.8;
                    }
                    .tokkSimplePageExcerpt {
                        margin-top: 20px;
                        padding: 18px;
                        border-radius: 12px;
                        background: rgba(0, 0, 0, 0.04);
                        line-height: 1.8;
                    }
                    .tokkSimplePageActions {
                        margin-top: 16px;
                        display: flex;
                        justify-content: center;
                    }
                    .tokkSimplePageActions .privateUserBtn {
                        position: static;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        width: min(100%, 360px);
                        max-width: 100%;
                        min-height: 50px;
                        border: 1px solid rgba(58, 58, 58, 0.75);
                        border-radius: 50px;
                        padding: 12px 28px;
                        font-size: 17px;
                        background: #fff;
                        line-height: 1.5;
                        white-space: normal;
                        text-align: center;
                        text-decoration: none;
                    }
                </style>

                <div class="tokkSimplePage">
                    <h1 class="title fs--22 tokkSimplePageTitle"><?php echo esc_html($title !== '' ? $title : '有料会員限定記事'); ?></h1>
                    <p class="tokkSimplePageLead">この記事は有料会員限定で公開しています。続きを読むにはログインまたはプラン変更を行ってください。</p>
                    <?php if ($isUnknownPlan): ?>
                        <p class="tokkSimplePageLead">会員状態の確認に失敗しました。時間をおいて再度お試しください。</p>
                    <?php endif; ?>
                    <?php if ($excerpt !== ''): ?>
                        <p class="tokkSimplePageExcerpt"><?php echo esc_html($excerpt); ?></p>
                    <?php endif; ?>
                    <?php if ($isGuestViewer): ?>
                        <p class="tokkSimplePageActions">
                            <a href="<?php echo esc_url($loginUrl); ?>" class="privateUserBtn" role="link" title="ログインして記事を読む">
                                ログインして記事を読む
                            </a>
                        </p>
                        <p class="tokkSimplePageActions">
                            <a href="<?php echo esc_url($signupUrl); ?>" class="privateUserBtn" role="link" title="TOKK会員に新規会員登録">
                                TOKK会員に新規会員登録
                            </a>
                        </p>
                    <?php elseif ($isFreeViewer): ?>
                        <p class="tokkSimplePageActions">
                            <a href="<?php echo esc_url($upgradeUrl); ?>" class="privateUserBtn" role="link" title="スタンダード会員にアップグレード">
                                スタンダード会員にアップグレード
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </main>
    <?php
    get_footer();
    exit;
}

/**
 * フォトギャラリーの未権限時に、ログイン/アップグレード導線つきの案内を表示して終了する。
 *
 * @param array{is_logged_in:bool,plan:string,uid:string} $memberContext
 * @param string $currentUrlForReturn
 * @return void
 */
function lutwiyo_render_inline_gallery_paid_member_guide_and_exit(array $memberContext, string $currentUrlForReturn): void
{
    $loginGuideBaseUrl = remove_query_arg('tokk_member_login', $currentUrlForReturn);
    $loginUrl = add_query_arg('tokk_member_login', '1', $loginGuideBaseUrl);
    $signupUrl = home_url('/regist/');
    $upgradeUrl = home_url('/paid-exp/');
    $memberPlan = (string) ($memberContext['plan'] ?? 'guest');
    $isGuestViewer = ($memberContext['is_logged_in'] ?? false) !== true;
    $isFreeViewer = $memberPlan === 'free';
    $isUnknownPlan = $memberPlan === 'unknown';

    get_header();
    ?>
    <main class="main wrapper" role="main">
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <div class="gridWide">
                <style>
                    .tokkSimplePage {
                        margin: 0 auto;
                        max-width: 640px;
                        font-size: 16px;
                    }
                    .tokkSimplePageTitle {
                        text-align: center;
                    }
                    .tokkSimplePageLead {
                        margin-top: 16px;
                        line-height: 1.8;
                    }
                    .tokkSimplePageActions {
                        margin-top: 24px;
                        display: flex;
                        justify-content: center;
                    }
                    .tokkSimplePageActions .privateUserBtn {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 50px;
                        border: 1px solid rgba(58, 58, 58, 0.75);
                        border-radius: 50px;
                        padding: 0 44px;
                        font-size: 17px;
                        background: #fff;
                        white-space: nowrap;
                        text-decoration: none;
                    }
                </style>

                <div class="tokkSimplePage">
                    <h1 class="title fs--22 tokkSimplePageTitle">フォトギャラリーはスタンダード会員限定です</h1>
                    <p class="tokkSimplePageLead">フォトギャラリーを閲覧するには、ログインまたはスタンダード会員への変更を行ってください。</p>
                    <?php if ($isUnknownPlan): ?>
                        <p class="tokkSimplePageLead">会員状態の確認に失敗しました。時間をおいて再度お試しください。</p>
                    <?php endif; ?>
                    <?php if ($isGuestViewer): ?>
                        <p class="tokkSimplePageActions">
                            <a href="<?php echo esc_url($loginUrl); ?>" class="privateUserBtn" role="link" title="ログインはこちら">
                                ログインはこちら
                            </a>
                        </p>
                        <p class="tokkSimplePageActions">
                            <a href="<?php echo esc_url($signupUrl); ?>" class="privateUserBtn" role="link" title="新規会員登録はこちら">
                                新規会員登録はこちら
                            </a>
                        </p>
                    <?php elseif ($isFreeViewer): ?>
                        <p class="tokkSimplePageActions">
                            <a href="<?php echo esc_url($upgradeUrl); ?>" class="privateUserBtn" role="link" title="スタンダード会員にアップグレード">
                                スタンダード会員にアップグレード
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </main>
    <?php
    get_footer();
    exit;
}

/**
 * 記事詳細の有料境界制御をテンプレートへ委譲する。
 *
 * `single-articles.php` 側で SSR の部分公開/ゲート表示を行うため、
 * ここでは早期 exit を行わない。
 */
function lutwiyo_guard_paid_limited_article_detail(): void
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (!is_singular('articles')) {
        return;
    }

    $postId = (int) get_queried_object_id();
    if ($postId <= 0) {
        return;
    }

    if (lutwiyo_get_article_member_access_plan($postId) !== 'paid_member') {
        return;
    }

    // 記事詳細の有料境界は single-articles.php 側で
    // "SSR-first + JS補正" のインラインゲートとして表示する。
    // ここでは早期 exit を行わず、テンプレートへ処理を委譲する。
    return;
}

add_action('template_redirect', 'lutwiyo_guard_paid_limited_article_detail', 2);

/**
 * 公開REST（articles）の本文を有料境界ルールでマスクする。
 */
add_filter('rest_prepare_articles', function ($response, $post, WP_REST_Request $request) {
    if (!$response instanceof WP_REST_Response || !$post instanceof WP_Post) {
        return $response;
    }

    if (!lutwiyo_should_mask_paid_article_content($post)) {
        return $response;
    }

    $data = $response->get_data();
    if (!is_array($data)) {
        return $response;
    }

    $maskedContent = lutwiyo_build_paid_article_masked_content($post);
    $safeDescription = lutwiyo_get_paid_article_safe_description($post);

    if (!isset($data['content']) || !is_array($data['content'])) {
        $data['content'] = [];
    }
    $data['content']['rendered'] = $maskedContent;
    $data['content']['protected'] = true;

    if (!isset($data['excerpt']) || !is_array($data['excerpt'])) {
        $data['excerpt'] = [];
    }
    $data['excerpt']['rendered'] = '<p>' . esc_html($safeDescription) . '</p>';

    if (isset($data['yoast_head_json']) && is_array($data['yoast_head_json'])) {
        $data['yoast_head_json']['description'] = $safeDescription;
        $data['yoast_head_json']['og_description'] = $safeDescription;
        $data['yoast_head_json']['twitter_description'] = $safeDescription;
    }

    $response->set_data($data);
    return $response;
}, 20, 3);

/**
 * RSS本文を有料境界ルールでマスクする。
 */
add_filter('the_content_feed', function (string $content): string {
    if (!is_feed()) {
        return $content;
    }

    global $post;
    if (!$post instanceof WP_Post) {
        return $content;
    }

    if (!lutwiyo_should_mask_paid_article_content($post)) {
        return lutwiyo_strip_paywall_gate_placeholder($content);
    }

    return lutwiyo_build_paid_article_masked_content($post);
}, 20);

/**
 * Yoastの説明系メタは、未権限時に全文由来の文言を出さない。
 */
foreach (['wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description'] as $yoastDescriptionFilter) {
    add_filter($yoastDescriptionFilter, function ($description) {
        if (!is_singular('articles')) {
            return $description;
        }

        $post = get_post((int) get_queried_object_id());
        if (!$post instanceof WP_Post || !lutwiyo_should_mask_paid_article_content($post)) {
            return $description;
        }

        return lutwiyo_get_paid_article_safe_description($post);
    }, 20);
}

/**
 * Yoast構造化データから未権限時の全文露出を避ける。
 */
add_filter('wpseo_schema_article', function ($data) {
    if (!is_singular('articles')) {
        return $data;
    }

    $post = get_post((int) get_queried_object_id());
    if (!$post instanceof WP_Post || !lutwiyo_should_mask_paid_article_content($post)) {
        return $data;
    }

    if (!is_array($data)) {
        return $data;
    }

    unset($data['articleBody']);
    $data['description'] = lutwiyo_get_paid_article_safe_description($post);
    $data['isAccessibleForFree'] = false;

    return $data;
}, 20);

add_filter('wpseo_schema_webpage', function ($data) {
    if (!is_singular('articles')) {
        return $data;
    }

    $post = get_post((int) get_queried_object_id());
    if (!$post instanceof WP_Post || !lutwiyo_should_mask_paid_article_content($post)) {
        return $data;
    }

    if (!is_array($data)) {
        return $data;
    }

    $data['description'] = lutwiyo_get_paid_article_safe_description($post);

    return $data;
}, 20);

/**
 * フォトギャラリー（一覧/タグ別/詳細）はスタンダード会員限定にする。
 */
function lutwiyo_guard_gallery_member_only(): void
{
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    $isGalleryTagPage = trim((string) get_query_var('gallery_tag', '')) !== '';
    $isGalleryArchivePage = is_post_type_archive('gallery');
    $isGallerySinglePage = is_singular('gallery');

    if (!$isGalleryTagPage && !$isGalleryArchivePage && !$isGallerySinglePage) {
        return;
    }

    $memberContext = lutwiyo_get_member_access_context();
    $plan = (string) ($memberContext['plan'] ?? 'guest');

    if ($plan === 'paid') {
        return;
    }

    lutwiyo_render_inline_gallery_paid_member_guide_and_exit($memberContext, lutwiyo_get_current_frontend_url_for_return());
}

add_action('template_redirect', 'lutwiyo_guard_gallery_member_only', 3);


/**
 * Gutenbergエディタで特定のタクソノミーブロックを非表示にする
 */
/**
 * 記事投稿タイプ（articles）のGutenbergエディタから特定タクソノミーを完全に非表示にする
 */
add_action('init', function () {
    foreach (['category', 'post_tag', 'area', 'station', 'articles_hide'] as $taxonomy) {
        global $wp_taxonomies;
        if (!empty($wp_taxonomies[$taxonomy])) {
            $wp_taxonomies[$taxonomy]->show_in_rest = false;
        }
    }
});

/**
 * リライトルールを登録
 *
 * @return void
 */
/**
 * ルーティング定義
 * - gallery: 一覧／詳細
 * - area: 記事一覧（taxonomy連携）
 * - articles: 記事詳細（/contents/... 系URL）
 */
function lutwiyo_register_rewrites()
{
    // 1. /gallery/detail/{post_name}
    add_rewrite_rule(
        '^gallery/detail/([^/]+)/?',
        'index.php?post_type=gallery&name=$matches[1]',
        'top'
    );

    // 2. /gallery/{tagname}
    add_rewrite_tag('%gallery_tag%', '([^&]+)');
    // /gallery/{tagname} → archive-gallery.php にルーティング
    add_rewrite_rule(
        '^gallery/([^/]+)/?$',
        'index.php?post_type=gallery&gallery_tag=$matches[1]',
        'top'
    );

    // 3. /area/{area_slug}/articles
    add_rewrite_rule(
        '^area/([^/]+)/articles/?$',
        'index.php?area=$matches[1]&post_type=articles',
        'top'
    );

    // 4. /area/{area_slug}/{category_slug as areacat}/
    add_rewrite_tag('%areacat%', '([^&]+)');
    add_rewrite_rule(
        '^area/([^/]+)/([^/]+)/?$',
        'index.php?area=$matches[1]&areacat=$matches[2]&post_type=articles',
        'top'
    );

    // 5. 4のページング
    add_rewrite_rule(
        '^area/([^/]+)/([^/]+)/page/([0-9]+)/?$',
        'index.php?area=$matches[1]&areacat=$matches[2]&post_type=articles&paged=$matches[3]',
        'top'
    );

    // ==============================================
    // 6〜9. articles の記事詳細（single-articles.php 呼び出し用）
    // ==============================================

    // 6. /contents/area/{acf_area}/{acf_category}/{post_name}/
    add_rewrite_rule(
        '^contents/area/([^/]+)/([^/]+)/([^/]+)/?$',
        'index.php?post_type=articles&name=$matches[3]',
        'top'
    );

    // 7. /contents/area/{acf_area}/{post_name}/
    add_rewrite_rule(
        '^contents/area/([^/]+)/([^/]+)/?$',
        'index.php?post_type=articles&name=$matches[2]',
        'top'
    );

    // 8. /contents/category/{acf_category}/{post_name}/
    add_rewrite_rule(
        '^contents/category/([^/]+)/([^/]+)/?$',
        'index.php?post_type=articles&name=$matches[2]',
        'top'
    );

    // 9. /contents/article/{post_name}/
    add_rewrite_rule(
        '^contents/article/([^/]+)/?$',
        'index.php?post_type=articles&name=$matches[1]',
        'top'
    );

    // 10. /contents/favorite/{favorite_article_id}/
    add_rewrite_rule(
        '^contents/favorite/([0-9]+)/?$',
        'index.php?post_type=articles&p=$matches[1]',
        'top'
    );

    // 11.
    add_rewrite_rule(
        '^favorite/page/([0-9]+)/?$',
        'index.php?pagename=favorite&paged=$matches[1]',
        'top'
    );

    // 12. /tag → タグ一覧
    add_rewrite_rule(
        '^tag/?$',
        'index.php?tokk_archive=tag',
        'top'
    );

    // 13. /category → カテゴリー一覧
    add_rewrite_rule(
        '^category/?$',
        'index.php?tokk_archive=category',
        'top'
    );

    // 14. /area → エリア一覧
    add_rewrite_rule(
        '^area/?$',
        'index.php?tokk_archive=area',
        'top'
    );

}

add_action('init', 'lutwiyo_register_rewrites');
flush_rewrite_rules();

/**
 * @param $post_link
 * @param $post
 * @param $leavename
 * @param $sample
 * @return mixed|string|null
 */

/**
 * カスタム投稿タイプ「articles」用のパーマリンク生成
 * ここを変更するときは、Yoast SEO の設定も合わせて変更すること
 *
 * URLパターン：
 * 0. /contents/area/{acf_area}/{acf_category}/{post_name}/
 * 1. /contents/area/{acf_area}/{post_name}/
 * 2. /contents/category/{acf_category}/{post_name}/
 * 3. /contents/article/{post_name}/
 */
function lutwiyo_custom_post_link($post_link, $post, $leavename, $sample)
{
    // gallery 用
    if ($post->post_type === 'gallery') {
        if (!empty($post->post_name)) {
            return home_url('/gallery/detail/' . $post->post_name . '/');
        }
        return $post_link;
    }

    // articles 以外は素通し
    if ($post->post_type !== 'articles') {
        return $post_link;
    }

    // サンプル生成時 & まだ確定前（auto-draft / draft）はコアに任せる
    if ($sample && in_array($post->post_status, ['auto-draft', 'draft'], true)) {
        return $post_link;
    }

    // post_name が空なら何もしない（壊れるので）
    if (empty($post->post_name)) {
        return $post_link;
    }

    // ACF側の area（タクソノミー: area）の先頭を取得（Term ID 戻り値想定）
    $area_ids = get_field('area', $post->ID);
    $area_ids = $area_ids ? (array) $area_ids : [];
    $first_area_id = reset($area_ids);

    $area_slug = '';

    if ($first_area_id) {
        $area_term = get_term($first_area_id, 'area');
        if (!is_wp_error($area_term) && $area_term) {
            $area_slug = $area_term->slug;
        }
    }

    // ACF側に値が無い場合は、従来どおり get_the_terms() にフォールバック
    if ($area_slug === '') {
        $area_terms = get_the_terms($post, 'area');
        $area_slug = (!is_wp_error($area_terms) && !empty($area_terms))
            ? $area_terms[0]->slug
            : '';
    }

    // ACF側の category（タクソノミー: category）の先頭を取得（Term ID 戻り値想定）
    $category_ids = get_field('category', $post->ID);
    $category_ids = $category_ids ? (array) $category_ids : [];
    $first_category_id = reset($category_ids);

    $category_slug = '';

    if ($first_category_id) {
        $category_term = get_term($first_category_id, 'category');
        if (!is_wp_error($category_term) && $category_term) {
            $category_slug = $category_term->slug;
        }
    }

    // ACF側に値が無い場合は、従来どおり get_the_terms() にフォールバック
    if ($category_slug === '') {
        $category_terms = get_the_terms($post, 'category');
        $category_slug = (!is_wp_error($category_terms) && !empty($category_terms))
            ? $category_terms[0]->slug
            : '';
    }

    // 両方ある：/contents/{area}/{category}/{slug}/
    if ($area_slug && $category_slug) {
        return home_url("/contents/area/{$area_slug}/{$category_slug}/{$post->post_name}/");
    }

    // area のみ：/contents/area/{area}/{slug}/
    if ($area_slug && !$category_slug) {
        return home_url("/contents/area/{$area_slug}/{$post->post_name}/");
    }

    // category のみ：/contents/category/{category}/{slug}/
    if (!$area_slug && $category_slug) {
        return home_url("/contents/category/{$category_slug}/{$post->post_name}/");
    }

    // どちらも無し：/contents/article/{slug}/
    return home_url("/contents/article/{$post->post_name}/");
}

add_filter('post_type_link', 'lutwiyo_custom_post_link', 10, 4);

add_filter('wpseo_canonical', function ($canonical) {

    global $post;

    if (!$post) {
        return $canonical;
    }

    /**
     * ==============================
     * 1. gallery の canonical
     * ==============================
     */
    if ($post->post_type === 'gallery') {

        if (!empty($post->post_name)) {
            return home_url("/gallery/detail/{$post->post_name}/");
        }

        // post_name が無い時は fallback
        return $canonical;
    }

    /**
     * ==============================
     * 2. articles の canonical
     * ==============================
     */
    if ($post->post_type === 'articles') {

        // post_name が空なら壊れるので fallback
        if (empty($post->post_name)) {
            return $canonical;
        }

        // タクソノミー取得（ACF優先＋未設定時は従来の get_the_terms() にフォールバック）
        $get_first_term_slug = function ($post, $taxonomy, $acf_field_key) {
            // ACF側（Term ID 戻り値）から先頭を取得
            $ids = get_field($acf_field_key, $post->ID);
            $ids = $ids ? (array) $ids : [];
            $first_id = reset($ids);

            if ($first_id) {
                $term = get_term($first_id, $taxonomy);
                if (!is_wp_error($term) && $term) {
                    return $term->slug;
                }
            }

            // ACFに値が無い or 取得失敗時は従来のタクソノミーから取得
            $terms = get_the_terms($post, $taxonomy);

            return (!is_wp_error($terms) && !empty($terms))
                ? $terms[0]->slug
                : '';
        };

        $area_slug     = $get_first_term_slug($post, 'area', 'area');
        $category_slug = $get_first_term_slug($post, 'category', 'category');

        // ① area + category
        if ($area_slug && $category_slug) {
            return home_url("/contents/area/{$area_slug}/{$category_slug}/{$post->post_name}/");
        }

        // ② area のみ
        if ($area_slug && !$category_slug) {
            return home_url("/contents/area/{$area_slug}/{$post->post_name}/");
        }

        // ③ category のみ
        if (!$area_slug && $category_slug) {
            return home_url("/contents/category/{$category_slug}/{$post->post_name}/");
        }

        // ④ どちらも無し
        return home_url("/contents/article/{$post->post_name}/");
    }


    /**
     * その他 post_type は触らない
     */
    return $canonical;
});

/**
 * テーマ切り替え時にリライトルールを更新
 *
 * @return void
 */
function lutwiyo_flush_rewrites_on_theme_activation()
{
    lutwiyo_register_rewrites();
    flush_rewrite_rules();
}

add_action('after_switch_theme', 'lutwiyo_flush_rewrites_on_theme_activation');

/**
 * カスタムクエリ変数を追加
 *
 * @param array $vars 既存のクエリ変数.
 * @return array
 */
function lutwiyo_register_query_vars($vars)
{
    $vars[] = 'pg';
    $vars[] = 'gallery_tag'; // ギャラリー用タグ
    $vars[] = 'tokk_archive'; // tag/category/area 一覧用
    return array_values(array_unique($vars));
}

add_filter('query_vars', 'lutwiyo_register_query_vars');

add_filter('template_include', function ($template) {
    $archive = get_query_var('tokk_archive');
    if ($archive === 'tag') {
        return get_template_directory() . '/tag-list.php';
    }
    if ($archive === 'category') {
        return get_template_directory() . '/category-list.php';
    }
    if ($archive === 'area') {
        return get_template_directory() . '/area-list.php';
    }
    return $template;
});

/**
 * 投稿保存時に JSON を更新
 *
 * @param int $post_id 投稿 ID.
 * @param WP_Post $post 投稿オブジェクト.
 * @param bool $update 更新フラグ.
 * @return void
 */
function lutwiyo_handle_post_insert($post_id, $post, $update)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    // Batch::update_json();
}

add_action('wp_insert_post', 'lutwiyo_handle_post_insert', 10, 3);

/**
 * 新規作成時に slug を article{ID} へ自動設定する
 */
/**
 * 新規作成時に slug を article{ID} へ自動設定する
 */
/**
 * post_type=articles の新規作成時、
 * 初回保存のタイミングで slug を article{投稿ID} に強制セットする
 */
function tokk_articles_auto_slug($post_id, $post, $update)
{

    // 自動保存／リビジョンは無視
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    // 既存投稿の更新時は何もしない（「初めて保存されたときだけ」動かしたい）
    if ($update) {
        return;
    }

    // 念のため post_type をチェック
    if ($post->post_type !== 'articles') {
        return;
    }

    // ここに来る時点で post_id は確定しているので、そのIDで slug を作る
    $new_slug = 'article' . $post_id;

    // すでに同じ slug なら何もしない
    if ($post->post_name === $new_slug) {
        return;
    }

    // slug を上書き
    wp_update_post([
        'ID' => $post_id,
        'post_name' => $new_slug,
    ]);
}

add_action('save_post_articles', 'tokk_articles_auto_slug', 10, 3);


/**
 * メインクエリの条件（WHERE / post_type / meta_query）を制御する
 *
 * 対象：フロント側のメインクエリのみ
 *
 * ■ 検索ページ（is_search）
 *   - post_type を articles のみに限定
 *   - meta_query / orderby / meta_key を初期化
 *     （検索結果の並び順は posts_clauses 側で完全制御するため）
 *
 * ■ present アーカイブ（is_post_type_archive('present')）
 *   - 応募期間内（start_date ≤ 現在 ≤ end_date）の投稿のみ抽出
 *   - 並び順：end_date ASC（終了日が近い順）
 *
 * ※ 並び順の最終決定（ORDER BY）はここでは行わない
 */
add_action('pre_get_posts', 'tokk_customize_main_query');
function tokk_customize_main_query($query)
{
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }

    /**
     * =========================
     * 検索ページ
     * =========================
     */
    if ($query->is_search()) {

        // 検索対象を articles に限定
        $query->set('post_type', 'articles');

        // 他用途の meta / order を検索に流入させない
        $query->set('meta_query', []);
        $query->set('meta_key', '');
        $query->set('orderby', '');
        $query->set('order', '');

        return;
    }

    /**
     * =========================
     * present アーカイブ
     * =========================
     */
    if ($query->is_post_type_archive('present')) {

        $now = get_current_jst(); // "Y-m-d H:i:s"

        // 応募期間内のみ
        $query->set('meta_query', [
            'relation' => 'AND',
            [
                'key'     => 'start_date',
                'value'   => $now,
                'compare' => '<=',
                'type'    => 'DATETIME',
            ],
            [
                'key'     => 'end_date',
                'value'   => $now,
                'compare' => '>=',
                'type'    => 'DATETIME',
            ],
        ]);

        // 終了日が近い順
        $query->set('meta_key', 'end_date');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'ASC');

        return;
    }
}


/**
 * 検索以外の一覧・アーカイブに対するデフォルトの並び順を定義
 *
 * 対象：
 *   - 検索ページ以外のフロント側メインクエリ
 *
 * 並び順の優先順位：
 *   1. update_date（メタが存在する場合）
 *   2. post_date（フォールバック）
 *
 * 実装内容：
 *   - update_date を postmeta から LEFT JOIN
 *   - CASE 式で update_date → post_date の順に降順ソート
 *
 * ※ 検索ページではこの処理は無効化される
 */
add_filter('posts_clauses', function ($clauses) {
    if (is_search()) {
        return $clauses;
    }

    global $wpdb;

    // update_date を JOIN
    $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS update_meta
                          ON ({$wpdb->posts}.ID = update_meta.post_id
                          AND update_meta.meta_key = 'update_date')";

    // 並び順：update_date → post_date
    $clauses['orderby'] = "CASE
        WHEN update_meta.meta_value IS NOT NULL
         AND update_meta.meta_value != ''
        THEN update_meta.meta_value
        ELSE {$wpdb->posts}.post_date
    END DESC";

    return $clauses;
}, 10);

/**
 * 検索結果（search.php）の並び順を最終的に確定させる
 *
 * 対象：
 *   - フロント側のメインクエリ
 *   - 検索ページ（is_search）のみ
 *
 * 並び順の優先順位：
 *   1. 検索キーワードとの関連度
 *        - FULL : タイトルに全キーワードを含む
 *        - PART : タイトルに一部キーワードを含む
 *        - BODY : 本文にのみキーワードを含む
 *   2. update_date（存在する場合）
 *   3. post_date（フォールバック）
 *
 * 実装内容：
 *   - update_date を LEFT JOIN（未結合時のみ）
 *   - CASE 式による関連度スコアリング
 *   - 関連度 → update_date → post_date の順で ORDER BY
 *
 * ※ priority を高く設定し、他の ORDER BY を必ず上書きする
 */
add_filter('posts_clauses', function ($clauses, $query) {
    if (is_admin() || ! $query->is_main_query() || ! $query->is_search()) {
        return $clauses;
    }

    global $wpdb;

    // update_date を JOIN（検索用）
    if (strpos($clauses['join'], 'update_meta') === false) {
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS update_meta
                              ON ({$wpdb->posts}.ID = update_meta.post_id
                              AND update_meta.meta_key = 'update_date')";
    }

    $search = trim($query->get('s'));

    /**
     * キーワードあり：関連度 → update_date → post_date
     */
    if ($search !== '') {

        $keywords = preg_split('/[\s　]+/u', $search, -1, PREG_SPLIT_NO_EMPTY);

        $title_full = [];
        $title_partial = [];
        $content = [];

        foreach ($keywords as $word) {
            $like = '%' . $wpdb->esc_like($word) . '%';
            $title_full[]    = "{$wpdb->posts}.post_title LIKE '{$like}'";
            $title_partial[] = "{$wpdb->posts}.post_title LIKE '{$like}'";
            $content[]       = "{$wpdb->posts}.post_content LIKE '{$like}'";
        }

        $clauses['orderby'] = "
            CASE
                WHEN (" . implode(' AND ', $title_full) . ") THEN 3
                WHEN (" . implode(' OR ',  $title_partial) . ") THEN 2
                WHEN (" . implode(' OR ',  $content) . ") THEN 1
                ELSE 0
            END DESC,
            CASE
                WHEN update_meta.meta_value IS NOT NULL
                 AND update_meta.meta_value != ''
                THEN update_meta.meta_value
                ELSE {$wpdb->posts}.post_date
            END DESC
        ";

        /**
         * キーワードなし：update_date → post_date
         */
    } else {

        $clauses['orderby'] = "
            CASE
                WHEN update_meta.meta_value IS NOT NULL
                 AND update_meta.meta_value != ''
                THEN update_meta.meta_value
                ELSE {$wpdb->posts}.post_date
            END DESC
        ";
    }

    return $clauses;
}, 100, 2);


/**
 * gallery アーカイブのカスタムクエリ調整
 */
add_action('pre_get_posts', function ($query) {

    // 管理画面やメインクエリ以外は除外
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    // 1. /gallery/ または /gallery/{slug}/ のページを対象にする
    if (is_post_type_archive('gallery') || get_query_var('gallery_tag')) {

        // =====================================================
        // A. /gallery/{gallery_tag}/ の場合
        // =====================================================
        $gallery_tag = get_query_var('gallery_tag');
        if (!empty($gallery_tag)) {

            $query->set('post_type', 'gallery');
            $query->set('posts_per_page', defined('POSTS_PER_PAGE') ? POSTS_PER_PAGE : 12);
            $query->set('tax_query', [[
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $gallery_tag,
            ]]);

            return; // gallery_tagありの場合はここで終了
        }

        // =====================================================
        // B. /gallery/（タグ指定なし）の場合
        // =====================================================
        if (is_post_type_archive('gallery')) {
            // ギャラリーポストのIDリストを取得
            $gallery_ids = get_posts([
                'post_type' => 'gallery',
                'numberposts' => -1,
                'fields' => 'ids',
            ]);

            // タグ一覧などを出す場合
            $query->set('hide_empty', true);
            $query->set('object_ids', $gallery_ids);
        }
    }
});


/**
 * area タクソノミーのカスタムクエリ調整
 * /area/{area_slug}/{category_slug}/ の場合に、
 * デフォルトクエリにカテゴリーがセットされた状態になるように
 * 調整する
 */
add_action('pre_get_posts', function ($query) {
    // 管理画面やサブクエリではなく、メインクエリのみ対象
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    // area タクソノミーが対象
    if (is_tax('area')) {
        $post_type = get_query_var('post_type');
        $areacat = get_query_var('areacat'); // 独自変数
        $area = get_query_var('area');

        // /area/{slug}/{category}/ の場合
        if ($post_type === 'articles' && !empty($areacat)) {
            $query->set('post_type', 'articles');

            $tax_query = [
                'relation' => 'AND',
                [
                    'taxonomy' => 'area',
                    'field' => 'slug',
                    'terms' => $area,
                ],
                [
                    'taxonomy' => 'category',
                    'field' => 'slug',
                    'terms' => $areacat,
                ],
            ];

            $query->set('tax_query', $tax_query);
        }
    }
});


/**
 * areaタクソノミーの default テンプレート時に Main Query を軽量化
 */
function lutwiyo_optimize_area_default_query($query)
{

    if (is_admin() || !$query->is_main_query()) return;

    // area タクソノミー以外は対象外
    if (!is_tax('area')) return;

    // ====== ★ areacat 保護（ここが最重要） =======
    $areacat = $query->get('areacat');
    if (!empty($areacat)) {
        // /area/kansai/gourmet/ などは絶対に軽量化しない
        return;
    }
    // ===========================================

    // post_type の判定
    $post_type = $query->get('post_type');

    // ★ articles の top のみ軽量化許可（あなたの taxonomy-area.php と厳密同期）
    if ($post_type === 'articles') {
        return; // articles top は軽量化しない（元のテンプレート処理）
    }

    // ----------------------------------
    // ★ ここから Main Query の完全軽量化（default のみ）
    // ----------------------------------

    $query->set('post__in', [0]);
    $query->set('posts_per_page', 1);

    $query->set('fields', 'ids');
    $query->set('orderby', 'none');
    $query->set('meta_query', []);
    $query->set('meta_key', '');

    // taxonomy は消さない（areacat を維持するため）
    // ★ ここ重要：$query->set('taxonomy', '') は絶対ダメ

    $query->set('post_type', 'any');

    $query->set('no_found_rows', true);
}

add_action('pre_get_posts', 'lutwiyo_optimize_area_default_query', 9999);


/**
 * taxonomy=category の一覧を post ではなく articles の一覧に変更
 */
add_action('pre_get_posts', function ($query) {

    // 管理画面・メインクエリ以外では処理しない
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    // カテゴリーアーカイブ（taxonomy=category）のときだけ適用
    if ($query->is_category()) {
        $query->set('post_type', ['articles']);
    }
});


/**
 * タグアーカイブで post_type=articles のみを表示する
 */
add_action('pre_get_posts', function ($query) {
    // 管理画面やサブループは除外
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    // /tag/{slug}/ にアクセスされた場合
    if ($query->is_tag()) {
        $query->set('post_type', 'articles');
    }
});

/**
 * 検索のメインクエリを articles のみに制限
 */
add_action('pre_get_posts', function ($query) {
    if (is_admin() || ! $query->is_main_query() || ! $query->is_search()) {
        return;
    }

    $tax_query = [];
    $meta_query = [];

    // category
    if (!empty($_GET['category']) && is_array($_GET['category'])) {
        $tax_query[] = [
            'taxonomy' => 'category',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_text_field', $_GET['category']),
        ];
    }

    // post_tag
    if (!empty($_GET['post_tag']) && is_array($_GET['post_tag'])) {
        $tax_query[] = [
            'taxonomy' => 'post_tag',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_text_field', $_GET['post_tag']),
        ];
    }

    // area（カスタムtaxonomy）
    if (!empty($_GET['area']) && is_array($_GET['area'])) {
        $tax_query[] = [
            'taxonomy' => 'area',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_text_field', $_GET['area']),
        ];
    }

    $memberAccessPlan = isset($_GET['member_access_plan'])
        ? sanitize_key(wp_unslash((string) $_GET['member_access_plan']))
        : '';
    if ($memberAccessPlan === 'paid_member') {
        $meta_query[] = [
            'key' => 'member_access_plan',
            'value' => 'paid_member',
        ];
    }

    if (!empty($tax_query)) {
        $query->set('tax_query', $tax_query);
    }

    if (!empty($meta_query)) {
        $query->set('meta_query', $meta_query);
    }
});



/**
 * 検索結果の title カラム先頭に評価種別を付与（検証用）
 * [FULL][PART][BODY]
 */
add_filter('posts_fields', 'tokk_search_prefix_label_to_title', 20, 2);
function tokk_search_prefix_label_to_title($fields, $query)
{
    if (is_admin() || ! $query->is_main_query() || ! $query->is_search()) {
        return $fields;
    }

    global $wpdb;

    $search = trim($query->get('s'));
    if ($search === '') {
        return $fields;
    }

    $keywords = preg_split('/[\s　]+/u', $search);

    $title_full = [];
    $title_partial = [];
    $content = [];

    foreach ($keywords as $word) {
        $like = '%' . $wpdb->esc_like($word) . '%';
        $title_full[]    = "{$wpdb->posts}.post_title LIKE '{$like}'";
        $title_partial[] = "{$wpdb->posts}.post_title LIKE '{$like}'";
        $content[]       = "{$wpdb->posts}.post_content LIKE '{$like}'";
    }

    $label_sql = "
        CASE
            WHEN (" . implode(' AND ', $title_full) . ") THEN '[FULL] '
            WHEN (" . implode(' OR ',  $title_partial) . ") THEN '[PART] '
            WHEN (" . implode(' OR ',  $content) . ") THEN '[BODY] '
            ELSE '[NONE] '
        END
    ";

    $fields = str_replace(
        "{$wpdb->posts}.*",
        "{$wpdb->posts}.*,
         CONCAT({$label_sql}, {$wpdb->posts}.post_title) AS post_title",
        $fields
    );

    return $fields;
}


add_filter('posts_orderby', 'tokk_search_orderby_score', 20, 2);
function tokk_search_orderby_score($orderby, $query)
{
    if (is_admin() || ! $query->is_main_query() || ! $query->is_search()) {
        return $orderby;
    }

    global $wpdb;

    $search = trim($query->get('s'));
    if ($search === '') {
        return $orderby;
    }

    $keywords = preg_split('/[\s　]+/u', $search);

    $title_full = [];
    $title_partial = [];
    $content = [];

    foreach ($keywords as $word) {
        $like = '%' . $wpdb->esc_like($word) . '%';
        $title_full[]    = "{$wpdb->posts}.post_title LIKE '{$like}'";
        $title_partial[] = "{$wpdb->posts}.post_title LIKE '{$like}'";
        $content[]       = "{$wpdb->posts}.post_content LIKE '{$like}'";
    }

    return "
        CASE
            WHEN (" . implode(' AND ', $title_full) . ") THEN 3
            WHEN (" . implode(' OR ',  $title_partial) . ") THEN 2
            WHEN (" . implode(' OR ',  $content) . ") THEN 1
            ELSE 0
        END DESC,
        {$wpdb->posts}.post_date DESC
    ";
}

TermModelHelper::set_taxonomy_config(
    'area',
    [

        // 抽出したいACFフィールド名の配列
        'acf_fields' => [
            // 英語表記
            'title_en',
            // 一覧への表示
            'is_clickable',
            // エリア画像 *
            'image',
            // XのURL
            'url_x',
            // FacebookページのURL
            'url_facebook',
            // エリアトップおすすめ記事の一覧 →エリアトップだけ出すのでconfigには不要
            'recommended_articles_list',
            // ピン留めする記事
            'pinned_list',
            // ピン留めするタイアップ記事
            'pinned_sponsored_articles_list',
            // おすすめエリア
            'recommended_area',
            // 広告エリア
            'ad_list',
            // カテゴリーとピン留め記事一覧のリスト
            'recommended_category_and_articles_list',
            // タグとピン留め記事一覧のリスト
            'recommended_tag_and_articles_list',
            // メモ
            'memo',
            // 緯度
            'lat',
            // 経度
            'lon',
            // エリア短縮名 *
            'shortname',
            // エリアカラー *
            'color'
        ],
        'transformers' => [
            // ACFで設定したエリア画像を取得する
            [
                'source' => 'image',
                'callback' => function ($value, $term) {
                    return $value['url'] ?? null;
                },
                'target' => 'image_url'
            ],
            // pinned_listを再加工する
            [
                'source' => 'pinned_list',
                'callback' => function ($pinned_list) {
                    if (!is_array($pinned_list)) {
                        return [];
                    }
                    return PostModelHelper::get_posts_payload(
                        $pinned_list,
                        []
                    );
                },
                'target' => 'pinned_articles_info_list'
            ],
        ],
    ]);

TermModelHelper::set_taxonomy_config(
    'category',
    [

        // 抽出したいACFフィールド名の配列
        'acf_fields' => [
            'image',
            'pinned_list',
        ],
        'transformers' => [
            // ACFで設定したエリア画像を取得する
            [
                'source' => 'image',
                'callback' => function ($value, $term) {
                    return $value['url'] ?? null;
                },
                'target' => 'image_url'
            ],
            // pinned_listを再加工する
            [
                'source' => 'pinned_list',
                'callback' => function ($pinned_list) {
                    if (!is_array($pinned_list)) {
                        return [];
                    }
                    return PostModelHelper::get_posts_payload(
                        $pinned_list,
                        []
                    );
                },
                'target' => 'pinned_articles_info_list'
            ],
        ],
    ]);

TermModelHelper::set_taxonomy_config(
    'post_tag',
    [

        // 抽出したいACFフィールド名の配列
        'acf_fields' => [
            'pinned_list',
        ],
        'transformers' => [
            // pinned_listを再加工する
            [
                'source' => 'pinned_list',
                'callback' => function ($pinned_list) {
                    if (!is_array($pinned_list)) {
                        return [];
                    }
                    return PostModelHelper::get_posts_payload(
                        $pinned_list,
                        []
                    );
                },
                'target' => 'pinned_articles_info_list'
            ],
        ],
    ]);

// -------------------------------------
// PostModelHelperで投稿を取得する際のプリセットのカスタマイズ
// サイト内のすべての表示フォーマットなどここで制御するので、取り扱いは慎重に！
// -------------------------------------
PostModelHelper::set_post_type_config('articles', [
    'acf_fields' => ['articles_img', 'area', 'category', 'tag', 'staff_list', 'reading_time', 'update_date', 'articles_pr', 'member_access_plan'],
    'transformers' => [
        // ACFで設定したサムネイル画像を取得する
        [
            'source' => 'articles_img',
            'callback' => function ($attachment_post_id, $post) {
                return wp_get_attachment_url($attachment_post_id) ?: PLACEHOLDER_IMAGE_URL;
            },
            'target' => 'image_url'
        ],
        // 読了時間ラベルを生成する
        [
            'source' => 'reading_time',
            'callback' => function ($reading_time) {
                if (!is_numeric($reading_time) || $reading_time <= 0) {
                    return '';
                }

                // 秒 → 分換算し四捨五入
                $minutes = round(((float)$reading_time) / 60);

                // 最低1分保証（任意）
                if ($minutes < 1) {
                    $minutes = 1;
                }

                return "{$minutes}min.";
            },
            'target' => 'reading_time_label',
        ],

        // 公開日をフォーマット (例: 24.05.03)
        [
            'source' => 'date',
            'callback' => ['PostModelHelper', 'format_date_ymd'],
            'target' => 'date_label',
        ],

        // ACFの更新日をフォーマット (例: 24.05.15)
        [
            'source' => 'update_date',
            'callback' => ['PostModelHelper', 'format_date_ymd'],
            'target' => 'update_date_label',
        ],
        // 画面上に出力する日付を決定する
        [
            'source' => 'date',
            'callback' => function ($date, $post) {
                return $post['update_date_label'] ?: $post['date_label'];
            },
            'target' => 'display_date_label',
        ],
        [
            'source' => 'update_date_label',
            'callback' => function ($update_date_label, $post) {
                // update_date_label があればそちらを優先、なければ date_label を使用
                if (!empty($update_date_label)) {
                    return $update_date_label;
                }
                return $post['date_label'] ?? '';
            },
            'target' => 'display_date_label',
        ],
        // 投稿者情報に、サムネイルとリンクなどの情報を追加する
        [
            'source' => 'staff_list',
            'callback' => function ($staff_list) {
                if (empty($staff_list) || !is_array($staff_list)) {
                    return [];
                }
                $staff_info = [];
                foreach ($staff_list as $staff) {
                    $staff_info[] = [
                        'image_url' => get_field('image', $staff)['url'] ?? DEFAULT_STAFF_IMAGE_URL,
                        'title' => get_the_title($staff),
                        'link' => get_permalink($staff),
                    ];
                }
                return $staff_info;
            },
            'target' => 'staff_info'
        ],
        // タグ情報を設定する
        [
            'source' => 'tag',
            'callback' => function ($tag_terms) {
                if (empty($tag_terms) || !is_array($tag_terms)) {
                    return [];
                }
                $tag_info = [];
                foreach ($tag_terms as $term_id) {
                    $term = get_term($term_id);
                    $tag_info[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'link' => get_term_link($term),
                    ];
                }
                return $tag_info;
            },
            'target' => 'tag_info'
        ],
        // カテゴリー情報を設定する
        [
            'source' => 'category',
            'callback' => function ($category_terms) {
                if (empty($category_terms) || !is_array($category_terms)) {
                    return [];
                }
                $category_info = [];
                foreach ($category_terms as $category_id) {
                    $term = get_term($category_id);
                    $category_info[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'link' => get_term_link($term),
                    ];
                }
                return $category_info;
            },
            'target' => 'category_info'
        ],
        // エリア情報を設定する
        [
            'source' => 'area',
            'callback' => function ($area_terms) {
                if (empty($area_terms) || !is_array($area_terms)) {
                    return [];
                }
                $area_info = [];
                foreach ($area_terms as $area_id) {
                    $term = get_term($area_id);
                    $area_info[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'link' => get_term_link($term),
                    ];
                }
                return $area_info;
            },
            'target' => 'area_info'
        ],
        // TODO: 特典の有無処理
        [
            'source' => 'id',
            'callback' => function ($article_id) {
                $related_article_ids = get_related_article_ids_from_presents();
                return in_array((int)$article_id, $related_article_ids, true);
            },
            'target' => 'has_present',
        ],
        // PRかどうかを判定する
        [
            'source' => 'articles_pr',
            'callback' => function ($articles_pr, $post) {
                return $articles_pr;
            },
            'target' => 'is_pr'
        ],
        // 会員向け公開プランを正規化する
        [
            'source' => 'member_access_plan',
            'callback' => function ($member_access_plan) {
                $plan = is_string($member_access_plan) ? sanitize_key($member_access_plan) : 'public';
                return in_array($plan, ['public', 'paid_member'], true) ? $plan : 'public';
            },
            'target' => 'member_access_plan',
        ],
        // お気に入り登録されているかを判定するフラグ
        [
            'source' => 'id',
            'callback' => function ($post_id) {
                return lutwiyo_get_current_member_favorite_state('tokk_article', (string) $post_id);
            },
            'target' => 'is_favorited',
        ],
        // 新着記事かどうかを判定するフラグ
        [
            'source' => 'date',
            'callback' => function ($date, $post) {
                // $post は現在処理中の投稿データ全体（配列またはオブジェクト）
                $update_date = $post['update_date'] ?? null;

                // 例：NEW_ARRIVAL_DAYS以内に公開・更新されたら新着とみなす
                $target_time = $update_date ?: $date;
                $is_new = (strtotime('now') - strtotime($target_time)) < (NEW_ARRIVAL_DAYS * 24 * 60 * 60);

                return $is_new;
            },
            'target' => 'is_new'
        ],
    ],
]);

PostModelHelper::set_post_type_config('gallery', [
    'acf_fields' => ['image', 'post_tag', 'gallery'],
    'transformers' => [
        // ACFで設定したサムネイル画像を取得する
        [
            'source' => 'image',
            'callback' => function ($attachment_post) {
                return $attachment_post['url'] ?? null;
            },
            'target' => 'image_url'
        ],
        // タグ情報を設定する
        [
            'source' => 'post_tag',
            'callback' => function ($tag_terms) {
                if (empty($tag_terms) || !is_array($tag_terms)) {
                    return [];
                }
                $tag_info = [];
                foreach ($tag_terms as $term_id) {
                    $term = get_term($term_id);
                    $tag_info[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'link' => get_term_link($term),
                    ];
                }
                return $tag_info;
            },
            'target' => 'tag_info'
        ],
    ],
]);

PostModelHelper::set_post_type_config('present', [
    'acf_fields' => ['start_date', 'end_date', 'related_articles', 'text', 'image'],
    'transformers' => [
        // 公開日をフォーマット (例: 24.05.03)
        [
            'source' => 'start_date',
            'callback' => ['PostModelHelper', 'format_date_ymd'],
            'target' => 'start_date_label',
        ],
        // 更新日をフォーマット (例: 24.05.15)
        [
            'source' => 'end_date',
            'callback' => ['PostModelHelper', 'format_date_ymd'],
            'target' => 'end_date_label',
        ],
        // リンク先を設定
        [
            'source' => 'related_articles',
            'callback' => function ($related_articles) {
                // デフォルト：関連投稿がない場合の遷移先
                $url = DEFAULT_PRESENT_LIST_URL;
                $link_type = 'present_list';

                // 関連投稿がセットされている場合のみ処理
                if ($related_articles instanceof WP_Post || is_numeric($related_articles)) {

                    // 投稿IDを取得（WP_Post なら ID、数値ならそのまま）
                    $post_id = ($related_articles instanceof WP_Post)
                        ? $related_articles->ID
                        : intval($related_articles);

                    // 投稿ステータスを取得
                    $status = get_post_status($post_id);

                    // 公開済み投稿のみ処理
                    if ($status === 'publish') {

                        // パーマリンクを取得
                        $permalink = get_permalink($post_id);

                        if (!empty($permalink)) {
                            $url = $permalink;
                            $link_type = 'present_detail';
                        }
                    }
                }

                return [
                    'url' => $url,
                    'link_type' => $link_type,
                ];
            },
            'target' => ['url', 'link_type'],
        ],
        // ACFで設定したサムネイル画像を取得する
        [
            'source' => 'image',
            'callback' => function ($attachment_post) {
                return $attachment_post['url'] ?? null;
            },
            'target' => 'image_url'
        ],
        // 新着記事かどうかを判定するフラグ
        [
            'source' => 'date',
            'callback' => function ($date, $post) {

                // 例：NEW_ARRIVAL_DAYS以内に公開・更新されたら新着とみなす
                $target_time = $date;
                $is_new = (strtotime('now') - strtotime($target_time)) < (NEW_ARRIVAL_DAYS * 24 * 60 * 60);

                return $is_new;
            },
            'target' => 'is_new'
        ],
        // 日本語形式の日付ラベルを生成する
        [
            'source' => 'start_date',
            'callback' => function ($date) {
                return format_japanese_date($date);
            },
            'target' => 'start_date_label'
        ],
        [
            'source' => 'end_date',
            'callback' => function ($date) {
                return format_japanese_date($date);
            },
            'target' => 'end_date_label'
        ],
    ],
]);

PostModelHelper::set_post_type_config('advertisement', [
    // 抽出したいACFフィールド名の配列
    'acf_fields' => ['ad_type', 'image', 'url', 'is_blank', 'code', 'start_date', 'end_date', 'text'],
    // 各投稿に対して追加実行する関数セット
    'transformers' => [
        // 公開日をフォーマット (例: 24.05.03)
        [
            'source' => 'start_date',
            'callback' => ['PostModelHelper', 'format_date_ymd'],
            'target' => 'start_date_label',
        ],
        // 終了日をフォーマット (例: 24.05.15)
        [
            'source' => 'end_date',
            'callback' => ['PostModelHelper', 'format_date_ymd'],
            'target' => 'end_date_label',
        ],
        // ACFで設定したサムネイル画像を取得する
        [
            'source' => 'image',
            'callback' => function ($attachment_post) {
                return $attachment_post['url'] ?? null;
            },
            'target' => 'image_url'
        ],
    ],
]);

// function my_theme_auto_enqueue_assets()
// {
//     $theme_dir = get_template_directory_uri();
//     $assets_dir = get_template_directory() . '/assets/';
//
//     // === CSS ===
//     $css_dir = $assets_dir . 'css/';
//     if (file_exists($css_dir)) {
//         foreach (glob($css_dir . '*.css') as $css_file) {
//             $handle = basename($css_file, '.css');
//             wp_enqueue_style(
//                 'my-theme-' . $handle,
//                 $theme_dir . '/assets/css/' . basename($css_file),
//                 array(),
//                 filemtime($css_file)
//             );
//         }
//     }
//
//     // === JS ===
//     $js_dir = $assets_dir . 'js/';
//     if (file_exists($js_dir)) {
//         foreach (glob($js_dir . '*.js') as $js_file) {
//             $handle = basename($js_file, '.js');
//             wp_enqueue_script(
//                 'my-theme-' . $handle,
//                 $theme_dir . '/assets/js/' . basename($js_file),
//                 array('jquery'),
//                 filemtime($js_file),
//                 true
//             );
//         }
//     }
// }
//
// add_action('wp_enqueue_scripts', 'my_theme_auto_enqueue_assets');

add_action('init', function () {
    add_rewrite_rule('^search/page/([0-9]+)/?$', 'index.php?pagename=search&paged=$matches[1]', 'top');
});


// -------------------------------------
// 記事を読むのにかかる時間の算出
// -------------------------------------
/**
 * 投稿タイプ articles の初回公開時に読了時間を自動算出して ACF に保存
 */
/**
 * 投稿タイプ articles の公開・更新時に読了時間を自動算出して ACF に保存
 */
add_action('acf/save_post', function ($post_id) {

    // 投稿タイプを確認
    if (get_post_type($post_id) !== 'articles') return;

    // ステータスが公開以外はスキップ（下書きや自動保存などを除外）
    // TODO 動作確認できたら、publishを復活させる
    // if (get_post_status($post_id) !== 'publish') return;

    // 投稿本文を取得
    $post = get_post($post_id);
    $content = $post->post_content;
    if (empty($content)) return;

    // ヒーロー画像（アイキャッチ）
    $hero_time = 5; // 常にヒーロー画像はあるので固定

    // 見出しタグ（h2〜h6)）
    preg_match_all('/<h[23456][^>]*>.*?<\/h[23456]>/', $content, $matches_headings);
    $heading_count = count($matches_headings[0]);
    $heading_time = $heading_count * 2;

    // 本文中の画像タグ
    preg_match_all('/<img[^>]+>/', $content, $matches_images);
    $image_count = count($matches_images[0]);
    $image_time = $image_count * 5;

    // 本文文字数（HTMLタグ除去後）
    $text_content = wp_strip_all_tags($content);
    $char_count = mb_strlen($text_content);

    // 550文字 = 60秒 → 1文字あたり (60/550) 秒
    $text_time = floor($char_count * (60 / 550));

    // 合計秒数（小数点以下切り捨て）
    $total_time = floor($hero_time + $heading_time + $image_time + $text_time);

    // ACFフィールドへ保存（数値フィールドを想定）
    // フィールドキーでの更新を推奨：update_field('field_XXXXXXXXXXXX', $total_time, $post_id);
    update_field('reading_time', $total_time, $post_id);

    // （開発用）ログ出力例
    // $log_file = $_SERVER['DOCUMENT_ROOT'] . '/result.txt';
    // $log = sprintf("[%s] acf/save_post fired | ID=%d | reading_time=%d\n", date('Y-m-d H:i:s'), $post_id, $total_time);
    // file_put_contents($log_file, $log, FILE_APPEND | LOCK_EX);

}, 20); // 優先度20でACFの保存処理完了後に実行

/**
 * ACFのpost_id形式を正規化して投稿ID（int）へ変換する。
 *
 * @param mixed $postId
 * @return int
 */
function lutwiyo_resolve_acf_post_id($postId)
{
    if (is_numeric($postId)) {
        return (int) $postId;
    }

    if (!is_string($postId)) {
        return 0;
    }

    $normalized = trim($postId);
    if ($normalized === '') {
        return 0;
    }

    if (preg_match('/^post_(\d+)$/', $normalized, $matches) === 1) {
        return (int) $matches[1];
    }

    return 0;
}

/**
 * コメント閲覧ACLを仕様値に正規化する。
 *
 * @param mixed $value
 * @return string
 */
function lutwiyo_normalize_comment_view_acl($value)
{
    if (is_array($value)) {
        if (array_key_exists('value', $value)) {
            $value = $value['value'];
        } else {
            $first = reset($value);
            $value = $first !== false ? $first : '';
        }
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['public', 'members', 'private'], true)
        ? $normalized
        : (function_exists('lutwiyo_get_comment_view_acl_default') ? lutwiyo_get_comment_view_acl_default() : 'public');
}

/**
 * コメント投稿ACLを仕様値に正規化する。
 *
 * @param mixed $value
 * @return string
 */
function lutwiyo_normalize_comment_post_acl($value)
{
    if (is_array($value)) {
        if (array_key_exists('value', $value)) {
            $value = $value['value'];
        } else {
            $first = reset($value);
            $value = $first !== false ? $first : '';
        }
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['enabled', 'disabled'], true)
        ? $normalized
        : (function_exists('lutwiyo_get_comment_post_acl_default') ? lutwiyo_get_comment_post_acl_default() : 'enabled');
}

/**
 * 既存TOKK記事のコメント投稿ACLを一括で投稿可能に寄せる。
 *
 * @param bool $dryRun
 * @return array{total:int,already_enabled:int,write_planned:int,sync_attempted:int}
 */
function lutwiyo_enable_comment_post_acl_for_all_articles(bool $dryRun = false): array
{
    if (!function_exists('get_posts')) {
        return [
            'total' => 0,
            'already_enabled' => 0,
            'write_planned' => 0,
            'sync_attempted' => 0,
        ];
    }

    $postIds = get_posts([
        'post_type' => 'articles',
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
        'suppress_filters' => true,
    ]);

    if (!is_array($postIds)) {
        $postIds = [];
    }

    $alreadyEnabled = 0;
    $syncAttempted = 0;

    foreach ($postIds as $postId) {
        $postId = (int) $postId;
        if ($postId <= 0 || get_post_type($postId) !== 'articles') {
            continue;
        }

        $currentPostAcl = function_exists('get_field')
            ? lutwiyo_normalize_comment_post_acl(get_field('comment_post_acl', $postId))
            : lutwiyo_normalize_comment_post_acl(get_post_meta($postId, 'comment_post_acl', true));

        if ($currentPostAcl === 'enabled') {
            $alreadyEnabled++;
        }

        if ($dryRun) {
            continue;
        }

        if (function_exists('update_field')) {
            update_field('field_comment_post_acl_articles_v1', 'enabled', $postId);
        } else {
            update_post_meta($postId, 'comment_post_acl', 'enabled');
        }

        lutwiyo_sync_comment_policy_for_articles($postId);
        $syncAttempted++;
    }

    return [
        'total' => count($postIds),
        'already_enabled' => $alreadyEnabled,
        'write_planned' => count($postIds),
        'sync_attempted' => $syncAttempted,
    ];
}

/**
 * TOKK記事（post_type=articles）のコメントポリシーを member-service へ同期する。
 *
 * 同期先:
 * - base_url: https://{wordpress-host}/member-service
 * - endpoint: /api/v1/admin/comment-policies/{resource_type}/{resource_id}
 */
function lutwiyo_sync_comment_policy_for_articles($acfPostId)
{
    if (!function_exists('lutwiyo_call_member_service_api')) {
        return;
    }

    $postId = lutwiyo_resolve_acf_post_id($acfPostId);
    if ($postId <= 0) {
        return;
    }

    if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
        return;
    }

    if (get_post_type($postId) !== 'articles') {
        return;
    }

    $commentViewAcl = function_exists('get_field')
        ? lutwiyo_normalize_comment_view_acl(get_field('comment_view_acl', $postId))
        : lutwiyo_normalize_comment_view_acl(get_post_meta($postId, 'comment_view_acl', true));

    $commentPostAcl = function_exists('get_field')
        ? lutwiyo_normalize_comment_post_acl(get_field('comment_post_acl', $postId))
        : lutwiyo_normalize_comment_post_acl(get_post_meta($postId, 'comment_post_acl', true));

    $storedRevision = (int) get_post_meta($postId, '_comment_policy_revision', true);
    $nextRevision = max(1, $storedRevision + 1);

    $sourceUpdatedAt = get_post_modified_time(DATE_ATOM, true, $postId);
    if (!is_string($sourceUpdatedAt) || $sourceUpdatedAt === '') {
        $sourceUpdatedAt = gmdate(DATE_ATOM);
    }

    $endpoint = sprintf(
        '/member-service/api/v1/admin/comment-policies/%s/%s',
        rawurlencode('tokk_article'),
        rawurlencode((string) $postId)
    );

    $payload = [
        'comment_view_acl' => $commentViewAcl,
        'comment_post_acl' => $commentPostAcl,
        'policy_revision' => $nextRevision,
        'source_updated_at' => $sourceUpdatedAt,
        'source_system' => 'tokk_wp',
    ];

    $response = lutwiyo_call_member_service_api($endpoint, 'PUT', $payload);
    if (!is_array($response) || !is_array($response['body'] ?? null)) {
        error_log(sprintf(
            '[tokk_comment_policy_sync] failed: no_response post_id=%d endpoint=%s',
            $postId,
            $endpoint
        ));
        return;
    }

    $status = (int) ($response['status'] ?? 0);
    $body = $response['body'];
    $requestId = is_array($body) ? (string) ($body['request_id'] ?? '') : '';

    if ($status === 409
        && ($body['result'] ?? '') === 'error'
        && ($body['code'] ?? '') === 'CONFLICT'
        && ($body['message'] ?? '') === 'stale_policy_revision'
    ) {
        // 管理API側でrevision衝突した場合は、時刻ベースのrevisionで1回だけ再送する。
        $retryPayload = $payload;
        $retryPayload['policy_revision'] = max(
            (int) current_time('timestamp', true),
            (int) $nextRevision + 1
        );
        $retryPayload['source_updated_at'] = gmdate(DATE_ATOM);

        $retryResponse = lutwiyo_call_member_service_api($endpoint, 'PUT', $retryPayload);
        if (is_array($retryResponse) && is_array($retryResponse['body'] ?? null)) {
            $status = (int) ($retryResponse['status'] ?? 0);
            $body = $retryResponse['body'];
            $requestId = is_array($body) ? (string) ($body['request_id'] ?? '') : '';
            $nextRevision = (int) $retryPayload['policy_revision'];
        } else {
            error_log(sprintf(
                '[tokk_comment_policy_sync] failed: retry_no_response post_id=%d endpoint=%s first_request_id=%s',
                $postId,
                $endpoint,
                $requestId
            ));
            return;
        }
    }

    if ($status === 200 && ($body['result'] ?? '') === 'ok') {
        $actualRevision = (int) (($body['data']['policy_revision'] ?? 0));
        if ($actualRevision < 1) {
            $actualRevision = $nextRevision;
        }

        update_post_meta($postId, '_comment_policy_revision', $actualRevision);
        update_post_meta($postId, '_comment_policy_synced_view_acl', $commentViewAcl);
        update_post_meta($postId, '_comment_policy_synced_post_acl', $commentPostAcl);
        update_post_meta($postId, '_comment_policy_synced_at', current_time('mysql', true));
        return;
    }

    error_log(sprintf(
        '[tokk_comment_policy_sync] failed: status=%d post_id=%d endpoint=%s request_id=%s code=%s message=%s',
        $status,
        $postId,
        $endpoint,
        $requestId,
        (string) ($body['code'] ?? ''),
        (string) ($body['message'] ?? '')
    ));
}
add_action('acf/save_post', 'lutwiyo_sync_comment_policy_for_articles', 30);

if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
    WP_CLI::add_command('tokk comment-policy enable-articles', function ($args, $assocArgs) {
        $dryRun = isset($assocArgs['dry-run']);
        $result = lutwiyo_enable_comment_post_acl_for_all_articles($dryRun);

        WP_CLI::log(sprintf(
            'articles=%d already_enabled=%d write_planned=%d sync_attempted=%d dry_run=%s',
            (int) $result['total'],
            (int) $result['already_enabled'],
            (int) $result['write_planned'],
            (int) $result['sync_attempted'],
            $dryRun ? 'true' : 'false'
        ));

        if ($dryRun) {
            WP_CLI::success('Dry-run completed.');
            return;
        }

        WP_CLI::success('TOKK article comment post ACL backfill completed.');
    });
}

/**
 * present投稿に関連付けられた article投稿ID一覧をキャッシュ付きで取得
 *
 * @return int[] related_articles に設定された記事IDの配列
 */
function get_related_article_ids_from_presents(): array
{
    $cache_key = 'present_related_articles_ids';
    $cached = get_transient($cache_key);

    if ($cached !== false && is_array($cached)) {
        return $cached;
    }

    $related_article_ids = [];

    // post_type=present かつ 公開済み の投稿を取得
    $present_posts = get_posts([
        'post_type' => 'present',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);

    if (!empty($present_posts) && function_exists('get_field')) {
        foreach ($present_posts as $present_id) {
            $related = get_field('related_articles', $present_id);
            if (empty($related)) {
                continue;
            }

            if (is_array($related)) {
                foreach ($related as $item) {
                    if ($item instanceof WP_Post) {
                        $related_article_ids[] = $item->ID;
                    } elseif (is_numeric($item)) {
                        $related_article_ids[] = (int)$item;
                    }
                }
            } elseif ($related instanceof WP_Post) {
                $related_article_ids[] = $related->ID;
            } elseif (is_numeric($related)) {
                $related_article_ids[] = (int)$related;
            }
        }
    }

    // 重複除去
    $related_article_ids = array_values(array_unique($related_article_ids));

    // 6時間キャッシュ
    set_transient($cache_key, $related_article_ids, HOUR_IN_SECONDS * 6);

    return $related_article_ids;
}

// present投稿が保存されたときにキャッシュをクリア
// get_related_article_ids_from_presents の結果を最新化するため
add_action('save_post_present', function ($post_id) {
    delete_transient('present_related_articles_ids');
});

/**
 * お気に入りの登録件数を返す
 *
 * @return int 件数
 */
function get_favorite_count()
{
    if (function_exists('tokk_member_service_disable_favorite_count')
        && tokk_member_service_disable_favorite_count()) {
        return 0;
    }

    return count(lutwiyo_get_current_member_favorite_article_ids());
}

/**
 * 現在ログイン中の会員UIDを Bridge API 経由で取得する。
 *
 * @return string 会員UID（未ログイン・取得失敗時は空文字）
 */
function lutwiyo_get_current_member_uid_from_bridge()
{
    static $resolved = false;
    static $cachedMemberUid = '';

    if ($resolved) {
        return $cachedMemberUid;
    }

    $resolved = true;

    $loginStatusResponse = lutwiyo_get_or_fetch_login_status_response();
    $isLoggedIn = is_array($loginStatusResponse)
        && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';

    if (!$isLoggedIn) {
        $cachedMemberUid = '';
        return $cachedMemberUid;
    }

    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();

    if (!is_array($memberInfoResponse) || ($memberInfoResponse['body']['result'] ?? '') !== 'ok') {
        $cachedMemberUid = '';
        return $cachedMemberUid;
    }

    $cachedMemberUid = trim((string) ($memberInfoResponse['body']['uid'] ?? ''));

    return $cachedMemberUid;
}

/**
 * 記事コメント一覧を member-service API から取得し、SSR描画しやすい形に正規化して返す。
 *
 * @param string $resourceType
 * @param string $resourceId
 * @param int $page
 * @param int $perPage
 * @param string $sort
 * @return array{
 *   ok: bool,
 *   status: int,
 *   code: string,
 *   message: string,
 *   total: int,
 *   items: array<int, array{
 *     comment_id:int,
 *     body:string,
 *     status:string,
 *     posted_at:string,
 *     member_display_name:string,
 *     member_profile_image_url:string,
 *     is_mine:bool
 *   }>
 * }
 */
function lutwiyo_get_member_comment_items($resourceType, $resourceId, $page = 1, $perPage = 20, $sort = 'posted_at:desc')
{
    $normalizedResourceType = trim((string) $resourceType);
    $normalizedResourceId = trim((string) $resourceId);
    if ($normalizedResourceType === '' || $normalizedResourceId === '') {
        return [
            'ok' => false,
            'status' => 400,
            'code' => 'INVALID_RESOURCE',
            'message' => 'resource_type or resource_id is empty',
            'total' => 0,
            'items' => [],
        ];
    }

    if (!function_exists('lutwiyo_call_member_service_api')) {
        return [
            'ok' => false,
            'status' => 500,
            'code' => 'CALLER_NOT_AVAILABLE',
            'message' => 'lutwiyo_call_member_service_api is not available',
            'total' => 0,
            'items' => [],
        ];
    }

    $endpoint = sprintf(
        '/member-service/api/v1/comments/%s/%s',
        rawurlencode($normalizedResourceType),
        rawurlencode($normalizedResourceId)
    );

    $response = lutwiyo_call_member_service_api($endpoint, 'GET', [
        'page' => max(1, (int) $page),
        'per_page' => max(1, min(100, (int) $perPage)),
        'sort' => (string) $sort,
    ], [
        'timeout' => 10,
    ]);

    if (!is_array($response)) {
        return [
            'ok' => false,
            'status' => 0,
            'code' => 'NO_RESPONSE',
            'message' => 'member-service response is empty',
            'total' => 0,
            'items' => [],
        ];
    }

    $status = (int) ($response['status'] ?? 0);
    $body = is_array($response['body'] ?? null) ? $response['body'] : [];
    $isOk = $status === 200 && ($body['result'] ?? '') === 'ok';

    if (!$isOk) {
        return [
            'ok' => false,
            'status' => $status,
            'code' => trim((string) ($body['code'] ?? 'API_ERROR')),
            'message' => trim((string) ($body['message'] ?? 'failed to fetch comments')),
            'total' => 0,
            'items' => [],
        ];
    }

    $data = is_array($body['data'] ?? null) ? $body['data'] : [];
    $itemsRaw = is_array($data['items'] ?? null) ? $data['items'] : [];
    $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
    $total = max(0, (int) ($pagination['total'] ?? count($itemsRaw)));

    $items = [];
    foreach ($itemsRaw as $item) {
        if (!is_array($item)) {
            continue;
        }

        $items[] = [
            'comment_id' => (int) ($item['comment_id'] ?? 0),
            'body' => trim((string) ($item['body'] ?? '')),
            'status' => trim((string) ($item['status'] ?? 'visible')),
            'posted_at' => trim((string) ($item['posted_at'] ?? '')),
            'member_display_name' => trim((string) (($item['member']['display_name'] ?? '') ?: '会員')),
            'member_profile_image_url' => trim((string) ($item['member']['profile_image_url'] ?? '')),
            'is_mine' => (bool) ($item['is_mine'] ?? false),
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'code' => '',
        'message' => '',
        'total' => $total,
        'items' => $items,
    ];
}

/**
 * 現在ログイン中会員のコメント一覧を取得し、SSR表示向けに正規化して返す。
 *
 * @return array{
 *   ok: bool,
 *   status: int,
 *   code: string,
 *   message: string,
 *   total: int,
 *   items: array<int, array{
 *     comment_id:int,
 *     resource_type:string,
 *     resource_id:string,
 *     resource_title:string,
 *     resource_url:string,
 *     body:string,
 *     posted_at:string,
 *     status:string,
 *     allowed_status_actions:array<int,string>
 *   }>
 * }
 */
function lutwiyo_get_current_member_comment_list($page = 1, $perPage = 20, $sort = 'posted_at:desc')
{
    if (!function_exists('lutwiyo_call_member_service_api')) {
        return [
            'ok' => false,
            'status' => 500,
            'code' => 'CALLER_NOT_AVAILABLE',
            'message' => 'lutwiyo_call_member_service_api is not available',
            'total' => 0,
            'items' => [],
        ];
    }

    $endpoint = '/member-service/api/v1/member/comments';
    $response = lutwiyo_call_member_service_api($endpoint, 'GET', [
        'page' => max(1, (int) $page),
        'per_page' => max(1, min(100, (int) $perPage)),
        'sort' => (string) $sort,
    ], [
        'timeout' => 10,
    ]);

    if (!is_array($response)) {
        return [
            'ok' => false,
            'status' => 0,
            'code' => 'NO_RESPONSE',
            'message' => 'member-service response is empty',
            'total' => 0,
            'items' => [],
        ];
    }

    $status = (int) ($response['status'] ?? 0);
    $body = is_array($response['body'] ?? null) ? $response['body'] : [];
    $isOk = $status === 200 && ($body['result'] ?? '') === 'ok';

    if (!$isOk) {
        return [
            'ok' => false,
            'status' => $status,
            'code' => trim((string) ($body['code'] ?? 'API_ERROR')),
            'message' => trim((string) ($body['message'] ?? 'failed to fetch member comments')),
            'total' => 0,
            'items' => [],
        ];
    }

    $data = is_array($body['data'] ?? null) ? $body['data'] : [];
    $itemsRaw = is_array($data['items'] ?? null) ? $data['items'] : [];
    $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
    $total = max(0, (int) ($pagination['total'] ?? count($itemsRaw)));
    $items = [];

    foreach ($itemsRaw as $item) {
        if (!is_array($item)) {
            continue;
        }

        $allowedActionsRaw = $item['allowed_status_actions'] ?? [];
        $allowedActions = [];
        if (is_array($allowedActionsRaw)) {
            foreach ($allowedActionsRaw as $allowedAction) {
                if (!is_string($allowedAction)) {
                    continue;
                }
                $allowedAction = trim($allowedAction);
                if ($allowedAction === '') {
                    continue;
                }
                $allowedActions[] = $allowedAction;
            }
        }

        $items[] = [
            'comment_id' => (int) ($item['comment_id'] ?? 0),
            'resource_type' => trim((string) ($item['resource_type'] ?? '')),
            'resource_id' => trim((string) ($item['resource_id'] ?? '')),
            'resource_title' => trim((string) ($item['resource_title'] ?? '')),
            'resource_url' => trim((string) ($item['resource_url'] ?? '')),
            'body' => trim((string) ($item['body'] ?? '')),
            'posted_at' => trim((string) ($item['posted_at'] ?? '')),
            'status' => trim((string) ($item['status'] ?? 'visible')),
            'allowed_status_actions' => $allowedActions,
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'code' => '',
        'message' => '',
        'total' => $total,
        'items' => $items,
    ];
}

/**
 * 現在ログイン中会員のコメントステータスを更新する。
 *
 * @return array{ok:bool,status:int,code:string,message:string,body:array<string,mixed>}
 */
function lutwiyo_update_current_member_comment_status($commentId, $toStatus = 'delete_requested', $reason = '')
{
    $normalizedCommentId = max(0, (int) $commentId);
    if ($normalizedCommentId <= 0) {
        return [
            'ok' => false,
            'status' => 400,
            'code' => 'INVALID_COMMENT_ID',
            'message' => 'comment_id is invalid',
            'body' => [],
        ];
    }

    if (!function_exists('lutwiyo_call_member_service_api')) {
        return [
            'ok' => false,
            'status' => 500,
            'code' => 'CALLER_NOT_AVAILABLE',
            'message' => 'lutwiyo_call_member_service_api is not available',
            'body' => [],
        ];
    }

    $endpoint = sprintf('/member-service/api/v1/member/comments/%d/status', $normalizedCommentId);
    $response = lutwiyo_call_member_service_api($endpoint, 'PATCH', [
        'to_status' => trim((string) $toStatus),
        'reason' => trim((string) $reason),
    ], [
        'timeout' => 10,
    ]);

    if (!is_array($response)) {
        return [
            'ok' => false,
            'status' => 0,
            'code' => 'NO_RESPONSE',
            'message' => 'member-service response is empty',
            'body' => [],
        ];
    }

    $status = (int) ($response['status'] ?? 0);
    $body = is_array($response['body'] ?? null) ? $response['body'] : [];
    $isOk = $status === 200 && ($body['result'] ?? '') === 'ok';

    return [
        'ok' => $isOk,
        'status' => $status,
        'code' => trim((string) ($body['code'] ?? '')),
        'message' => trim((string) ($body['message'] ?? '')),
        'body' => $body,
    ];
}

/**
 * 現在ログイン中会員のいいね状態を返す。
 *
 * @param string $resourceType
 * @param string $resourceId
 * @return bool true: liked / false: unliked または未ログイン・取得失敗
 */
function lutwiyo_get_current_member_like_state($resourceType, $resourceId)
{
    static $likeStateCache = [];

    $normalizedResourceType = trim((string) $resourceType);
    $normalizedResourceId = trim((string) $resourceId);
    if ($normalizedResourceType === '' || $normalizedResourceId === '') {
        return false;
    }

    $cacheKey = $normalizedResourceType . '::' . $normalizedResourceId;
    if (array_key_exists($cacheKey, $likeStateCache)) {
        return (bool) $likeStateCache[$cacheKey];
    }

    if ($normalizedResourceType === 'tokk_article'
        && function_exists('lutwiyo_is_current_single_article_resource_id')
        && lutwiyo_is_current_single_article_resource_id($normalizedResourceId)) {
        $articleViewerContextResponse = lutwiyo_get_or_fetch_article_viewer_context_response($normalizedResourceType, $normalizedResourceId);
        $initialState = lutwiyo_extract_article_viewer_context_like_state($articleViewerContextResponse);
        if (is_bool($initialState)) {
            $likeStateCache[$cacheKey] = $initialState;

            return $initialState;
        }
    }

    if (!function_exists('lutwiyo_call_member_service_api')) {
        $likeStateCache[$cacheKey] = false;
        return false;
    }

    $endpoint = sprintf(
        '/member-service/api/v1/reactions/like/%s/%s',
        rawurlencode($normalizedResourceType),
        rawurlencode($normalizedResourceId)
    );

    $likeResponse = lutwiyo_call_member_service_api($endpoint, 'GET', null);
    $isLiked = is_array($likeResponse)
        && (int) ($likeResponse['status'] ?? 0) === 200
        && is_array($likeResponse['body'] ?? null)
        && ($likeResponse['body']['result'] ?? '') === 'ok'
        && isset($likeResponse['body']['liked'])
        ? (bool) $likeResponse['body']['liked']
        : false;

    $likeStateCache[$cacheKey] = $isLiked;

    return $isLiked;
}

/**
 * 現在ログイン中会員のお気に入り状態を返す。
 *
 * @param string $resourceType
 * @param string $resourceId
 * @return bool true: favorited / false: unfavorited または未ログイン・取得失敗
 */
function lutwiyo_get_current_member_favorite_state($resourceType, $resourceId)
{
    static $favoriteStateCache = [];

    $normalizedResourceType = trim((string) $resourceType);
    $normalizedResourceId = trim((string) $resourceId);
    if ($normalizedResourceType === '' || $normalizedResourceId === '') {
        return false;
    }

    $cacheKey = $normalizedResourceType . '::' . $normalizedResourceId;
    if (array_key_exists($cacheKey, $favoriteStateCache)) {
        return (bool) $favoriteStateCache[$cacheKey];
    }

    if ($normalizedResourceType === 'tokk_article'
        && function_exists('lutwiyo_is_current_single_article_resource_id')
        && lutwiyo_is_current_single_article_resource_id($normalizedResourceId)) {
        $articleViewerContextResponse = lutwiyo_get_or_fetch_article_viewer_context_response($normalizedResourceType, $normalizedResourceId);
        $initialState = lutwiyo_extract_article_viewer_context_favorite_state($articleViewerContextResponse);
        if (is_bool($initialState)) {
            $favoriteStateCache[$cacheKey] = $initialState;

            return $initialState;
        }
    }

    if ($normalizedResourceType === 'tokk_article'
        && function_exists('lutwiyo_is_disabled_article_list_favorite_state_id')
        && lutwiyo_is_disabled_article_list_favorite_state_id($normalizedResourceId)) {
        $favoriteStateCache[$cacheKey] = false;

        return false;
    }

    $bulkStates = lutwiyo_get_current_member_favorite_states_by_resource_ids($normalizedResourceType, [$normalizedResourceId]);
    if (array_key_exists($normalizedResourceId, $bulkStates)) {
        $favoriteStateCache[$cacheKey] = (bool) $bulkStates[$normalizedResourceId];

        return (bool) $favoriteStateCache[$cacheKey];
    }

    if (!function_exists('lutwiyo_call_member_service_api')) {
        $favoriteStateCache[$cacheKey] = false;
        return false;
    }

    $endpoint = sprintf(
        '/member-service/api/v1/reactions/favorite/%s/%s',
        rawurlencode($normalizedResourceType),
        rawurlencode($normalizedResourceId)
    );

    $favoriteResponse = lutwiyo_call_member_service_api($endpoint, 'GET', null);
    $isFavorited = is_array($favoriteResponse)
        && (int) ($favoriteResponse['status'] ?? 0) === 200
        && is_array($favoriteResponse['body'] ?? null)
        && ($favoriteResponse['body']['result'] ?? '') === 'ok'
        && isset($favoriteResponse['body']['favorited'])
        ? (bool) $favoriteResponse['body']['favorited']
        : false;

    $favoriteStateCache[$cacheKey] = $isFavorited;

    return $isFavorited;
}

/**
 * 現在ログイン中会員のお気に入り状態をリソースID配列で一括取得する。
 *
 * @param string $resourceType
 * @param string[] $resourceIds
 * @return array<string,bool> key: resource_id
 */
function lutwiyo_get_current_member_favorite_states_by_resource_ids($resourceType, array $resourceIds)
{
    static $bulkFavoriteStateCache = [];

    $normalizedResourceType = trim((string) $resourceType);
    if ($normalizedResourceType === '') {
        return [];
    }

    $normalizedIds = array_values(array_unique(array_filter(array_map(static function ($id): string {
        return trim((string) $id);
    }, $resourceIds), static function (string $id): bool {
        return $id !== '';
    })));

    if (empty($normalizedIds)) {
        return [];
    }

    $cacheBucket = $bulkFavoriteStateCache[$normalizedResourceType] ?? [];
    if (lutwiyo_should_short_circuit_guest_favorite_requests()) {
        foreach ($normalizedIds as $resourceId) {
            $cacheBucket[$resourceId] = false;
        }

        $bulkFavoriteStateCache[$normalizedResourceType] = $cacheBucket;

        $result = [];
        foreach ($normalizedIds as $resourceId) {
            $result[$resourceId] = false;
        }

        return $result;
    }

    if ($normalizedResourceType === 'tokk_article'
        && function_exists('lutwiyo_is_disabled_article_list_favorite_state_id')) {
        foreach ($normalizedIds as $resourceId) {
            if (lutwiyo_is_disabled_article_list_favorite_state_id($resourceId)) {
                $cacheBucket[$resourceId] = false;
            }
        }
    }

    $missingIds = [];
    foreach ($normalizedIds as $resourceId) {
        if (!array_key_exists($resourceId, $cacheBucket)) {
            $missingIds[] = $resourceId;
        }
    }

    if (!empty($missingIds) && function_exists('lutwiyo_call_member_service_api')) {
        $bulkResponse = lutwiyo_call_member_service_api(
            '/member-service/api/v1/member/favorites/status-bulk',
            'GET',
            [
                'resource_type' => $normalizedResourceType,
                'resource_ids' => $missingIds,
            ]
        );

        foreach ($missingIds as $resourceId) {
            $cacheBucket[$resourceId] = false;
        }

        if (is_array($bulkResponse)
            && (int) ($bulkResponse['status'] ?? 0) === 200
            && is_array($bulkResponse['body'] ?? null)
            && ($bulkResponse['body']['result'] ?? '') === 'ok'
            && is_array($bulkResponse['body']['items'] ?? null)) {
            foreach ($bulkResponse['body']['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $resourceId = trim((string) ($item['resource_id'] ?? ''));
                if ($resourceId === '') {
                    continue;
                }

                $cacheBucket[$resourceId] = (bool) ($item['favorited'] ?? false);
            }

        }

        $bulkFavoriteStateCache[$normalizedResourceType] = $cacheBucket;
    }

    $result = [];
    foreach ($normalizedIds as $resourceId) {
        if (array_key_exists($resourceId, $cacheBucket)) {
            $result[$resourceId] = (bool) $cacheBucket[$resourceId];
        }
    }

    return $result;
}

/**
 * 記事一覧用favorite状態の無効化対象をリクエスト内で保持する。
 *
 * @return array<string,bool>
 */
function &lutwiyo_get_disabled_article_list_favorite_state_registry(): array
{
    static $disabledArticleIds = [];

    return $disabledArticleIds;
}

/**
 * 記事一覧用favorite状態の無効化対象IDを登録する。
 *
 * @param string[] $articleIds
 * @return void
 */
function lutwiyo_register_disabled_article_list_favorite_state_ids(array $articleIds): void
{
    $registry = &lutwiyo_get_disabled_article_list_favorite_state_registry();

    foreach ($articleIds as $articleId) {
        $normalizedArticleId = trim((string) $articleId);
        if ($normalizedArticleId === '') {
            continue;
        }

        $registry[$normalizedArticleId] = true;
    }
}

/**
 * 記事一覧用favorite状態の無効化対象かを判定する。
 *
 * @param string $articleId
 * @return bool
 */
function lutwiyo_is_disabled_article_list_favorite_state_id(string $articleId): bool
{
    $normalizedArticleId = trim($articleId);
    if ($normalizedArticleId === '') {
        return false;
    }

    $registry = &lutwiyo_get_disabled_article_list_favorite_state_registry();

    return isset($registry[$normalizedArticleId]);
}

/**
 * 記事詳細のメイン記事payloadかを判定する。
 *
 * @param string[] $articleIds
 * @return bool
 */
function lutwiyo_is_single_article_primary_payload(array $articleIds): bool
{
    $normalizedArticleIds = array_values(array_unique(array_filter(array_map(static function ($articleId): string {
        return trim((string) $articleId);
    }, $articleIds), static function (string $articleId): bool {
        return $articleId !== '';
    })));

    if (count($normalizedArticleIds) !== 1) {
        return false;
    }

    if (!function_exists('is_singular') || !is_singular('articles')) {
        return false;
    }

    $queriedObjectId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
    if ($queriedObjectId <= 0) {
        return false;
    }

    return $normalizedArticleIds[0] === (string) $queriedObjectId;
}

/**
 * 記事一覧favorite状態の無効化対象payloadかを判定する。
 *
 * @param string[] $articleIds
 * @return bool
 */
function lutwiyo_is_public_article_list_favorite_state_context(): bool
{
    if (function_exists('is_admin') && is_admin()) {
        return false;
    }

    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return false;
    }

    if (function_exists('is_page_template')) {
        $excludedPageTemplates = [
            'page-mypage.php',
            'page-favorite.php',
            'page-memberblog-editor.php',
            'page-memberblog-list.php',
        ];

        foreach ($excludedPageTemplates as $pageTemplate) {
            if (is_page_template($pageTemplate)) {
                return false;
            }
        }
    }

    if (function_exists('is_front_page') && is_front_page()) {
        return true;
    }

    if (function_exists('is_home') && is_home()) {
        return true;
    }

    if (function_exists('is_search') && is_search()) {
        return true;
    }

    if (function_exists('is_category') && is_category()) {
        return true;
    }

    if (function_exists('is_tag') && is_tag()) {
        return true;
    }

    if (function_exists('is_tax') && is_tax()) {
        return true;
    }

    if (function_exists('is_archive') && is_archive()) {
        return true;
    }

    return false;
}

/**
 * 記事一覧favorite状態の無効化対象payloadかを判定する。
 *
 * @param string[] $articleIds
 * @return bool
 */
function lutwiyo_should_disable_list_favorite_state_for_article_ids(array $articleIds): bool
{
    if (!tokk_member_service_disable_list_favorite_state()) {
        return false;
    }

    if (!lutwiyo_is_public_article_list_favorite_state_context()) {
        return false;
    }

    return !lutwiyo_is_single_article_primary_payload($articleIds);
}

/**
 * 記事一覧描画前にお気に入り状態をまとめて事前取得する。
 *
 * @param string[] $articleIds
 * @return void
 */
function lutwiyo_preload_current_member_favorite_states_for_articles(array $articleIds)
{
    if (tokk_article_viewer_context_enabled()
        && function_exists('lutwiyo_is_single_article_primary_payload')
        && lutwiyo_is_single_article_primary_payload($articleIds)) {
        return;
    }

    if (function_exists('lutwiyo_should_disable_list_favorite_state_for_article_ids')
        && lutwiyo_should_disable_list_favorite_state_for_article_ids($articleIds)) {
        lutwiyo_register_disabled_article_list_favorite_state_ids($articleIds);
    }

    lutwiyo_get_current_member_favorite_states_by_resource_ids('tokk_article', $articleIds);
}

/**
 * 現在ログイン中会員のお気に入り記事ID一覧を返す。
 *
 * @return int[]
 */
function lutwiyo_get_current_member_favorite_article_ids()
{
    static $favoriteArticleIds = null;

    if (is_array($favoriteArticleIds)) {
        return $favoriteArticleIds;
    }

    $favoriteArticleIds = [];
    if (lutwiyo_should_short_circuit_guest_favorite_requests()) {
        return $favoriteArticleIds;
    }

    if (!function_exists('lutwiyo_call_member_service_api')) {
        return $favoriteArticleIds;
    }

    $page = 1;
    $perPage = 100;
    $collectedIds = [];
    $maxPage = 50;

    while ($page <= $maxPage) {
        $favoriteListResponse = lutwiyo_call_member_service_api(
            '/member-service/api/v1/member/favorites',
            'GET',
            [
                'page' => $page,
                'per_page' => $perPage,
                'resource_type' => 'tokk_article',
            ]
        );

        if (!is_array($favoriteListResponse)
            || (int) ($favoriteListResponse['status'] ?? 0) !== 200
            || !is_array($favoriteListResponse['body'] ?? null)
            || ($favoriteListResponse['body']['result'] ?? '') !== 'ok'
            || !is_array($favoriteListResponse['body']['items'] ?? null)) {
            return [];
        }

        $items = $favoriteListResponse['body']['items'];
        foreach ($items as $item) {
            $resourceId = is_array($item) ? (string) ($item['resource_id'] ?? '') : '';
            if ($resourceId !== '' && ctype_digit($resourceId)) {
                $collectedIds[] = (int) $resourceId;
            }
        }

        $total = (int) ($favoriteListResponse['body']['total'] ?? 0);
        if ($total <= 0 || count($collectedIds) >= $total || count($items) < $perPage) {
            break;
        }

        $page++;
    }

    // 呼び出し側の既存並びを維持するため昇順相当へ揃える。
    $favoriteArticleIds = array_values(array_unique($collectedIds));
    $favoriteArticleIds = array_values(array_reverse($favoriteArticleIds));

    return $favoriteArticleIds;
}

// -------------------------------------
// カスタムフィードの追加
// -------------------------------------
/**
 * NTT / dmenu / goo 用フィード設定
 * @since 2.0.0
 * @author S. Ito
 */

add_action('init', function () {
    // /feed/ntt の追加フィード
    add_feed('ntt', function () {
        // テーマ内の ntt.php を読み込む
        get_template_part('ntt');
    });

    // /feed/gunosy の追加フィード
    add_feed('gunosy', function () {
        // テーマ内の gunosy.php を読み込む
        get_template_part('gunosy');
    });

    // /feed/line_news の追加フィード
    add_feed('line_news', function () {
        // テーマ内の line_news.php を読み込む
        get_template_part('line_news');
    });
});

/**
 * ntt フィードの Content-Type を RSS2 に強制
 * SmartNews / dmenu 用の仕様として必要
 */
add_filter('feed_content_type', function ($content_type, $type) {
    if ('ntt' === $type || 'gunosy' === $type || 'line_news' === $type) {
        return feed_content_type('rss2');
    }
    return $content_type;
}, 10, 2);



/**
 * WordPress 標準の RSS2 フィードを置き換え
 * /feed/rss2 と /feed/rss2-comments を
 * goo.php / goo-comments.php に差し替える
 */
remove_filter('do_feed_rss2', 'do_feed_rss2', 10);

function custom_feed_rss2($for_comments)
{

    // 読み込むテンプレートファイルを選択 (goo.php or goo-comments.php)
    $template_file = '/goo' . ($for_comments ? '-comments' : '') . '.php';

    // 子テーマ側にファイルがあれば優先、それ以外は wp-includes/
    $template_file = (
        file_exists(get_stylesheet_directory() . $template_file)
            ? get_stylesheet_directory()
            : ABSPATH . WPINC
        ) . $template_file;

    // 指定テンプレートを読み込む
    load_template($template_file);
}

add_action('do_feed_rss2', 'custom_feed_rss2', 10, 1);
add_theme_support('title-tag');


// =========================================================
// ここから下にリダイレクト処理を追加してください
// =========================================================

/**
 * --------------------------------------------------------
 * 誤ったURLアクセスを検知して正しいURLへ301リダイレクト
 * --------------------------------------------------------
 */
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }

    /* --------------------------------------------------------
     * ① /?post_type=articles&p=343 の正規化
     * -------------------------------------------------------- */
    if (!empty($_GET['post_type']) && $_GET['post_type'] === 'articles' && !empty($_GET['p'])) {
        $post_id = intval($_GET['p']);
        $correct_url = get_permalink($post_id);
        if (!empty($correct_url)) {
            wp_redirect($correct_url, 301);
            exit;
        }
    }

    /* --------------------------------------------------------
     * ② 通常の articles シングルページ → 正規URLと違えば修正
     * -------------------------------------------------------- */
    if (is_singular('articles')) {
        global $post;
        if ($post instanceof WP_Post) {
            $current_url = home_url($_SERVER['REQUEST_URI']);
            $correct_url = get_permalink($post);
            if (untrailingslashit($current_url) !== untrailingslashit($correct_url)) {
                wp_redirect($correct_url, 301);
                exit;
            }
        }
    }

    /* --------------------------------------------------------
     * ③ 404 の場合にも記事IDパターンを救う
     *    /articles/gourmet/56786/
     * -------------------------------------------------------- */
    if (is_404()) {
        $uri = $_SERVER['REQUEST_URI'];
        // 数字だけの末尾 → 記事IDとして扱う
        if (preg_match('#/([0-9]+)/?$#', $uri, $m)) {
            $post_id = intval($m[1]);
            if ($post_id > 0) {
                $post = get_post($post_id);
                // 記事が存在し、articles の場合のみリダイレクト
                if ($post && $post->post_type === 'articles') {
                    $correct_url = get_permalink($post_id);
                    if (!empty($correct_url)) {
                        wp_redirect($correct_url, 301);
                        exit;
                    }
                }
            }
        }
    }
});

/**
 * ACFの画像を Yoast の og:image / twitter:image に優先設定
 */
function lutwiyo_get_acf_image_url($field_name, $post_id) {
    $v = get_field($field_name, $post_id);

    if (is_array($v) && !empty($v['url'])) {
        return $v['url']; // Image Array
    }
    if (is_numeric($v)) {
        $url = wp_get_attachment_url((int) $v); // Image ID
        return $url ?: '';
    }
    if (is_string($v) && $v !== '') {
        return $v; // Image URL
    }
    return '';
}
/**
 * Yoast が本文中の画像を OG:image 候補にするのを防ぐ
 */
add_filter('wpseo_opengraph_image_from_content', function ($image) {
    return is_singular('articles') ? false : $image;
});

/**
 * OG:image を ACF 画像で強制上書き（最終段）
 */
add_filter('wpseo_opengraph_image', function ($image) {
    if (!is_singular('articles')) {
        return $image;
    }

    $post_id = get_queried_object_id();
    $url = lutwiyo_get_acf_image_url('articles_img', $post_id);

    return $url !== '' ? $url : $image;
}, 9999);

/**
 * Twitter: 画像URLを差し替え
 */
add_filter('wpseo_twitter_image', function ($img) {
    if (!is_singular('articles')) return $img;

    $post_id = get_queried_object_id();
    $url = lutwiyo_get_acf_image_url('articles_img', $post_id);
    return $url !== '' ? $url : $img;
});

/**
 * お気に入りボタン（ハート）用のフロント設定値を出力する。
 */
function lutwiyo_get_comment_report_reasons(): array
{
    return [
        [
            'code' => 'abuse_discrimination',
            'label' => '誹謗中傷・差別などの不適切な表現',
        ],
        [
            'code' => 'stalking',
            'label' => 'つきまとい',
        ],
        [
            'code' => 'obscene_violent',
            'label' => 'わいせつ／暴力など過度に不快な内容',
        ],
        [
            'code' => 'personal_information',
            'label' => '個人情報の掲載（氏名・連絡先・SNS ID等）',
        ],
        [
            'code' => 'spam_promotion',
            'label' => 'スパム／宣伝／勧誘',
        ],
        [
            'code' => 'impersonation_rights',
            'label' => 'なりすまし・権利侵害（転載等）',
        ],
        [
            'code' => 'other',
            'label' => 'その他（運営に判断を委ねる）',
        ],
    ];
}

add_action('wp_head', function () {
    $articleViewerContextResponse = null;
    $articleViewerFavoriteInitialStates = [];
    $articleViewerLikeInitialStates = [];
    if (function_exists('is_singular') && is_singular('articles')) {
        $articleId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        if ($articleId > 0) {
            $commentViewAcl = function_exists('lutwiyo_normalize_comment_view_acl')
                ? lutwiyo_normalize_comment_view_acl(function_exists('get_field') ? get_field('comment_view_acl', $articleId) : get_post_meta($articleId, 'comment_view_acl', true))
                : 'public';
            $commentPostAcl = function_exists('lutwiyo_normalize_comment_post_acl')
                ? lutwiyo_normalize_comment_post_acl(function_exists('get_field') ? get_field('comment_post_acl', $articleId) : get_post_meta($articleId, 'comment_post_acl', true))
                : 'enabled';
            $articleViewerContextResponse = lutwiyo_get_or_fetch_article_viewer_context_response('tokk_article', (string) $articleId, [
                'comment_view_acl' => $commentViewAcl,
                'comment_post_acl' => $commentPostAcl,
            ]);
            $initialFavoriteState = lutwiyo_extract_article_viewer_context_favorite_state($articleViewerContextResponse);
            if (is_bool($initialFavoriteState)) {
                $articleViewerFavoriteInitialStates['tokk_article::' . $articleId] = $initialFavoriteState;
            }
            $initialLikeState = lutwiyo_extract_article_viewer_context_like_state($articleViewerContextResponse);
            if (is_bool($initialLikeState)) {
                $articleViewerLikeInitialStates['tokk_article::' . $articleId] = $initialLikeState;
            }
        }
    }

    $memberInfoResponse = lutwiyo_get_or_fetch_member_info_response();
    $isLoggedInByMemberInfo = is_array($memberInfoResponse)
        && ($memberInfoResponse['body']['result'] ?? '') === 'ok';
    $memberUid = $isLoggedInByMemberInfo
        ? trim((string) ($memberInfoResponse['body']['uid'] ?? ''))
        : '';
    $uidAssertion = $memberUid !== ''
        ? lutwiyo_build_member_uid_assertion($memberUid, 300)
        : '';

    $favoriteConfig = [
        'loginRequiredUrl' => home_url('/login/'),
        'baseUrl' => home_url('/member-service'),
        'memberContextEnabled' => tokk_member_context_enabled(),
        'memberContextEndpoint' => '/api/v1/bridge/auth/member-context',
        'loginStatusEndpoint' => '/api/v1/bridge/auth/login-status',
        'favoriteEndpointTemplate' => '/api/v1/reactions/favorite/{resource_type}/{resource_id}',
        'loginUrl' => home_url('/login/'),
        'registUrl' => home_url('/regist/'),
        'loginDialogMessage' => '会員にログインすると記事を保存できるようになります。',
        'registLabel' => '会員登録はこちら',
        'loginLabel' => 'ログインはこちら',
        'initialLoggedIn' => $isLoggedInByMemberInfo,
        'memberUidAssertion' => $uidAssertion,
        'initialStates' => $articleViewerFavoriteInitialStates,
    ];
    $likeConfig = [
        'baseUrl' => home_url('/member-service'),
        'memberContextEnabled' => tokk_member_context_enabled(),
        'memberContextEndpoint' => '/api/v1/bridge/auth/member-context',
        'loginStatusEndpoint' => '/api/v1/bridge/auth/login-status',
        'likeEndpointTemplate' => '/api/v1/reactions/like/{resource_type}/{resource_id}',
        'loginUrl' => home_url('/login/'),
        'registUrl' => home_url('/regist/'),
        'loginDialogMessage' => '会員にログインするといいねできるようになります。',
        'loginLabel' => 'ログインはこちら',
        'registLabel' => '会員登録はこちら',
        'initialLoggedIn' => $isLoggedInByMemberInfo,
        'memberUidAssertion' => $uidAssertion,
        'initialStates' => $articleViewerLikeInitialStates,
    ];
    $commentConfig = [
        'baseUrl' => home_url('/member-service'),
        'memberContextEnabled' => tokk_member_context_enabled(),
        'memberContextEndpoint' => '/api/v1/bridge/auth/member-context',
        'loginStatusEndpoint' => '/api/v1/bridge/auth/login-status',
        'commentListEndpointTemplate' => '/api/v1/comments/{resource_type}/{resource_id}',
        'commentPostEndpointTemplate' => '/api/v1/comments/{resource_type}/{resource_id}',
        'commentReportEndpointTemplate' => '/api/v1/comments/{comment_id}/reports',
        'memberCommentStatusEndpointTemplate' => '/api/v1/member/comments/{comment_id}/status',
        'loginUrl' => home_url('/login/'),
        'loginDialogMessage' => '会員にログインするとコメント投稿できます。',
        'initialLoggedIn' => $isLoggedInByMemberInfo,
        'reportReasonOptions' => lutwiyo_get_comment_report_reasons(),
    ];

    echo "\n<script>window.tokkFavorite = " . wp_json_encode($favoriteConfig) . ";</script>\n";
    echo "\n<script>window.tokkLike = " . wp_json_encode($likeConfig) . ";</script>\n";
    echo "\n<script>window.tokkComment = " . wp_json_encode($commentConfig) . ";</script>\n";
}, 20);
