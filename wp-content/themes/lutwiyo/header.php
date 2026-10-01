<?php
// --------------------------------------
// 実装ルール
// --------------------------------------
// - すべてのページで共通で使うものは、functions.phpが読み込んでいるinc/global.phpの中で、変数名：global_XXXXで初期化してください。
// - ページ固有のものは、各テンプレートファイルで変数名：ブロックorパーツ名_XXXXで初期化してください。
// - ページ内のテンプレートは、データを取得するループと、テンプレートを出力するループを完全分離してください。

// --------------------------------------
// グローバルデータ
// --------------------------------------
// inc/global.php で `wp` フックに紐づけて初期化済み。
global $global_is_home;
// 現在のタームのデータ
global $global_queried_object;
// 現在areaの配下かどうか
global $global_is_area_context;
//  エリアの基盤データ
global $global_area_info_list;
// home.phpなどで再利用する場合があるため、グローバル変数として保持
global $global_header_area_info_list;
//  カテゴリの基盤データ
global $global_category_info_list;
// エリア配下のカテゴリ基盤データ(エリア=XXXが付与されている記事のカテゴリ一覧)
global $global_area_category_info_list;
// タグの基盤データ
global $global_tag_info_list;

// bodyクラス等の設定
list($body_class, $body_data_area, $data_area_attr) = lutwiyo_get_body_info();

?>
<?php //フェーズ2 グローバルヘッダーでの会員共通処理
// 使用API：ログイン判定API、ログインAPI
// 未ログイン(not_login)、スタンダード会員(standard)、フリー会員(free)を取得する

// 初期表示は未ログインとして扱う。
$is_rank = 'not_login';
// 会員名は取得できるまでデフォルト表示にする。
$header_user_display_name = '会員';
$header_renewal_status = '';
// 現在URLを組み立て、ログイン導線の戻り先としてCookieへ保持する。
$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);
$headerReturnToUrl = remove_query_arg(['tokk_member_login', 'tokk_member_regist'], $currentUrlForReturn);
$headerReturnToCookieName = 'tokk_member_return_to';
$headerReturnToCookieTtl = time() + 1800;
$currentPath = untrailingslashit((string) wp_parse_url($headerReturnToUrl, PHP_URL_PATH));
$currentRequestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$currentRequestAccept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

$isValidHeaderReturnToPath = static function (string $path): bool {
    if ($path === '' || $path === '/') {
        return true;
    }

    if (in_array($path, ['/login', '/regist'], true)) {
        return false;
    }

    // 静的アセットや会員基盤API配下は復帰先に含めない。
    if ((bool) preg_match('#^/(wp-content|wp-includes|wp-admin|member-service)(/|$)#', $path)) {
        return false;
    }

    // 末尾が拡張子のリクエストは画像・CSS・JS等の可能性が高いため除外する。
    if ((bool) preg_match('/\.[a-z0-9]{2,8}$/i', $path)) {
        return false;
    }

    return true;
};

// 戻り先CookieはHTML画面のGETリクエストに限定して更新する。
$isHtmlLikeRequest = $currentRequestAccept === ''
    || str_contains($currentRequestAccept, 'text/html')
    || str_contains($currentRequestAccept, 'application/xhtml+xml');
$canRefreshReturnToCookie = $currentRequestMethod === 'GET'
    && $isHtmlLikeRequest
    && $isValidHeaderReturnToPath($currentPath);

if ($canRefreshReturnToCookie) {
    setcookie($headerReturnToCookieName, $headerReturnToUrl, lutwiyo_cookie_options($headerReturnToCookieTtl));
}

$headerReturnToFromCookie = '';
if (isset($_COOKIE[$headerReturnToCookieName])) {
    $cookieValue = sanitize_text_field(wp_unslash($_COOKIE[$headerReturnToCookieName]));
    $validatedCookieUrl = wp_validate_redirect($cookieValue, '');
    $validatedCookiePath = untrailingslashit((string) wp_parse_url($validatedCookieUrl, PHP_URL_PATH));
    if ($validatedCookieUrl !== '' && $isValidHeaderReturnToPath($validatedCookiePath)) {
        $headerReturnToFromCookie = $validatedCookieUrl;
    }
}

// ヘッダーの新規登録・ログインは案内画面へ遷移する（戻り先はCookieで保持）。
$header_regist_url = home_url('/regist/');
$header_login_url = home_url('/login/');
$header_member_menu_groups = [
    [
        'key' => 'mypage',
        'label' => 'マイページ',
        'url' => home_url('/mypage/'),
        'children' => [],
    ],
    [
        'key' => 'member_features',
        'label' => '会員機能',
        'url' => home_url('/mypage/'),
        'children' => [
            ['label' => '会員情報編集', 'url' => home_url('/mypage-edit/')],
            ['label' => 'お支払い履歴', 'url' => home_url('/mypage/')],
        ],
    ],
    [
        'key' => 'blog',
        'label' => 'ブログ',
        'url' => home_url('/memberblog-list/'),
        'children' => [
            ['label' => 'ブログを書く', 'url' => home_url('/member/blog/')],
            ['label' => '投稿したブログの一覧', 'url' => home_url('/memberblog-list/')],
        ],
    ],
    [
        'key' => 'member_benefits',
        'label' => '会員特典',
        'url' => lutwiyo_get_member_benefit_entry_url(home_url('/coupon/')),
        'children' => [
            ['label' => 'クーポン', 'url' => lutwiyo_get_member_benefit_entry_url(home_url('/coupon/'))],
            ['label' => 'プレゼント', 'url' => lutwiyo_get_member_benefit_entry_url(home_url('/present/'))],
            ['label' => 'ギャラリー', 'url' => lutwiyo_get_member_benefit_entry_url(home_url('/gallery/'))],
        ],
    ],
];
$header_logout_form_action = home_url('/mypage/');
$header_logout_form_nonce = wp_create_nonce('tokk_mypage_action');
$header_search_member_access_plan = isset($_GET['member_access_plan'])
    ? sanitize_key(wp_unslash((string) $_GET['member_access_plan']))
    : '';
// $is_login_debug = isset($_GET['tokk_member_login_debug']) && $_GET['tokk_member_login_debug'] === '1';

// ログインボタンクリック時は、ログイン開始APIを呼んで外部認証画面へ遷移する。
if (isset($_GET['tokk_member_login']) && $_GET['tokk_member_login'] === '1') {
    $currentUrl = $headerReturnToUrl;
    if ($headerReturnToFromCookie !== '') {
        $currentUrl = remove_query_arg(['tokk_member_login', 'tokk_member_regist'], $headerReturnToFromCookie);
    }

    // ブリッジAPI側で必要な.env設定を読むため、WordPress側は戻り先URLのみを渡す。
    $loginApiResponse = lutwiyo_call_bridge_api('login', [
        'current_url' => $currentUrl,
    ]);

    // if ($is_login_debug) {
    //     header('Content-Type: text/plain; charset=UTF-8');
    //     print_r([
    //         'request_path' => '/member-service/api/v1/bridge/auth/login',
    //         'current_url' => $currentUrl,
    //         'response' => $loginApiResponse,
    //     ]);
    //     exit;
    // }

    if (is_array($loginApiResponse) && ($loginApiResponse['body']['result'] ?? '') === 'redirect') {
        $redirectTo = (string) ($loginApiResponse['body']['redirect_to'] ?? '');
        if ($redirectTo !== '') {
            wp_redirect($redirectTo);
            exit;
        }
    }

    // ログイン開始に失敗した場合は同じクエリでの再試行ループを防ぐため、トリガークエリを外して元画面へ戻す。
    if (is_array($loginApiResponse)) {
        error_log(sprintf(
            '[tokk_member_bridge] login start failed. status=%s result=%s reason=%s',
            (string) ($loginApiResponse['status'] ?? ''),
            (string) ($loginApiResponse['body']['result'] ?? ''),
            (string) ($loginApiResponse['body']['reason'] ?? '')
        ));
    } else {
        error_log('[tokk_member_bridge] login start failed. response is null.');
    }

    wp_safe_redirect($currentUrl);
    exit;
}

// 新規会員登録ボタンクリック時は、登録開始APIを呼んで外部認証画面へ遷移する。
if (isset($_GET['tokk_member_regist']) && $_GET['tokk_member_regist'] === '1') {
    $currentUrl = $headerReturnToUrl;
    if ($headerReturnToFromCookie !== '') {
        $currentUrl = remove_query_arg(['tokk_member_login', 'tokk_member_regist'], $headerReturnToFromCookie);
    }

    $registApiResponse = lutwiyo_call_bridge_api('regist', [
        'current_url' => $currentUrl,
    ]);

    if (is_array($registApiResponse) && ($registApiResponse['body']['result'] ?? '') === 'redirect') {
        $redirectTo = (string) ($registApiResponse['body']['redirect_to'] ?? '');
        if ($redirectTo !== '') {
            wp_redirect($redirectTo);
            exit;
        }
    }

    if (is_array($registApiResponse)) {
        error_log(sprintf(
            '[tokk_member_bridge] regist start failed. status=%s result=%s reason=%s',
            (string) ($registApiResponse['status'] ?? ''),
            (string) ($registApiResponse['body']['result'] ?? ''),
            (string) ($registApiResponse['body']['reason'] ?? '')
        ));
    } else {
        error_log('[tokk_member_bridge] regist start failed. response is null.');
    }

    wp_safe_redirect($currentUrl);
    exit;
}

// 会員情報APIを事実源として、表示に使う会員ランクを決定する。
// 一時的にmember-infoが失敗した場合は login-status を再実行してから
// member-info を再試行し、会員ランクの復元可能性を高める。
$memberInfoResponse = function_exists('lutwiyo_get_member_info_response_with_login_status_retry')
    ? lutwiyo_get_member_info_response_with_login_status_retry()
    : lutwiyo_get_or_fetch_member_info_response();
$shouldLoadOneTrust = lutwiyo_is_onetrust_enabled_host();

if (is_array($memberInfoResponse) && ($memberInfoResponse['body']['result'] ?? '') === 'ok') {
    // ログイン中は最低でもフリー会員として扱う。
    $is_rank = 'free';
    $header_user_display_name = '会員';

    $localMember = $memberInfoResponse['body']['local_member'] ?? [];
    if (is_array($localMember)) {
        $header_renewal_status = trim((string) ($localMember['renewal_status'] ?? ''));
    }
    if (is_array($localMember) && (int) ($localMember['member_rank_id'] ?? 0) === 2) {
        $is_rank = 'standard';
    }

    $currentMemberProfileSummary = function_exists('lutwiyo_get_current_member_profile_summary')
        ? lutwiyo_get_current_member_profile_summary()
        : ['nickname' => ''];
    $nickname = (string) ($currentMemberProfileSummary['nickname'] ?? '');
    $fallbackNickname = is_array($localMember)
        ? (string) ($localMember['nickname'] ?? '')
        : '';

    if (function_exists('lutwiyo_has_non_whitespace_text') && lutwiyo_has_non_whitespace_text($nickname)) {
        $header_user_display_name = trim($nickname);
    } elseif (function_exists('lutwiyo_has_non_whitespace_text') && lutwiyo_has_non_whitespace_text($fallbackNickname)) {
        $header_user_display_name = trim($fallbackNickname);
    }
} else {
    // 再試行後も member-info が取得できない場合は login-status を最終フォールバックとし、
    // ログインボタンの誤表示を防ぎつつログイン状態判定を厳格に維持する。
    $loginStatusResponse = lutwiyo_get_or_fetch_login_status_response();
    $isLoggedInViaStatus = is_array($loginStatusResponse)
        && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';

    if ($isLoggedInViaStatus) {
        $is_rank = 'free';
        $header_user_display_name = '会員';
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <?php if ($shouldLoadOneTrust): ?>
    <!-- Google Tag Manager -->
    <script>(function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start':
                    new Date().getTime(), event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-KHS6M2C');</script>
    <?php endif; ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7889233348174815"
            crossorigin="anonymous"></script>
    <!-- End Google Tag Manager -->
    <?php
    $term = $global_queried_object;
    // lutwiyo_debug($term);
    // lutwiyo_debug($global_queried_object);

    // カレントエリアの抽出と設定
    $header_area_info_list = TermModelHelper::apply_term_transforms($global_area_info_list, array(
        [
            'source' => 'slug',
            'callback' => function ($slug) use ($global_queried_object) {
                return ($global_queried_object && $global_queried_object->slug === $slug) ? true : false;
            },
            'target' => 'is_current_area'
        ]
    ));

    // クリッカブルなもののみ抽出した配列に再加工
    $header_area_info_list = array_filter($header_area_info_list, function ($area) {
        return !empty($area['is_clickable']);
    });
    $global_header_area_info_list = $header_area_info_list;

    ?>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, user-scalable=yes, maximum-scale=1.0, minimum-scale=1.0">
    <meta name="format-detection" content="telephone=no">

    <!-- webfont -->
    <!-- google fonts (zen Kaku Gothic New)  -->
    <link rel="preconnect" href="//fonts.googleapis.com">
    <link rel="preconnect" href="//fonts.gstatic.com" crossorigin>
    <link href="//fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@300;400;500;700;900&display=swap"
          rel="stylesheet">

    <!-- font-plus (general sans m/sb)  -->
    <link href="//api.fontshare.com/v2/css?f[]=general-sans@500,600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/yakuhanjp@3.3.1/dist/css/yakuhanjp.min.css">
    <?php foreach (get_css_file('style') as $css_href): ?>
        <link rel="stylesheet" type="text/css" href="<?= esc_attr($css_href); ?>" media="screen,print"/>
    <?php endforeach; ?>

    <style>
        :root {
            --color-body: 242, 240, 231;
            --color-bodySub: 238, 234, 222;
            --color-footer: 118, 125, 130;
            --color-btnBg: 79, 79, 79;
            --color-text: 58, 58, 58;
            --color-textGray: 118, 118, 118;
            --color-border: 204, 204, 204;

            --color-pageDetail-box: 231, 227, 211;
        }

        /* ================================================
        1) 各エリアのカラーパレット
        ================================================ */
        :root {
            --color-key: #fe766b;
            --color-area-all: #fe766b;
        <?php foreach ($header_area_info_list as $area): ?> --color-area-<?php echo esc_html($area['slug']); ?>: <?php echo esc_html($area['color']); ?>;
        <?php endforeach; ?>
        }

        /* ================================================
        2) data-area に応じて --area-color を割り当て
        ================================================ */
        [data-area="all"] {
            --area-color: var(--color-area-all);
        }

        <?php foreach ($header_area_info_list as $area): ?>
        [data-area="<?php echo esc_attr($area['slug']); ?>"] {
            --area-color: var(--color-area-<?php echo esc_attr($area['slug']); ?>);
        }

        <?php endforeach; ?>

        .globalNav__targetModal--member {
            overflow-y: auto;
            display: flex;
            justify-content: center;
        }

        .globalNav__target--member .globalNav__targetIcon {
            display: none;
        }

        .memberNav {
            display: flex;
            align-items: flex-start;
            gap: 28px;
            margin: 0;
            padding: 4px 0;
            list-style: none;
            flex-wrap: nowrap;
            width: fit-content;
            min-width: auto;
        }

        .memberNav__group {
            margin: 0;
            min-width: 130px;
        }

        .memberNav__groupLink,
        a.memberNav__groupLink {
            display: inline-block;
            margin: 0;
            font-size: 14px;
            line-height: 1.4;
            font-weight: 700;
            color: rgba(var(--color-text), 1);
            text-decoration: none;
        }

        .memberNav__children {
            margin: 10px 0 0;
            padding: 0 0 0 14px;
            list-style: none;
        }

        .memberNav__childItem {
            margin: 0 0 8px;
        }

        .memberNav__childItem:last-child {
            margin-bottom: 0;
        }

        .memberNav__childLink,
        a.memberNav__childLink {
            display: inline-block;
            margin: 0;
            font-size: 13px;
            line-height: 1.4;
            color: rgba(var(--color-textGray), 1);
            text-decoration: none;
        }

        .memberNav__logoutForm {
            display: inline;
            margin: 0;
        }

        .memberNav__logoutButton {
            display: inline-block;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: rgba(var(--color-textGray), 1);
            font-size: 13px;
            font-weight: 400;
            line-height: 1.4;
            cursor: pointer;
            text-decoration: none;
        }

        .memberNavSp__children {
            margin: 6px 0 0;
            padding: 0 0 0 14px;
            list-style: none;
        }

        .memberNavSp__childItem {
            margin: 0 0 4px;
        }

        .memberNavSp__childItem:last-child {
            margin-bottom: 0;
        }

        .memberNavSp__logoutForm {
            margin: 10px 0 0;
            padding: 0;
        }

        button.memberNavSp__logoutButton {
            margin: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: rgba(var(--color-text), 1);
            text-align: left;
            width: 100%;
            padding: 0;
            line-height: 1.6;
        }

        @media only screen and (max-width: 767px) {
            .caution--p {
                overflow: hidden;
                white-space: nowrap;
            }

            .caution--p.is--marquee-base {
                text-align: left;
            }

            .caution--p .caution--pText {
                display: inline-block;
                transform: translateX(0);
                will-change: transform;
            }

            .caution--p.is--marquee .caution--pText {
                animation: cautionMarquee var(--caution-marquee-duration, 12s) linear infinite;
            }

            .navToggle__innerList--member {
                display: block;
                padding-top: 10px;
            }

            .navToggle__innerList--member .navToggle__target {
                width: 100%;
            }

            .navToggle__innerList--member .navToggle__target--memberGroup {
                margin-bottom: 12px;
            }

            .navToggle__innerList--member .navToggle__target--memberGroup:last-child {
                margin-bottom: 0;
            }


            .navToggle__link--memberGroup {
                font-size: 16px;
                font-weight: 700;
                line-height: 1.6;
            }

            .navToggle__link--memberChild {
                font-size: 15px;
                color: rgba(var(--color-textGray), 1);
                text-indent: 0;
                line-height: 1.6;
            }

            button.memberNavSp__logoutButton {
                font-size: 13px;
                font-weight: 400;
                line-height: 1.6;
            }
        }

        @media only screen and (min-width: 768px) {
            .caution.is--message-shifted .cautionInner {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding-top: 8px;
                padding-bottom: 8px;
            }

            .caution.is--message-shifted .caution__mark {
                position: relative;
                top: auto;
                left: auto;
                transform: none;
            }

            .caution.is--message-shifted .caution--p {
                position: relative;
                top: auto;
                bottom: auto;
                left: auto;
                transform: none;
                max-width: calc(100% - 24px);
                white-space: normal;
                line-height: 1.35;
            }
        }

        @keyframes cautionMarquee {
            0% {
                transform: translateX(var(--caution-marquee-from, 0px));
            }
            100% {
                transform: translateX(var(--caution-marquee-to, 0px));
            }
        }


    </style>
    <!-- Plugin scripts -->
    <script src="/assets/js/bundle.js"></script>

    <!-- Custom script -->
    <script src="/assets/js/common.js" defer></script>

    <?php wp_head(); ?>
</head>

<body class="<?= $body_class; ?>" <?= $data_area_attr; ?>>
<?php if ($shouldLoadOneTrust): ?>
<!-- Google Tag Manager (noscript) -->
<noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KHS6M2C"
            height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
<!-- End Google Tag Manager (noscript) -->
<?php endif; ?>
<?php
// --------------------------------------
// ヘッダ情報
// --------------------------------------
?>
<?php
// --------------------------------------
// header-01 エリアで探す
// 注意点：
// - グローバルトップ・エリアトップごとにハイライトしている位置が異なるので、この部分は都度加工して取得しています。
//--------------------------------------
// $term = get_queried_object();
// カレントエリアの抽出と設定
// $header_area_info_list = TermModelHelper::apply_term_transforms($global_area_info_list, array(
//     [
//         'source' => 'slug',
//         'callback' => function ($slug) use ($term) {
//             return ($term && $term->slug === $slug) ? true : false;
//         },
//         'target' => 'is_current_area'
//     ]
// ));

// クリッカブルなもののみ抽出した配列に再加工
// $header_area_info_list = array_filter($header_area_info_list, function ($area) {
//     return !empty($area['is_clickable']);
// });
// lutwiyo_debug('$header_area_info', $header_area_info_list);
?>
<?php // TODO 以下のロジックをテンプレートに適用してください。
// TODO エリアで探すで出力している文字列が、shortnameフィールドの内容になっているか確認しておく
?>
<?php foreach ($header_area_info_list as $area): ?>
    <?php
    //    =========== header-01 エリアで探す ===========
    //    エリア名： $area['name']
    //    リンク先URL：$area['slug']
    //    data-area属性：$area['slug']
    //    エリアカラー：$area['color']
    //    一覧への表示：$area['is_clickable']
    //    XのURL：$area['url_x']
    //    FacebookページのURL：$area['url_facebook']
    //    エリア画像：$area['image_url']
    //    echo var_dump($area);
    ?>
<?php endforeach; ?>
<?php

// --------------------------------------
// header-02 カテゴリーから探す
//--------------------------------------
// カレントエリアの抽出と設定
global $header_category_info_list;
$header_category_info_list = array_filter(
    $global_category_info_list,
    function ($term) {
        // 無効な要素を排除
        if (!is_array($term)) {
            return false;
        }

        // 「タイアップ」「未分類」は除外
        if (in_array($term['name'] ?? '', ['タイアップ', '未分類'], true)) {
            return false;
        }

        // 投稿数が0件のカテゴリも除外
        if (empty($term['count']) || intval($term['count']) === 0) {
            return false;
        }

        // すべての条件を通過した要素のみ残す
        return true;
    }
);

// lutwiyo_debug('$header_category_info', $header_category_info_list);
?>
<?php
// TODO 以下のロジックをテンプレートに適用してください。
// TODO カテゴリーに遷移するリンクは、横断トップとエリア配下で異なるため、出しわけが必要です。
?>
<?php foreach ($header_category_info_list as $category): ?>
    <?php
    //    =========== header-02 カテゴリーから探す ===========
    // カテゴリー名：$category['name']
    // リンク先URL：/category/$category['slug']
    ?>
<?php endforeach; ?>

<?php
// --------------------------------------
// header-03 複合検索UI
// --------------------------------------
?>
<?php
// --------------------------------------
// header-04 一番右端SNSアイコンクリック時のUI
// --------------------------------------
?>
<?php // TODO 以下のロジックをテンプレートに適用してください。?>
<?php foreach ($header_area_info_list as $area): ?>
    <?php
    // =========== header-04 一番右端SNSアイコンクリック時のUI ===========
    // エリア名：$area['name']
    // FacebookのURL：$area['url_facebook']
    // XのURL：$area['url_x']);
    ?>
<?php endforeach; ?>
<?php
// --------------------------------------
// ヘッダのカテゴリーナビゲーション
// --------------------------------------
?>

<?php // TODO $global_is_home を使ってトップページか否かを判定し、トップの場合はカレント表示にしてください。 ?>
<?php
// 総合トップページかどうか：$global_is_home
?>
<?php // TODO 以下のロジックをテンプレートに適用してください。?>
<?php foreach ($header_area_info_list as $area): ?>
    <?php
    // エリア名：$area['name']
    // エリアのスラッグ(リンク先URLの合成、data-area属性で利用)：$area['slug']
    // 現在表示中のエリアか否か：$area['is_current_area']
    ?>
<?php endforeach; ?>

<!-- test -->

<!--
    coding memo

        スクロール後設定
            body addClass 'is--scroll'

        sp下部メニュー非表示設定
            body addClass 'is--headerMain--hidden'

        エリアで探す カテゴリーから探す　hover 時
            body addClass 'is--hoverCover--show'
            js--hoverTarget addClass 'active'

        js--searchForm クリック
            body addClass 'is--hoverCover--show is--searchForm--show'
            js--searchForm  addClass 'active'

        エリアページのbody add class
            page-area-(エリア名) 例 'page-area-suita'


-->
<!-- ==================================================================== ↓ common ↓ -->
<!-- top用 <h1>タグ  -->

<!-- z:60 -->
<?php
$yoast_site_name = '';

if (function_exists('YoastSEO')) {
    $yoast_site_name = (string) YoastSEO()->meta->for_current_page()->site_name;
}

// フォールバック（Yoastが無い/空のときはWPのサイト名）
if ($yoast_site_name === '') {
    $yoast_site_name = get_bloginfo('name');
}
?>
<?php if($global_is_home): ?>
<h1 class="globalLogo header__globalLogo js--headerScroll">
    <a class="flex--cc maskBgColor__hoverWrapper globalLogo__inner" href="/"
       title="<?= esc_attr($yoast_site_name); ?>" role="link">
        <p class="globalLogo__mark"><span class="mask mask__bgColor--text globalLogo__markInner"><?= esc_attr($yoast_site_name); ?></span>
        </p>
    </a>
</h1>
<?php else: ?>
    <div class="globalLogo header__globalLogo js--headerScroll">
        <a class="flex--cc maskBgColor__hoverWrapper globalLogo__inner" href="/"
           title="<?= esc_attr($yoast_site_name); ?>" role="link">
            <p class="globalLogo__mark"><span class="mask mask__bgColor--text globalLogo__markInner"><?= esc_attr($yoast_site_name); ?></span>
            </p>
        </a>
    </div>
<?php endif; ?>

<!-- 全サイト共通 header  -->
<div class="caution">
    <div class="cautionInner">
        <div class="caution__mark"><p class="caution__mark--p">WEB版「TOKK」が「TOKK関西」にリニューアル</p></div>
        <?php //フェーズ2 会員ランクにより表示内容を変える ?>
        <?php if($is_rank == "free" && $header_renewal_status === '失敗'): ?>
            <p class="fontW--r caution--p"><a href="<?= esc_url(home_url('/paid-exp/')); ?>"><span class="caution--pText">定期課金処理に失敗しました。スタンダード会員に復帰する場合は手動で再度決済を行なってください。</span></a></p>
        <?php elseif($is_rank == "free"): ?>
            <p class="fontW--r caution--p"><span class="caution--pText">スタンダード会員（有料）になると有料記事が読み放題になります！</span></p>
        <?php elseif($is_rank == "standard"): ?>
            <p class="fontW--r caution--p"><span class="caution--pText">コメント記入やブログ投稿でTOKK関西を一緒に盛り上げよう！</span></p>
        <?php else: ?>
            <p class="fontW--r caution--p"><span class="caution--pText">フリー（無料）会員になるとプレゼントの応募やお得なクーポンの利用ができるようになります！</span></p>
        <?php endif; ?>
    </div>
</div>
<script>
    (function () {
        const mobileMedia = window.matchMedia('(max-width: 767px)');
        const desktopMedia = window.matchMedia('(min-width: 768px)');
        const cautionMessages = document.querySelectorAll('.caution--p');
        const cautionContainer = document.querySelector('.caution');
        const cautionInner = document.querySelector('.cautionInner');
        const cautionMark = document.querySelector('.caution__mark');

        if (!cautionMessages.length) {
            return;
        }

        const updateCautionMarquee = function () {
            cautionMessages.forEach(function (message) {
                const textNode = message.querySelector('.caution--pText');
                if (!textNode) {
                    return;
                }

                message.classList.remove('is--marquee');
                message.classList.remove('is--marquee-base');
                message.style.removeProperty('--caution-marquee-from');
                message.style.removeProperty('--caution-marquee-to');
                message.style.removeProperty('--caution-marquee-duration');

                if (!mobileMedia.matches) {
                    return;
                }

                message.classList.add('is--marquee-base');
                const overflow = textNode.scrollWidth - message.clientWidth;
                if (overflow <= 0) {
                    message.classList.remove('is--marquee-base');
                    return;
                }

                const travelDistance = textNode.scrollWidth + message.clientWidth;
                const durationSec = Math.min(Math.max(travelDistance / 60, 8), 24);
                message.style.setProperty('--caution-marquee-from', message.clientWidth + 'px');
                message.style.setProperty('--caution-marquee-to', '-' + textNode.scrollWidth + 'px');
                message.style.setProperty('--caution-marquee-duration', durationSec + 's');
                message.classList.add('is--marquee');
            });
        };

        const updateDesktopCautionLayout = function () {
            const rootStyle = document.documentElement.style;
            const desktopStackBreakpoint = 1250;

            if (!desktopMedia.matches || !cautionContainer || !cautionInner || !cautionMark) {
                if (cautionContainer) {
                    cautionContainer.classList.remove('is--message-shifted');
                }
                rootStyle.removeProperty('--caution-height');
                return;
            }

            rootStyle.setProperty('--caution-height', '45px');

            const message = cautionInner.querySelector('.caution--p');
            const textNode = message ? message.querySelector('.caution--pText') : null;

            if (!message || !textNode) {
                cautionContainer.classList.remove('is--message-shifted');
                return;
            }

            cautionContainer.classList.remove('is--message-shifted');
            const markWidth = cautionMark.getBoundingClientRect().width;
            const innerWidth = cautionInner.getBoundingClientRect().width;
            const safetyGap = 24;
            const availableInlineWidth = Math.max(innerWidth - markWidth - safetyGap, 0);
            const requiredInlineWidth = textNode.scrollWidth;
            const shouldShiftByWidth = window.innerWidth < desktopStackBreakpoint;
            const shouldShiftByOverflow = requiredInlineWidth > availableInlineWidth;
            const shouldShiftMessage = shouldShiftByWidth || shouldShiftByOverflow;

            if (!shouldShiftMessage) {
                return;
            }

            cautionContainer.classList.add('is--message-shifted');
            const markHeight = cautionMark.getBoundingClientRect().height;
            const messageHeight = message.getBoundingClientRect().height;
            const stackedHeight = Math.ceil(markHeight + messageHeight + 30);
            rootStyle.setProperty('--caution-height', Math.max(stackedHeight, 72) + 'px');
        };

        const updateCautionLayout = function () {
            updateCautionMarquee();
            updateDesktopCautionLayout();
        };

        updateCautionLayout();
        window.addEventListener('resize', updateCautionLayout);
    })();
</script>
<!-- ------------------------------------- ↓ header ↓　-->
<header class="header globalHeader" role="banner">
    <!-- ------------------------------------- ↓ headerSub z:50 ↓　-->
    <?php //フェーズ2 ログインしている場合は会員情報を表示 ?>
    <?php if($is_rank != "not_login"): ?>

        <div class="headerSub js--headerScroll">
            <!-- user -->
            <a href="/mypage/" class="userBtn header__userBtn" role="link" title="<?= esc_attr($header_user_display_name); ?>さん">
                <svg class="userIcon" aria-label="<?= esc_attr($header_user_display_name); ?>さん" role="img" viewBox="0 0 20 20">
                    <path d="M12.4,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM6.9,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM8.8,11.1h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7H4.6v-.7c0-2.3,1.9-4.2,4.2-4.2Z"/>
                </svg>
                <p class="userBtn__title"><?= esc_html($header_user_display_name); ?>さん</p>
            </a>
        </div>

    <?php //フェーズ2 未ログインの場合は新規登録orログインを表示 ?>
    <?php else:?>

        <div class="headerSub js--headerScroll">
            <?php /* 一時的に非表示（リリース対象外）
            <!-- businessUserBtn -->
            <a href="<?= esc_url($header_regist_url); ?>" class="businessUserBtn header__businessUserBtn" role="link" title="ビジネス会員新規登録">
                <p class="businessUserBtn__title">ビジネス<span class="short">会員新規</span><span class="short-2">登録</span></p>
                <svg class="businessUserIcon" aria-label="ビジネス会員新規登録" role="img" viewBox="0 0 20 20">
                    <path d="M3,3v4c0,.8.5,1.4,1.2,1.6v6.7c0,.9.8,1.7,1.7,1.7h8.3c.9,0,1.7-.8,1.7-1.7v-6.7c.7-.2,1.2-.8,1.2-1.6V3H3ZM10.5,7.7v-3.7h2.2v3.7h-2.2ZM7.2,7.7v-3.7h2.2v3.7h-2.2ZM4,4h2.2v3.7h-1.6c-.4,0-.7-.3-.7-.7v-3ZM14.1,16H5.9c-.4,0-.7-.3-.7-.7v-6.6h9.6v6.6c0,.4-.3.7-.7.7ZM15.3,7.7h-1.6v-3.7h2.2v3c0,.4-.3.7-.7.7Z"/>
                    <polygon points="9.4 13.1 8 11.7 7.3 12.4 9.4 14.5 12.7 11.2 12 10.5 9.4 13.1"/>
                </svg>
            </a>
            */ ?>
            <?php //フェーズ2 ?>
            <?php if($is_rank == "free"): ?>
            <?php elseif($is_rank == "standard"): ?>
            <?php else: ?>
                <!-- privateUserBtn -->
                <a href="<?= esc_url($header_regist_url); ?>" class="privateUserBtn header__privateUserBtn" role="link" title="プライベート会員新規登録">
                    <p class="privateUserBtn__title">プライベート<span class="short">会員新規</span><span class="short-2">登録</span></p>
                    <svg class="privateUserIcon" aria-label="プライベート会員新規登録" role="img" viewBox="0 0 20 20">
                        <path d="M9.5,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM4,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM12.5,16H1.7v-.7c0-2.3,1.9-4.2,4.2-4.2h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7Z"/>
                        <path d="M18.8,5.6h-2.1v-2.1c0-.3-.2-.5-.5-.5s-.5.2-.5.5v2.1h-2.1c-.3,0-.5.2-.5.5s.2.5.5.5h2.1v2.1c0,.3.2.5.5.5s.5-.2.5-.5v-2.1h2.1c.3,0,.5-.2.5-.5s-.2-.5-.5-.5Z"/>
                    </svg>
                </a>
            <?php endif; ?>  

            <?php //フェーズ2 ?>
            <?php if($is_rank == "not_login"): ?>
                <!-- login -->
                <a href="<?= esc_url($header_login_url); ?>" class="loginBtn header__loginBtn" role="link" title="ログイン">
                    <p class="loginBtn__title">ログイン</p>
                    <svg class="loginIcon" aria-label="ログイン" role="img" viewBox="0 0 20 20">
                        <path d="M12.4,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM6.9,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM8.8,11.1h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7H4.6v-.7c0-2.3,1.9-4.2,4.2-4.2Z"/>
                    </svg>
                </a>
            <?php endif; ?>  
        </div>
    <?php endif;?>


<?php /* //TODO:お気に入りはマイページに移動するので後で消す。
        <!-- favBtn -->
        <a class="favBtn header__favBtn" role="link" href="/favorite/" title="お気に入り">
            <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                <title>お気に入り</title>
                <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
            </svg>
            <?php
            // お気に入り数の取得
            $favorite_count = get_favorite_count();
            ?>
            <?php if ($favorite_count > 0): ?>
                <p class="fontEn favBtn__num"><?= esc_html($favorite_count); ?></p>
            <?php endif; ?>
        </a>
*/ ?>

</header>
<!-- ------------------------------------- ↓ searchForm z:52 ↓　-->
<div class="searchForm js--searchForm">
    <div class="searchForm__inner">
        <button class="searchBtn" role="button" type="submit">
            <svg class="searchIcon" aria-label="SEARCH" role="img" viewBox="0 0 16 16">
                <title>SEARCH</title>
                <circle class="cls-1" cx="6.79" cy="6.79" r="3.85"/>
                <line class="cls-1" x1="9.52" y1="9.52" x2="13.2" y2="13.2"/>
            </svg>
            <div class="closeIcon"></div>
        </button>
        <div class="searchForm__closeText">
            <p class="fontW--r searchForm__closeText--p">検索する</p>
            <p class="fontW--r searchForm__closeText--p">閉じる</p>
        </div>
    </div>
</div>
<!-- ------------------------------------- ↓ searchModal z:51 ↓　-->
<div class="searchModal">
    <div class="searchModal__inner">
        <form class="searchModal__form" action="/" role="search">
            <div class="searchModal__formBlock searchModal__formBlock--flex" data-boxBgColor="body">
                <div class="searchModal__form--freeWord">
                    <p class="searchModal__formTitle">フリーワードで探す</p>
                    <input class="searchInput" name="s" type="search" placeholder="街や駅名から探す"
                           aria-label="Keywords" data-target="modal-dialog.autoFocus">
                </div>
                <div class="searchModal__form--category">
                    <p class="searchModal__formTitle">カテゴリーから探す</p>
                    <div class="checkbox__list">
                        <?php $counter = 1; ?>
                        <?php foreach ($header_category_info_list as $category): ?>
                            <div class="checkbox__target">
                                <input type="checkbox" id="category-<?php echo $counter; ?>" name="category[]"
                                       value="<?php echo esc_attr($category['slug']); ?>">
                                <label for="category-<?php echo $counter; ?>"><?php echo esc_html($category['name']); ?></label>
                            </div>
                            <?php $counter++; // カウンターをインクリメント ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="searchModal__formBlock" data-boxBgColor="bodySub">
                <div class="searchModal__form--category">
                    <p class="searchModal__formTitle">人気おすすめタグ</p>
                    <div class="checkbox__list">
                        <?php $popular_tags = get_field('popular_tag_list', 'option');
                        $terms = array(); ?>
                        <?php $counter = 1; ?>
                        <?php foreach ($popular_tags as $tag_item): ?>
                            <?php $tag_id = $tag_item['tag'];
                            $term = get_term($tag_id, 'post_tag');
                            $terms[] = $term; ?>
                            <div class="checkbox__target">
                                <input type="checkbox" id="tags-<?php echo $counter; ?>" name="tag[]"
                                       value="<?php echo esc_html($term->slug); ?>">
                                <label for="tags-<?php echo $counter; ?>">#<?php echo esc_html($term->name); ?></label>
                            </div>
                            <?php $counter++; // カウンターをインクリメント ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="searchModal__formBlock" data-boxBgColor="body">
                <div class="searchModal__form--category">
                    <p class="searchModal__formTitle">エリアから探す</p>
                    <div class="checkbox__list">
                        <?php $counter = 1; ?>
                        <?php foreach ($header_area_info_list as $area): ?>
                            <div class="checkbox__target">
                                <input type="checkbox" id="area-<?php echo $counter; ?>" name="area[]"
                                       value="<?php echo esc_attr($area['slug']); ?>">
                                <label for="area-<?php echo $counter; ?>"><?php echo esc_html($area['name']); ?></label>
                            </div>
                            <?php $counter++; // カウンターをインクリメント ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="searchModal__formBlock" data-boxBgColor="bodySub">
                <div class="searchModal__form--category">
                    <p class="searchModal__formTitle">会員向け記事で絞り込む</p>
                    <div class="checkbox__list">
                        <div class="checkbox__target">
                            <input type="checkbox"
                                   id="member-access-plan-paid"
                                   name="member_access_plan"
                                   value="paid_member"
                                   <?php checked($header_search_member_access_plan, 'paid_member'); ?>>
                            <label for="member-access-plan-paid">有料会員限定の記事のみ表示する</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="searchModal__formBlock searchModal__searchBtn--wrapper" data-boxBgColor="body">
                <!-- searchBtn -->
                <button class="searchBtn searchModal__searchBtn" role="button" type="submit">
                    <p class="searchModal__searchBtn--p">検索する</p>
                    <div class="searchModal__searchBtn--icon">
                        <svg class="searchIcon" aria-label="SEARCH" role="img" viewBox="0 0 16 16">
                            <title>検索する</title>
                            <circle class="cls-1" cx="6.79" cy="6.79" r="3.85"/>
                            <line class="cls-1" x1="9.52" y1="9.52" x2="13.2" y2="13.2"/>
                        </svg>
                    </div>
                </button>
            </div>
        </form>
    </div>
</div>
<!-- ------------------------------------- ↓ headerMain z:49 ↓　-->
<div class="flex--cc headerMain js--headerScroll">
    <!-- ------------------------------------- globalNav z:3 -->
    <nav class="globalNav" role="navigation">
        <ul class="flex--rc globalNav__list" role="list">
            <?php if($is_rank === "free" || $is_rank === "standard"): ?>
                <li class="textHoverWrapper globalNav__target globalNav__target--member js--hoverTarget" role="listitem">
                    <a class="globalNav__target--inner" href="/mypage/" title="マイページ">
                        <div class="globalNav__targetIcon mask partsSp"></div>
                        <p class="textHover__target globalNav__target--title">マイページ</p>
                        <div class="globalNav__arrow"></div>
                    </a>
                    <div class="globalNav__targetModal">
                        <div class="globalNav__targetModal--list globalNav__targetModal--member" data-boxBgColor="body">
                            <ul class="memberNav" role="list">
                                <?php foreach ($header_member_menu_groups as $header_member_menu_group): ?>
                                    <?php
                                    $groupKey = trim((string) ($header_member_menu_group['key'] ?? ''));
                                    $groupLabel = trim((string) ($header_member_menu_group['label'] ?? ''));
                                    $groupUrl = trim((string) ($header_member_menu_group['url'] ?? ''));
                                    $groupChildren = is_array($header_member_menu_group['children'] ?? null)
                                        ? $header_member_menu_group['children']
                                        : [];
                                    if ($groupLabel === '' || $groupUrl === '') {
                                        continue;
                                    }
                                    ?>
                                    <li class="memberNav__group" role="listitem">
                                        <a class="memberNav__groupLink" href="<?= esc_url($groupUrl); ?>" title="<?= esc_attr($groupLabel); ?>"><?= esc_html($groupLabel); ?></a>
                                        <?php if (!empty($groupChildren)): ?>
                                            <ul class="memberNav__children" role="list">
                                                <?php foreach ($groupChildren as $header_member_menu_child): ?>
                                                    <?php
                                                    $childLabel = trim((string) ($header_member_menu_child['label'] ?? ''));
                                                    $childUrl = trim((string) ($header_member_menu_child['url'] ?? ''));
                                                    if ($childLabel === '' || $childUrl === '') {
                                                        continue;
                                                    }
                                                    ?>
                                                    <li class="memberNav__childItem" role="listitem">
                                                        <a class="memberNav__childLink" href="<?= esc_url($childUrl); ?>" title="<?= esc_attr($childLabel); ?>"><?= esc_html($childLabel); ?></a>
                                                    </li>
                                                <?php endforeach; ?>
                                                <?php if ($groupKey === 'member_features'): ?>
                                                    <li class="memberNav__childItem memberNav__childItem--logout" role="listitem">
                                                        <form class="memberNav__logoutForm" action="<?= esc_url($header_logout_form_action); ?>" method="post">
                                                            <input type="hidden" name="mypage_action" value="logout">
                                                            <input type="hidden" name="mypage_nonce" value="<?= esc_attr($header_logout_form_nonce); ?>">
                                                            <button class="memberNav__childLink memberNav__logoutButton" type="submit">ログアウト</button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </li>
            <?php endif; ?>
            <li class="textHoverWrapper globalNav__target globalNav__target--area js--hoverTarget" role="listitem">
                <div class="globalNav__target--inner">
                    <div class="globalNav__targetIcon mask partsSp"></div>
                    <p class="textHover__target globalNav__target--title">エリアで探す</p>
                    <div class="globalNav__arrow"></div>
                </div>
                <div class="globalNav__targetModal">
                    <div class="globalNav__targetModal--list" data-boxBgColor="body">
                        <div class="areaNav__target" role="tab" aria-selected="true" data-area="all">
                            <a class="areaNav__link" href="/" title="すべて"><p class="areaNav__link--p">すべて</p></a>
                        </div>
                        <?php foreach ($header_area_info_list as $area): ?>
                            <div class="areaNav__target" role="tab" aria-selected="false"
                                 data-area="<?php echo esc_attr($area['slug']); ?>">
                                <a class="areaNav__link" href="/area/<?php echo esc_attr($area['slug']); ?>"
                                   title="<?php echo esc_attr($area['name']); ?>">
                                    <p class="areaNav__link--p"><?php echo esc_html($area['name']); ?></p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </li>
            <li class="textHoverWrapper globalNav__target globalNav__target--cate js--hoverTarget" role="listitem">
                <div class="globalNav__target--inner">
                    <div class="globalNav__targetIcon mask partsSp"></div>
                    <p class="textHover__target globalNav__target--title">カテゴリーから探す</p>
                    <div class="globalNav__arrow"></div>
                </div>
                <div class="globalNav__targetModal">
                    <div class="globalNav__targetModal--list" data-boxBgColor="body">
                        <?php
                        $category_info_list = $global_area_category_info_list ?? $header_category_info_list;
                        $category_url_suffix = ($global_is_area_context) ? "/area/{$global_queried_object->slug}/" : "/category/";
                        ?>
                        <?php foreach ($category_info_list as $category): ?>
                            <div class="areaNav__target" role="tab" aria-selected="false">
                                <a class="areaNav__link"
                                   href="<?= $category_url_suffix; ?><?php echo esc_attr($category['slug']); ?>/"
                                   title="<?php echo esc_attr($category['name']); ?>">
                                    <p class="areaNav__link--p"><?php echo esc_html($category['name']); ?></p>
                                    <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </li>
            <li class="textHoverWrapper globalNav__target globalNav__target--coupon" role="listitem">
                <a class="globalNav__target--inner" href="<?php echo esc_url(lutwiyo_get_member_benefit_entry_url(home_url('/coupon/'))); ?>" title="クーポンを探す">
                    <div class="globalNav__targetIcon mask partsSp"></div>
                    <p class="textHover__target globalNav__target--title">クーポンを探す</p>
                </a>
            </li>
            <?php //TODO: 遷移先・遷移元のページの実装ともにまだpage-present.phpを利用して/presentで遷移させる。遷移先ページ実装は11/3を想定 ?>
            <li class="textHoverWrapper globalNav__target globalNav__target--present" role="listitem">
                <a class="globalNav__target--inner" href="<?php echo esc_url(lutwiyo_get_member_benefit_entry_url(home_url('/present/'))); ?>" title="プレゼントを探す">
                    <div class="globalNav__targetIcon mask partsSp"></div>
                    <p class="textHover__target globalNav__target--title">プレゼントを探す</p>
                </a>
            </li>
        </ul>
    </nav>
    <!-- ------------------------------------- ↓ navSns z:4 ↓　-->
    <div class="navSns">
        <ul class="navSns__list">
            <li class="navSns__target">
                <a class="snsLink" href="https://x.com/tokk_kansai" target="_blank" title="X">
                    <div class="snsIcon snsIcon--x">
                        <div class="mask mask__bgColor--btnBg snsIcon__inner">X(Twitter)</div>
                    </div>
                </a>
            </li>
            <li class="navSns__target">
                <a class="snsLink" href="https://www.instagram.com/tokk_kansai/" target="_blank"
                   title="Instagram">
                    <div class="snsIcon snsIcon--ins">
                        <div class="mask mask__bgColor--btnBg snsIcon__inner">Instagram</div>
                    </div>
                </a>
            </li>
            <li class="navSns__target">
                <a class="snsLink" href="https://www.facebook.com/tokk.localmedia.kansai" target="_blank" title="Facebook">
                    <div class="snsIcon snsIcon--fb">
                        <div class="mask mask__bgColor--btnBg snsIcon__inner">Facebook</div>
                    </div>
                </a>
            </li>
        </ul>
        <div class="navSns__btn js--hoverTarget">
            <div class="globalNav__arrow"></div>
            <div class="globalNav__targetModal navSns__modal">
                <div class="globalNav__targetModal--list" data-boxBgColor="body">
                    <?php foreach ($header_area_info_list as $area): ?>
                        <div class="navSns__modalBox">
                            <p class="navSns__modalBox--title"><?php echo esc_html($area['shortname']); ?></p>
                            <ul class="areaSns__list">
                                <li class="areaSns__target">
                                    <?php if (!empty($area['url_x'])): ?>
                                        <a class="snsLink" href="<?php echo esc_url($area['url_x']); ?>" target="_blank"
                                           title="X(Twitter)"></a>
                                    <?php endif; ?>
                                    <div class="snsIcon snsIcon--x">
                                        <div class="mask mask__bgColor--text snsIcon__inner">X(Twitter)</div>
                                    </div>
                                </li>

                                <li class="areaSns__target">
                                    <?php if (!empty($area['url_facebook'])): ?>
                                        <a class="snsLink" href="<?php echo esc_url($area['url_facebook']); ?>"
                                           target="_blank" title="Facebook"></a>
                                    <?php endif; ?>
                                    <div class="snsIcon snsIcon--fb">
                                        <div class="mask mask__bgColor--text snsIcon__inner">Facebook</div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- ------------------------------------- ↓ header__scrollSub z:4 ↓　-->
    <div class="header__scrollSub">
        <!-- globalLogo -->
        <div class="globalLogo header__scrollSub--logo">
            <a class="flex--cc maskBgColor__hoverWrapper globalLogo__inner" href="/"
               title="TOKK（トック）大阪京都神戸阪急沿線おでかけ情報メディア" role="link">
                <p class="globalLogo__mark"><span class="mask mask__bgColor--text globalLogo__markInner">TOKK（トック）大阪京都神戸阪急沿線おでかけ情報メディア</span>
                </p>
            </a>
        </div>
        <?php //フェーズ2 ?>
        <?php if($is_rank == "free"): ?>
        <?php elseif($is_rank == "standard"): ?>
            <?php else: ?>
            <!-- login -->
            <a href="<?= esc_url($header_login_url); ?>" class="loginBtn header__loginBtn" role="link" title="ログイン">
                <p class="loginBtn__title">ログイン</p>
                <svg class="loginIcon" aria-label="ログイン" role="img" viewBox="0 0 20 20">
                    <path d="M12.4,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM6.9,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM8.8,11.1h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7H4.6v-.7c0-2.3,1.9-4.2,4.2-4.2Z"/>
                </svg>
            </a>
        <?php endif; ?>  

<?php /* TODO:お気に入りはマイページに移動するので後で消す。
        <!-- favBtn -->
        <?php // TODO: お気に入り機能とお気に入り一覧ページへの遷移がまだ。11/4以降に実装 cookie同意がないと活性化できない?>
        <a class="favBtn header__favBtn" role="link" href="/favorite/" title="お気に入り">
            <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                <title>お気に入り</title>
                <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
            </svg>
            <?php // $favorite_countはすでに定義済み ?>
            <?php if ($favorite_count > 0): ?>
                <p class="fontEn favBtn__num"><?= esc_html($favorite_count); ?></p>
            <?php endif; ?>
        </a>
*/ ?>
    </div>
</div>
<!-- ------------------------------------- ↓ menuBtn z:55 ↓　-->
<div class="menuBtn">
    <div class="menuBtn__inner">
        <div class="menuBtn__icon"></div>
    </div>
</div>
<!-- ------------------------------------- ↓ menu z:49 ↓　-->
<div class="menu">
    <div class="menuInner">
        <div class="guestMenu">
            <?php if($is_rank == "not_login"): ?>
                <div class="guestMenu__block">
                    <!-- privateUserBtn -->
                    <a href="<?= esc_url($header_regist_url); ?>" class="privateUserBtn" role="link" title="プライベート会員新規登録">
                        <p class="privateUserBtn__title">プライベート<span class="short">会員新規</span><span class="short-2">登録</span></p>
                        <svg class="privateUserIcon" aria-label="プライベート会員新規登録" role="img" viewBox="0 0 20 20">
                            <path d="M9.5,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM4,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM12.5,16H1.7v-.7c0-2.3,1.9-4.2,4.2-4.2h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7Z"/>
                            <path d="M18.8,5.6h-2.1v-2.1c0-.3-.2-.5-.5-.5s-.5.2-.5.5v2.1h-2.1c-.3,0-.5.2-.5.5s.2.5.5.5h2.1v2.1c0,.3.2.5.5.5s.5-.2.5-.5v-2.1h2.1c.3,0,.5-.2.5-.5s-.2-.5-.5-.5Z"/>
                        </svg>
                    </a>
                </div>
                <div class="guestMenu__block">
                    <!-- login -->
                    <a href="<?= esc_url($header_login_url); ?>" class="loginBtn" role="link" title="ログイン">
                        <p class="loginBtn__title">ログイン</p>
                        <svg class="loginIcon" aria-label="ログイン" role="img" viewBox="0 0 20 20">
                            <path d="M12.4,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM6.9,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM8.8,11.1h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7H4.6v-.7c0-2.3,1.9-4.2,4.2-4.2Z"/>
                        </svg>
                    </a>
                </div>
                <?php /* 一時的に非表示（リリース対象外）
                <div class="guestMenu__block">
                    <!-- businessUserBtn -->
                    <a href="<?= esc_url($header_regist_url); ?>" class="businessUserBtn" role="link" title="ビジネス会員新規登録">
                        <p class="businessUserBtn__title">ビジネス<span class="short">会員新規</span><span class="short-2">登録</span></p>
                        <svg class="businessUserIcon" aria-label="ビジネス会員新規登録" role="img" viewBox="0 0 20 20">
                            <path d="M3,3v4c0,.8.5,1.4,1.2,1.6v6.7c0,.9.8,1.7,1.7,1.7h8.3c.9,0,1.7-.8,1.7-1.7v-6.7c.7-.2,1.2-.8,1.2-1.6V3H3ZM10.5,7.7v-3.7h2.2v3.7h-2.2ZM7.2,7.7v-3.7h2.2v3.7h-2.2ZM4,4h2.2v3.7h-1.6c-.4,0-.7-.3-.7-.7v-3ZM14.1,16H5.9c-.4,0-.7-.3-.7-.7v-6.6h9.6v6.6c0,.4-.3.7-.7.7ZM15.3,7.7h-1.6v-3.7h2.2v3c0,.4-.3.7-.7.7Z"/>
                            <polygon points="9.4 13.1 8 11.7 7.3 12.4 9.4 14.5 12.7 11.2 12 10.5 9.4 13.1"/>
                        </svg>
                    </a>
                </div>
                */ ?>
            <?php else: ?>
                <div class="guestMenu__block">
                    <!-- user -->
                    <a href="/mypage/" class="userBtn" role="link" title="<?= esc_attr($header_user_display_name); ?>さん">
                        <svg class="userIcon" aria-label="<?= esc_attr($header_user_display_name); ?>さん" role="img" viewBox="0 0 20 20">
                            <path d="M12.4,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM6.9,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM8.8,11.1h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7H4.6v-.7c0-2.3,1.9-4.2,4.2-4.2Z"/>
                        </svg>
                        <p class="userBtn__title"><?= esc_html($header_user_display_name); ?>さん</p>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="menuInner__contents">
            <?php if($is_rank === "free" || $is_rank === "standard"): ?>
                <dl class="navToggle__wrapper navToggle__wrapper--member">
                    <dt class="textHoverWrapper globalNav__target globalNav__target--member navToggle__btn">
                        <a class="globalNav__target--inner" href="/mypage/" title="マイページ">
                            <div class="globalNav__targetIcon mask partsSp" aria-hidden="true"></div>
                            <p class="globalNav__target--title">マイページ</p>
                        </a>
                        <div class="partsSp navToggle__icon">
                            <div class="navToggle__iconInner"></div>
                        </div>
                    </dt>
                    <dd class="navToggle__inner">
                        <ul class="fontW--r navToggle__innerList navToggle__innerList--member" role="list">
                            <?php foreach ($header_member_menu_groups as $header_member_menu_group): ?>
                                <?php
                                $groupLabel = trim((string) ($header_member_menu_group['label'] ?? ''));
                                $groupUrl = trim((string) ($header_member_menu_group['url'] ?? ''));
                                $groupChildren = is_array($header_member_menu_group['children'] ?? null)
                                    ? $header_member_menu_group['children']
                                    : [];
                                if ($groupLabel === '' || $groupUrl === '') {
                                    continue;
                                }
                                ?>
                                <li class="navToggle__target navToggle__target--memberGroup" role="listitem">
                                    <a class="navToggle__link navToggle__link--memberGroup" href="<?= esc_url($groupUrl); ?>" title="<?= esc_attr($groupLabel); ?>"><?= esc_html($groupLabel); ?></a>
                                    <?php if (!empty($groupChildren)): ?>
                                        <ul class="memberNavSp__children" role="list">
                                            <?php foreach ($groupChildren as $header_member_menu_child): ?>
                                                <?php
                                                $childLabel = trim((string) ($header_member_menu_child['label'] ?? ''));
                                                $childUrl = trim((string) ($header_member_menu_child['url'] ?? ''));
                                                if ($childLabel === '' || $childUrl === '') {
                                                    continue;
                                                }
                                                ?>
                                                <li class="memberNavSp__childItem" role="listitem">
                                                    <a class="navToggle__link navToggle__link--memberChild" href="<?= esc_url($childUrl); ?>" title="<?= esc_attr($childLabel); ?>"><?= esc_html($childLabel); ?></a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                            <li class="memberNavSp__childItem memberNavSp__childItem--logout" role="listitem">
                                <form class="memberNavSp__logoutForm" action="<?= esc_url($header_logout_form_action); ?>" method="post">
                                    <input type="hidden" name="mypage_action" value="logout">
                                    <input type="hidden" name="mypage_nonce" value="<?= esc_attr($header_logout_form_nonce); ?>">
                                    <button class="navToggle__link navToggle__link--memberGroup memberNavSp__logoutButton" type="submit">ログアウト</button>
                                </form>
                            </li>
                        </ul>
                    </dd>
                </dl>
            <?php endif; ?>
            <dl class="navToggle__wrapper navToggle__wrapper--area">
                <dt class="textHoverWrapper globalNav__target globalNav__target--area navToggle__btn">
                    <a class="globalNav__target--inner" href="/" title="エリアで探す">
                        <p class="globalNav__target--title">エリアで探す</p>
                    </a>
                    <div class="partsSp navToggle__icon">
                        <div class="navToggle__iconInner"></div>
                    </div>
                </dt>
                <dd class="navToggle__inner">
                    <ul class="fontW--r navToggle__innerList" role="list">
                        <li class="navToggle__target" role="listitem" aria-selected="true" data-area="all">
                            <a class="navToggle__link" href="/" title="すべて">すべて</a></li>
                        <?php foreach ($header_area_info_list as $area): ?>
                            <li class="navToggle__target" role="listitem" aria-selected="false"
                                data-area="<?php echo esc_attr($area['slug']); ?>">
                                <a class="navToggle__link" href="/area/<?php echo esc_html($area['slug']); ?>"
                                   title="<?php echo esc_attr($area['name']); ?>"><?php echo esc_attr($area['name']); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </dd>
            </dl>
            <dl class="navToggle__wrapper navToggle__wrapper--cate">
                <dt class="textHoverWrapper globalNav__target globalNav__target--area navToggle__btn">
                    <a class="globalNav__target--inner" href="/" title="カテゴリーから探す">
                        <p class="globalNav__target--title">カテゴリーから探す</p>
                    </a>
                    <div class="partsSp navToggle__icon">
                        <div class="navToggle__iconInner"></div>
                    </div>
                </dt>
                <dd class="navToggle__inner">
                    <ul class="fontW--r navToggle__innerList" role="list">
                        <?php foreach ($header_category_info_list as $category): ?>
                            <?php
                            // 'タイアップ'と'未分類'のカテゴリはスキップ
                            if ($category['name'] === 'タイアップ' || $category['name'] === '未分類') {
                                continue;
                            }
                            ?>
                            <li class="navToggle__target" role="listitem">
                                <a class="navToggle__link"
                                   href="/area/category/<?php echo esc_attr($category['slug']); ?>/"
                                   title="<?php echo esc_attr($category['name']); ?>"><?php echo esc_attr($category['name']); ?></a>
                            </li>
                            <?php $counter++; // カウンターをインクリメント ?>
                        <?php endforeach; ?>
                    </ul>
                </dd>
            </dl>
            <ul class="footerNav__list">
                <li class="textHoverWrapper globalNav__target globalNav__target--coupon">
                    <a class="globalNav__target--inner" href="<?php echo esc_url(lutwiyo_get_member_benefit_entry_url(home_url('/coupon/'))); ?>" title="クーポンを探す">
                        <p class="globalNav__target--title">クーポンを探す</p>
                    </a>
                </li>
                <li class="textHoverWrapper globalNav__target globalNav__target--present">
                    <a class="globalNav__target--inner" href="<?php echo esc_url(lutwiyo_get_member_benefit_entry_url(home_url('/present/'))); ?>" title="プレゼントを探す">
                        <p class="globalNav__target--title">プレゼントを探す</p>
                    </a>
                </li>
            </ul>
            <dl class="navToggle__wrapper navToggle__wrapper--sns">
                <dt class="textHoverWrapper globalNav__target globalNav__target--snsLink navToggle__btn">
                    <ul class="navSns__list">
                        <li class="navSns__target">
                            <a class="snsLink" href="https://x.com/tokk_kansai" target="_blank" title="X">
                                <div class="snsIcon snsIcon--x">
                                    <div class="mask mask__bgColor--btnBg snsIcon__inner">X(Twitter)</div>
                                </div>
                            </a>
                        </li>
                        <li class="navSns__target">
                            <a class="snsLink" href="https://www.instagram.com/tokk_kansai/" target="_blank" title="Instagram">
                                <div class="snsIcon snsIcon--ins">
                                    <div class="mask mask__bgColor--btnBg snsIcon__inner">Instagram</div>
                                </div>
                            </a>
                        </li>
                        <li class="navSns__target">
                            <a class="snsLink" href="https://www.facebook.com/tokk.localmedia.kansai" target="_blank" title="Facebook">
                                <div class="snsIcon snsIcon--fb">
                                    <div class="mask mask__bgColor--btnBg snsIcon__inner">Facebook</div>
                                </div>
                            </a>
                        </li>
                    </ul>
                    <div class="partsSp navToggle__icon">
                        <div class="navToggle__iconInner"></div>
                    </div>
                </dt>
                <?php //TODO: 動作確認がまだ ?>
                <dd class="navToggle__inner">
                    <ul class="fontW--r navToggle__innerList" role="list">
                        <?php foreach ($header_area_info_list as $area): ?>
                            <li class="navToggle__target navSns__modalBox" role="listitem">
                                <p class="navSns__modalBox--title"><?php echo esc_html($area['shortname']); ?></p>
                                <ul class="areaSns__list">
                                    <li class="areaSns__target">
                                        <?php if (!empty($area['url_x'])): ?>
                                            <a class="snsLink" href="<?php echo esc_url($area['url_x']); ?>"
                                               target="_blank" title="X(Twitter)"></a>
                                        <?php endif; ?>
                                        <div class="snsIcon snsIcon--x">
                                            <div class="mask mask__bgColor--text snsIcon__inner">X(Twitter)</div>
                                        </div>
                                    </li>
                                    <li class="areaSns__target">
                                        <?php if (!empty($area['url_facebook'])): ?>
                                            <a class="snsLink" href="<?php echo esc_url($area['url_facebook']); ?>"
                                               target="_blank" title="Facebook"></a>
                                        <?php endif; ?>
                                        <div class="snsIcon snsIcon--fb">
                                            <div class="mask mask__bgColor--text snsIcon__inner">Facebook</div>
                                        </div>
                                    </li>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </dd>
            </dl>
            <a class="menuCm" href="/ad" title="広告募集">
                <img src="/assets/img/common/footer--ad.jpg" alt="広告募集">
            </a>
            <ul class="footerBottom__list menuBottom__list">
                <li class="footerBottom__target">
                    <a class="footerBottom__target--link" href="https://tokk-kansai.jp/about/" title="TOKKとは">
                        <p class="fontW--r footerBottom__target--p">TOKKとは</p></a>
                </li>
                <li class="footerBottom__target">
                    <a class="footerBottom__target--link" href="/about-company/" title="運営会社について">
                        <p class="fontW--r footerBottom__target--p">運営会社について</p></a>
                </li>
                <li class="footerBottom__target">
                    <a class="footerBottom__target--link" href="https://tokk-kansai.jp/ad/" title="広告について">
                        <p class="fontW--r footerBottom__target--p">広告について</p></a>
                </li>
                <li class="footerBottom__target">
                    <a class="footerBottom__target--link" href="https://tokk-kansai.jp/privacy/" title="プライバシーポリシー">
                        <p class="fontW--r footerBottom__target--p">プライバシーポリシー
                        </p></a>
                </li>
                <li class="footerBottom__target">
                    <a class="footerBottom__target--link" href="https://tokk-kansai.jp/cookie-policy/" title="クッキーポリシー">
                        <p class="fontW--r footerBottom__target--p">クッキーポリシー</p></a>
                </li>
                <li class="footerBottom__target">
                    <a class="footerBottom__target--link" href="https://tokk-kansai.jp/howto/" title="ご利用にあたって">
                        <p class="fontW--r footerBottom__target--p">ご利用にあたって</p></a>
                </li>
                <li class="footerBottom__target">
                    <a class="footerBottom__target--link" href="https://tokk-kansai.jp/contact/" title="情報提供・お問い合わせ">
                        <p class="fontW--r footerBottom__target--p">情報提供・お問い合わせ</p></a>
                </li>
                <li class="arrowIcon__hoverWrapper footerBottom__target">
                    <a class="footerBottom__target--link" href="https://www.hankyu.co.jp/" title="阪急電鉄ホームページ" target="_blank">
                        <p class="fontW--r footerBottom__target--p">阪急電鉄ホームページ
                        <div class="arrowBlank"></div>
                        </p></a>
                </li>
                <li class="arrowIcon__hoverWrapper footerBottom__target">
                    <a class="footerBottom__target--link" href="https://www.hanshin.co.jp/" title="阪神電気鉄道ホームページ" target="_blank">
                        <p class="fontW--r footerBottom__target--p">阪神電気鉄道ホームページ
                        <div class="arrowBlank"></div>
                        </p></a>
                </li>
            </ul>
        </div>
    </div>
</div>
<!-- ------------------------------------- ↓ hoverCover z:40 ↓　-->
<div class="hoverCover"></div>
