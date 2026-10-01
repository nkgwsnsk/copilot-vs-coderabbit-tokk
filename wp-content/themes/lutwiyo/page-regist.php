<?php
/* Template Name: 新規登録案内 */

$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);
$pageRegistGuideBaseUrl = remove_query_arg(['tokk_member_login', 'tokk_member_regist'], $currentUrlForReturn);

$pageRegistStartUrl = add_query_arg('tokk_member_regist', '1', $pageRegistGuideBaseUrl);

get_header();
?>

<main class="main wrapper" role="main">
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <div class="gridWide">
            <style>
                .tokkSimplePage {
                    margin: 0 auto;
                    max-width: 600px;
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
                .tokkSimplePageLead a {
                    display: inline;
                    height: auto;
                    text-decoration: underline;
                }
                .tokkSimplePageActions {
                    margin-top: 24px;
                    display: flex;
                    justify-content: center;
                }
                .tokkSimplePageActions .loginBtn,
                .tokkSimplePageActions .privateUserBtn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 50px;
                    border: 1px solid rgba(58, 58, 58, 0.75);
                    border-radius: 50px;
                    padding: 0 44px;
                    font-size: 17px;
                    background: #fff;
                    white-space: nowrap;
                    text-decoration: none;
                    color: #3a3a3a;
                }
                .tokkSimplePageActions .loginBtn__title,
                .tokkSimplePageActions .privateUserBtn__title {
                    color: #3a3a3a;
                }
                @media only screen and (max-width: 767px) {
                    .tokkSimplePage {
                        padding-bottom: 150px;
                    }
                }
            </style>

            <div class="tokkSimplePage">
            <h1 class="title fs--22 tokkSimplePageTitle">新規会員登録のご案内</h1>
            <div class="tokkSimplePageLead">
                <p>TOKK会員にご登録いただくと、以下のサービスが利用可能となります。</p>
                <p style="margin-top: 12px; font-weight: 700;">■フリー会員（無料）</p>
                <ul style="margin-top: 6px; padding-left: 1.25em;">
                    <li>・プレゼントの応募</li>
                    <li>・お得なクーポンの利用</li>
                    <li>・記事お気に入り登録</li>
                    <li>・無料会員限定記事の閲覧</li>
                </ul>
                <p style="margin-top: 12px; font-weight: 700;">■スタンダード会員（有料）</p>
                <ul style="margin-top: 6px; padding-left: 1.25em;">
                    <li>・記事コメント</li>
                    <li>・ギャラリー閲覧</li>
                    <li>・有料/無料会員限定記事の閲覧</li>
                    <li>・広告非表示</li>
                    <li>・プロフィール公開</li>
                    <li>・ブログ機能</li>
                </ul>
                <p style="margin-top: 8px;">※スタンダード会員（有料）は、フリー会員（無料）のご登録後にお申し込み可能です。</p>
                <p style="margin-top: 16px;">「TOKK関西の会員サービス」のご利用には、阪急阪神ホールディングスグループの各種サービスで使えるグループ共通ID「<a href="https://www.hhcross.hankyu-hanshin.jp/about/" target="_blank" rel="noopener noreferrer">HH cross ID</a>」へのご登録が必要です。新規会員登録をご希望の方は、下記ボタンから新規会員登録へお進みください。</p>
            </div>
            <p class="tokkSimplePageActions">
                <a href="<?php echo esc_url($pageRegistStartUrl); ?>" class="privateUserBtn" role="link" title="新規登録">
                    <span class="privateUserBtn__title">新規会員登録</span>
                </a>
            </p>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
