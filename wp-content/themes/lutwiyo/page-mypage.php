<?php
// --------------------------------------
// 会員マイページ表示前ガード
// --------------------------------------
// 未ログインはログイン導線へリダイレクトする。
$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);
$mypageBaseUrl = remove_query_arg(['tokk_member_login', 'logged_out'], $currentUrlForReturn);
$mypageLoggedOutRedirectUrl = home_url('/');
$mypagePaidExpUrl = home_url('/paid-exp/');
$mypagePaymentMethodRequiredUrl = home_url('/paid-payment-method-required/');
$mypagePlanLabel = 'フリー';
$isMypageFreePlan = true;

$mypageLogoutErrorMessage = '';
$mypagePaymentErrorMessage = '';

$postedAction = isset($_POST['mypage_action'])
    ? sanitize_text_field(wp_unslash($_POST['mypage_action']))
    : '';
$isLogoutPost = $_SERVER['REQUEST_METHOD'] === 'POST' && $postedAction === 'logout';

if ($isLogoutPost) {
    $nonce = isset($_POST['mypage_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['mypage_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'tokk_mypage_action')) {
        $mypageLogoutErrorMessage = 'セッションの有効期限が切れました。再度お試しください。';
    } else {
        if ($isLogoutPost) {
            // --- ログアウトAPI呼び出し開始 ---
            // 外部認証基盤のセッションを破棄し、SLOは行わず当サイト内のログアウトのみを実施する。
            $logoutResponse = lutwiyo_call_bridge_api('logout', [
                'slo_logout' => '0',
            ]);
            // --- ログアウトAPI呼び出し終了 ---

            $logoutReason = is_array($logoutResponse)
                ? trim((string) ($logoutResponse['body']['reason'] ?? ''))
                : '';

            $isLogoutSuccess = is_array($logoutResponse)
                && (int) ($logoutResponse['status'] ?? 0) === 200
                && in_array($logoutReason, ['revoked', 'already_logged_out'], true);

            if ($isLogoutSuccess) {
                wp_safe_redirect($mypageLoggedOutRedirectUrl);
                exit;
            }

            $mypageLogoutErrorMessage = 'ログアウトに失敗しました。時間をおいて再度お試しください。';
        }

    }
}

// --- ログイン状態確認API呼び出し開始 ---
// マイページ表示可否を判定するため、現在のログイン状態を取得する。
$loginStatusResponse = function_exists('lutwiyo_get_or_fetch_login_status_response')
    ? lutwiyo_get_or_fetch_login_status_response()
    : lutwiyo_call_bridge_api('login_status');
// --- ログイン状態確認API呼び出し終了 ---
$isLoggedIn = is_array($loginStatusResponse)
    && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';
$memberInfoResponse = null;
$isLoginStartRequested = isset($_GET['tokk_member_login'])
    && (string) $_GET['tokk_member_login'] === '1';

if (!$isLoggedIn) {
    // /mypage だけは member_info で再確認し、瞬間的な判定ぶれを吸収する。
    $memberInfoResponse = function_exists('lutwiyo_get_or_fetch_member_info_response')
        ? lutwiyo_get_or_fetch_member_info_response()
        : lutwiyo_call_bridge_api('member_info', [
            'content' => 'sub,data',
        ]);
    $isLoggedIn = is_array($memberInfoResponse)
        && ($memberInfoResponse['body']['result'] ?? '') === 'ok';

    if (!$isLoginStartRequested) {
        if (!$isLoggedIn) {
            lutwiyo_render_inline_login_guide_and_exit($currentUrlForReturn);
        }
    }
}

// --- 会員情報取得API呼び出し開始 ---
// uidはセッションやグローバル変数へ保持せず、ページ表示時に都度取得する。
if (!is_array($memberInfoResponse)) {
    $memberInfoResponse = function_exists('lutwiyo_get_or_fetch_member_info_response')
        ? lutwiyo_get_or_fetch_member_info_response()
        : lutwiyo_call_bridge_api('member_info', [
            'content' => 'sub,data',
        ]);
}
// --- 会員情報取得API呼び出し終了 ---
$memberUid = '';
$favoriteArticleIdList = [];
$mypagePaidArticlesInfoList = [];
$mypageFavoriteArticlesInfoList = [];
$mypageFavoriteAreaSections = [];
$mypageMemberBlogContext = [
    'status' => 'idle',
    'items' => [],
];
$mypageMemberBlogAccessStatus = function_exists('tokk_memberblog_get_management_access_context')
    ? (string) ((tokk_memberblog_get_management_access_context()['status'] ?? 'unknown'))
    : 'unknown';
$mypageCommentItems = [];
$mypageCommentTotalCount = 0;
$mypageCommentActionNotice = '';
$mypageCommentActionError = '';

if (is_array($memberInfoResponse) && ($memberInfoResponse['body']['result'] ?? '') === 'ok') {
    $memberUid = trim((string) ($memberInfoResponse['body']['uid'] ?? ''));

    $localMember = $memberInfoResponse['body']['local_member'] ?? [];
    $isStandardMember = is_array($localMember) && (int) ($localMember['member_rank_id'] ?? 0) === 2;
    if ($isStandardMember) {
        $recurringBillingReadyRaw = $localMember['recurring_billing_ready'] ?? false;
        $isRecurringBillingReady = in_array($recurringBillingReadyRaw, [true, 1, '1', 'true'], true);
        if (! $isRecurringBillingReady) {
            wp_safe_redirect($mypagePaymentMethodRequiredUrl);
            exit;
        }

        $mypagePlanLabel = 'スタンダード';
        $isMypageFreePlan = false;
    }
}
// lutwiyo_debug("■マイページ内でのuid取得",$memberUid);

if ($memberUid !== '') {
    // --- 会員プロフィール取得API呼び出し開始 ---
    // TOKK独自項目の有無を確認し、マイページ表示データの判定に利用する。
    // マイページAPI（TOKK独自項目）を呼び出して会員データ有無を確認する。
    $memberProfileResponse = function_exists('lutwiyo_get_or_fetch_current_member_profile_response')
        ? lutwiyo_get_or_fetch_current_member_profile_response()
        : lutwiyo_call_bridge_api('member_profile', [
            'uid' => $memberUid,
            'operation' => 'get',
        ]);
    // --- 会員プロフィール取得API呼び出し終了 ---
    // lutwiyo_debug("■マイページ内でのuidがあった場合のマイページAPI（TOKK独自項目）呼び出し結果",$memberProfileResponse);

    // --- お気に入り記事一覧取得開始 ---
    // お気に入り記事一覧は新API（/member/favorites）を唯一の取得元として扱う。
    $favoriteArticleIdList = lutwiyo_get_current_member_favorite_article_ids();
    // --- お気に入り記事一覧取得終了 ---
    // lutwiyo_debug("■マイページ内でのお気に入り記事ID一覧", $favoriteArticleIdList);

    $memberProfile = function_exists('lutwiyo_get_current_member_profile_member')
        ? lutwiyo_get_current_member_profile_member()
        : (is_array($memberProfileResponse)
            && ($memberProfileResponse['body']['result'] ?? '') === 'ok'
            && is_array($memberProfileResponse['body']['member'] ?? null)
            ? $memberProfileResponse['body']['member']
            : []);
    $favoriteAreaSlug = trim((string) ($memberProfile['favorite_area'] ?? ''));
    if ($favoriteAreaSlug !== '' && function_exists('lutwiyo_get_member_favorite_area_sections')) {
        $mypageFavoriteAreaSections = lutwiyo_get_member_favorite_area_sections([$favoriteAreaSlug], 1, 3);
    }
}

$favIdsForMypage = array_values(array_reverse($favoriteArticleIdList));
if (!empty($favIdsForMypage)) {
    $favoriteQuery = new WP_Query([
        "post_type" => "articles",
        "post__in" => $favIdsForMypage,
        "orderby" => "post__in",
        "posts_per_page" => -1,
    ]);

    $mypageFavoriteArticlesInfoList = PostModelHelper::get_posts_payload(
        $favoriteQuery->posts,
        [],
        [],
        $favoriteQuery
    );
}

$mypagePaidArticleQuery = new WP_Query([
    'post_type' => 'articles',
    'post_status' => 'publish',
    'posts_per_page' => 12,
    'meta_query' => [
        [
            'key' => 'member_access_plan',
            'value' => 'paid_member',
        ],
    ],
]);

if (!empty($mypagePaidArticleQuery->posts)) {
    $mypagePaidArticlesInfoList = PostModelHelper::get_posts_payload(
        $mypagePaidArticleQuery->posts,
        [],
        [],
        $mypagePaidArticleQuery
    );
}

if ($memberUid !== '' && $mypageMemberBlogAccessStatus === 'standard' && function_exists('tokk_memberblog_get_posts_context')) {
    $mypageMemberBlogContext = tokk_memberblog_get_posts_context($memberUid);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postedAction === 'member_comment_delete_request') {
    $nonce = isset($_POST['mypage_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['mypage_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'tokk_mypage_action')) {
        $mypageCommentActionError = 'セッションの有効期限が切れました。再度お試しください。';
    } else {
        $commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;
        $updateResponse = function_exists('lutwiyo_update_current_member_comment_status')
            ? lutwiyo_update_current_member_comment_status($commentId, 'delete_requested', 'member_requested_delete')
            : ['ok' => false, 'status' => 500, 'code' => 'CALLER_NOT_AVAILABLE', 'message' => ''];

        if (($updateResponse['ok'] ?? false) === true) {
            $mypageCommentActionNotice = 'コメントの削除依頼を受け付けました。';
        } else {
            $mypageCommentActionError = '削除依頼に失敗しました。時間をおいて再度お試しください。';
        }
    }
}

if ($memberUid !== '' && function_exists('lutwiyo_get_current_member_comment_list')) {
    $mypageCommentResponse = lutwiyo_get_current_member_comment_list(1, 50, 'posted_at:desc');
    if (($mypageCommentResponse['ok'] ?? false) === true) {
        $mypageCommentItems = is_array($mypageCommentResponse['items'] ?? null) ? $mypageCommentResponse['items'] : [];
        $mypageCommentTotalCount = max((int) ($mypageCommentResponse['total'] ?? 0), count($mypageCommentItems));
    } else {
        $mypageCommentActionError = $mypageCommentActionError !== ''
            ? $mypageCommentActionError
            : 'コメント一覧の取得に失敗しました。時間をおいて再度お試しください。';
    }
}

$mypageFormatCommentPostedAt = static function ($rawPostedAt): string {
    $normalized = trim((string) $rawPostedAt);
    if ($normalized === '') {
        return '';
    }

    try {
        $datetime = new DateTime($normalized);
    } catch (Exception $exception) {
        return '';
    }

    return $datetime->format('Y.m.d H:i');
};

$mypageResolveCommentResourceTitle = static function (array $commentItem): string {
    static $articleTitleCache = [];
    static $memberBlogTitleCache = [];

    $resourceType = trim((string) ($commentItem['resource_type'] ?? ''));
    $resourceId = trim((string) ($commentItem['resource_id'] ?? ''));
    $fallbackTitle = trim((string) ($commentItem['resource_title'] ?? ''));

    if ($resourceType === 'tokk_article') {
        $normalizedId = (int) $resourceId;
        if ($normalizedId > 0) {
            if (!array_key_exists($normalizedId, $articleTitleCache)) {
                $articlePost = get_post($normalizedId);
                $articleTitleCache[$normalizedId] = $articlePost instanceof WP_Post
                    ? trim((string) get_the_title($articlePost))
                    : '';
            }

            if ($articleTitleCache[$normalizedId] !== '') {
                return $articleTitleCache[$normalizedId];
            }
        }
    }

    if ($resourceType === 'member_blog_post') {
        $normalizedId = (int) $resourceId;
        if ($normalizedId > 0 && function_exists('tokk_memberblog_fetch_public_post')) {
            if (!array_key_exists($normalizedId, $memberBlogTitleCache)) {
                $publicPost = tokk_memberblog_fetch_public_post($normalizedId);
                $memberBlogTitleCache[$normalizedId] = ($publicPost['success'] ?? false) === true
                    ? trim((string) (($publicPost['item']['title'] ?? '')))
                    : '';
            }

            if ($memberBlogTitleCache[$normalizedId] !== '') {
                return $memberBlogTitleCache[$normalizedId];
            }
        }
    }

    if ($fallbackTitle !== '') {
        return $fallbackTitle;
    }

    return '投稿先';
};

// マイページ上の決済関連ボタン押下時にBridge APIを呼び出し、PAY画面へのリダイレクトURLを取得する。
$isPaymentPost = $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($postedAction, ['payment_history', 'payment_method'], true);
if ($isPaymentPost) {
    $nonce = isset($_POST['mypage_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['mypage_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'tokk_mypage_action')) {
        $mypagePaymentErrorMessage = 'セッションの有効期限が切れました。再度お試しください。';
    } elseif ($memberUid === '') {
        $mypagePaymentErrorMessage = '会員情報の取得に失敗しました。時間をおいて再度お試しください。';
    } else {
        $action = $postedAction === 'payment_history' ? 'payment_history' : 'payment_method';
        $paymentDestinationLabel = $action === 'payment_history' ? 'お支払い履歴' : '決済方法';
        $response = lutwiyo_call_bridge_api($action, [
            'uid' => $memberUid,
            'current_url' => $mypageBaseUrl,
        ]);

        if (is_array($response)
            && ($response['body']['result'] ?? '') === 'redirect'
            && trim((string) ($response['body']['redirect_to'] ?? '')) !== '') {
            wp_redirect((string) $response['body']['redirect_to']);
            exit;
        }

        $mypagePaymentErrorMessage = sprintf(
            '%sへの遷移に失敗しました。時間をおいて再度お試しください。',
            $paymentDestinationLabel
        );
    }
}

get_header();
?>

        <style>
            .mypageShortcutLinks__item,
            .mypageShortcutLinks__button {
                color: #3a3a3a !important;
                -webkit-text-fill-color: #3a3a3a !important;
                opacity: 1 !important;
            }
        </style>

    <!-- ==================================================================== ↓ wrapper ↓ -->
    <main class="main wrapper" role="main">

        <?php if ($mypageLogoutErrorMessage !== ''): ?>
            <p style="margin-bottom:12px;color:#d00;"><?php echo esc_html($mypageLogoutErrorMessage); ?></p>
        <?php endif; ?>

        <?php if ($mypagePaymentErrorMessage !== ''): ?>
            <p style="margin-bottom:12px;color:#d00;"><?php echo esc_html($mypagePaymentErrorMessage); ?></p>
        <?php endif; ?>

        <div class="mypageContent">
            <div class="mypageBtnSet">
                <span class="mypageBtn">現在の会員プラン</span>
                <?php if ($isMypageFreePlan): ?>
                    <a href="<?php echo esc_url($mypagePaidExpUrl); ?>" class="mypageBtn">フリー</a>
                <?php else: ?>
                    <span class="mypageBtn"><?php echo esc_html($mypagePlanLabel); ?></span>
                <?php endif; ?>
            </div>

            <div class="mypageShortcutLinks">
                <a href="<?php echo esc_url(home_url('/mypage-edit')); ?>" class="mypageShortcutLinks__item">会員情報編集</a>
                <?php if (!$isMypageFreePlan): ?>
                    <form id="mypage-payment-history-form" method="post" action="<?php echo esc_url($mypageBaseUrl); ?>" class="mypageShortcutLinks__form">
                        <?php wp_nonce_field('tokk_mypage_action', 'mypage_nonce'); ?>
                        <input type="hidden" name="mypage_action" value="payment_history">
                        <button type="submit" class="mypageShortcutLinks__item mypageShortcutLinks__button">お支払い履歴</button>
                    </form>
                    <form id="mypage-payment-method-form" method="post" action="<?php echo esc_url($mypageBaseUrl); ?>" class="mypageShortcutLinks__form">
                        <?php wp_nonce_field('tokk_mypage_action', 'mypage_nonce'); ?>
                        <input type="hidden" name="mypage_action" value="payment_method">
                        <button type="submit" class="mypageShortcutLinks__item mypageShortcutLinks__button">決済方法</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>


        <?php if (!empty($mypagePaidArticlesInfoList)): ?>
        <article class="articlePT articlePB tieupArticle" data-boxBgColor="bodyMypage">
            <h2 class="gridWide title fs--22">有料記事</h2>
            <?php
            get_template_part(
                'partials/modules/article-list',
                'mypage-favorite',
                [
                    'articles_info_list' => $mypagePaidArticlesInfoList,
                    'show_all_link' => false,
                ]
            );
            ?>
        </article>
        <?php endif; ?>

        <?php if (!empty($mypageFavoriteArticlesInfoList)): ?>
        <article class="articlePT articlePB tieupArticle" data-boxBgColor="bodyMypage">
            <h2 class="gridWide title fs--22">保存した記事一覧</h2>
            <?php
            get_template_part(
                'partials/modules/article-list',
                'mypage-favorite',
                ['articles_info_list' => $mypageFavoriteArticlesInfoList]
            );
            ?>
        </article>
        <?php endif; ?>

        <?php if (!empty($mypageFavoriteAreaSections)): ?>
        <article class="gridWide articlePT articlePB recommendArticle" data-boxBgColor="bodySub">
            <h2 class="title fs--22">おすすめエリア</h2>
            <?php foreach ($mypageFavoriteAreaSections as $favoriteAreaInfo): ?>
                <?php
                get_template_part(
                    'partials/modules/article-list',
                    'area',
                    [
                        'area_info' => $favoriteAreaInfo,
                        'integrated_articles_info_list' => (array) ($favoriteAreaInfo['integrated_articles_info_list'] ?? []),
                    ]
                );
                ?>
            <?php endforeach; ?>
        </article>
        <?php endif; ?>

        <article class="articlePT articlePB cateArticle" data-boxBgColor="bodyWhite">
            <div class="gridWide">
                <h2 class="title fs--22">ブログ</h2>
            </div>
            <section class="gridWide cateSection">
                <h3 class="title fs--22">自分の書いたブログの一覧</h3>
                <?php if ($mypageMemberBlogAccessStatus !== 'standard') : ?>
                    <?php tokk_memberblog_render_management_access_notice($mypageMemberBlogAccessStatus); ?>
                <?php else : ?>
                    <?php
                    if (($mypageMemberBlogContext['status'] ?? 'idle') === 'error') {
                        echo '<p class="memberblog-mypage-empty">会員ブログ一覧の取得に失敗しました。時間をおいて再度お試しください。</p>';
                    } else {
                        tokk_memberblog_render_mypage_post_list(
                            is_array($mypageMemberBlogContext['items'] ?? null) ? $mypageMemberBlogContext['items'] : [],
                            5
                        );
                    }
                    ?>
                <?php endif; ?>
            </section>
            <section class="gridWide cateSection">
                <h3 class="title fs--22">自分が書いたコメント一覧</h3>
                <?php if ($mypageCommentActionNotice !== ''): ?>
                    <p class="memberblog-mypage-empty" style="color:#176b2a;"><?php echo esc_html($mypageCommentActionNotice); ?></p>
                <?php endif; ?>
                <?php if ($mypageCommentActionError !== ''): ?>
                    <p class="memberblog-mypage-empty" style="color:#b00020;"><?php echo esc_html($mypageCommentActionError); ?></p>
                <?php endif; ?>
                <?php if ($mypageCommentItems === []): ?>
                    <p class="memberblog-mypage-empty">コメントはまだありません。</p>
                <?php else: ?>
                    <div class="cateSection__list mypage-comment-list">
                        <?php foreach ($mypageCommentItems as $commentItem): ?>
                            <?php
                            $commentId = max(0, (int) ($commentItem['comment_id'] ?? 0));
                            if ($commentId <= 0) {
                                continue;
                            }
                            $commentStatus = trim((string) ($commentItem['status'] ?? 'visible'));
                            if ($commentStatus === 'deleted') {
                                continue;
                            }
                            $commentBody = trim((string) ($commentItem['body'] ?? ''));
                            $resourceTitle = $mypageResolveCommentResourceTitle($commentItem);
                            $resourceUrl = trim((string) ($commentItem['resource_url'] ?? ''));
                            $postedAtLabel = $mypageFormatCommentPostedAt($commentItem['posted_at'] ?? '');
                            $cardClassList = ['blockBox', 'blockBox--cate', 'mypage-comment-card'];
                            if ($commentStatus === 'delete_requested') {
                                $cardClassList[] = 'mypage-comment-card--delete-requested';
                            }
                            ?>
                            <article class="<?php echo esc_attr(implode(' ', $cardClassList)); ?>" data-boxBgColor="white">
                                <div class="blockBox__info">
                                    <p class="mypage-comment-card__title">
                                        投稿先:
                                        <?php if ($resourceUrl !== ''): ?>
                                            <a href="<?php echo esc_url(home_url($resourceUrl)); ?>"><?php echo esc_html($resourceTitle); ?></a>
                                        <?php else: ?>
                                            <?php echo esc_html($resourceTitle); ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="mypage-comment-card__body"><?php echo nl2br(esc_html($commentBody)); ?></div>
                                    <div class="blockBox__infoSub--list">
                                        <?php if ($postedAtLabel !== ''): ?>
                                            <div class="blockBox__infoSub--target">
                                                <div class="icon iconTime"><div class="mask iconInner"></div></div>
                                                <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer"><?php echo esc_html($postedAtLabel); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($commentStatus === 'delete_requested'): ?>
                                        <p class="mypage-comment-card__notice">削除依頼を受け付けています。</p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </article>


        <article class="articlePT articlePB articleBanner bgClose" data-boxBgColor="bodyMypage">
            <div class="gridWide flexColumn flexColumn--2">
                            <!-- ------------------------------------- ↓ presentSection ↓　-->
            <section class="keenSlider__wrapper flexColumnBox borderBox presentSection">
                <h2 class="title fs--19">今月のプレゼント</h2>
                <div class="presentSlider sliderWrap" data-keen="true"
                    data-loop="false"
                    data-mode="snap"
                    data-rtl="false"
                    data-origin="auto"
                    data-per-view-pc="1.03"
                    data-spacing-pc="0"
                    data-per-view-tablet="1.03"
                    data-spacing-tablet="0"
                    data-per-view-sp="1"
                    data-spacing-sp="20">
                    <div class="keen-slider">
                        <div class="keen-slider__slide">
                            <div class="blockBox blockBox--flex">
                                <a class="blockBox__link" href="/present/0000/" title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                                <div class="blockBox__thum"><div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--1.webp" alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店" loading="lazy" width="1900" height="1270"></div></div>
                                <div class="blockBox__info" data-boxBgColor="bodyMypage">
                                    <p class="fs--17 textHover__target blockTitle">Dr.ハウシュカ <br>アフターサンケア</p>
                                    <p class="fontW--r textColor--footer blockSubText">日焼け後のほてりや乾燥しがちな肌をクールダウンさせ、潤いを与え、肌本来の健やかな力をサポートします。日焼け後のほてりや乾燥しがちな肌をクールダウンさせ、潤いを与え、肌本来の健やかな力をサポートします。</p>
                                     <div class="btn btnShaped btnShaped__border" data-shaped="auto-38">
                                        <div class="flex--cc btnLink">
                                            <p class="btnTtext">記事を読んでプレゼントに応募する</p>
                                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="keen-slider__slide">
                            <div class="blockBox blockBox--flex">
                                <a class="blockBox__link" href="/gift-form/" target="_blank" title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                                <div class="blockBox__thum"><div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--2.webp" alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店" loading="lazy" width="1900" height="1270"></div></div>
                                <div class="blockBox__info" data-boxBgColor="bodyMypage">
                                    <p class="fs--17 textHover__target blockTitle">Dr.ハウシュカ <br>アフターサンケア</p>
                                    <p class="fontW--r textColor--footer blockSubText">日焼け後のほてりや乾燥しがちな肌をクールダウンさせ、潤いを与え、肌本来の健やかな力をサポートします。</p>
                                    <div class="btn btnShaped btnShaped__border" data-shaped="auto-38">
                                        <div class="flex--cc btnLink">
                                            <p class="btnTtext">プレゼントに応募する</p>
                                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="keen-slider__slide">
                            <div class="blockBox blockBox--flex">
                                <div class="blockBox__thum"><div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--2.webp" alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店" loading="lazy" width="1900" height="1270"></div></div>
                                <div class="blockBox__info" data-boxBgColor="bodyMypage">
                                    <p class="fs--17 textHover__target blockTitle">Dr.ハウシュカ <br>アフターサンケア</p>
                                    <p class="fontW--r textColor--footer blockSubText">日焼け後のほてりや乾燥しがちな肌をクールダウンさせ、潤いを与え、肌本来の健やかな力をサポートします。</p>
                                    <div class="btn btnShaped btnShaped__border" data-shaped="auto-38">
                                        <div class="flex--cc btnLink">
                                            <p class="btnTtext">終了しました</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- keen dots -->
                    <div class="keen-dots fontEn" data-keen-dots></div>
                    <!-- keen button -->
                    <button type="button" data-keen-prev aria-label="prev">
                        <div class="btnShaped" data-shaped="30-30">
                            <div class="btnArrow btnArrow--prev" data-arrow="w-8"></div>
                        </div>
                    </button>
                    <button type="button" data-keen-next aria-label="next">
                        <div class="btnShaped" data-shaped="30-30">
                            <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                        </div>
                    </button>
                    <div class="btn btnShaped btnBgColor btnAll" data-shaped="120-28">
                        <a class="flex--cc btnLink" href="/present/" aria-label="すべてみる" title="すべてみる">
                            <p class="btnTtext">すべてみる</p>
                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                        </a>
                    </div>
                </div>
            </section>
            <!-- ------------------------------------- ↓ couponSection ↓　-->
            <section class="keenSlider__wrapper couponSection flexColumnBox borderBox">
                <h2 class="title fs--19">TOKK関西クーポン</h2>
                <div class="blockBox blockBox--flex blockBox--flex--half blockBox__coupon">
                    <a class="blockBox__link" href="/coupon/0000/" title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                    <div class="blockBox__thum"><div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/coupon.webp" alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店" loading="lazy" width="1900" height="1270"></div></div>
                    <div class="blockBox__info" data-boxBgColor="bodyMypage">
                        <p class="fs--17 textHover__target blockTitle">お得なクーポン配布中</p>
                        <p class="fontW--r textColor--footer blockSubText">無料でお使いいただける、お得なクーポンを配信中！TOKK関西で配信中のクーポンを要チェック。</p>
                        <div class="btn btnShaped btnShaped__border" data-shaped="auto-38">
                            <div class="flex--cc btnLink">
                                <p class="btnTtext">クーポンをチェック</p>
                                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            </div>
        </article>


        <div class="mypageCancellation">
            <a href="<?php echo esc_url(home_url('/cancel-input/')); ?>" class="cancellation">会員退会</a>
        </div>


    </main>
<?php get_footer(); ?>
