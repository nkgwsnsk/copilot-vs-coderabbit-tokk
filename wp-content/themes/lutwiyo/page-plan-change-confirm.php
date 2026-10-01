<?php
/* Template Name: 会員プラン変更画面2（確認） */

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
$localMember = is_array($memberInfoBody['local_member'] ?? null)
    ? (array) $memberInfoBody['local_member']
    : [];
$isStandardMember = (int) ($localMember['member_rank_id'] ?? 0) === 2;
$membershipExpiredAt = trim((string) ($localMember['membership_expired_at'] ?? ''));
$membershipExpiredAtTimestamp = $membershipExpiredAt !== '' ? strtotime($membershipExpiredAt) : false;
$isMembershipExpiredForStandard = $isStandardMember
    && $membershipExpiredAtTimestamp !== false
    && (int) $membershipExpiredAtTimestamp < (int) current_time('timestamp');
$planDowngradeScheduledAt = trim((string) ($localMember['plan_downgrade_scheduled_at'] ?? ''));

if (!$isStandardMember) {
    wp_safe_redirect(home_url('/mypage-edit/'));
    exit;
}

if ($planDowngradeScheduledAt !== '') {
    wp_safe_redirect(home_url('/plan-change-complete/'));
    exit;
}

$planChangeErrorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = isset($_POST['plan_change_confirm_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['plan_change_confirm_nonce']))
        : '';

    if (!wp_verify_nonce($nonce, 'tokk_plan_change_confirm_action')) {
        $planChangeErrorMessage = 'セッションの有効期限が切れました。再度お試しください。';
    } elseif ($isMembershipExpiredForStandard) {
        $planChangeErrorMessage = '会員有効期限の自動延伸処理中です。プラン変更は実行できません。プラン変更を行う場合は会員有効期限内に実施してください。';
    } else {
        $planChangeResponse = lutwiyo_call_bridge_api('plan_change');
        $planChangeBody = is_array($planChangeResponse)
            ? (array) ($planChangeResponse['body'] ?? [])
            : [];

        $isPlanChangeSuccess = is_array($planChangeResponse)
            && (int) ($planChangeResponse['status'] ?? 0) === 200
            && ($planChangeBody['result'] ?? '') === 'ok';

        if ($isPlanChangeSuccess) {
            wp_safe_redirect(home_url('/plan-change-complete/'));
            exit;
        }

        $planChangeReason = trim((string) ($planChangeBody['reason'] ?? ''));
        if ($planChangeReason === 'membership_expired') {
            $planChangeErrorMessage = '会員有効期限の自動延伸処理中です。プラン変更は実行できません。プラン変更を行う場合は会員有効期限内に実施してください。';
        } else {
            $planChangeErrorMessage = '会員プラン変更の受付に失敗しました。時間をおいて再度お試しください。';
        }
    }
}

get_header();
?>

<main class="main wrapper" role="main">
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <div class="gridWide">
            <style>
                .tokkSimplePage { margin: 0 auto; max-width: 560px; font-size: 16px; padding-bottom: 120px; }
                .tokkSimplePageTitle { text-align: center; }
                .tokkSimplePageLead { margin-top: 16px; line-height: 1.8; }
                .tokkSimplePageActions { display: flex; justify-content: center; margin: 0; color: #3a3a3a; }
                .tokkSimplePageActionsGroup { margin-top: 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; }
                .tokkSimplePageActions .memberFlowPrimaryBtn, .tokkSimplePageActions .privateUserBtn {
                    display: inline-flex; align-items: center; justify-content: center; width: 260px; max-width: 100%; min-height: 50px;
                    border: 1px solid rgba(58, 58, 58, 0.75); border-radius: 50px; padding: 0 24px; font-size: 17px; background: #fff;
                    white-space: normal; text-decoration: none; position: static; margin: 0;
                }
                .tokkSimplePageActions .memberFlowPrimaryBtn__title, .tokkSimplePageActions .privateUserBtn__title { color: #3a3a3a; font-size: 13px; }
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
            </style>

            <div class="tokkSimplePage">
                <h1 class="title fs--22 tokkSimplePageTitle">会員プラン変更確認</h1>

                <?php if ($planChangeErrorMessage !== ''): ?>
                    <p style="margin-top:16px;color:#d00;"><?php echo esc_html($planChangeErrorMessage); ?></p>
                <?php endif; ?>

                <p class="tokkSimplePageLead">会員有効期限まではスタンダード会員として利用でき、会員有効期限後にフリー会員へ変更されます。よろしければ受付ボタンを押してください。</p>
                <?php if ($isMembershipExpiredForStandard): ?>
                    <p class="tokkSimplePageWarning">会員有効期限の自動延伸処理中です。プラン変更は実行できません。プラン変更を行う場合は会員有効期限内に実施してください。</p>
                <?php endif; ?>

                <div class="tokkSimplePageActionsGroup">
                    <form method="post" action="" class="tokkSimplePageActions">
                        <?php wp_nonce_field('tokk_plan_change_confirm_action', 'plan_change_confirm_nonce'); ?>
                        <button type="submit" class="memberFlowPrimaryBtn" <?php echo $isMembershipExpiredForStandard ? 'disabled' : ''; ?>>
                            <span class="memberFlowPrimaryBtn__title">プラン変更を受け付ける</span>
                        </button>
                    </form>
                    <p class="tokkSimplePageActions">
                        <a href="<?php echo esc_url(home_url('/plan-change-input/')); ?>" class="privateUserBtn" role="link" title="戻る">
                            <span class="privateUserBtn__title">戻る</span>
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
