<?php
/* Template Name: 有料会員登録画面4（継続課金カード設定必須） */

$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);
$requiredBaseUrl = remove_query_arg(['tokk_member_login'], $currentUrlForReturn);
$requiredErrorMessage = '';

$loginStatusResponse = lutwiyo_call_bridge_api('login_status');
$isLoggedIn = is_array($loginStatusResponse)
    && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';
$isLoginStartRequested = isset($_GET['tokk_member_login'])
    && (string) $_GET['tokk_member_login'] === '1';

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

if (!$isStandardMember) {
    wp_safe_redirect(home_url('/mypage/'));
    exit;
}

$paymethodStatusResponse = lutwiyo_call_bridge_api('paymethod_status');
$paymethodStatusBody = is_array($paymethodStatusResponse)
    ? (array) ($paymethodStatusResponse['body'] ?? [])
    : [];
$paymethodMainStatus = trim((string) ($paymethodStatusBody['paymethod_main_status'] ?? 'unknown'));
$originReturnToFromApi = trim((string) ($paymethodStatusBody['origin_return_to'] ?? ''));
$safeOriginReturnTo = '';
if ($originReturnToFromApi !== '') {
    $safeOriginReturnTo = (string) wp_validate_redirect($originReturnToFromApi, '');
}

if (($paymethodStatusBody['result'] ?? '') === 'ok' && $paymethodMainStatus === 'set') {
    if ($safeOriginReturnTo !== '') {
        wp_safe_redirect($safeOriginReturnTo);
        exit;
    }

    wp_safe_redirect(home_url('/mypage/'));
    exit;
}

$postedAction = isset($_POST['payment_method_required_action'])
    ? sanitize_text_field(wp_unslash($_POST['payment_method_required_action']))
    : '';

$isPaymentMethodPost = $_SERVER['REQUEST_METHOD'] === 'POST' && $postedAction === 'open_payment_method';
if ($isPaymentMethodPost) {
    $nonce = isset($_POST['payment_method_required_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['payment_method_required_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'tokk_payment_method_required_action')) {
        $requiredErrorMessage = 'セッションの有効期限が切れました。再度お試しください。';
    } else {
        $response = lutwiyo_call_bridge_api('payment_method', [
            'current_url' => $safeOriginReturnTo !== '' ? $safeOriginReturnTo : $requiredBaseUrl,
        ]);

        if (is_array($response)
            && ($response['body']['result'] ?? '') === 'redirect'
            && trim((string) ($response['body']['redirect_to'] ?? '')) !== '') {
            wp_redirect((string) $response['body']['redirect_to']);
            exit;
        }

        $requiredErrorMessage = '決済方法画面への遷移に失敗しました。時間をおいて再度お試しください。';
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
                .tokkSimplePageActions {
                    margin-top: 24px;
                    display: flex;
                    justify-content: center;
                }
                .tokkSimplePageActions .loginBtn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 50px;
                    min-width: 320px;
                    border: 1px solid rgba(58, 58, 58, 0.75);
                    border-radius: 50px;
                    padding: 0 36px;
                    font-size: 17px;
                    line-height: 1.5;
                    background: #fff;
                    white-space: nowrap;
                    text-decoration: none;
                    color: #3a3a3a;
                }
                .tokkSimplePageActions .loginBtn__title {
                    color: #3a3a3a;
                }
                @media only screen and (max-width: 767px) {
                    .tokkSimplePage {
                        padding-bottom: 150px;
                    }
                    .tokkSimplePageActions .loginBtn {
                        min-width: min(320px, 100%);
                    }
                }
            </style>

            <div class="tokkSimplePage">
                <h1 class="title fs--22 tokkSimplePageTitle">継続課金カード設定のお願い</h1>
                <p class="tokkSimplePageLead">スタンダード会員登録は完了しています。継続課金を有効化するため、カードの「継続払いメイン設定」を完了してください。以下のボタンよりHH cross PAYの継続払いを行うクレジットカードが登録可能です。</p>

                <?php if ($requiredErrorMessage !== ''): ?>
                    <p style="margin-top:16px;color:#d00;"><?php echo esc_html($requiredErrorMessage); ?></p>
                <?php endif; ?>

                <div class="tokkSimplePageActionsGroup">
                    <form method="post" action="" class="tokkSimplePageActions">
                        <?php wp_nonce_field('tokk_payment_method_required_action', 'payment_method_required_nonce'); ?>
                        <input type="hidden" name="payment_method_required_action" value="open_payment_method">
                        <button type="submit" class="loginBtn">
                            <span class="loginBtn__title">決済方法を設定する</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
