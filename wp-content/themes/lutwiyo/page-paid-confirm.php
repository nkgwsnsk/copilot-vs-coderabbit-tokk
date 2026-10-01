<?php
/* Template Name: 有料会員登録画面3（申込確認） */

$nonce = isset($_POST['paid_term_nonce'])
    ? sanitize_text_field(wp_unslash($_POST['paid_term_nonce']))
    : '';
$isAgreed = isset($_POST['agree_terms']) && (string) wp_unslash($_POST['agree_terms']) === '1';
$isValidAccess = $_SERVER['REQUEST_METHOD'] === 'POST'
    && wp_verify_nonce($nonce, 'tokk_paid_term_action')
    && $isAgreed;

$paidConfirmCookieName = 'tokk_paid_confirm_allowed';
$paidReturnToCookieName = 'tokk_paid_return_to';
$paidPreauthCookieName = 'tokk_paid_preauth_pending';
$paidPreauthStateCookieName = 'tokk_paid_preauth_state';
$paidConfirmErrorMessage = '';
$hasCallbackPaymentError = isset($_GET['payment_error']) && (string) $_GET['payment_error'] === '1';
$payPreauthResult = isset($_GET['pay_preauth'])
    ? sanitize_key(wp_unslash((string) $_GET['pay_preauth']))
    : '';
$payPreauthState = isset($_GET['pay_preauth_state'])
    ? sanitize_text_field(wp_unslash((string) $_GET['pay_preauth_state']))
    : '';
$hasPayPreauthError = $payPreauthResult === 'error';
$hasPayPreauthSuccess = $payPreauthResult === 'ok';
$hasPayPreauthCallback = $payPreauthResult !== '';

if ($hasCallbackPaymentError) {
    $paidConfirmErrorMessage = '決済確認に失敗しました。時間をおいて再度お試しください。';
}
if ($hasPayPreauthError) {
    $paidConfirmErrorMessage = '利用規約の確認に失敗しました。時間をおいて再度お試しください。';
}

$clearPayPreauthCookies = static function () use ($paidPreauthCookieName, $paidPreauthStateCookieName): void {
    setcookie($paidPreauthCookieName, '', lutwiyo_cookie_options(time() - 3600));
    setcookie($paidPreauthStateCookieName, '', lutwiyo_cookie_options(time() - 3600));
};

if ($hasCallbackPaymentError || $hasPayPreauthError || ($payPreauthResult !== '' && !$hasPayPreauthSuccess)) {
    $clearPayPreauthCookies();
}

// 規約同意済みのPOSTのみ決済実行を許可し、保存済みの戻り先URLを使って決済リクエストを開始する。
$loginStatusResponse = lutwiyo_call_bridge_api('login_status');
$isLoggedIn = is_array($loginStatusResponse)
    && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';

if (!$isLoggedIn) {
    wp_safe_redirect(home_url('/paid-exp/'));
    exit;
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
    wp_safe_redirect(home_url('/'));
    exit;
}

if ($isValidAccess) {
    // 規約同意済みでこの画面に到達したことを短時間cookieに保存し、直アクセスでの決済実行を防ぐ。
    setcookie($paidConfirmCookieName, '1', lutwiyo_cookie_options(time() + 1800));
}

$postedAction = isset($_POST['paid_confirm_action'])
    ? sanitize_text_field(wp_unslash($_POST['paid_confirm_action']))
    : '';
$isPaymentSubmit = $_SERVER['REQUEST_METHOD'] === 'POST' && $postedAction === 'payment_request';
$shouldStartPayPreauth = false;
$shouldExecutePaymentRequest = false;
$paymentRequestAutoTriggered = false;

if ($isPaymentSubmit) {
    // 決済実行POST時は nonce と許可cookie を検証し、規約確認フローを経由した操作のみ受け付ける。
    $confirmNonce = isset($_POST['paid_confirm_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['paid_confirm_nonce']))
        : '';

    $hasAllowedCookie = isset($_COOKIE[$paidConfirmCookieName])
        && (string) $_COOKIE[$paidConfirmCookieName] === '1';

    if (!wp_verify_nonce($confirmNonce, 'tokk_paid_confirm_action') || !$hasAllowedCookie) {
        wp_safe_redirect(home_url('/paid-terms_of_service/'));
        exit;
    }

    $shouldStartPayPreauth = true;
}

if ($hasPayPreauthSuccess) {
    $hasPreauthCookie = isset($_COOKIE[$paidPreauthCookieName])
        && (string) $_COOKIE[$paidPreauthCookieName] === '1';
    $preauthStateCookieValue = isset($_COOKIE[$paidPreauthStateCookieName])
        ? sanitize_text_field(wp_unslash((string) $_COOKIE[$paidPreauthStateCookieName]))
        : '';
    $isValidPreauthState = $payPreauthState !== ''
        && hash_equals($preauthStateCookieValue, $payPreauthState);

    if ($hasPreauthCookie && $isValidPreauthState) {
        $shouldExecutePaymentRequest = true;
        $paymentRequestAutoTriggered = true;
        // callback復帰後の自動実行は1回だけにし、リロード時の再送を防ぐ。
        $clearPayPreauthCookies();
    } else {
        $clearPayPreauthCookies();
        $paidConfirmErrorMessage = 'セッションの有効期限が切れました。もう一度お試しください。';
    }
}

if ($shouldStartPayPreauth || $shouldExecutePaymentRequest) {
    // ログイン中会員のUIDを取得し、Bridge APIの payment_request に必要な client_id を組み立てる。
    $memberUid = '';
    if (($memberInfoBody['result'] ?? '') === 'ok') {
        $memberUid = trim((string) ($memberInfoBody['uid'] ?? ''));
    }

    if ($memberUid === '') {
        $paidConfirmErrorMessage = '会員情報の取得に失敗しました。時間をおいて再度お試しください。';
    } else {
        // paid-exp 側で保持した戻り先URL(cookie)を current_url に引き継ぎ、決済完了後に元ページへ戻せるようにする。
        $paidReturnToUrl = '/';
        if (isset($_COOKIE[$paidReturnToCookieName])) {
            $cookieValue = sanitize_text_field(wp_unslash($_COOKIE[$paidReturnToCookieName]));
            $paidReturnToUrl = lutwiyo_normalize_paid_return_to($cookieValue, '/');
        }

        if ($shouldStartPayPreauth) {
            // 要件: 初回決済前に毎回 web-auth(mode=pay) を通し、外部認証基盤の規約画面を必ず経由する。
            $payPreauthStateToken = wp_generate_password(32, false, false);
            setcookie($paidPreauthCookieName, '1', lutwiyo_cookie_options(time() + 1800));
            setcookie($paidPreauthStateCookieName, $payPreauthStateToken, lutwiyo_cookie_options(time() + 1800));
            $payPreauthResponse = lutwiyo_call_bridge_api('login', [
                'current_url' => add_query_arg([
                    'pay_preauth' => 'ok',
                    'pay_preauth_state' => $payPreauthStateToken,
                ], home_url('/paid-confirm/')),
                'mode' => 'pay',
                'pay_preauth' => '1',
            ]);

            if (is_array($payPreauthResponse)
                && ($payPreauthResponse['body']['result'] ?? '') === 'redirect'
                && trim((string) ($payPreauthResponse['body']['redirect_to'] ?? '')) !== '') {
                wp_redirect((string) $payPreauthResponse['body']['redirect_to']);
                exit;
            }

            $clearPayPreauthCookies();
            $paidConfirmErrorMessage = '利用規約画面への遷移に失敗しました。時間をおいて再度お試しください。';
        } else {
            $paymentRequestResponse = lutwiyo_call_bridge_api('payment_request', [
                'client_id' => $memberUid,
                'current_url' => $paidReturnToUrl,
                'mode' => 'BySystem',
                'method_list' => 'credit',
            ]);

            if (is_array($paymentRequestResponse)
                && ($paymentRequestResponse['body']['result'] ?? '') === 'redirect'
                && trim((string) ($paymentRequestResponse['body']['redirect_to'] ?? '')) !== '') {
                // 外部決済画面へ遷移できる場合は許可cookieを破棄し、二重送信を避けてリダイレクトする。
                setcookie($paidConfirmCookieName, '', lutwiyo_cookie_options(time() - 3600));
                wp_redirect((string) $paymentRequestResponse['body']['redirect_to']);
                exit;
            }

            if (is_array($paymentRequestResponse)) {
                $responseBody = is_array($paymentRequestResponse['body'] ?? null)
                    ? $paymentRequestResponse['body']
                    : [];
                error_log(sprintf(
                    '[tokk_paid_confirm] payment_request failed. status=%d result=%s reason=%s external_status=%s call_id=%s error=%s error_description=%s',
                    (int) ($paymentRequestResponse['status'] ?? 0),
                    (string) ($responseBody['result'] ?? ''),
                    (string) ($responseBody['reason'] ?? ''),
                    isset($responseBody['external_status']) ? (string) $responseBody['external_status'] : '',
                    (string) ($responseBody['call_id'] ?? ''),
                    (string) ($responseBody['error'] ?? ''),
                    (string) ($responseBody['error_description'] ?? '')
                ));
            } else {
                error_log('[tokk_paid_confirm] payment_request failed. response is null or invalid.');
            }

            $paidConfirmErrorMessage = '決済画面への遷移に失敗しました。時間をおいて再度お試しください。';
        }
    }
}

if (!$isValidAccess && !$isPaymentSubmit && !$hasCallbackPaymentError && !$hasPayPreauthCallback && !$paymentRequestAutoTriggered) {
    wp_safe_redirect(home_url('/paid-terms_of_service/'));
    exit;
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
                .tokkSimplePageActions {
                    display: flex;
                    justify-content: center;
                    margin: 0;
                    color: #3a3a3a;
                }
                .tokkSimplePageActionsGroup {
                    margin-top: 24px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 12px;
                }
                .tokkSimplePageActions .memberFlowPrimaryBtn,
                .tokkSimplePageActions .privateUserBtn,
                .tokkSimplePage .memberFlowPrimaryBtn,
                .tokkSimplePage .privateUserBtn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 360px;
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
            <h1 class="title fs--22 tokkSimplePageTitle">スタンダード（有料）会員 申込確認</h1>

            <div class="tokkSimplePageCard">
                <p><strong>プラン名：</strong>スタンダード（有料）会員</p>
                <p style="margin-top:8px;"><strong>課金開始日：</strong>決済確定日から1ヶ月</p>
                <p style="margin-top:8px;"><strong>注意事項：</strong>HH cross PAYの画面に遷移します。途中でブラウザの戻るボタンを押さないでください。</p>
                <p style="margin-top:16px;">
                    <a href="https://www.hhcross.hankyu-hanshin.jp/pay/" target="_blank" rel="noopener noreferrer" style="text-decoration: underline; text-underline-offset: 2px;">HH cross PAYとは？（別タブで開きます）</a>
                </p>
            </div>

            <?php if ($paidConfirmErrorMessage !== ''): ?>
                <p style="margin-top:16px;color:#d00;"><?php echo esc_html($paidConfirmErrorMessage); ?></p>
            <?php endif; ?>

            <div class="tokkSimplePageActionsGroup">
                <form method="post" action="" class="tokkSimplePageActions">
                    <?php wp_nonce_field('tokk_paid_confirm_action', 'paid_confirm_nonce'); ?>
                    <input type="hidden" name="paid_confirm_action" value="payment_request">
                    <button type="submit" class="memberFlowPrimaryBtn">
                        <span class="memberFlowPrimaryBtn__title">お支払いの設定へ（HH cross PAY）</span>
                    </button>
                </form>
                <p class="tokkSimplePageActions">
                    <a href="<?php echo esc_url(home_url('/paid-terms_of_service/')); ?>" class="privateUserBtn" role="link" title="戻る">
                        <span class="privateUserBtn__title">戻る</span>
                    </a>
                </p>
            </div>
                    </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
