<?php
/**
 * coupon.php (server-cron only)
 *
 * クーポンAPIを呼び出して、成功時のみ /wp-content/json/coupon.json に原子的に上書き保存。
 * - 認証: Bearer TOKEN（wp-config.php に COUPON_API_KEY を定義）
 * - エンドポイント: GET {COUPON_API_BASE}
 * - パラメータ: property_id
 * - エラー時: 上書きしない・ログを残さない（仕様）
 * - WP-CLI: wp coupon update
 *
 * 事前準備（wp-config.php）に定義済み
 */

if ( ! defined('ABSPATH') ) { exit; }

/** ========== 設定ヘルパ ========== */

/** APIベースURL */
function my_coupon_api_base() {
    if ( defined('COUPON_API_BASE') && COUPON_API_BASE ) {
        return COUPON_API_BASE;
    }
    return ''; // definedで指定しているので事実上このステップは実行されない。
}

/** 保存ファイルパス（filterで差し替え可） */
function my_coupons_json_path() {
    $default = WP_CONTENT_DIR . '/json/coupon.json';
    return apply_filters('my_coupons_json_path', $default);
}

/** ========== API呼び出し ========== */

/**
 * クーポン一覧を取得（成功: 生のJSON文字列 / 失敗: WP_Error）
 * @param array $args 追加クエリ（property_id / order_by など）
 * @return string|WP_Error
 */
function my_fetch_coupons_raw( array $args = [] ) {
    if ( ! defined('COUPON_API_KEY') || ! COUPON_API_KEY ) {
        return new WP_Error('coupon_key_missing', 'COUPON_API_KEY が未設定です（wp-config.php を確認）');
    }

    // 1) 呼び出し側の $args を基準に構築
    $params = $args;

    // 2) property_id が未指定なら、wp-config.php の COUPON_API_PROPERTY_ID を自動適用
    if ( ! isset($params['property_id']) && defined('COUPON_API_PROPERTY_ID') && COUPON_API_PROPERTY_ID !== '' ) {
        $params['property_id'] = COUPON_API_PROPERTY_ID;
    }

    // 3) フィルタで最終調整（外部から order_by などを注入したい場合に利用）
    $params = apply_filters('my_coupons_api_params', $params);

    // 4) 必要に応じていつでも有効化できるコメント（残しておきます）
    // if (!isset($params['order_by'])) { $params['order_by'] = 'start_date_desc'; }

    $endpoint = trailingslashit( my_coupon_api_base() );
    $url = add_query_arg( $params, $endpoint );

    $res = wp_remote_get( $url, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . COUPON_API_KEY,
            'Accept'        => 'application/json',
        ],
    ]);

    if ( is_wp_error($res) ) {
        return $res;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    if ( $code !== 200 || ! $body ) {
        return new WP_Error('coupon_http_error', 'HTTPステータスが200ではありません', ['code'=>$code]);
    }

    $decoded = json_decode($body, true);
    if ( ! is_array($decoded) || ! isset($decoded['status']) || $decoded['status'] !== 'success' ) {
        return new WP_Error('coupon_api_error', 'APIのstatusがsuccessではありません');
    }

    return $body; // 成功時は生JSONをそのまま返す
}

/** ========== 取得→保存（成功時のみ上書き） ========== */

/**
 * クーポンJSONを取得して保存（原子的置換）
 * @return array|WP_Error ['saved'=>true,'file'=>..., 'bytes'=>N] / WP_Error
 */
function get_coupons_json_api() {
    $raw = my_fetch_coupons_raw(/* $args = [] */);
    if ( is_wp_error($raw) ) {
        return $raw; // 失敗 → 上書きしない・ログもしない
    }

    $file = my_coupons_json_path();
    $dir  = dirname($file);

    if ( ! is_dir($dir) ) {
        wp_mkdir_p($dir);
    }

    $tmp   = $file . '.' . uniqid('tmp_', true);
    $bytes = file_put_contents($tmp, $raw, LOCK_EX);
    if ( $bytes === false ) {
        @unlink($tmp);
        return new WP_Error('coupon_write_failed', '一時ファイルの書き込みに失敗しました');
    }
    @chmod($tmp, 0644);

    if ( ! @rename($tmp, $file) ) {
        @unlink($tmp);
        return new WP_Error('coupon_rename_failed', 'coupons.json の置き換えに失敗しました');
    }

    return ['saved'=>true, 'file'=>$file, 'bytes'=>$bytes];
}

/** ========== WP-CLI: wp coupon update ========== */
if ( defined('WP_CLI') && WP_CLI ) {
    WP_CLI::add_command('coupon update', function() {
        $res = get_coupons_json_api();
        if ( is_wp_error($res) ) {
            $code = $res->get_error_code();          // 例: coupon_http_error / coupon_api_error
            $data = $res->get_error_data();          // 例: ['code' => 401]
            $http = (is_array($data) && isset($data['code'])) ? $data['code'] : 'unknown';
            WP_CLI::error( sprintf('[%s] HTTP:%s - %s', $code, $http, $res->get_error_message()) );
        } else {
            WP_CLI::success( sprintf('Saved coupons to %s (%d bytes)', $res['file'], $res['bytes']) );
        }
    });
}
