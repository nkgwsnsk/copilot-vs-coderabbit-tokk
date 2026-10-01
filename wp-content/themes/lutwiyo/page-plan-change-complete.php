<?php
/* Template Name: 会員プラン変更画面3（完了） */

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
$scheduledAtRaw = trim((string) ($localMember['plan_downgrade_scheduled_at'] ?? ''));
$scheduledAtText = '-';
if ($scheduledAtRaw !== '') {
    $timestamp = strtotime($scheduledAtRaw);
    $scheduledAtText = $timestamp !== false
        ? wp_date('Y年m月d日', $timestamp)
        : $scheduledAtRaw;
}

if ($scheduledAtRaw === '') {
    wp_safe_redirect(home_url('/mypage/'));
    exit;
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
                .tokkSimplePageActions { display: flex; justify-content: center; margin-top: 24px; }
                .tokkSimplePageActions .privateUserBtn {
                    display: inline-flex; align-items: center; justify-content: center; width: 260px; max-width: 100%; min-height: 50px;
                    border: 1px solid rgba(58, 58, 58, 0.75); border-radius: 50px; padding: 0 24px; font-size: 17px; background: #fff;
                    text-decoration: none;
                }
                .tokkSimplePageActions .privateUserBtn__title { color: #3a3a3a; font-size: 13px; }
            </style>

            <div class="tokkSimplePage">
                <h1 class="title fs--22 tokkSimplePageTitle">会員プラン変更受付完了</h1>
                <p class="tokkSimplePageLead">会員プラン変更を受け付けました。会員有効期限まではスタンダード会員としてご利用いただけます。</p>

                <div class="tokkSimplePageCard">
                    <p><strong>フリー会員への変更予定日</strong></p>
                    <p style="margin-top:8px;"><?php echo esc_html($scheduledAtText); ?></p>
                    <p style="margin-top:8px; font-size:14px;">会員プラン変更の取り消しにつきましては、<a href="<?php echo esc_url(home_url('/contact/')); ?>" style="display:inline; text-decoration:underline;">お問い合わせページ</a>から別途お問い合わせください。</p>
                </div>

                <p class="tokkSimplePageActions">
                    <a href="<?php echo esc_url(home_url('/mypage/')); ?>" class="privateUserBtn" role="link" title="マイページへ戻る">
                        <span class="privateUserBtn__title">マイページへ戻る</span>
                    </a>
                </p>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
