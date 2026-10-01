<?php
/* Template Name: スタンダード（有料）会員登録画面1（説明） */

$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);

// paidフロー開始元URLをCookieへ保持し、途中ページ遷移時は既存Cookieを維持する。
$paidReturnToCookieName = 'tokk_paid_return_to';
$paidProfileRedirectBypassCookieName = 'tokk_paid_profile_redirect_bypass_once';
$paidReturnToFallbackUrl = '/';
$paidRefererRaw = wp_get_referer();
$paidReferer = is_string($paidRefererRaw) ? wp_validate_redirect($paidRefererRaw, '') : '';
$paidReturnToUrl = $paidReturnToFallbackUrl;
$paidReturnToFromQueryRaw = isset($_GET['return_to'])
    ? trim((string) wp_unslash($_GET['return_to']))
    : '';
$hasExplicitPaidReturnTo = $paidReturnToFromQueryRaw !== '';
$paidReturnToFromQueryValidated = $hasExplicitPaidReturnTo
    ? wp_validate_redirect($paidReturnToFromQueryRaw, '')
    : '';

// return_to が明示指定されている場合は最優先とし、無効値はトップへフォールバックする。
if ($hasExplicitPaidReturnTo) {
    $paidReturnToUrl = $paidReturnToFromQueryValidated !== ''
        ? lutwiyo_normalize_paid_return_to($paidReturnToFromQueryRaw, $paidReturnToFallbackUrl)
        : $paidReturnToFallbackUrl;
}

if (!$hasExplicitPaidReturnTo && isset($_COOKIE[$paidReturnToCookieName])) {
    $cookieValue = sanitize_text_field(wp_unslash($_COOKIE[$paidReturnToCookieName]));
    $paidReturnToUrl = lutwiyo_normalize_paid_return_to($cookieValue, $paidReturnToFallbackUrl);
}

if (!$hasExplicitPaidReturnTo && $paidReferer !== '') {
    $paidRefererPath = (string) wp_parse_url($paidReferer, PHP_URL_PATH);
    $isIntermediatePaidPage = in_array(
        untrailingslashit($paidRefererPath),
        [
            '/paid-exp',
            '/paid-terms_of_service',
            '/paid-confirm',
        ],
        true
    );

    $isIntermediatePaidPage = $isIntermediatePaidPage || str_starts_with(untrailingslashit($paidRefererPath), '/member-service/api/v1/crosspay/callback_endpoint');

    if (!$isIntermediatePaidPage) {
        $paidReturnToUrl = lutwiyo_normalize_paid_return_to($paidReferer, $paidReturnToFallbackUrl);
    }
}

setcookie($paidReturnToCookieName, $paidReturnToUrl, lutwiyo_cookie_options(time() + 1800));

$paidReturnToPath = (string) wp_parse_url($paidReturnToUrl, PHP_URL_PATH);
$isMyPageOrigin = $paidReturnToPath !== ''
    && preg_match('#^/mypage(?:-edit)?(?:/|$)#', $paidReturnToPath) === 1;

$loginStatusResponse = lutwiyo_call_bridge_api('login_status');
$isLoggedIn = is_array($loginStatusResponse)
    && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';
$isLoginStartRequested = isset($_GET['tokk_member_login'])
    && (string) $_GET['tokk_member_login'] === '1';


$loginRetryCookieName = 'tokk_paid_member_login_retry';
$hasLoginRetried = isset($_COOKIE[$loginRetryCookieName])
    && (string) $_COOKIE[$loginRetryCookieName] === '1';

if (!$isLoggedIn && $isLoginStartRequested) {
    setcookie($loginRetryCookieName, '1', lutwiyo_cookie_options(time() + 300));
}

if ($isLoggedIn && $hasLoginRetried) {
    setcookie($loginRetryCookieName, '', lutwiyo_cookie_options(time() - 3600));
    $hasLoginRetried = false;
}

if (!$isLoggedIn && !$isLoginStartRequested) {
    lutwiyo_render_inline_login_guide_and_exit($currentUrlForReturn);
}

$memberInfoResponse = lutwiyo_call_bridge_api('member_info', [
    'content' => 'sub,data',
]);
$memberInfoBody = is_array($memberInfoResponse)
    ? (array) ($memberInfoResponse['body'] ?? [])
    : [];
$localMember = is_array($memberInfoBody['local_member'] ?? null)
    ? (array) $memberInfoBody['local_member']
    : [];
$isStandardMember = (int) ($localMember['member_rank_id'] ?? 0) === 2;

if ($isStandardMember) {
    setcookie($paidProfileRedirectBypassCookieName, '', lutwiyo_cookie_options(time() - 3600));
    wp_safe_redirect(home_url('/'));
    exit;
}

if (!$isMyPageOrigin && $paidReturnToPath !== '') {
    setcookie($paidProfileRedirectBypassCookieName, $paidReturnToUrl, lutwiyo_cookie_options(time() + 1800));
} else {
    setcookie($paidProfileRedirectBypassCookieName, '', lutwiyo_cookie_options(time() - 3600));
}

$paidPlanLabel = 'スタンダード（有料）会員';
$paidPlanAmountText = '金額取得に失敗しました';

$planAmountResponse = lutwiyo_call_bridge_api('payment_plan_amount', [
    'plan_type' => 'C',
]);

if (is_array($planAmountResponse) && ($planAmountResponse['body']['result'] ?? '') === 'ok') {
    $amount = (int) ($planAmountResponse['body']['amount'] ?? 0);
    if ($amount > 0) {
        $paidPlanAmountText = number_format($amount) . '円（税込）/月';
    }
}

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
                    padding-bottom: 120px;
                }
                .tokkSimplePageTitle {
                    text-align: center;
                }
                .tokkSimplePageLead {
                    margin-top: 16px;
                    line-height: 1.8;
                }
                .tokkSimplePageCard {
                    margin-top: 24px;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 12px;
                    background: #fff;
                }
                .tokkSimplePageCard p + p {
                    margin-top: 8px;
                }
                .tokkSimplePageActions {
                    display: flex;
                    justify-content: center;
                    margin: 0;
                }
                .tokkSimplePageActionsGroup {
                    margin-top: 24px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 12px;
                }
                .tokkSimplePageActions .memberFlowPrimaryBtn,
                .tokkSimplePageActions .privateUserBtn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 220px;
                    max-width: 100%;
                    min-height: 50px;
                    border: 1px solid rgba(58, 58, 58, 0.75);
                    border-radius: 50px;
                    padding: 0 24px;
                    font-size: 17px;
                    background: #fff;
                    white-space: normal;
                    text-decoration: none;
                    position: static;
                    margin: 0;
                    color: #3a3a3a;
                }
                .tokkSimplePageActions .memberFlowPrimaryBtn__title,
                .tokkSimplePageActions .privateUserBtn__title {
                    color: #3a3a3a;
                    font-size: 13px;
                }
                @media only screen and (max-width: 767px) {
                    .tokkSimplePage {
                        padding-bottom: 150px;
                    }
                }
            </style>

            <div class="tokkSimplePage">
                <h1 class="title fs--22 tokkSimplePageTitle">スタンダード（有料）会員登録</h1>
                <p class="tokkSimplePageLead">スタンダード（有料）会員に登録すると、限定記事の閲覧やスタンダード（有料）会員向け特典をご利用いただけます。</p>

                <div class="tokkSimplePageCard">
                    <p><strong>プラン名：</strong><?php echo esc_html($paidPlanLabel); ?></p>
                    <p><strong>料金：</strong><?php echo esc_html($paidPlanAmountText); ?></p>
                    <p>※ お申し込み内容は次画面で規約をご確認のうえ、登録手続きを行ってください。</p>
                </div>

                <div class="tokkSimplePageActionsGroup">
                    <p class="tokkSimplePageActions">
                        <a href="<?php echo esc_url(home_url('/paid-terms_of_service/')); ?>" class="memberFlowPrimaryBtn" role="link" title="利用規約へ">
                            <span class="memberFlowPrimaryBtn__title">利用規約へ</span>
                        </a>
                    </p>
                    <p class="tokkSimplePageActions">
                        <a href="<?php echo esc_url(home_url('/mypage/')); ?>" class="privateUserBtn" role="link" title="戻る">
                            <span class="privateUserBtn__title">戻る</span>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
