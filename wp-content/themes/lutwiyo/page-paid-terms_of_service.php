<?php
/* Template Name: 有料会員登録画面2（利用規約） */

$paidTermErrorMessage = '';

$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);

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
    wp_safe_redirect(home_url('/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = isset($_POST['paid_term_nonce'])
        ? sanitize_text_field(wp_unslash($_POST['paid_term_nonce']))
        : '';
    $isAgreed = isset($_POST['agree_terms']) && (string) wp_unslash($_POST['agree_terms']) === '1';

    if (!wp_verify_nonce($nonce, 'tokk_paid_term_action')) {
        $paidTermErrorMessage = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (!$isAgreed) {
        $paidTermErrorMessage = '利用規約への同意が必要です。';
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
                    margin-top: 12px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 12px;
                }
                .tokkSimplePageTermsForm {
                    margin-top: 24px;
                }
                .tokkSimplePageTermsLabel {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    margin-top: 16px;
                    cursor: pointer;
                }
                .tokkSimplePageTermsCheckbox {
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
                .tokkSimplePageTermsCheckbox:checked::after {
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
                .tokkSimplePageHowtoLink,
                .tokkSimplePageHowtoLink:visited,
                .tokkSimplePageHowtoLink:hover,
                .tokkSimplePageHowtoLink:focus {
                    text-decoration: underline !important;
                    text-underline-offset: 0.08em;
                }
                @media only screen and (max-width: 767px) {
                    .tokkSimplePage {
                        padding-bottom: 150px;
                    }
                }
            </style>

            <div class="tokkSimplePage">
            <h1 class="title fs--22 tokkSimplePageTitle">スタンダード（有料）会員 利用規約</h1>

            <?php if ($paidTermErrorMessage !== ''): ?>
                <p style="margin-top:16px; color:#d00;"><?php echo esc_html($paidTermErrorMessage); ?></p>
            <?php endif; ?>

            <div class="tokkSimplePageCard" style="margin-top:16px;">
                <p class="tokkSimplePageLead" style="margin-top:0;">
                    利用規約の詳細は、以下の「ご利用にあたって」ページにてご確認ください。
                </p>
                <p class="tokkSimplePageLead">
                    <a href="<?php echo esc_url(home_url('/howto/')); ?>" target="_blank" rel="noopener noreferrer" class="tokkSimplePageHowtoLink">
                        ご利用にあたってページを開く（別タブで開きます）
                    </a>
                </p>
            </div>

            <form method="post" action="<?php echo esc_url(home_url('/paid-confirm/')); ?>" class="tokkSimplePageTermsForm">
                <?php wp_nonce_field('tokk_paid_term_action', 'paid_term_nonce'); ?>
                <label class="tokkSimplePageTermsLabel">
                    <input type="checkbox" name="agree_terms" value="1" id="agree_terms" class="tokkSimplePageTermsCheckbox">
                    <span>利用規約に同意する</span>
                </label>

                <div class="tokkSimplePageActionsGroup">
                    <p class="tokkSimplePageActions">
                        <button type="submit" id="paid_term_submit" class="memberFlowPrimaryBtn" style="opacity:0.5; pointer-events:none;">
                        <span class="memberFlowPrimaryBtn__title">申込確認へ進む</span>
                        </button>
                    </p>
                    <p class="tokkSimplePageActions">
                        <a href="<?php echo esc_url(home_url('/paid-exp/')); ?>" class="privateUserBtn" role="link" title="戻る">
                            <span class="privateUserBtn__title">戻る</span>
                        </a>
                    </p>
                </div>
            </form>
                    </div>
        </div>
    </article>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var check = document.getElementById('agree_terms');
    var submit = document.getElementById('paid_term_submit');
    if (!check || !submit) {
        return;
    }

    check.addEventListener('change', function() {
        if (check.checked) {
            submit.style.opacity = '1';
            submit.style.pointerEvents = 'auto';
        } else {
            submit.style.opacity = '0.5';
            submit.style.pointerEvents = 'none';
        }
    });
});
</script>

<?php get_footer(); ?>
