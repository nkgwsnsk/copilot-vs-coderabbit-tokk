<?php
/**
 * 旧 /search/?search=xxx 形式を
 * 新 WordPress 標準検索URL（?s=xxx）へリダイレクト
 */

if (isset($_GET['search'])) {

    // search → s に変換
    $query_args = $_GET;
    $query_args['s'] = trim($query_args['search']);
    unset($query_args['search']);

    // クエリを再構築
    $query_string = http_build_query($query_args);

    // 新検索URL（トップ + ?s=）
    $redirect_url = home_url('/?' . $query_string);

    // 恒久リダイレクト
    wp_safe_redirect($redirect_url, 301);
    exit;
}
