<?php
/* Template Name: 会員解約画面3（完了） */

if (!defined('ABSPATH')) {
    header('Location: /', true, 302);
    exit;
}

$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);

$oneTimeCompleteToken = isset($_GET['free_cancel_once'])
    ? sanitize_text_field(wp_unslash($_GET['free_cancel_once']))
    : '';
$isOneTimeFreeCancelAccess = false;

if ($oneTimeCompleteToken !== '') {
    $transientKey = 'tokk_cancel_complete_once_' . $oneTimeCompleteToken;
    $isOneTimeFreeCancelAccess = get_transient($transientKey) === '1';
    if ($isOneTimeFreeCancelAccess) {
        delete_transient($transientKey);
    }
}

$loginStatusResponse = lutwiyo_call_bridge_api('login_status');
$isLoggedIn = is_array($loginStatusResponse)
    && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';
$isLoginStartRequested = isset($_GET['tokk_member_login'])
    && (string) $_GET['tokk_member_login'] === '1';

if (!$isLoggedIn && !$isLoginStartRequested && !$isOneTimeFreeCancelAccess) {
    lutwiyo_render_inline_login_guide_and_exit($currentUrlForReturn);
}

$memberInfoBody = [];
$memberInfoResult = '';
$hasLocalMember = false;
$localMember = [];

if ($isLoggedIn) {
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
}

$isStandardMember = (int) ($localMember['member_rank_id'] ?? 0) === 2;
$serviceCancelScheduledAt = trim((string) ($localMember['service_cancel_scheduled_at'] ?? ''));
$cancelledAt = trim((string) ($localMember['cancelled_at'] ?? ''));

$scheduledAtFromQuery = isset($_GET['service_cancel_scheduled_at'])
    ? sanitize_text_field(wp_unslash($_GET['service_cancel_scheduled_at']))
    : '';

if ($scheduledAtFromQuery !== '') {
    $serviceCancelScheduledAt = $scheduledAtFromQuery;
}

$formatDateOnly = static function (string $dateTimeRaw): string {
    if ($dateTimeRaw === '') {
        return '';
    }

    $timestamp = strtotime($dateTimeRaw);
    if ($timestamp === false) {
        return $dateTimeRaw;
    }

    return wp_date('Y年m月d日', $timestamp);
};
$serviceCancelScheduledAt = $formatDateOnly($serviceCancelScheduledAt);

// 画面3は再表示可能とし、会員情報から都度表示状態を再判定する。
$isStandardCancelled = $isStandardMember && $cancelledAt !== '';
$isStandardScheduled = $isStandardMember && $serviceCancelScheduledAt !== '';
$isFreeCancelledWithoutLocalMember = !$isStandardMember && !$hasLocalMember && $memberInfoResult === 'ok';
$isFreeCancelled = !$isStandardMember
    && (
        $cancelledAt !== ''
        || $isOneTimeFreeCancelAccess
        || $isFreeCancelledWithoutLocalMember
    );

if (!$isStandardCancelled && !$isStandardScheduled && !$isFreeCancelled) {
    wp_safe_redirect(home_url('/'));
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
                    margin-top: 24px;
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
                @media only screen and (max-width: 767px) {
                    .tokkSimplePage {
                        padding-bottom: 150px;
                    }
                }
            </style>

            <div class="tokkSimplePage">
            <h1 class="title fs--22 tokkSimplePageTitle">会員退会完了</h1>

            <?php if ($isStandardCancelled): ?>
                <?php
                // TODO: スタンダードかつ解約済みユーザー向け表示要素をここに追加する。
                // TODO: 例）完全解約後の再入会導線や注意事項など、後で要件確定後に実装する。
                ?>
                <p class="tokkSimplePageLead">スタンダード会員の退会手続きは完了しています。</p>
            <?php elseif ($isStandardScheduled): ?>
                <?php // スタンダード会員の解約予約が成立しているため、解約予定日と合わせて受付完了メッセージを表示する。 ?>
                <p class="tokkSimplePageLead">サービス退会を受け付けました。</p>
                <p style="margin-top:8px;">
                    スタンダード会員は
                    <?php echo esc_html($serviceCancelScheduledAt); ?>
                    まで会員状態が維持され、同日に退会されます。
                </p>
            <?php elseif ($isFreeCancelled): ?>
                <?php // フリー会員は即時解約のため、解約完了を確定メッセージとして表示する（初回ログアウト遷移時の表示も含む）。 ?>
                <p style="margin-top:16px;">フリー（無料）会員のサービス退会が完了しました。</p>
            <?php else: ?>
                <?php // 解約状態を判定できなかったケースのため、確認画面へ戻して再実行できる導線を表示する。 ?>
                <p style="margin-top:16px;">退会状態を確認できませんでした。必要に応じて確認画面から再度お手続きください。</p>
                <p class="tokkSimplePageActions">
                    <a href="<?php echo esc_url(home_url('/cancel-confirm/')); ?>" class="memberFlowPrimaryBtn" role="link" title="退会確認画面へ">
                        <span class="memberFlowPrimaryBtn__title">退会確認画面へ</span>
                    </a>
                </p>
            <?php endif; ?>

            <div class="tokkSimplePageActionsGroup">
                <p class="tokkSimplePageActions">
                    <?php if ($isFreeCancelled): ?>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="privateUserBtn" role="link" title="TOPページに戻る">
                            <span class="privateUserBtn__title">TOPページに戻る</span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url(home_url('/mypage/')); ?>" class="privateUserBtn" role="link" title="マイページへ戻る">
                            <span class="privateUserBtn__title">マイページへ戻る</span>
                        </a>
                    <?php endif; ?>
                </p>
            </div>
                    </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
