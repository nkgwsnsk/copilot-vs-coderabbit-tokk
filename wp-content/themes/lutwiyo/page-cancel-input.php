<?php
/* Template Name: 会員解約画面1（説明） */

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
                .tokkSimplePageActionDisabled {
                    opacity: 0.45;
                    pointer-events: none;
                }
                @media only screen and (max-width: 767px) {
                    .tokkSimplePage {
                        padding-bottom: 150px;
                    }
                }
            </style>

            <div class="tokkSimplePage">
            <h1 class="title fs--22 tokkSimplePageTitle">退会</h1>
            <p class="tokkSimplePageLead">退会に関する下記の注意事項をご確認のうえ、お手続きをお進めください。</p>
            <?php if ($isMembershipExpiredForStandard): ?>
                <p class="tokkSimplePageWarning">会員有効期限の自動延伸処理中です。退会は実行できません。退会を行う場合は会員有効期限内に実施してください。</p>
            <?php endif; ?>

            <?php if ($isFreeMember): ?>
                <div class="tokkSimplePageCard">
                    <p><strong>フリー（無料）会員 退会時のデメリット</strong></p>
                    <ul style="margin-top:8px; padding-left:20px; list-style:disc;">
                        <li>※退会された場合、マイページにログイン出来なくなり、プレゼント応募やクーポンの利用、保存した記事の閲覧ができなくなります。</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tokkSimplePageCard">
                    <p><strong>スタンダード会員 解約時のデメリット</strong></p>
                    <ul style="margin-top:8px; padding-left:20px; list-style:disc;">
                        <li>スタンダード（有料）会員向け特典は退会予定日以降に利用できなくなります。</li>
                        <li>継続特典がある場合、次回更新対象外になる可能性があります。</li>
                        <li>※退会された場合、マイページにログイン出来なくなり、コメント機能、ブログ機能の内容は削除されます。</li>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="tokkSimplePageActionsGroup">
                <p class="tokkSimplePageActions">
                    <a href="<?php echo esc_url($isMembershipExpiredForStandard ? '#' : home_url('/cancel-confirm/')); ?>" class="memberFlowPrimaryBtn <?php echo $isMembershipExpiredForStandard ? 'tokkSimplePageActionDisabled' : ''; ?>" role="link" title="確認画面へ進む" aria-disabled="<?php echo $isMembershipExpiredForStandard ? 'true' : 'false'; ?>">
                        <span class="memberFlowPrimaryBtn__title">確認画面へ進む</span>
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
