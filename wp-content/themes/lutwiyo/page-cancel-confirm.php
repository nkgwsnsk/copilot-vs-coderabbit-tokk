<?php
/* Template Name: 会員解約画面2（確認） */

$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);

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
$memberInfoResult = (string) ($memberInfoBody['result'] ?? '');
$hasLocalMember = array_key_exists('local_member', $memberInfoBody)
    && is_array($memberInfoBody['local_member']);

$localMember = $hasLocalMember
    ? (array) $memberInfoBody['local_member']
    : [];

$isStandardMember = (int) ($localMember['member_rank_id'] ?? 0) === 2;
$isFreeMember = !$isStandardMember;
$membershipExpiredAt = trim((string) ($localMember['membership_expired_at'] ?? ''));
$membershipExpiredAtTimestamp = $membershipExpiredAt !== '' ? strtotime($membershipExpiredAt) : false;
$isMembershipExpiredForStandard = $isStandardMember
    && $membershipExpiredAtTimestamp !== false
    && (int) $membershipExpiredAtTimestamp < (int) current_time('timestamp');
$serviceCancelScheduledAt = trim((string) ($localMember['service_cancel_scheduled_at'] ?? ''));
$cancelledAt = trim((string) ($localMember['cancelled_at'] ?? ''));
$isFreeCancelledWithoutLocalMember = !$hasLocalMember && $memberInfoResult === 'ok';
$isCancelledUser = $cancelledAt !== ''
    || ($isStandardMember && $serviceCancelScheduledAt !== '')
    || $isFreeCancelledWithoutLocalMember;

if ($isCancelledUser) {
    wp_safe_redirect(home_url('/cancel-complete/'));
    exit;
}

$cancelErrorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = isset($_POST['cancel_confirm_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['cancel_confirm_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'tokk_cancel_confirm_action')) {
        $cancelErrorMessage = 'セッションの有効期限が切れました。再度お試しください。';
    } elseif ($isMembershipExpiredForStandard) {
        $cancelErrorMessage = '会員有効期限の自動延伸処理中です。退会は実行できません。退会を行う場合は会員有効期限内に実施してください。';
    } else {
        $serviceCancelResponse = lutwiyo_call_bridge_api('service_cancel');
        $serviceCancelBody = is_array($serviceCancelResponse)
            ? (array) ($serviceCancelResponse['body'] ?? [])
            : [];

        $isServiceCancelSuccess = is_array($serviceCancelResponse)
            && (int) ($serviceCancelResponse['status'] ?? 0) === 200
            && ($serviceCancelBody['result'] ?? '') === 'ok';

        if ($isServiceCancelSuccess) {
            $redirectArgs = [
                'service_status' => (string) ($serviceCancelBody['service_status'] ?? ''),
                'service_cancel_scheduled_at' => (string) ($serviceCancelBody['service_cancel_scheduled_at'] ?? ''),
                'cancelled_at' => (string) ($serviceCancelBody['cancelled_at'] ?? ''),
            ];

            if ($isFreeMember) {
                $logoutResponse = lutwiyo_call_bridge_api('logout', [
                    'slo_logout' => '0',
                ]);
                $logoutReason = is_array($logoutResponse)
                    ? trim((string) ($logoutResponse['body']['reason'] ?? ''))
                    : '';
                $isLogoutSuccess = is_array($logoutResponse)
                    && (int) ($logoutResponse['status'] ?? 0) === 200
                    && in_array($logoutReason, ['revoked', 'already_logged_out'], true);

                if ($isLogoutSuccess) {
                    $oneTimeCompleteToken = wp_generate_password(20, false, false);
                    set_transient('tokk_cancel_complete_once_' . $oneTimeCompleteToken, '1', 5 * MINUTE_IN_SECONDS);
                    $redirectArgs['free_cancel_once'] = $oneTimeCompleteToken;
                }
            }

            wp_safe_redirect(add_query_arg($redirectArgs, home_url('/cancel-complete/')));
            exit;
        }

        $cancelReason = trim((string) ($serviceCancelBody['reason'] ?? ''));
        if ($cancelReason === 'membership_expired') {
            $cancelErrorMessage = '会員有効期限の自動延伸処理中です。退会は実行できません。退会を行う場合は会員有効期限内に実施してください。';
        } else {
            $cancelErrorMessage = 'サービス解約に失敗しました。時間をおいて再度お試しください。';
        }
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
                }
                .tokkSimplePageActions .memberFlowPrimaryBtn__title,
                .tokkSimplePageActions .privateUserBtn__title {
                    color: #3a3a3a;
                    font-size: 13px;
                }
                .tokkSimplePageWarning {
                    margin-top: 16px;
                    color: #d00;
                    font-weight: 700;
                    line-height: 1.8;
                }
                .tokkSimplePageActions .memberFlowPrimaryBtn[disabled] {
                    opacity: 0.45;
                    cursor: not-allowed;
                }
                @media only screen and (max-width: 767px) {
                    .tokkSimplePage {
                        padding-bottom: 150px;
                    }
                }
            </style>

            <div class="tokkSimplePage">
            <h1 class="title fs--22 tokkSimplePageTitle">会員退会確認</h1>

            <?php if ($cancelErrorMessage !== ''): ?>
                <p style="margin-top:16px;color:#d00;"><?php echo esc_html($cancelErrorMessage); ?></p>
            <?php endif; ?>

            <?php if ($isFreeMember): ?>
                <p class="tokkSimplePageLead">フリー（無料）会員の退会は実行後、即時で適用されます。よろしければ退会ボタンを押してください。</p>
            <?php else: ?>
                <p class="tokkSimplePageLead">スタンダード会員の退会は即時ではなく、会員期限日に退会されます。よろしければ退会ボタンを押してください。</p>
            <?php endif; ?>
            <?php if ($isMembershipExpiredForStandard): ?>
                <p class="tokkSimplePageWarning">会員有効期限の自動延伸処理中です。退会は実行できません。退会を行う場合は会員有効期限内に実施してください。</p>
            <?php endif; ?>

            <div class="tokkSimplePageActionsGroup">
                <form method="post" action="" class="tokkSimplePageActions">
                    <?php wp_nonce_field('tokk_cancel_confirm_action', 'cancel_confirm_nonce'); ?>
                    <button type="submit" class="memberFlowPrimaryBtn" <?php echo $isMembershipExpiredForStandard ? 'disabled' : ''; ?>>
                        <span class="memberFlowPrimaryBtn__title">退会する</span>
                    </button>
                </form>
                <p class="tokkSimplePageActions">
                    <a href="<?php echo esc_url(home_url('/cancel-input/')); ?>" class="privateUserBtn" role="link" title="戻る">
                        <span class="privateUserBtn__title">戻る</span>
                    </a>
                </p>
            </div>
                    </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
