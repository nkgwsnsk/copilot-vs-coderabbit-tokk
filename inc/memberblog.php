<?php
// -------------------------------------
// 会員ブログ一覧取得（memberblog API）関連
// -------------------------------------

/**
 * 会員UIDを取得する共通関数（Issue #3 のインターフェース）
 *
 * @return string|null 会員UID（未ログインの場合はnull）
 */
function tokk_get_current_member_uid(): ?string
{
    if (function_exists('lutwiyo_get_current_member_uid_from_bridge')) {
        $bridgeUid = trim((string) lutwiyo_get_current_member_uid_from_bridge());
        if ($bridgeUid !== '') {
            return $bridgeUid;
        }
    }

    // Providerを取得してUID取得処理を集約する
    $provider = tokk_get_member_uid_provider();

    try {
        $uid = $provider->get_uid();
    } catch (Throwable $exception) {
        // 例外は握り潰さずログに残し、nullへフォールバックする
        error_log('[member_uid] provider exception: ' . $exception->getMessage());
        return null;
    }

    // 文字列でない場合や空文字は未ログインとして扱う
    if (!is_string($uid) || trim($uid) === '') {
        return null;
    }

    return $uid;
}

/**
 * 会員UID取得のProviderインターフェース
 */
interface Tokk_MemberUidProviderInterface
{
    /**
     * @return string|null 会員UID（未ログイン時はnull）
     */
    public function get_uid(): ?string;
}

/**
 * スタブの会員UID取得Provider
 */
class Tokk_StubMemberUidProvider implements Tokk_MemberUidProviderInterface
{
    /**
     * @return string|null
     */
    public function get_uid(): ?string
    {
        // フィルターでUIDを差し替えられるようにする
        $uid = function_exists('apply_filters') ? apply_filters('tokk_stub_member_uid', null) : null;

        // 旧来フィルター互換としてフォールバックする
        if (!is_string($uid) || trim($uid) === '') {
            $uid = function_exists('apply_filters') ? apply_filters('tokk_current_member_uid', null) : null;
        }

        if (!is_string($uid) || trim($uid) === '') {
            return null;
        }

        return $uid;
    }
}

/**
 * 外部会員基盤（Laravel想定）の会員UID取得Provider
 */
class Tokk_LaravelMemberUidProvider implements Tokk_MemberUidProviderInterface
{
    /**
     * @return string|null
     */
    public function get_uid(): ?string
    {
        // 設定されたエンドポイントを取得する
        $endpoint = tokk_get_laravel_member_uid_endpoint();
        if (null === $endpoint) {
            error_log('[member_uid] laravel endpoint is not configured.');
            return null;
        }

        // 認証トークンは設定値から取得する
        $token = tokk_get_laravel_member_uid_token();

        $headers = array(
            'Accept' => 'application/json',
        );
        if (is_string($token) && trim($token) !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        // 外部通信はWP HTTP API経由で行う
        $response = wp_remote_get(
            $endpoint,
            array(
                'headers' => $headers,
                'timeout' => 5,
            )
        );

        if (is_wp_error($response)) {
            error_log('[member_uid] laravel request failed: ' . $response->get_error_message());
            return null;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body        = (string) wp_remote_retrieve_body($response);

        if (200 !== $status_code) {
            error_log('[member_uid] laravel response error: ' . $status_code . ' body: ' . $body);
            return null;
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            error_log('[member_uid] laravel response parse error.');
            return null;
        }

        $uid = $payload['uid'] ?? null;
        if (!is_string($uid) || trim($uid) === '') {
            return null;
        }

        return $uid;
    }
}

/**
 * 会員UID Providerの種別を取得する
 *
 * @return string
 */
function tokk_get_member_uid_provider_type(): string
{
    // 定数または環境変数からProvider設定を取得する
    $provider = defined('TOKK_MEMBER_UID_PROVIDER') ? TOKK_MEMBER_UID_PROVIDER : getenv('TOKK_MEMBER_UID_PROVIDER');
    if (!is_string($provider) || trim($provider) === '') {
        return 'stub';
    }

    $normalized = strtolower(trim($provider));
    if (!in_array($normalized, array('stub', 'laravel'), true)) {
        return 'stub';
    }

    return $normalized;
}

/**
 * 会員UID Providerを取得する
 *
 * @return Tokk_MemberUidProviderInterface
 */
function tokk_get_member_uid_provider(): Tokk_MemberUidProviderInterface
{
    $type = tokk_get_member_uid_provider_type();
    if (!isset($GLOBALS['tokk_member_uid_providers']) || !is_array($GLOBALS['tokk_member_uid_providers'])) {
        $GLOBALS['tokk_member_uid_providers'] = array();
    }

    if (!isset($GLOBALS['tokk_member_uid_providers'][$type])) {
        $GLOBALS['tokk_member_uid_providers'][$type] = tokk_create_member_uid_provider($type);
    }

    return $GLOBALS['tokk_member_uid_providers'][$type];
}

/**
 * Providerインスタンスを生成する
 *
 * @param string $type
 * @return Tokk_MemberUidProviderInterface
 */
function tokk_create_member_uid_provider(string $type): Tokk_MemberUidProviderInterface
{
    if ($type === 'laravel') {
        return new Tokk_LaravelMemberUidProvider();
    }

    return new Tokk_StubMemberUidProvider();
}

/**
 * Providerキャッシュを初期化する
 *
 * @return void
 */
function tokk_reset_member_uid_provider_cache(): void
{
    $GLOBALS['tokk_member_uid_providers'] = array();
}

/**
 * Laravel側の会員UID取得エンドポイントを取得する
 *
 * @return string|null
 */
function tokk_get_laravel_member_uid_endpoint(): ?string
{
    $endpoint = defined('TOKK_MEMBER_UID_LARAVEL_ENDPOINT')
        ? TOKK_MEMBER_UID_LARAVEL_ENDPOINT
        : getenv('TOKK_MEMBER_UID_LARAVEL_ENDPOINT');

    if (!is_string($endpoint) || trim($endpoint) === '') {
        return null;
    }

    return $endpoint;
}

/**
 * Laravel側の会員UID取得トークンを取得する
 *
 * @return string|null
 */
function tokk_get_laravel_member_uid_token(): ?string
{
    $token = defined('TOKK_MEMBER_UID_LARAVEL_TOKEN')
        ? TOKK_MEMBER_UID_LARAVEL_TOKEN
        : getenv('TOKK_MEMBER_UID_LARAVEL_TOKEN');

    if (!is_string($token) || trim($token) === '') {
        return null;
    }

    return $token;
}

/**
 * 会員ブログAPIのmember-service側プレフィックスを取得する
 *
 * @return string
 */
function tokk_get_memberblog_member_service_prefix(): string
{
    return '/member-service/api/v1/member/blog';
}

/**
 * 会員ブログAPIの共通リクエストを実行する
 *
 * @param string $method
 * @param string $endpointPrefixRemoved
 * @param array<string,mixed> $payload
 * @return array{success:bool,status:int,body:array,error_message?:string}
 */
function tokk_memberblog_call_api(string $method, string $endpointPrefixRemoved, array $payload = []): array
{
    if (!function_exists('lutwiyo_call_member_service_api')) {
        return [
            'success' => false,
            'status' => 500,
            'body' => [],
            'error_message' => 'member-service caller is not available.',
        ];
    }

    $path = rtrim(tokk_get_memberblog_member_service_prefix(), '/') . '/' . ltrim($endpointPrefixRemoved, '/');

    $response = lutwiyo_call_member_service_api(
        $path,
        strtoupper($method),
        $payload,
        [
            'timeout' => 10,
        ]
    );

    if (!is_array($response)) {
        return [
            'success' => false,
            'status' => 500,
            'body' => [],
            'error_message' => '会員ブログAPIの呼び出しに失敗しました。',
        ];
    }

    $status = (int) ($response['status'] ?? 500);
    $body = is_array($response['body'] ?? null) ? $response['body'] : [];

    if ($status < 200 || $status >= 300 || ($body['result'] ?? '') !== 'ok') {
        return [
            'success' => false,
            'status' => $status,
            'body' => $body,
            'error_message' => (string) ($body['message'] ?? '会員ブログAPIの呼び出しに失敗しました。'),
        ];
    }

    return [
        'success' => true,
        'status' => $status,
        'body' => $body,
    ];
}

/**
 * 会員ブログ投稿一覧を取得する
 *
 * @param array<string,mixed> $args
 * @return array{success:bool,items:array,total:int,error_message?:string}
 */
function tokk_memberblog_fetch_posts(array $args = array()): array
{
    $page = isset($args['page']) ? max(1, (int) $args['page']) : 1;
    $perPage = isset($args['per_page']) ? max(1, min(100, (int) $args['per_page'])) : 20;
    $status = isset($args['status']) ? trim((string) $args['status']) : 'all';

    $response = tokk_memberblog_call_api('GET', 'posts', [
        'page' => $page,
        'per_page' => $perPage,
        'status' => $status,
    ]);

    if (!$response['success']) {
        return [
            'success' => false,
            'items' => [],
            'total' => 0,
            'error_message' => $response['error_message'] ?? '一覧を取得できませんでした。',
        ];
    }

    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    return [
        'success' => true,
        'items' => $items,
        'total' => (int) ($data['total'] ?? 0),
    ];
}

/**
 * 会員ブログ公開一覧を取得する（会員別/エリア別）。
 *
 * @param array<string,mixed> $args
 * @return array{success:bool,items:array,total:int,available_areas?:array<int,array{slug:string,label?:string,count:int}>,available_tags?:array<int,array{slug:string,label?:string,count:int}>,error_message?:string}
 */
function tokk_memberblog_fetch_public_posts(array $args = array()): array
{
    $page = isset($args['page']) ? max(1, (int) $args['page']) : 1;
    $perPage = isset($args['per_page']) ? max(1, min(100, (int) $args['per_page'])) : 20;
    $memberUid = isset($args['member_uid']) ? trim((string) $args['member_uid']) : '';
    $memberSlug = isset($args['member_slug']) ? trim((string) $args['member_slug']) : '';
    $areaSlug = isset($args['area_slug']) ? trim((string) $args['area_slug']) : '';
    $tagSlug = isset($args['tag_slug']) ? trim((string) $args['tag_slug']) : '';

    $payload = [
        'page' => $page,
        'per_page' => $perPage,
    ];
    if ($memberUid !== '') {
        $payload['member_uid'] = $memberUid;
    } elseif ($memberSlug !== '') {
        $payload['member_slug'] = $memberSlug;
    }
    if ($areaSlug !== '') {
        $payload['area_slug'] = $areaSlug;
    }
    if ($tagSlug !== '') {
        $payload['tag_slug'] = $tagSlug;
    }

    $response = tokk_memberblog_call_api('GET', 'public/posts', $payload);

    if (!$response['success']) {
        return [
            'success' => false,
            'items' => [],
            'total' => 0,
            'error_message' => $response['error_message'] ?? '公開一覧を取得できませんでした。',
        ];
    }

    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    return [
        'success' => true,
        'items' => $items,
        'total' => (int) ($data['total'] ?? 0),
        'available_areas' => isset($data['available_areas']) && is_array($data['available_areas']) ? $data['available_areas'] : [],
        'available_tags' => isset($data['available_tags']) && is_array($data['available_tags']) ? $data['available_tags'] : [],
    ];
}

/**
 * 会員ブログ公開詳細の利用者向け取得失敗メッセージを返す。
 *
 * @return string
 */
function tokk_memberblog_get_public_detail_error_message(): string
{
    return 'ブログ記事の表示に失敗しました。再読み込みをお試しください。';
}

/**
 * 会員向け一覧カードの pending 表示文言を返す。
 *
 * @return string
 */
function tokk_memberblog_get_pending_notice_message(): string
{
    return '不適切なワードが含まれているため投稿できません。';
}

/**
 * 会員ブログ公開投稿の単体詳細を取得する。
 *
 * @param int $postId
 * @return array{success:bool,item?:array<string,mixed>,error_message?:string}
 */
function tokk_memberblog_fetch_public_post(int $postId): array
{
    if ($postId <= 0) {
        return [
            'success' => false,
            'error_message' => tokk_memberblog_get_public_detail_error_message(),
        ];
    }

    $response = tokk_memberblog_call_api('GET', 'public/posts/' . $postId);
    if (!$response['success']) {
        return [
            'success' => false,
            'error_message' => tokk_memberblog_get_public_detail_error_message(),
        ];
    }

    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];

    return [
        'success' => true,
        'item' => $data,
    ];
}

/**
 * 会員ブログ公開詳細のブロック配列を簡易描画する。
 *
 * @param array<int,mixed> $blocks
 */
function tokk_memberblog_render_public_post_blocks(array $blocks): void
{
    if ($blocks === []) {
        return;
    }

    echo '<section class="memberblog-public-detail-body">';
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $type = trim((string) ($block['type'] ?? ''));
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $blockClassSuffix = str_replace('/', '-', $type !== '' ? $type : 'unknown');
        echo '<div class="memberblog-public-detail-block memberblog-public-detail-block--' . esc_attr($blockClassSuffix) . '">';

        switch ($type) {
            case 'core/heading':
                $level = max(1, min(6, (int) ($data['level'] ?? 2)));
                $text = (string) ($data['text'] ?? '');
                echo '<h' . $level . '>' . esc_html($text) . '</h' . $level . '>';
                break;
            case 'core/paragraph':
                echo '<p>' . esc_html((string) ($data['text'] ?? '')) . '</p>';
                break;
            case 'core/image':
                $url = trim((string) ($data['url'] ?? ''));
                if ($url !== '') {
                    echo '<figure><img src="' . esc_url($url) . '" alt="' . esc_attr((string) ($data['alt'] ?? '')) . '" /></figure>';
                }
                break;
            case 'core/list':
                $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
                if ($items !== []) {
                    echo '<ul>';
                    foreach ($items as $item) {
                        echo '<li>' . esc_html((string) $item) . '</li>';
                    }
                    echo '</ul>';
                }
                break;
            case 'core/quote':
                echo '<blockquote>' . esc_html((string) ($data['text'] ?? '')) . '</blockquote>';
                break;
            case 'core/separator':
                echo '<hr />';
                break;
            case 'core/buttons':
                $buttons = isset($data['buttons']) && is_array($data['buttons']) ? $data['buttons'] : [];
                if ($buttons !== []) {
                    echo '<div class="memberblog-public-detail-buttons">';
                    foreach ($buttons as $button) {
                        if (!is_array($button)) {
                            continue;
                        }
                        $text = esc_html((string) ($button['text'] ?? 'リンク'));
                        $url = trim((string) ($button['url'] ?? ''));
                        if ($url !== '') {
                            echo '<a class="memberblog-public-detail-button-link" href="' . esc_url($url) . '">' . $text . '</a> ';
                        }
                    }
                    echo '</div>';
                }
                break;
            case 'core/embed':
                $url = trim((string) ($data['url'] ?? ''));
                if ($url !== '') {
                    echo '<p><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a></p>';
                }
                break;
            case 'tokk/store-card':
                $storeId = trim((string) ($data['store_id'] ?? ''));
                if ($storeId !== '') {
                    echo '<p>店舗情報ID: <span class="memberblog-public-detail-store-id">' . esc_html($storeId) . '</span></p>';
                }
                break;
            case 'tokk/paywall-break':
                echo '<hr /><p class="memberblog-public-detail-paywall-note">ここから先は有料会員向けコンテンツです。</p>';
                break;
            default:
                $rawHtml = (string) ($data['raw_html'] ?? '');
                if ($rawHtml !== '') {
                    echo wp_kses_post($rawHtml);
                }
                break;
        }
        echo '</div>';
    }
    echo '</section>';
}

/**
 * 会員ブログ投稿単体を取得する
 *
 * @param int $postId
 * @return array{success:bool,item?:array,error_message?:string}
 */
function tokk_memberblog_fetch_post(int $postId): array
{
    $response = tokk_memberblog_call_api('GET', 'posts/' . $postId);
    if (!$response['success']) {
        return [
            'success' => false,
            'error_message' => $response['error_message'] ?? '投稿を取得できませんでした。',
        ];
    }

    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];

    return [
        'success' => true,
        'item' => $data,
    ];
}

/**
 * 会員ブログ投稿を作成する
 *
 * @param array<string,mixed> $payload
 * @return array{success:bool,item?:array,error_message?:string}
 */
function tokk_memberblog_create_post(array $payload): array
{
    $response = tokk_memberblog_call_api('POST', 'posts', $payload);
    if (!$response['success']) {
        return [
            'success' => false,
            'error_message' => $response['error_message'] ?? '投稿を作成できませんでした。',
        ];
    }

    return [
        'success' => true,
        'item' => is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [],
    ];
}

/**
 * 会員ブログ投稿を更新する
 *
 * @param int $postId
 * @param array<string,mixed> $payload
 * @return array{success:bool,item?:array,error_message?:string}
 */
function tokk_memberblog_update_post(int $postId, array $payload): array
{
    $response = tokk_memberblog_call_api('PUT', 'posts/' . $postId, $payload);
    if (!$response['success']) {
        return [
            'success' => false,
            'error_message' => $response['error_message'] ?? '投稿を更新できませんでした。',
        ];
    }

    return [
        'success' => true,
        'item' => is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [],
    ];
}

/**
 * 会員ブログ投稿のステータスを更新する
 *
 * @param int $postId
 * @param string $status
 * @return array{success:bool,item?:array,error_message?:string}
 */
function tokk_memberblog_update_status(int $postId, string $status): array
{
    $response = tokk_memberblog_call_api('PATCH', 'posts/' . $postId . '/status', [
        'status' => $status,
    ]);
    if (!$response['success']) {
        return [
            'success' => false,
            'error_message' => $response['error_message'] ?? 'ステータスを更新できませんでした。',
        ];
    }

    return [
        'success' => true,
        'item' => is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [],
    ];
}

/**
 * 会員ブログ投稿を削除（trash）する
 *
 * @param int $postId
 * @return array{success:bool,item?:array,error_message?:string}
 */
function tokk_memberblog_delete_post(int $postId): array
{
    $response = tokk_memberblog_call_api('DELETE', 'posts/' . $postId);
    if (!$response['success']) {
        return [
            'success' => false,
            'error_message' => $response['error_message'] ?? '投稿を削除できませんでした。',
        ];
    }

    return [
        'success' => true,
        'item' => is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [],
    ];
}

/**
 * 投稿画面のアクション処理（作成/更新/ステータス変更/削除）
 *
 * @param array<int,string>|null $allowedActions
 * @return array{status:string,message:string,action?:string,item?:array<string,mixed>}
 */
function tokk_memberblog_handle_actions(?array $allowedActions = null): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['status' => 'idle', 'message' => ''];
    }

    $nonce = isset($_POST['memberblog_nonce']) ? sanitize_text_field(wp_unslash($_POST['memberblog_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'tokk_memberblog_action')) {
        return ['status' => 'error', 'message' => '不正な操作です。ページを再読み込みしてください。'];
    }

    $action = isset($_POST['memberblog_action']) ? sanitize_text_field(wp_unslash($_POST['memberblog_action'])) : '';
    if (is_array($allowedActions) && ! in_array($action, $allowedActions, true)) {
        return ['status' => 'error', 'message' => '不正な操作です。'];
    }
    $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'draft';
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $commentViewAcl = tokk_memberblog_normalize_comment_view_acl(isset($_POST['comment_view_acl']) ? sanitize_text_field(wp_unslash($_POST['comment_view_acl'])) : 'public');
    $commentPostAcl = tokk_memberblog_normalize_comment_post_acl(isset($_POST['comment_post_acl']) ? sanitize_text_field(wp_unslash($_POST['comment_post_acl'])) : 'enabled');
    $statusOptions = tokk_memberblog_get_status_options();
    if (! array_key_exists($status, $statusOptions)) {
        $status = 'draft';
    }

    if ($action === 'create' || $action === 'update') {
        $blocks = tokk_memberblog_resolve_blocks_from_post();
        if ($blocks === null) {
            return ['status' => 'error', 'message' => '本文ブロックの形式が不正です。'];
        }
        $blockValidationError = tokk_memberblog_validate_blocks($blocks);
        if ($blockValidationError !== null) {
            return ['status' => 'error', 'message' => $blockValidationError, 'action' => $action];
        }

        $areaSlugsErrorMessage = '';
        $areaSlugs = tokk_memberblog_resolve_area_slugs_from_post($areaSlugsErrorMessage);
        if ($areaSlugs === null) {
            return ['status' => 'error', 'message' => $areaSlugsErrorMessage !== '' ? $areaSlugsErrorMessage : 'エリア指定が不正です。'];
        }

        $tagSlugsErrorMessage = '';
        $tagSlugs = tokk_memberblog_resolve_tag_slugs_from_post($tagSlugsErrorMessage);
        if ($tagSlugs === null) {
            return ['status' => 'error', 'message' => $tagSlugsErrorMessage !== '' ? $tagSlugsErrorMessage : 'タグ指定が不正です。'];
        }
    } else {
        $blocks = [];
        $areaSlugs = [];
        $tagSlugs = [];
    }

    if ($action === 'create') {
        $created = tokk_memberblog_create_post([
            'title' => $title,
            'status' => $status,
            'blocks' => $blocks,
            'area_slugs' => $areaSlugs,
            'tag_slugs' => $tagSlugs,
            'comment_view_acl' => $commentViewAcl,
            'comment_post_acl' => $commentPostAcl,
        ]);

        if (!$created['success']) {
            return ['status' => 'error', 'message' => (string) ($created['error_message'] ?? '投稿作成に失敗しました。'), 'action' => 'create'];
        }

        return [
            'status' => 'success',
            'message' => '投稿を作成しました。',
            'action' => 'create',
            'item' => is_array($created['item'] ?? null) ? $created['item'] : [],
        ];
    }

    if ($postId <= 0) {
        return ['status' => 'error', 'message' => '投稿IDが不正です。'];
    }

    if ($action === 'update') {
        $updated = tokk_memberblog_update_post($postId, [
            'title' => $title,
            'status' => $status,
            'blocks' => $blocks,
            'area_slugs' => $areaSlugs,
            'tag_slugs' => $tagSlugs,
            'comment_view_acl' => $commentViewAcl,
            'comment_post_acl' => $commentPostAcl,
        ]);
        if (!$updated['success']) {
            return ['status' => 'error', 'message' => (string) ($updated['error_message'] ?? '投稿更新に失敗しました。'), 'action' => 'update'];
        }

        return [
            'status' => 'success',
            'message' => '投稿を更新しました。',
            'action' => 'update',
            'item' => is_array($updated['item'] ?? null) ? $updated['item'] : [],
        ];
    }

    if ($action === 'update_status') {
        $updated = tokk_memberblog_update_status($postId, $status);
        if (!$updated['success']) {
            return ['status' => 'error', 'message' => (string) ($updated['error_message'] ?? 'ステータス更新に失敗しました。'), 'action' => 'update_status'];
        }

        return ['status' => 'success', 'message' => 'ステータスを更新しました。', 'action' => 'update_status'];
    }

    if ($action === 'delete') {
        $deleted = tokk_memberblog_delete_post($postId);
        if (!$deleted['success']) {
            return ['status' => 'error', 'message' => (string) ($deleted['error_message'] ?? '投稿削除に失敗しました。'), 'action' => 'delete'];
        }

        return ['status' => 'success', 'message' => '投稿を削除しました。', 'action' => 'delete'];
    }

    return ['status' => 'error', 'message' => '不正な操作です。'];
}

/**
 * 送信直後のフォーム状態を組み立てる。
 *
 * @param array<string,mixed>|null $baseItem
 * @return array<string,mixed>|null
 */
function tokk_memberblog_build_editor_item_from_request(?array $baseItem = null): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }

    $action = isset($_POST['memberblog_action']) ? sanitize_text_field(wp_unslash($_POST['memberblog_action'])) : '';
    if (! in_array($action, ['create', 'update'], true)) {
        return null;
    }

    $statusOptions = tokk_memberblog_get_status_options();
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'draft';
    if (! array_key_exists($status, $statusOptions)) {
        $status = 'draft';
    }

    $blocks = tokk_memberblog_resolve_blocks_from_post();
    if (! is_array($blocks) || $blocks === []) {
        $blocks = isset($baseItem['blocks']) && is_array($baseItem['blocks'])
            ? $baseItem['blocks']
            : tokk_memberblog_build_default_blocks();
    }

    $rawAreaSlugs = isset($_POST['area_slugs']) ? wp_unslash($_POST['area_slugs']) : [];
    $areaSlugs = is_array($rawAreaSlugs) ? tokk_memberblog_normalize_area_slugs($rawAreaSlugs) : [];

    $rawTagSlugs = isset($_POST['tag_slugs']) ? wp_unslash($_POST['tag_slugs']) : [];
    $tagSlugs = is_array($rawTagSlugs) ? tokk_memberblog_filter_allowed_tag_slugs($rawTagSlugs) : [];

    $item = is_array($baseItem) ? $baseItem : [];
    $item['id'] = isset($_POST['post_id']) ? (int) $_POST['post_id'] : (int) ($item['id'] ?? 0);
    $item['title'] = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : (string) ($item['title'] ?? '');
    $item['status'] = $status;
    $item['comment_view_acl'] = tokk_memberblog_normalize_comment_view_acl(isset($_POST['comment_view_acl']) ? sanitize_text_field(wp_unslash($_POST['comment_view_acl'])) : (string) ($item['comment_view_acl'] ?? 'public'));
    $item['comment_post_acl'] = tokk_memberblog_normalize_comment_post_acl(isset($_POST['comment_post_acl']) ? sanitize_text_field(wp_unslash($_POST['comment_post_acl'])) : (string) ($item['comment_post_acl'] ?? 'enabled'));
    $item['area_slugs'] = $areaSlugs;
    $item['tag_slugs'] = $tagSlugs;
    $item['blocks'] = $blocks;

    return $item;
}

/**
 * 投稿フォーム入力から blocks を生成する
 *
 * @return array<int,array<string,mixed>>|null
 */
function tokk_memberblog_resolve_blocks_from_post(): ?array
{
    $blocksJson = isset($_POST['blocks_json']) ? trim((string) wp_unslash($_POST['blocks_json'])) : '';
    if ($blocksJson === '') {
        return null;
    }

    $decoded = json_decode($blocksJson, true);
    if (! is_array($decoded) || $decoded === []) {
        return null;
    }

    return $decoded;
}

/**
 * @param array<int,array<string,mixed>> $blocks
 */
function tokk_memberblog_validate_blocks(array $blocks): ?string
{
    foreach ($blocks as $index => $block) {
        if (! is_array($block)) {
            continue;
        }

        $type = trim((string) ($block['type'] ?? ''));
        if ($type !== 'core/paragraph') {
            continue;
        }

        $data = isset($block['data']) && is_array($block['data']) ? $block['data'] : [];
        $text = trim((string) ($data['text'] ?? ''));
        if (mb_strlen($text) > 400) {
            return '段落ブロック' . (string) ($index + 1) . 'は400文字以内で入力してください。';
        }
    }

    return null;
}

function tokk_memberblog_get_area_selection_limit(): int
{
    return 5;
}

function tokk_memberblog_get_tag_selection_limit(): int
{
    return 5;
}

/**
 * @return array<int,array{slug:string,name:string}>
 */
function tokk_memberblog_get_area_options(): array
{
    if (! function_exists('get_terms') || ! function_exists('is_wp_error')) {
        return [];
    }

    $terms = get_terms([
        'taxonomy' => 'area',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
    if (is_wp_error($terms) || ! is_array($terms)) {
        return [];
    }

    $options = [];
    foreach ($terms as $term) {
        if (! is_object($term)) {
            continue;
        }

        $slug = trim((string) ($term->slug ?? ''));
        if ($slug === '' || ! preg_match('/^[a-z0-9_-]{1,120}$/', $slug)) {
            continue;
        }

        $options[] = [
            'slug' => $slug,
            'name' => (string) ($term->name ?? $slug),
        ];
    }

    return $options;
}

/**
 * @return array<int,array{slug:string,name:string}>
 */
function tokk_memberblog_get_allowed_tag_options(): array
{
    if (! function_exists('get_terms') || ! function_exists('is_wp_error')) {
        return [];
    }

    $metaKey = function_exists('tokk_memberblog_allowed_tag_meta_key')
        ? tokk_memberblog_allowed_tag_meta_key()
        : 'tokk_memberblog_allowed';

    $terms = get_terms([
        'taxonomy' => 'post_tag',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => $metaKey,
                'value' => '1',
                'compare' => '=',
            ],
        ],
    ]);
    if (is_wp_error($terms) || ! is_array($terms)) {
        return [];
    }

    $options = [];
    foreach ($terms as $term) {
        if (! is_object($term)) {
            continue;
        }

        $slug = trim((string) ($term->slug ?? ''));
        if ($slug === '' || ! preg_match('/^[a-z0-9_-]{1,120}$/', $slug)) {
            continue;
        }

        $options[] = [
            'slug' => $slug,
            'name' => (string) ($term->name ?? $slug),
        ];
    }

    return $options;
}

/**
 * @return array<string,bool>
 */
function tokk_memberblog_get_allowed_tag_slug_map(): array
{
    static $slugMap = null;

    if (is_array($slugMap)) {
        return $slugMap;
    }

    $slugMap = [];
    foreach (tokk_memberblog_get_allowed_tag_options() as $option) {
        $slug = trim((string) ($option['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $slugMap[$slug] = true;
    }

    return $slugMap;
}

function tokk_memberblog_is_allowed_tag_slug(string $slug): bool
{
    $normalizedSlug = trim($slug);
    if ($normalizedSlug === '') {
        return false;
    }

    $slugMap = tokk_memberblog_get_allowed_tag_slug_map();
    return isset($slugMap[$normalizedSlug]);
}

/**
 * @param array<int,mixed> $slugs
 * @return array<int,string>
 */
function tokk_memberblog_filter_allowed_tag_slugs(array $slugs): array
{
    $normalized = tokk_memberblog_normalize_tag_slugs($slugs);
    $slugMap = tokk_memberblog_get_allowed_tag_slug_map();
    if ($slugMap === []) {
        return [];
    }

    $filtered = [];
    foreach ($normalized as $slug) {
        if (isset($slugMap[$slug])) {
            $filtered[] = $slug;
        }
    }

    return $filtered;
}

/**
 * @param array<int,mixed> $slugs
 * @return array<int,string>
 */
function tokk_memberblog_normalize_area_slugs(array $slugs): array
{
    $normalized = [];
    $dedupe = [];
    $maxCount = tokk_memberblog_get_area_selection_limit();
    foreach ($slugs as $slugRaw) {
        if (! is_string($slugRaw) && ! is_numeric($slugRaw)) {
            continue;
        }

        $slug = trim((string) $slugRaw);
        if ($slug === '' || ! preg_match('/^[a-z0-9_-]{1,120}$/', $slug) || isset($dedupe[$slug])) {
            continue;
        }

        $dedupe[$slug] = true;
        $normalized[] = $slug;

        if (count($normalized) >= $maxCount) {
            break;
        }
    }

    return $normalized;
}

/**
 * @param array<int,mixed> $slugs
 * @return array<int,string>
 */
function tokk_memberblog_normalize_tag_slugs(array $slugs): array
{
    $normalized = [];
    $dedupe = [];
    $maxCount = tokk_memberblog_get_tag_selection_limit();
    foreach ($slugs as $slugRaw) {
        if (! is_string($slugRaw) && ! is_numeric($slugRaw)) {
            continue;
        }

        $slug = trim((string) $slugRaw);
        if ($slug === '' || ! preg_match('/^[a-z0-9_-]{1,120}$/', $slug) || isset($dedupe[$slug])) {
            continue;
        }

        $dedupe[$slug] = true;
        $normalized[] = $slug;

        if (count($normalized) >= $maxCount) {
            break;
        }
    }

    return $normalized;
}

/**
 * @param string|null $errorMessage
 * @return array<int,string>|null
 */
function tokk_memberblog_resolve_area_slugs_from_post(?string &$errorMessage = null): ?array
{
    $errorMessage = '';

    $rawAreaSlugs = isset($_POST['area_slugs']) ? wp_unslash($_POST['area_slugs']) : [];
    if (! is_array($rawAreaSlugs)) {
        $errorMessage = 'エリア指定の形式が不正です。';
        return null;
    }

    $maxCount = tokk_memberblog_get_area_selection_limit();
    $areaSlugs = [];
    $slugMap = [];
    foreach ($rawAreaSlugs as $slugRaw) {
        if (! is_string($slugRaw) && ! is_numeric($slugRaw)) {
            $errorMessage = 'エリアslugの形式が不正です。';
            return null;
        }

        $slug = trim((string) $slugRaw);
        if ($slug === '' || ! preg_match('/^[a-z0-9_-]{1,120}$/', $slug)) {
            $errorMessage = 'エリアslugの形式が不正です。';
            return null;
        }

        if (isset($slugMap[$slug])) {
            $errorMessage = '同じエリアは1回だけ選択してください。';
            return null;
        }

        $slugMap[$slug] = true;
        $areaSlugs[] = $slug;

        if (count($areaSlugs) > $maxCount) {
            $errorMessage = 'エリアは最大' . $maxCount . '件まで選択できます。';
            return null;
        }
    }

    $availableAreaOptions = tokk_memberblog_get_area_options();
    $availableSlugMap = [];
    foreach ($availableAreaOptions as $option) {
        $slug = trim((string) ($option['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $availableSlugMap[$slug] = true;
    }

    foreach ($areaSlugs as $slug) {
        if (! isset($availableSlugMap[$slug])) {
            $errorMessage = '指定されたエリアが存在しません。';
            return null;
        }
    }

    return $areaSlugs;
}

/**
 * @param string|null $errorMessage
 * @return array<int,string>|null
 */
function tokk_memberblog_resolve_tag_slugs_from_post(?string &$errorMessage = null): ?array
{
    $errorMessage = '';

    $rawTagSlugs = isset($_POST['tag_slugs']) ? wp_unslash($_POST['tag_slugs']) : [];
    if (! is_array($rawTagSlugs)) {
        $errorMessage = 'タグ指定の形式が不正です。';
        return null;
    }

    $maxCount = tokk_memberblog_get_tag_selection_limit();
    $tagSlugs = [];
    $slugMap = [];
    foreach ($rawTagSlugs as $slugRaw) {
        if (! is_string($slugRaw) && ! is_numeric($slugRaw)) {
            $errorMessage = 'タグslugの形式が不正です。';
            return null;
        }

        $slug = trim((string) $slugRaw);
        if ($slug === '' || ! preg_match('/^[a-z0-9_-]{1,120}$/', $slug)) {
            $errorMessage = 'タグslugの形式が不正です。';
            return null;
        }

        if (isset($slugMap[$slug])) {
            $errorMessage = '同じタグは1回だけ選択してください。';
            return null;
        }

        $slugMap[$slug] = true;
        $tagSlugs[] = $slug;

        if (count($tagSlugs) > $maxCount) {
            $errorMessage = 'タグは最大' . $maxCount . '件まで選択できます。';
            return null;
        }
    }

    $availableSlugMap = tokk_memberblog_get_allowed_tag_slug_map();

    foreach ($tagSlugs as $slug) {
        if (! isset($availableSlugMap[$slug])) {
            $errorMessage = '指定されたタグは会員ブログでは利用できません。';
            return null;
        }
    }

    return $tagSlugs;
}

/**
 * 会員ブログ投稿ステータスの定義を返す
 *
 * @return array<string,string>
 */
function tokk_memberblog_get_status_options(): array
{
    return [
        'draft' => '下書き',
        'pending' => '承認待ち',
        'private' => '非公開',
        'publish' => '公開',
    ];
}

/**
 * 会員ブログコメント閲覧ACL候補を返す
 *
 * @return array<string,string>
 */
function tokk_memberblog_get_comment_view_acl_options(): array
{
    return [
        'public' => '公開（誰でも閲覧可）',
        'members' => '会員限定（ログイン会員のみ閲覧可）',
        'private' => '非表示（コメントブロックを表示しない）',
    ];
}

/**
 * 会員ブログコメント投稿ACL候補を返す
 *
 * @return array<string,string>
 */
function tokk_memberblog_get_comment_post_acl_options(): array
{
    return [
        'enabled' => '許可',
        'disabled' => '停止',
    ];
}

/**
 * @param mixed $value
 */
function tokk_memberblog_normalize_comment_view_acl($value): string
{
    $normalized = trim((string) $value);
    $options = tokk_memberblog_get_comment_view_acl_options();
    if (! array_key_exists($normalized, $options)) {
        return 'public';
    }

    return $normalized;
}

/**
 * @param mixed $value
 */
function tokk_memberblog_normalize_comment_post_acl($value): string
{
    $normalized = trim((string) $value);
    $options = tokk_memberblog_get_comment_post_acl_options();
    if (! array_key_exists($normalized, $options)) {
        return 'enabled';
    }

    return $normalized;
}

/**
 * 会員ブログ編集UIで初期表示するブロック候補を返す
 *
 * @return array<string,string>
 */
function tokk_memberblog_get_editor_block_type_options(): array
{
    return [
        'core/heading' => '見出し',
        'core/paragraph' => '本文',
        'core/image' => '画像',
        'core/embed' => 'URL埋め込み',
        'tokk/store-card' => '店舗カード',
        'tokk/paywall-break' => '有料境界（paywall-break）',
    ];
}

/**
 * @return array<int,array<string,mixed>>
 */
function tokk_memberblog_build_default_blocks(): array
{
    return [
        [
            'type' => 'core/paragraph',
            'data' => [
                'text' => '',
            ],
        ],
    ];
}

/**
 * 画面表示用のコンテキストを組み立てる
 *
 * @param string|null $uid 会員UID
 * @return array
 */
function tokk_memberblog_get_posts_context(?string $uid): array
{
    // 未ログインの場合はログイン誘導の状態にする
    if (null === $uid) {
        return array(
            'status' => 'logged_out',
            'items'  => array(),
        );
    }

    // API取得結果を画面コンテキストに変換する
    $result = tokk_memberblog_fetch_posts([
        'status' => 'all',
        'page' => 1,
        'per_page' => 20,
    ]);
    if (!$result['success']) {
        return array(
            'status'        => 'error',
            'items'         => array(),
            'error_message' => $result['error_message'] ?? '一覧を取得できませんでした。',
        );
    }

    $rawItems = isset($result['items']) && is_array($result['items']) ? $result['items'] : [];
    $visibleItems = array_values(array_filter($rawItems, static function ($item): bool {
        if (!is_array($item)) {
            return false;
        }
        $status = strtolower(trim((string) ($item['status'] ?? '')));

        // 会員画面ではゴミ箱投稿を非表示にする。
        return $status !== 'trash';
    }));

    return array(
        'status' => 'success',
        'items'  => $visibleItems,
    );
}

/**
 * 会員ブログ一覧のHTMLを出力する
 *
 * @param array $context 表示用コンテキスト
 * @param array<string,mixed> $actionResult
 * @return void
 */
function tokk_memberblog_resolve_editor_item(array $actionResult = []): ?array
{
    $editPostId = isset($_GET['edit_post']) ? (int) $_GET['edit_post'] : 0;
    $editItem = null;

    if (in_array((string) ($actionResult['action'] ?? ''), ['create', 'update'], true) && is_array($actionResult['item'] ?? null)) {
        $editItem = $actionResult['item'];
    }

    if ($editPostId > 0) {
        $fetched = tokk_memberblog_fetch_post($editPostId);
        if ($fetched['success']) {
            $editItem = is_array($fetched['item'] ?? null) ? $fetched['item'] : null;
        }
    }

    if (($actionResult['status'] ?? '') === 'error') {
        $submittedItem = tokk_memberblog_build_editor_item_from_request($editItem);
        if (is_array($submittedItem)) {
            $editItem = $submittedItem;
        }
    }

    return $editItem;
}

/**
 * @param array<string,mixed>|null $editItem
 */
function tokk_memberblog_render_editor_form(?array $editItem = null): void
{
    $statusOptions = tokk_memberblog_get_status_options();
    $editorStatusOptions = array_intersect_key($statusOptions, array_flip(['draft', 'private', 'publish']));
    $commentViewOptions = tokk_memberblog_get_comment_view_acl_options();
    $commentPostOptions = tokk_memberblog_get_comment_post_acl_options();
    $areaOptions = tokk_memberblog_get_area_options();
    $areaSelectionLimit = tokk_memberblog_get_area_selection_limit();
    $tagOptions = tokk_memberblog_get_allowed_tag_options();
    $tagSelectionLimit = tokk_memberblog_get_tag_selection_limit();
    $isWithdrawnLocked = $editItem && ((string) ($editItem['moderation_status'] ?? 'normal') === 'withdrawn');
    $moderationReason = trim((string) ($editItem['moderation_reason'] ?? ''));
    if ($isWithdrawnLocked) {
        unset($editorStatusOptions['publish']);
    }
    $selectedStatus = (string) ($editItem['status'] ?? 'draft');
    if (!array_key_exists($selectedStatus, $editorStatusOptions)) {
        $selectedStatus = 'draft';
    }
    if ($isWithdrawnLocked && $selectedStatus === 'publish') {
        $selectedStatus = 'private';
    }
    $selectedCommentViewAcl = tokk_memberblog_normalize_comment_view_acl((string) ($editItem['comment_view_acl'] ?? 'public'));
    $selectedCommentPostAcl = tokk_memberblog_normalize_comment_post_acl((string) ($editItem['comment_post_acl'] ?? 'enabled'));
    $selectedAreaSlugs = tokk_memberblog_normalize_area_slugs(
        isset($editItem['area_slugs']) && is_array($editItem['area_slugs']) ? $editItem['area_slugs'] : []
    );
    $selectedAreaSlugMap = array_fill_keys($selectedAreaSlugs, true);
    $selectedTagSlugs = tokk_memberblog_normalize_tag_slugs(
        isset($editItem['tag_slugs']) && is_array($editItem['tag_slugs']) ? $editItem['tag_slugs'] : []
    );
    $selectedTagSlugMap = array_fill_keys($selectedTagSlugs, true);
    $initialBlocks = tokk_memberblog_build_default_blocks();
    if ($editItem && isset($editItem['blocks']) && is_array($editItem['blocks']) && $editItem['blocks'] !== []) {
        $initialBlocks = $editItem['blocks'];
    }
    $initialBlocksJson = wp_json_encode($initialBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (! is_string($initialBlocksJson) || $initialBlocksJson === '') {
        $initialBlocksJson = '[]';
    }
    $initialBlocksJsonAttr = esc_attr($initialBlocksJson);
    $initialBlocksTextarea = esc_textarea((string) wp_json_encode($initialBlocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $uploadEndpoint = esc_attr(rtrim(tokk_get_memberblog_member_service_prefix(), '/') . '/uploads');

    echo '<section class="memberblog-editor">';
    echo '<h2 class="memberblog-editor__title">' . ($editItem ? '会員ブログ記事編集' : '会員ブログ記事新規作成') . '</h2>';
    echo '<form method="post" class="js-memberblog-editor-form" data-initial-blocks="' . $initialBlocksJsonAttr . '" data-upload-endpoint="' . $uploadEndpoint . '" data-withdrawn-locked="' . ($isWithdrawnLocked ? '1' : '0') . '">';
    wp_nonce_field('tokk_memberblog_action', 'memberblog_nonce');
    echo '<input type="hidden" name="memberblog_action" class="js-memberblog-action-input" value="' . ($editItem ? 'update' : 'create') . '" />';
    if ($editItem) {
        echo '<input type="hidden" name="post_id" value="' . (int) ($editItem['id'] ?? 0) . '" />';
    }
    echo '<div class="memberblog-editor__layout">';
    echo '<div class="memberblog-editor__main">';
    if ($isWithdrawnLocked) {
        $withdrawnMessage = '運営判断により公開差し止めとなっているため公開できません。';
        if ($moderationReason !== '') {
            $withdrawnMessage .= ' 理由: ' . $moderationReason;
        }
        echo '<p class="memberblog-editor__withdrawn-warning">' . esc_html($withdrawnMessage) . '</p>';
    }
    echo '<p class="memberblog-editor__field"><label>タイトル<br /><input type="text" name="title" value="' . esc_attr((string) ($editItem['title'] ?? '')) . '" required /></label></p>';
    echo '<p class="memberblog-editor__field"><label>ステータス<br /><select name="status">';
    foreach ($editorStatusOptions as $value => $label) {
        $selected = $selectedStatus === $value ? ' selected' : '';
        echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label></p>';
    echo '<p class="memberblog-editor__field"><label>エリア（最大' . (int) $areaSelectionLimit . '）<br /><select name="area_slugs[]" class="js-memberblog-area-select" multiple size="8" data-max-select="' . (int) $areaSelectionLimit . '" style="height:auto;min-height:160px;">';
    if ($areaOptions === []) {
        echo '<option value="" disabled>選択可能なエリアがありません</option>';
    } else {
        foreach ($areaOptions as $option) {
            $slug = trim((string) ($option['slug'] ?? ''));
            $name = trim((string) ($option['name'] ?? ''));
            if ($slug === '') {
                continue;
            }
            if ($name === '') {
                $name = $slug;
            }
            $selected = isset($selectedAreaSlugMap[$slug]) ? ' selected' : '';
            echo '<option value="' . esc_attr($slug) . '"' . $selected . '>' . esc_html($name) . '</option>';
        }
    }
    echo '</select></label><span class="memberblog-editor__field-note">※記事の内容に該当するエリアを選択してください。複数選択可（パソコンはCtrl/Cmd+クリック、スマートフォンは長押し選択）。</span></p>';
    echo '<p class="memberblog-editor__field"><label>ハッシュタグ（最大' . (int) $tagSelectionLimit . '）<br /><select name="tag_slugs[]" class="js-memberblog-tag-select" multiple size="8" data-max-select="' . (int) $tagSelectionLimit . '" style="height:auto;min-height:160px;">';
    if ($tagOptions === []) {
        echo '<option value="" disabled>選択可能なタグがありません（管理画面で「会員ブログ利用可」を設定してください）</option>';
    } else {
        foreach ($tagOptions as $option) {
            $slug = trim((string) ($option['slug'] ?? ''));
            $name = trim((string) ($option['name'] ?? ''));
            if ($slug === '') {
                continue;
            }
            if ($name === '') {
                $name = $slug;
            }
            $selected = isset($selectedTagSlugMap[$slug]) ? ' selected' : '';
            echo '<option value="' . esc_attr($slug) . '"' . $selected . '>' . esc_html($name) . '</option>';
        }
    }
    echo '</select></label></p>';
    echo '<p class="memberblog-editor__field"><label>コメントの表示範囲<br /><select name="comment_view_acl">';
    foreach ($commentViewOptions as $value => $label) {
        $selected = $selectedCommentViewAcl === $value ? ' selected' : '';
        echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label><span class="memberblog-editor__field-note">この会員ブログのコメントを誰が閲覧できるかを設定します。</span></p>';
    echo '<p class="memberblog-editor__field"><label>他のユーザーによるコメント投稿<br /><select name="comment_post_acl">';
    foreach ($commentPostOptions as $value => $label) {
        $selected = $selectedCommentPostAcl === $value ? ' selected' : '';
        echo '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
    }
    echo '</select></label><span class="memberblog-editor__field-note">この会員ブログに他のユーザーがコメントできるかを設定します。</span></p>';
    echo '<textarea name="blocks_json" class="js-memberblog-blocks-json memberblog-editor__blocks-json" rows="8">' . $initialBlocksTextarea . '</textarea>';
    echo '<div class="memberblog-block-controls">';
    echo '<div class="memberblog-block-controls__inner">';
    echo '<select class="js-memberblog-block-type">';
    foreach (tokk_memberblog_get_editor_block_type_options() as $type => $label) {
        echo '<option value="' . esc_attr($type) . '">' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<button type="button" class="js-memberblog-add-block">ブロック追加</button>';
    echo '</div>';
    echo '</div>';
    echo '<div class="memberblog-block-list js-memberblog-block-list"></div>';
    echo '<div class="memberblog-action-sheet js-memberblog-action-sheet" aria-hidden="true">';
    echo '<div class="memberblog-action-sheet__bg js-memberblog-sheet-close"></div>';
    echo '<div class="memberblog-action-sheet__panel">';
    echo '<button type="button" class="js-memberblog-delete-block">このブロックを削除</button>';
    echo '<button type="button" class="js-memberblog-sheet-close">キャンセル</button>';
    echo '</div>';
    echo '</div>';
    echo '<p class="memberblog-editor__submit-note">内容確認後に実行してください。画像は「ファイル選択」後に「画像アップロード」が必要です。</p>';
    echo '<p class="memberblog-editor__submit-error js-memberblog-submit-error" hidden></p>';
    echo '<button type="submit" class="js-memberblog-final-submit memberblog-editor__final-submit" hidden>最終実行</button>';
    echo '<div class="memberblog-submit-controls"><button type="button" class="js-memberblog-open-confirm">確認して実行</button></div>';
    if ($editItem) {
        echo '<div class="memberblog-editor__danger-zone">';
        echo '<p class="memberblog-editor__danger-note">削除すると会員側からは復元できません。</p>';
        echo '<button type="button" class="memberblog-editor__delete-button js-memberblog-delete-post">ゴミ箱に移動する</button>';
        echo '</div>';
    }
    echo '<div class="memberblog-confirm-sheet js-memberblog-confirm-sheet" aria-hidden="true">';
    echo '<div class="memberblog-confirm-sheet__bg js-memberblog-confirm-close"></div>';
    echo '<div class="memberblog-confirm-sheet__panel">';
    echo '<p class="memberblog-confirm-sheet__title">送信前確認</p>';
    echo '<p class="memberblog-confirm-sheet__meta">タイトル: <span class="js-memberblog-confirm-title">-</span></p>';
    echo '<p class="memberblog-confirm-sheet__meta">実行内容: <span class="js-memberblog-confirm-status">更新</span></p>';
    if ($isWithdrawnLocked) {
        echo '<p class="memberblog-confirm-sheet__warning">取り下げ済み投稿は再公開できません。</p>';
    }
    echo '<div class="memberblog-confirm-sheet__blocks js-memberblog-confirm-blocks"></div>';
    echo '<div class="memberblog-confirm-sheet__actions">';
    echo '<button type="button" class="js-memberblog-confirm-submit">更新する</button>';
    echo '<button type="button" class="js-memberblog-confirm-close memberblog-confirm-sheet__cancel">戻る</button>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<aside class="memberblog-editor-preview js-memberblog-preview" aria-label="ブログプレビュー">';
    echo '<p class="memberblog-editor-preview__label">パソコンプレビュー</p>';
    echo '<div class="memberblog-editor-preview__card">';
    echo '<p class="memberblog-editor-preview__status js-memberblog-preview-status">下書き</p>';
    echo '<h3 class="memberblog-editor-preview__title js-memberblog-preview-title">タイトル未入力</h3>';
    echo '<div class="memberblog-editor-preview__meta">';
    echo '<p class="memberblog-editor-preview__meta-line"><span class="memberblog-editor-preview__meta-label">エリア</span><span class="js-memberblog-preview-areas">未選択</span></p>';
    echo '<p class="memberblog-editor-preview__meta-line"><span class="memberblog-editor-preview__meta-label">タグ</span><span class="js-memberblog-preview-tags">未選択</span></p>';
    echo '</div>';
    echo '<div class="memberblog-editor-preview__blocks js-memberblog-preview-blocks"></div>';
    echo '</div>';
    echo '</aside>';
    echo '</div>';
    echo '</form>';
    echo '</section>';
}

/**
 * 会員ブログ一覧のHTMLを出力する
 *
 * @param array $context 表示用コンテキスト
 * @return void
 */
function tokk_memberblog_render_post_list(array $context): void
{
    if ($context['status'] === 'logged_out') {
        echo '<p>ログインが必要です。<a href="/login/">会員ログイン</a>を行ってください。</p>';
        return;
    }

    if ($context['status'] === 'error') {
        echo '<p>一覧を取得できませんでした。</p>';
        return;
    }

    $items = $context['items'] ?? array();
    if (empty($items)) {
        echo '<p class="memberblog-mypage-empty">投稿はまだありません。</p>';
        return;
    }
    $editorPageUrl = tokk_memberblog_get_editor_page_url();

    echo '<section class="memberblog-list">';
    echo '<h2 class="memberblog-list__title">投稿一覧</h2>';
    echo '<div class="cateSection__list memberblog-mypage-list memberblog-manage-list">';
    foreach ($items as $item) {
        $postId = (int) ($item['id'] ?? 0);
        if ($postId <= 0) {
            continue;
        }
        $titleRaw = trim((string) ($item['title'] ?? ''));
        if ($titleRaw === '') {
            $titleRaw = '無題';
        }
        $title = esc_html($titleRaw);
        $statusRaw = trim((string) ($item['status'] ?? ''));
        if (strtolower($statusRaw) === 'trash') {
            continue;
        }
        $updatedLabel = tokk_memberblog_format_updated_date_label((string) ($item['updated_at'] ?? ''));
        $moderationRaw = (string) ($item['moderation_status'] ?? 'normal');
        $permalink = esc_url((string) ($item['permalink'] ?? ''));
        $isPublicVisible = ($statusRaw === 'publish' && $moderationRaw === 'normal' && $permalink !== '');
        $imageUrl = tokk_memberblog_get_public_card_image_url($item);
        $excerpt = trim((string) ($item['excerpt'] ?? ''));
        $editUrl = esc_url(tokk_memberblog_build_url_with_query_arg($editorPageUrl, 'edit_post', (string) $postId));
        $areaSlugs = isset($item['area_slugs']) && is_array($item['area_slugs']) ? $item['area_slugs'] : [];
        $tagSlugs = isset($item['tag_slugs']) && is_array($item['tag_slugs']) ? tokk_memberblog_filter_allowed_tag_slugs($item['tag_slugs']) : [];

        echo '<article class="blockBox blockBox--cate memberblog-mypage-card memberblog-manage-card" data-boxBgColor="white">';
        echo '<div class="blockBox__thum">';
        echo '<a href="' . $editUrl . '" title="' . $title . '">';
        echo '<div class="thumImg__wrapper"><img class="thumImg" src="' . esc_url($imageUrl) . '" alt="' . $title . '" loading="lazy" /></div>';
        echo '</a>';
        echo '</div>';
        echo '<div class="blockBox__info">';
        if ($statusRaw === 'pending') {
            echo '<p class="memberblog-manage-card__notice memberblog-manage-card__notice--pending">' . esc_html(tokk_memberblog_get_pending_notice_message()) . '</p>';
        } elseif ($moderationRaw === 'withdrawn') {
            $moderationReason = trim((string) ($item['moderation_reason'] ?? ''));
            $withdrawnMessage = '公開できません。';
            if ($moderationReason !== '') {
                $withdrawnMessage .= ' 理由: ' . $moderationReason;
            }
            echo '<p class="memberblog-manage-card__notice memberblog-manage-card__notice--withdrawn">' . esc_html($withdrawnMessage) . '</p>';
        }
        echo '<a href="' . $editUrl . '" title="' . $title . '"><p class="fs--13 textHover__target blockTitle">' . $title . '</p></a>';
        if ($excerpt !== '') {
            echo '<p class="fontW--r textColor--footer blockSubText">' . esc_html($excerpt) . '</p>';
        }
        echo '<div class="blockBox__infoSub--list">';
        if ($updatedLabel !== '') {
            echo '<div class="blockBox__infoSub--target"><div class="icon iconTime"><div class="mask iconInner"></div></div><p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">' . esc_html($updatedLabel) . '</p></div>';
        }
        if ($areaSlugs !== []) {
            $primaryAreaSlug = trim((string) reset($areaSlugs));
            $primaryAreaName = tokk_memberblog_get_term_name_by_slug('area', $primaryAreaSlug);
            if ($primaryAreaName !== '') {
                echo '<div class="blockBox__infoSub--target"><div class="icon iconMap"><div class="mask iconInner"></div></div><p class="fontW--r textColor--footer blockBox__infoSub--text">' . esc_html($primaryAreaName) . '</p></div>';
            }
        }
        echo '</div>';
        if ($tagSlugs !== []) {
            echo '<ul class="hashList">';
            foreach (array_slice($tagSlugs, 0, 3) as $tagSlug) {
                $tagName = tokk_memberblog_get_term_name_by_slug('post_tag', (string) $tagSlug);
                if ($tagName === '') {
                    continue;
                }
                echo '<li class="hashTarget hashUser"><span class="hashLink"><p class="hashTarget--p">' . esc_html($tagName) . '</p></span></li>';
            }
            echo '</ul>';
        }
        echo '<div class="memberblog-mypage-card__actions">';
        echo '<a class="memberblog-mypage-card__action" href="' . $editUrl . '">編集する</a>';
        if ($isPublicVisible) {
            echo '<a class="memberblog-mypage-card__action" href="' . $permalink . '">公開ページを見る</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</article>';
    }
    echo '</div>';
    echo '</section>';
}

/**
 * 互換用: 旧一覧テンプレートからは編集フォーム + 一覧の複合描画を維持する。
 *
 * @param array $context
 * @param array<string,mixed> $actionResult
 */
function tokk_memberblog_render_list(array $context, array $actionResult = []): void
{
    if ($context['status'] === 'logged_out') {
        echo '<p>ログインが必要です。<a href="/login/">会員ログイン</a>を行ってください。</p>';
        return;
    }

    if ($context['status'] === 'error') {
        echo '<p>一覧を取得できませんでした。</p>';
        return;
    }

    tokk_memberblog_render_editor_form(tokk_memberblog_resolve_editor_item($actionResult));
    tokk_memberblog_render_post_list($context);
}

/**
 * 会員ブログの管理状態ラベルを返す。
 */
function tokk_memberblog_get_moderation_label(string $moderationStatus): string
{
    return match (strtolower(trim($moderationStatus))) {
        'withdrawn' => '取り下げ',
        default => '',
    };
}

/**
 * 会員ブログ管理画面のURLを返す。
 */
function tokk_memberblog_get_manage_page_url(): string
{
    return tokk_memberblog_get_list_page_url();
}

function tokk_memberblog_get_list_page_url(): string
{
    return tokk_memberblog_home_url('/memberblog-list/');
}

function tokk_memberblog_get_editor_page_url(): string
{
    return tokk_memberblog_home_url('/member/blog/');
}

/**
 * 会員ブログ管理UIのアクセス状態を返す。
 *
 * @return array{status:string,member_context:array<string,mixed>}
 */
function tokk_memberblog_get_management_access_context(): array
{
    $memberContext = function_exists('lutwiyo_get_member_access_context')
        ? lutwiyo_get_member_access_context()
        : ['is_logged_in' => false, 'plan' => 'guest', 'uid' => ''];

    $status = 'unknown';
    if (($memberContext['is_logged_in'] ?? false) !== true) {
        $status = 'guest';
    } else {
        $plan = (string) ($memberContext['plan'] ?? 'unknown');
        if ($plan === 'paid') {
            $status = 'standard';
        } elseif ($plan === 'free') {
            $status = 'free';
        }
    }

    return [
        'status' => $status,
        'member_context' => is_array($memberContext) ? $memberContext : ['is_logged_in' => false, 'plan' => 'guest', 'uid' => ''],
    ];
}

/**
 * 会員ブログ管理UI向けのアクセス案内を描画する。
 */
function tokk_memberblog_render_management_access_notice(string $status): void
{
    if ($status === 'guest') {
        echo '<div class="memberblog-access-notice memberblog-access-notice--login">';
        echo '<p class="memberblog-access-notice__text">会員ブログの管理には会員ログインが必要です。</p>';
        echo '<div class="btn btnShaped btnBgColor memberblog-access-notice__button" data-shaped="auto-38">';
        echo '<a class="flex--cc btnLink" href="' . esc_url(home_url('/login/')) . '" aria-label="会員ログイン" title="会員ログイン">';
        echo '<p class="btnTtext">会員ログイン</p>';
        echo '<div class="btnArrow btnArrow--next" data-arrow="w-12"></div>';
        echo '</a>';
        echo '</div>';
        echo '</div>';

        return;
    }

    if ($status === 'free') {
        echo '<div class="memberblog-access-notice memberblog-access-notice--upgrade">';
        echo '<p class="memberblog-access-notice__text">会員ブログの管理はスタンダード会員限定です。スタンダード会員にアップグレードしてください。</p>';
        echo '<div class="btn btnShaped btnBgColor memberblog-access-notice__button" data-shaped="auto-38">';
        echo '<a class="flex--cc btnLink" href="' . esc_url(home_url('/plan-change-input/')) . '" aria-label="スタンダード会員にアップグレード" title="スタンダード会員にアップグレード">';
        echo '<p class="btnTtext">スタンダード会員にアップグレード</p>';
        echo '<div class="btnArrow btnArrow--next" data-arrow="w-12"></div>';
        echo '</a>';
        echo '</div>';
        echo '</div>';

        return;
    }

    echo '<div class="memberblog-access-notice memberblog-access-notice--unknown">';
    echo '<p class="memberblog-access-notice__text">会員状態を確認できません。時間をおいて再度お試しください。</p>';
    echo '</div>';
}

function tokk_memberblog_home_url(string $path = ''): string
{
    if (function_exists('home_url')) {
        return (string) home_url($path);
    }

    return $path;
}

function tokk_memberblog_build_url_with_query_arg(string $url, string $key, string $value): string
{
    if (function_exists('add_query_arg')) {
        return (string) add_query_arg($key, $value, $url);
    }

    $parts = wp_parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }
    $query[$key] = $value;

    $rebuilt = '';
    if (isset($parts['scheme'])) {
        $rebuilt .= $parts['scheme'] . '://';
    }
    if (isset($parts['user'])) {
        $rebuilt .= $parts['user'];
        if (isset($parts['pass'])) {
            $rebuilt .= ':' . $parts['pass'];
        }
        $rebuilt .= '@';
    }
    if (isset($parts['host'])) {
        $rebuilt .= $parts['host'];
    }
    if (isset($parts['port'])) {
        $rebuilt .= ':' . $parts['port'];
    }
    $rebuilt .= $parts['path'] ?? '';

    $queryString = http_build_query($query);
    if ($queryString !== '') {
        $rebuilt .= '?' . $queryString;
    }
    if (isset($parts['fragment'])) {
        $rebuilt .= '#' . $parts['fragment'];
    }

    return $rebuilt;
}

/**
 * マイページ向け会員ブログ一覧を描画する。
 *
 * @param array<int,array<string,mixed>> $items
 */
function tokk_memberblog_render_mypage_post_list(array $items, int $maxItems = 5): void
{
    $items = array_values(array_filter($items, static function ($item): bool {
        return is_array($item);
    }));

    if ($maxItems > 0) {
        $items = array_slice($items, 0, $maxItems);
    }

    if ($items === []) {
        echo '<p class="memberblog-mypage-empty">投稿はまだありません。</p>';
        return;
    }

    $listPageUrl = tokk_memberblog_get_list_page_url();
    $editorPageUrl = tokk_memberblog_get_editor_page_url();

    echo '<div class="cateSection__list memberblog-mypage-list">';
    foreach ($items as $item) {
        $postId = max(0, (int) ($item['id'] ?? 0));
        if ($postId <= 0) {
            continue;
        }

        $title = trim((string) ($item['title'] ?? ''));
        if ($title === '') {
            $title = '無題';
        }
        $titleEsc = esc_html($title);
        $statusRaw = trim((string) ($item['status'] ?? 'draft'));
        $moderationRaw = trim((string) ($item['moderation_status'] ?? 'normal'));
        $imageUrl = tokk_memberblog_get_public_card_image_url($item);
        $excerpt = trim((string) ($item['excerpt'] ?? ''));
        $updatedLabel = tokk_memberblog_format_updated_date_label((string) ($item['updated_at'] ?? ''));
        $editUrl = esc_url(tokk_memberblog_build_url_with_query_arg($editorPageUrl, 'edit_post', (string) $postId));
        $permalink = trim((string) ($item['permalink'] ?? ''));
        $publicUrl = ($statusRaw === 'publish' && $moderationRaw === 'normal' && $permalink !== '')
            ? esc_url($permalink)
            : '';
        $areaSlugs = isset($item['area_slugs']) && is_array($item['area_slugs']) ? $item['area_slugs'] : [];
        $tagSlugs = isset($item['tag_slugs']) && is_array($item['tag_slugs']) ? tokk_memberblog_filter_allowed_tag_slugs($item['tag_slugs']) : [];

        echo '<article class="blockBox blockBox--cate memberblog-mypage-card" data-boxBgColor="white">';
        echo '<div class="blockBox__thum">';
        echo '<a href="' . $editUrl . '" title="' . $titleEsc . '">';
        echo '<div class="thumImg__wrapper"><img class="thumImg" src="' . esc_url($imageUrl) . '" alt="' . $titleEsc . '" loading="lazy" /></div>';
        echo '</a>';
        echo '</div>';
        echo '<div class="blockBox__info">';
        if ($statusRaw === 'pending') {
            echo '<p class="memberblog-manage-card__notice memberblog-manage-card__notice--pending">' . esc_html(tokk_memberblog_get_pending_notice_message()) . '</p>';
        } elseif ($moderationRaw === 'withdrawn') {
            $moderationReason = trim((string) ($item['moderation_reason'] ?? ''));
            $withdrawnMessage = '公開できません。';
            if ($moderationReason !== '') {
                $withdrawnMessage .= ' 理由: ' . $moderationReason;
            }
            echo '<p class="memberblog-manage-card__notice memberblog-manage-card__notice--withdrawn">' . esc_html($withdrawnMessage) . '</p>';
        }
        echo '<a href="' . $editUrl . '" title="' . $titleEsc . '"><p class="fs--13 textHover__target blockTitle">' . $titleEsc . '</p></a>';
        if ($excerpt !== '') {
            echo '<p class="fontW--r textColor--footer blockSubText">' . esc_html($excerpt) . '</p>';
        }
        echo '<div class="blockBox__infoSub--list">';
        if ($updatedLabel !== '') {
            echo '<div class="blockBox__infoSub--target"><div class="icon iconTime"><div class="mask iconInner"></div></div><p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">' . esc_html($updatedLabel) . '</p></div>';
        }
        if ($areaSlugs !== []) {
            $primaryAreaSlug = trim((string) reset($areaSlugs));
            $primaryAreaName = tokk_memberblog_get_term_name_by_slug('area', $primaryAreaSlug);
            if ($primaryAreaName !== '') {
                echo '<div class="blockBox__infoSub--target"><div class="icon iconMap"><div class="mask iconInner"></div></div><p class="fontW--r textColor--footer blockBox__infoSub--text">' . esc_html($primaryAreaName) . '</p></div>';
            }
        }
        echo '</div>';
        if ($tagSlugs !== []) {
            echo '<ul class="hashList">';
            foreach (array_slice($tagSlugs, 0, 3) as $tagSlug) {
                $tagName = tokk_memberblog_get_term_name_by_slug('post_tag', (string) $tagSlug);
                if ($tagName === '') {
                    continue;
                }
                echo '<li class="hashTarget"><span class="hashLink"><p class="hashTarget--p">' . esc_html($tagName) . '</p></span></li>';
            }
            echo '</ul>';
        }
        echo '<div class="memberblog-mypage-card__actions">';
        echo '<a class="memberblog-mypage-card__action" href="' . $editUrl . '">編集する</a>';
        if ($publicUrl !== '') {
            echo '<a class="memberblog-mypage-card__action" href="' . $publicUrl . '">公開ページを見る</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '</article>';
    }
    echo '</div>';
    echo '<div class="btn btnShaped btnBgColor btnAll" data-shaped="145-38">';
    echo '<a class="flex--cc btnLink" href="' . esc_url($listPageUrl) . '" aria-label="会員ブログ一覧を見る" title="会員ブログ一覧を見る">';
    echo '<p class="btnTtext">すべてみる</p>';
    echo '<div class="btnArrow btnArrow--next" data-arrow="w-12"></div>';
    echo '</a>';
    echo '</div>';
}

/**
 * 会員ブログ公開一覧の表示コンテキストを組み立てる。
 *
 * @param array<string,mixed> $filters
 * @return array<string,mixed>
 */
function tokk_memberblog_get_public_posts_context_from_filters(array $filters): array
{
    $memberUid = isset($filters['member_uid']) ? sanitize_text_field((string) $filters['member_uid']) : '';
    $memberSlug = isset($filters['member_slug']) ? sanitize_text_field((string) $filters['member_slug']) : '';
    $areaSlug = isset($filters['area_slug']) ? sanitize_text_field((string) $filters['area_slug']) : '';
    $tagSlug = isset($filters['tag_slug']) ? sanitize_text_field((string) $filters['tag_slug']) : '';
    $isInvalidTagFilter = ($tagSlug !== '' && !tokk_memberblog_is_allowed_tag_slug($tagSlug));
    $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
    $perPage = isset($filters['per_page']) ? max(1, min(50, (int) $filters['per_page'])) : 20;

    if ($isInvalidTagFilter) {
        return [
            'status' => 'success',
            'items' => [],
            'total' => 0,
            'available_areas' => [],
            'available_tags' => [],
            'filters' => [
                'member_uid' => $memberUid,
                'member_slug' => $memberSlug,
                'area_slug' => $areaSlug,
                'tag_slug' => $tagSlug,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    $result = tokk_memberblog_fetch_public_posts([
        'member_uid' => $memberUid,
        'member_slug' => $memberSlug,
        'area_slug' => $areaSlug,
        'tag_slug' => $tagSlug,
        'page' => $page,
        'per_page' => $perPage,
    ]);

    if (!$result['success']) {
        return [
            'status' => 'error',
            'items' => [],
            'total' => 0,
            'filters' => [
                'member_uid' => $memberUid,
                'member_slug' => $memberSlug,
                'area_slug' => $areaSlug,
                'tag_slug' => $tagSlug,
                'page' => $page,
                'per_page' => $perPage,
            ],
            'error_message' => $result['error_message'] ?? '公開一覧を取得できませんでした。',
        ];
    }

    return [
        'status' => 'success',
        'items' => $result['items'],
        'total' => (int) $result['total'],
        'available_areas' => tokk_memberblog_prepare_public_filter_options(
            isset($result['available_areas']) && is_array($result['available_areas']) ? $result['available_areas'] : [],
            'area'
        ),
        'available_tags' => tokk_memberblog_prepare_public_filter_options(
            isset($result['available_tags']) && is_array($result['available_tags']) ? $result['available_tags'] : [],
            'post_tag'
        ),
        'filters' => [
            'member_uid' => $memberUid,
            'member_slug' => $memberSlug,
            'area_slug' => $areaSlug,
            'tag_slug' => $tagSlug,
            'page' => $page,
            'per_page' => $perPage,
        ],
    ];
}

/**
 * 会員ブログ公開一覧の表示コンテキストを組み立てる（クエリ文字列版）。
 *
 * @return array<string,mixed>
 */
function tokk_memberblog_get_public_posts_context(): array
{
    return tokk_memberblog_get_public_posts_context_from_filters([
        'member_uid' => isset($_GET['member_uid']) ? wp_unslash((string) $_GET['member_uid']) : '',
        'member_slug' => isset($_GET['member_slug']) ? wp_unslash((string) $_GET['member_slug']) : '',
        'area_slug' => isset($_GET['area_slug']) ? wp_unslash((string) $_GET['area_slug']) : '',
        'tag_slug' => isset($_GET['tag_slug']) ? wp_unslash((string) $_GET['tag_slug']) : '',
        'page' => isset($_GET['page']) ? (int) $_GET['page'] : 1,
        'per_page' => isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20,
    ]);
}

/**
 * スラッグからターム名を取得する（キャッシュ付き）。
 */
function tokk_memberblog_get_term_name_by_slug(string $taxonomy, string $slug): string
{
    static $cache = [];

    $normalizedTaxonomy = trim($taxonomy);
    $normalizedSlug = trim($slug);
    if ($normalizedTaxonomy === '' || $normalizedSlug === '') {
        return '';
    }

    $cacheKey = $normalizedTaxonomy . '::' . $normalizedSlug;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!function_exists('get_term_by') || !class_exists('WP_Term')) {
        $cache[$cacheKey] = $normalizedSlug;
        return $cache[$cacheKey];
    }

    $term = get_term_by('slug', $normalizedSlug, $normalizedTaxonomy);
    if (!($term instanceof WP_Term)) {
        $cache[$cacheKey] = $normalizedSlug;
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = trim((string) $term->name) !== '' ? (string) $term->name : $normalizedSlug;
    return $cache[$cacheKey];
}

/**
 * @param array<int,array{slug:string,label?:string,count:int}> $rawOptions
 * @return array<int,array{slug:string,label:string,count:int}>
 */
function tokk_memberblog_prepare_public_filter_options(array $rawOptions, string $taxonomy): array
{
    $prepared = [];
    foreach ($rawOptions as $option) {
        if (!is_array($option)) {
            continue;
        }

        $slug = trim((string) ($option['slug'] ?? ''));
        $count = max(0, (int) ($option['count'] ?? 0));
        if ($slug === '' || $count <= 0) {
            continue;
        }

        if ($taxonomy === 'post_tag' && !tokk_memberblog_is_allowed_tag_slug($slug)) {
            continue;
        }

        $label = trim((string) ($option['label'] ?? ''));
        if ($label === '' || $label === $slug) {
            $label = tokk_memberblog_get_term_name_by_slug($taxonomy, $slug);
        }

        $prepared[$slug] = [
            'slug' => $slug,
            'label' => $label !== '' ? $label : $slug,
            'count' => $count,
        ];
    }

    $prepared = array_values($prepared);
    usort($prepared, static function (array $left, array $right): int {
        return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    });

    return $prepared;
}

/**
 * @param array<string,mixed> $filters
 */
function tokk_memberblog_build_public_filter_url(array $filters, string $axis, string $slug = ''): string
{
    $query = [];

    $memberUid = trim((string) ($filters['member_uid'] ?? ''));
    $memberSlug = trim((string) ($filters['member_slug'] ?? ''));
    if ($memberSlug !== '') {
        $query['member_slug'] = $memberSlug;
    } elseif ($memberUid !== '') {
        $query['member_uid'] = $memberUid;
    }

    if ($axis === 'area' && $slug !== '') {
        $query['area_slug'] = $slug;
    } elseif ($axis === 'tag' && $slug !== '') {
        $query['tag_slug'] = $slug;
    }

    $url = tokk_memberblog_home_url('/memberblog-public-list/');
    if ($query === []) {
        return $url;
    }

    return $url . '?' . http_build_query($query);
}

/**
 * 会員ブログ公開カードの日付ラベルを生成する。
 */
function tokk_memberblog_format_updated_date_label(string $updatedAt): string
{
    $normalized = trim($updatedAt);
    if ($normalized === '') {
        return '';
    }

    try {
        $datetime = new DateTime($normalized);
    } catch (Exception $exception) {
        return '';
    }

    return $datetime->format('y.m.d');
}

/**
 * 会員ブログ公開カード画像URLを解決する。
 */
function tokk_memberblog_get_public_card_image_url(array $item): string
{
    $candidates = [
        isset($item['thumbnail_url']) ? (string) $item['thumbnail_url'] : '',
        isset($item['image_url']) ? (string) $item['image_url'] : '',
    ];

    foreach ($candidates as $candidate) {
        $url = trim($candidate);
        if ($url !== '') {
            return $url;
        }
    }

    $blocks = isset($item['blocks']) && is_array($item['blocks']) ? $item['blocks'] : [];
    foreach ($blocks as $block) {
        if (!is_array($block) || trim((string) ($block['type'] ?? '')) !== 'core/image') {
            continue;
        }

        $data = isset($block['data']) && is_array($block['data']) ? $block['data'] : [];
        $url = trim((string) ($data['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }
    }

    if (function_exists('home_url')) {
        return (string) home_url('/assets/img/common/tokk--noimage.jpg');
    }

    return '/assets/img/common/tokk--noimage.jpg';
}

/**
 * 会員ブログ公開一覧のお気に入り状態取得停止フラグの有効状態を返す。
 */
function tokk_memberblog_should_disable_public_list_favorite_state(): bool
{
    return function_exists('tokk_member_service_disable_memberblog_list_favorite_state')
        && tokk_member_service_disable_memberblog_list_favorite_state();
}

/**
 * 会員ブログ公開一覧カードのお気に入りボタン描画可否を返す。
 */
function tokk_memberblog_should_render_public_list_favorite_button(): bool
{
    return !tokk_memberblog_should_disable_public_list_favorite_state();
}

/**
 * 会員ブログ公開カード単体を描画する。
 *
 * @param array<string,mixed> $item
 * @param array<string,bool> $favoriteStates key: resource_id
 * @param array<string,mixed> $options
 */
function tokk_memberblog_render_public_post_card(array $item, array $favoriteStates = [], array $options = []): void
{
    $postId = max(0, (int) ($item['id'] ?? 0));
    $resourceId = (string) $postId;
    $isCompact = !empty($options['compact']);
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        $title = '無題';
    }

    $memberUid = trim((string) ($item['member_uid'] ?? ''));
    $memberSlug = trim((string) ($item['member_slug'] ?? ''));
    $memberDisplayName = trim((string) ($item['member_display_name'] ?? ''));
    if ($memberDisplayName === '') {
        $memberDisplayName = '会員';
    }
    $memberDisplayNameWithSuffix = $memberDisplayName === '会員' ? '会員' : $memberDisplayName . 'さん';
    $memberProfileImageUrl = trim((string) ($item['member_profile_image_url'] ?? ''));
    $updatedLabel = tokk_memberblog_format_updated_date_label((string) ($item['updated_at'] ?? ''));
    $imageUrl = tokk_memberblog_get_public_card_image_url($item);
    $detailUrl = $postId > 0 ? tokk_memberblog_home_url('/memberblog-public-detail/?post_id=' . $postId) : '';
    $memberUrl = $memberSlug !== '' ? tokk_memberblog_home_url('/memberblog-public-member-list/?member_slug=' . rawurlencode($memberSlug)) : '';

    $areaSlugs = isset($item['area_slugs']) && is_array($item['area_slugs']) ? $item['area_slugs'] : [];
    $tagSlugs = isset($item['tag_slugs']) && is_array($item['tag_slugs']) ? $item['tag_slugs'] : [];
    $tagSlugs = tokk_memberblog_filter_allowed_tag_slugs($tagSlugs);
    if ($isCompact) {
        $tagSlugs = array_slice($tagSlugs, 0, 2);
    }
    $shouldRenderFavoriteButton = tokk_memberblog_should_render_public_list_favorite_button();

    $favoriteActiveClass = '';
    $favoriteAriaPressed = 'false';
    if ($resourceId !== '' && isset($favoriteStates[$resourceId]) && $favoriteStates[$resourceId] === true) {
        $favoriteActiveClass = 'active';
        $favoriteAriaPressed = 'true';
    }

    $cardClass = 'blockBox blockBox--hash memberblog-public-card';
    if ($isCompact) {
        $cardClass .= ' memberblog-public-card--compact';
    }

    echo '<article class="' . esc_attr($cardClass) . '" data-boxBgColor="white">';
    echo '<div class="blockBox__thum">';
    if ($detailUrl !== '') {
        echo '<a href="' . esc_url($detailUrl) . '" title="' . esc_attr($title) . '">';
    }
    echo '<div class="thumImg__wrapper"><img class="thumImg" src="' . esc_url($imageUrl) . '" alt="' . esc_attr($title) . '" loading="lazy" width="1900" height="1270"></div>';
    if ($detailUrl !== '') {
        echo '</a>';
    }
    if ($resourceId !== '' && $shouldRenderFavoriteButton) {
        echo '<div class="favBtn js--favBtn ' . esc_attr($favoriteActiveClass) . '" role="button" data-id="' . esc_attr($resourceId) . '" data-resource-type="member_blog_post" data-resource-id="' . esc_attr($resourceId) . '" aria-pressed="' . esc_attr($favoriteAriaPressed) . '" tabindex="0">';
        echo '<svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20"><title>お気に入り</title><path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/></svg>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="blockBox__info no__xPadding">';
    if ($detailUrl !== '') {
        echo '<a href="' . esc_url($detailUrl) . '" title="' . esc_attr($title) . '"><p class="fs--13 textHover__target blockTitle">' . esc_html($title) . '</p></a>';
    } else {
        echo '<p class="fs--13 textHover__target blockTitle">' . esc_html($title) . '</p>';
    }

    echo '<div class="blockBox__infoSub--list memberblog-public-card__meta">';
    if (!empty($areaSlugs)) {
        $primaryAreaSlug = trim((string) $areaSlugs[0]);
        if ($primaryAreaSlug !== '') {
            $primaryAreaName = tokk_memberblog_get_term_name_by_slug('area', $primaryAreaSlug);
            $primaryAreaUrl = tokk_memberblog_home_url('/memberblog-public-area-list/?area_slug=' . rawurlencode($primaryAreaSlug));
            echo '<a class="textHoverWrapper blockBox__infoSub--target" href="' . esc_url($primaryAreaUrl) . '" title="' . esc_attr($primaryAreaName) . '">';
            echo '<div class="icon iconMap"><div class="mask iconInner"></div></div>';
            echo '<p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">' . esc_html($primaryAreaName) . '</p>';
            echo '</a>';
        }
    }
    echo '</div>';

    if ($tagSlugs !== []) {
        echo '<ul class="hashList memberblog-public-card__tags">';
        foreach ($tagSlugs as $tagSlugRaw) {
            $tagSlug = trim((string) $tagSlugRaw);
            if ($tagSlug === '') {
                continue;
            }
            $tagName = tokk_memberblog_get_term_name_by_slug('post_tag', $tagSlug);
            $tagUrl = tokk_memberblog_home_url('/memberblog-public-list/?tag_slug=' . rawurlencode($tagSlug));
            echo '<li class="hashTarget hashUser">';
            echo '<a class="textHoverWrapper hashLink" href="' . esc_url($tagUrl) . '" title="' . esc_attr($tagName) . '"><p class="textHover__target hashTarget--p">' . esc_html($tagName) . '</p></a>';
            echo '</li>';
        }
        echo '</ul>';
    }

    if ($updatedLabel !== '') {
        echo '<div class="fontEn date memberblog-public-card__date"><p class="textColor--textGray date--p">' . esc_html($updatedLabel) . '</p></div>';
    }

    if ($memberUid !== '' && $memberUrl !== '') {
        $userIconUrl = $memberProfileImageUrl !== '' ? $memberProfileImageUrl : (get_stylesheet_directory_uri() . '/assets/img/sample/thum--28.webp');
        echo '<a href="' . esc_url($memberUrl) . '" class="userInfo memberblog-public-card__member" title="' . esc_attr($memberDisplayNameWithSuffix) . 'の公開一覧">';
        echo '<div class="userInfo--thumb"><img src="' . esc_url($userIconUrl) . '" alt="' . esc_attr($memberDisplayName) . '"></div>';
        echo '<div class="userInfo--text">' . esc_html($memberDisplayNameWithSuffix) . '</div>';
        echo '</a>';
    }

    echo '</div>';
    echo '</article>';
}

/**
 * 会員ブログ公開詳細の主エリア情報を取得する。
 *
 * @param array<string,mixed> $item
 * @return array<string,mixed>
 */
function tokk_memberblog_get_primary_area_info(array $item): array
{
    $areaSlugs = isset($item['area_slugs']) && is_array($item['area_slugs']) ? $item['area_slugs'] : [];
    $primaryAreaSlug = trim((string) ($areaSlugs[0] ?? ''));
    if ($primaryAreaSlug === '') {
        return [];
    }

    if (!class_exists('TermModelHelper')) {
        return [];
    }

    $areaInfoList = TermModelHelper::get_terms_payload(
        'area',
        ['slug' => $primaryAreaSlug],
        ['recommended_area_and_articles_list', 'ad_list'],
        []
    );

    return is_array($areaInfoList[0] ?? null) ? $areaInfoList[0] : [];
}

/**
 * 会員ブログ公開詳細サイドバー用ランキング記事を取得する。
 *
 * @param array<string,mixed> $areaInfo
 * @return array<int,array<string,mixed>>
 */
function tokk_memberblog_get_sidebar_ranking_articles(array $areaInfo): array
{
    if (!class_exists('\\WordPressPopularPosts\\Query') || !class_exists('PostModelHelper')) {
        return [];
    }

    $queryArgs = [
        'post_type' => 'articles',
        'limit' => 5,
        'range' => defined('RANKING_RANGE') ? RANKING_RANGE : 'last7days',
    ];
    if (!empty($areaInfo['id'])) {
        $queryArgs['taxonomy'] = 'area';
        $queryArgs['term_id'] = (string) $areaInfo['id'];
    }

    $popular = new \WordPressPopularPosts\Query($queryArgs);
    $popularIds = [];
    foreach ($popular->get_posts() as $ranked) {
        $popularIds[] = (int) ($ranked->id ?? 0);
    }
    $popularIds = array_values(array_filter(array_unique($popularIds)));
    if (empty($popularIds)) {
        return [];
    }

    return PostModelHelper::get_posts_payload($popularIds, [], []);
}

/**
 * 会員ブログ公開詳細サイドバー向けカテゴリ一覧を返す。
 *
 * @return array<int,array<string,mixed>>
 */
function tokk_memberblog_get_sidebar_categories(): array
{
    global $global_area_category_info_list;
    global $header_category_info_list;

    if (is_array($global_area_category_info_list) && !empty($global_area_category_info_list)) {
        return $global_area_category_info_list;
    }

    return is_array($header_category_info_list) ? $header_category_info_list : [];
}

/**
 * @param array<int,mixed> $rows
 * @return array<int,int>
 */
function tokk_memberblog_extract_post_ids(array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        if (is_numeric($row)) {
            $ids[] = (int) $row;
            continue;
        }

        if (is_object($row)) {
            if (isset($row->ID) && is_numeric($row->ID)) {
                $ids[] = (int) $row->ID;
                continue;
            }

            if (isset($row->id) && is_numeric($row->id)) {
                $ids[] = (int) $row->id;
                continue;
            }

            continue;
        }

        if (!is_array($row)) {
            continue;
        }

        if (isset($row['ID']) && is_numeric($row['ID'])) {
            $ids[] = (int) $row['ID'];
            continue;
        }

        if (isset($row['id']) && is_numeric($row['id'])) {
            $ids[] = (int) $row['id'];
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

/**
 * 会員ブログ公開詳細サイドバー向け広告一覧を返す。
 *
 * @param array<string,mixed> $areaInfo
 * @return array<int,array<string,mixed>>
 */
function tokk_memberblog_get_sidebar_ad_list(array $areaInfo): array
{
    if (!class_exists('PostModelHelper')) {
        return [];
    }

    $adRows = isset($areaInfo['ad_list']) && is_array($areaInfo['ad_list']) ? $areaInfo['ad_list'] : [];
    if (empty($adRows) && function_exists('get_field')) {
        $optionRows = get_field('ad_list', 'option');
        if (is_array($optionRows)) {
            $adRows = $optionRows;
        }
    }

    $adIds = tokk_memberblog_extract_post_ids($adRows);
    if (empty($adIds)) {
        return [];
    }

    $currentJst = function_exists('get_current_jst') ? get_current_jst() : current_time('mysql');
    $adInfoList = PostModelHelper::get_posts_payload(
        [
            'post_type' => 'advertisement',
            'post__in' => $adIds,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'post__in',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'start_date',
                    'value' => $currentJst,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ],
                [
                    'key' => 'end_date',
                    'value' => $currentJst,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
            ],
        ],
        [],
        []
    );

    if (!is_array($adInfoList) || empty($adInfoList)) {
        return [];
    }

    $orderMap = [];
    foreach ($adIds as $index => $id) {
        $orderMap[(string) $id] = $index;
    }

    $filtered = array_values(array_filter($adInfoList, static function ($ad) use ($orderMap): bool {
        if (!is_array($ad) || !isset($ad['id'])) {
            return false;
        }
        return isset($orderMap[(string) $ad['id']]);
    }));

    usort($filtered, static function (array $a, array $b) use ($orderMap): int {
        $posA = $orderMap[(string) ($a['id'] ?? '')] ?? PHP_INT_MAX;
        $posB = $orderMap[(string) ($b['id'] ?? '')] ?? PHP_INT_MAX;
        return $posA <=> $posB;
    });

    return $filtered;
}

/**
 * 会員ブログ公開一覧を描画する。
 *
 * @param array<string,mixed> $context
 * @param array<string,mixed> $options
 */
function tokk_memberblog_render_public_list(array $context, array $options = []): void
{
    $showFilterForm = isset($options['show_filter_form']) && (bool) $options['show_filter_form'];
    $heading = isset($options['heading']) ? trim((string) $options['heading']) : '公開中の会員ブログ';
    $hideHeading = isset($options['hide_heading']) ? (bool) $options['hide_heading'] : false;
    $excludePostId = isset($options['exclude_post_id']) ? max(0, (int) $options['exclude_post_id']) : 0;
    $maxItems = isset($options['max_items']) ? max(0, (int) $options['max_items']) : 0;
    $emptyMessage = isset($options['empty_message']) ? trim((string) $options['empty_message']) : '公開中の会員ブログ投稿はありません。';
    $viewAllUrl = isset($options['view_all_url']) ? trim((string) $options['view_all_url']) : '';

    $filters = is_array($context['filters'] ?? null) ? $context['filters'] : [];
    $memberUid = (string) ($filters['member_uid'] ?? '');
    $memberSlug = (string) ($filters['member_slug'] ?? '');
    $areaSlug = (string) ($filters['area_slug'] ?? '');
    $tagSlug = (string) ($filters['tag_slug'] ?? '');
    $availableAreas = isset($context['available_areas']) && is_array($context['available_areas']) ? $context['available_areas'] : [];
    $availableTags = isset($context['available_tags']) && is_array($context['available_tags']) ? $context['available_tags'] : [];
    $activeAreaLabel = $areaSlug !== '' ? tokk_memberblog_get_term_name_by_slug('area', $areaSlug) : '';
    foreach ($availableAreas as $option) {
        if (!is_array($option) || trim((string) ($option['slug'] ?? '')) !== $areaSlug) {
            continue;
        }
        $activeAreaLabel = trim((string) ($option['label'] ?? '')) !== '' ? (string) $option['label'] : $activeAreaLabel;
        break;
    }
    $activeTagLabel = $tagSlug !== '' ? tokk_memberblog_get_term_name_by_slug('post_tag', $tagSlug) : '';
    foreach ($availableTags as $option) {
        if (!is_array($option) || trim((string) ($option['slug'] ?? '')) !== $tagSlug) {
            continue;
        }
        $activeTagLabel = trim((string) ($option['label'] ?? '')) !== '' ? (string) $option['label'] : $activeTagLabel;
        break;
    }

    if ($showFilterForm && ($availableAreas !== [] || $availableTags !== [] || $areaSlug !== '' || $tagSlug !== '')) {
        echo '<section class="memberblog-public-list-filter">';
        echo '<div class="memberblog-public-list-filter__header">';
        echo '<p class="memberblog-public-list-filter__label">絞り込み</p>';
        if ($areaSlug !== '' || $tagSlug !== '') {
            echo '<div class="memberblog-public-list-filter__active">';
            if ($areaSlug !== '') {
                echo '<span class="memberblog-public-list-filter__activeItem">エリア: ' . esc_html($activeAreaLabel !== '' ? $activeAreaLabel : $areaSlug) . '</span>';
            }
            if ($tagSlug !== '') {
                echo '<span class="memberblog-public-list-filter__activeItem">ハッシュタグ: ' . esc_html($activeTagLabel !== '' ? $activeTagLabel : $tagSlug) . '</span>';
            }
            echo '<a class="memberblog-public-list-filter__clear" href="' . esc_url(tokk_memberblog_build_public_filter_url($filters, 'clear')) . '">絞り込み解除</a>';
            echo '</div>';
        }
        echo '</div>';
        if ($availableAreas !== []) {
            echo '<div class="memberblog-public-list-filter__group">';
            echo '<p class="memberblog-public-list-filter__groupLabel">エリアで絞る</p>';
            echo '<ul class="memberblog-public-list-filter__chips">';
            foreach ($availableAreas as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $optionSlug = trim((string) ($option['slug'] ?? ''));
                if ($optionSlug === '') {
                    continue;
                }
                $optionLabel = trim((string) ($option['label'] ?? $optionSlug));
                $optionCount = max(0, (int) ($option['count'] ?? 0));
                $activeClass = $optionSlug === $areaSlug ? ' is-active' : '';
                echo '<li class="memberblog-public-list-filter__chipItem">';
                echo '<a class="memberblog-public-list-filter__chip' . esc_attr($activeClass) . '" href="' . esc_url(tokk_memberblog_build_public_filter_url($filters, 'area', $optionSlug)) . '">';
                echo '<span class="memberblog-public-list-filter__chipLabel">' . esc_html($optionLabel) . '</span>';
                echo '<span class="memberblog-public-list-filter__chipCount">' . esc_html((string) $optionCount) . '</span>';
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        if ($availableTags !== []) {
            echo '<div class="memberblog-public-list-filter__group">';
            echo '<p class="memberblog-public-list-filter__groupLabel">ハッシュタグで絞る</p>';
            echo '<ul class="memberblog-public-list-filter__chips">';
            foreach ($availableTags as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $optionSlug = trim((string) ($option['slug'] ?? ''));
                if ($optionSlug === '') {
                    continue;
                }
                $optionLabel = trim((string) ($option['label'] ?? $optionSlug));
                $optionCount = max(0, (int) ($option['count'] ?? 0));
                $activeClass = $optionSlug === $tagSlug ? ' is-active' : '';
                echo '<li class="memberblog-public-list-filter__chipItem">';
                echo '<a class="memberblog-public-list-filter__chip memberblog-public-list-filter__chip--tag' . esc_attr($activeClass) . '" href="' . esc_url(tokk_memberblog_build_public_filter_url($filters, 'tag', $optionSlug)) . '">';
                echo '<span class="memberblog-public-list-filter__chipLabel">#' . esc_html($optionLabel) . '</span>';
                echo '<span class="memberblog-public-list-filter__chipCount">' . esc_html((string) $optionCount) . '</span>';
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        echo '</section>';
    }

    if (($context['status'] ?? '') === 'error') {
        echo '<p class="memberblog-public-list__message memberblog-public-list__message--error">公開一覧を取得できませんでした。</p>';
        return;
    }

    $items = is_array($context['items'] ?? null) ? $context['items'] : [];
    if ($excludePostId > 0) {
        $items = array_values(array_filter($items, static function ($item) use ($excludePostId): bool {
            return (int) ($item['id'] ?? 0) !== $excludePostId;
        }));
    }
    if ($maxItems > 0) {
        $items = array_slice($items, 0, $maxItems);
    }

    if ($items === []) {
        echo '<p class="memberblog-public-list__message">' . esc_html($emptyMessage) . '</p>';
        return;
    }

    $resourceIds = array_values(array_filter(array_map(static function ($item): string {
        $id = (int) ($item['id'] ?? 0);
        return $id > 0 ? (string) $id : '';
    }, $items), static function (string $id): bool {
        return $id !== '';
    }));
    $favoriteStates = [];
    if (!tokk_memberblog_should_disable_public_list_favorite_state()
        && function_exists('lutwiyo_get_current_member_favorite_states_by_resource_ids')) {
        $favoriteStates = lutwiyo_get_current_member_favorite_states_by_resource_ids('member_blog_post', $resourceIds);
    }

    $compactCards = isset($options['compact_cards']) ? (bool) $options['compact_cards'] : false;

    echo '<section class="gridWide latestSection memberblog-page memberblog-page--wide memberblog-public-list">';
    if (!$hideHeading) {
        echo '<h2 class="title fs--22 memberblog-public-list__heading">' . esc_html($heading) . '</h2>';
    }
    echo '<div class="hashSection__list memberblog-public-list__items">';
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        tokk_memberblog_render_public_post_card($item, $favoriteStates, [
            'compact' => $compactCards,
        ]);
    }
    echo '</div>';
    if ($viewAllUrl !== '') {
        echo '<div class="btn btnShaped btnBgColor btnAll" data-shaped="145-38">';
        echo '<a class="flex--cc btnLink" href="' . esc_url($viewAllUrl) . '" title="すべてみる">';
        echo '<p class="btnTtext">すべてみる</p>';
        echo '<div class="btnArrow btnArrow--next" data-arrow="w-12"></div>';
        echo '</a>';
        echo '</div>';
    }
    echo '</section>';
}

/**
 * 会員ブログ公開一覧セクションをフィルタ指定で描画する。
 * 取得失敗時や0件時は、デフォルトでセクション自体を出さない。
 *
 * @param array<string,mixed> $filters
 * @param array<string,mixed> $options
 */
function tokk_memberblog_render_public_list_section_from_filters(array $filters, array $options = []): void
{
    if (!array_key_exists('compact_cards', $options)) {
        $options['compact_cards'] = true;
    }

    $context = tokk_memberblog_get_public_posts_context_from_filters($filters);
    $status = trim((string) ($context['status'] ?? ''));
    $items = is_array($context['items'] ?? null) ? $context['items'] : [];

    $renderOnError = isset($options['render_on_error']) ? (bool) $options['render_on_error'] : false;
    $renderWhenEmpty = isset($options['render_when_empty']) ? (bool) $options['render_when_empty'] : false;

    if ($status === 'error' && !$renderOnError) {
        return;
    }

    if ($items === [] && !$renderWhenEmpty) {
        return;
    }

    echo '<article class="articlePT articlePB memberblog-public-section" data-boxBgColor="body">';
    tokk_memberblog_render_public_list($context, $options);
    echo '</article>';
}
