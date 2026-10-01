<?php
/* Template Name: 会員プラン変更画面1（説明） */

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
    wp_safe_redirect(home_url('/'));
    exit;
}

if ($planDowngradeScheduledAt !== '') {
    wp_safe_redirect(home_url('/plan-change-complete/'));
    exit;
}

$planChangeErrorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = isset($_POST['plan_change_input_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['plan_change_input_nonce']))
        : '';
    $isAgreed = isset($_POST['plan_change_agreed'])
        && sanitize_text_field(wp_unslash($_POST['plan_change_agreed'])) === '1';

    if (!wp_verify_nonce($nonce, 'tokk_plan_change_input_action')) {
        $planChangeErrorMessage = 'セッションの有効期限が切れました。再度お試しください。';
    } elseif (!$isAgreed) {
        $planChangeErrorMessage = '注意事項に同意してからプラン変更してください。';
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
            wp_safe_redirect(add_query_arg([
                'plan_change_status' => (string) ($planChangeBody['plan_change_status'] ?? ''),
                'plan_downgrade_scheduled_at' => (string) ($planChangeBody['plan_downgrade_scheduled_at'] ?? ''),
                'reason_code' => (string) ($planChangeBody['reason_code'] ?? ''),
            ], home_url('/plan-change-complete/')));
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
                .tokkSimplePageCard { margin-top: 24px; padding: 20px; border: 1px solid #ddd; border-radius: 12px; background: #fff; }
                .tokkSimplePageActions { display: flex; justify-content: center; margin: 0; color: #3a3a3a; }
                .tokkSimplePageActionsGroup { margin-top: 24px; display: flex; flex-direction: column; align-items: center; gap: 12px; }
                .tokkSimplePageConsent {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-top: 16px;
                    cursor: pointer;
                    font-size: 14px;
                    writing-mode: horizontal-tb;
                }
                .tokkSimplePageConsent input[type="checkbox"] {
                    appearance: none;
                    width: 20px;
                    height: 20px;
                    margin: 0;
                    border: 1px solid #555;
                    border-radius: 4px;
                    background: #fff;
                    position: relative;
                    cursor: pointer;
                    flex-shrink: 0;
                }
                .tokkSimplePageConsent input[type="checkbox"]:checked::after {
                    content: '';
                    position: absolute;
                    top: 1px;
                    left: 6px;
                    width: 5px;
                    height: 11px;
                    border: solid #111;
                    border-width: 0 2px 2px 0;
                    transform: rotate(45deg);
                }
                .tokkSimplePageConsent span {
                    display: inline-block;
                    writing-mode: horizontal-tb;
                }
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
                <h1 class="title fs--22 tokkSimplePageTitle">会員プラン変更</h1>

                <?php if ($planChangeErrorMessage !== ''): ?>
                    <p style="margin-top:16px;color:#d00;"><?php echo esc_html($planChangeErrorMessage); ?></p>
                <?php endif; ?>

                <p class="tokkSimplePageLead">スタンダード会員からフリー会員への変更に関するご案内です。内容をご確認のうえ、同意後にプラン変更してください。</p>
                <?php if ($isMembershipExpiredForStandard): ?>
                    <p class="tokkSimplePageWarning">会員有効期限の自動延伸処理中です。プラン変更は実行できません。プラン変更を行う場合は会員有効期限内に実施してください。</p>
                <?php endif; ?>

                <div class="tokkSimplePageCard">
                    <p><strong>プラン変更時のご案内</strong></p>
                    <ul style="margin-top:8px; padding-left:20px; list-style:disc;">
                        <li>会員有効期限まではスタンダード会員としてサービスをご利用いただけます。</li>
                        <li>会員有効期限を過ぎると自動でフリー会員へ変更されます。</li>
                        <li>スタンダード会員からフリー会員に変更に伴い、コメント機能、ブログ機能が使用できなくなります。</li>
                    </ul>
                </div>

                <form method="post" action="" class="tokkSimplePageActionsGroup" id="plan-change-form">
                    <?php wp_nonce_field('tokk_plan_change_input_action', 'plan_change_input_nonce'); ?>
                    <label class="tokkSimplePageConsent" for="plan-change-agreed">
                        <input type="checkbox" name="plan_change_agreed" id="plan-change-agreed" value="1">
                        <span>注意事項に同意する</span>
                    </label>
                    <p class="tokkSimplePageActions">
                        <button type="submit" class="memberFlowPrimaryBtn" id="plan-change-submit-btn" <?php echo $isMembershipExpiredForStandard ? 'disabled' : ''; ?>>
                            <span class="memberFlowPrimaryBtn__title">プラン変更する</span>
                        </button>
                    </p>
                    <p class="tokkSimplePageActions">
                        <a href="<?php echo esc_url(home_url('/mypage-edit/')); ?>" class="privateUserBtn" role="link" title="戻る">
                            <span class="privateUserBtn__title">戻る</span>
                        </a>
                    </p>
                </form>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
