<?php
/* Template Name: ログイン案内 */

$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);
$pageLoginGuideBaseUrl = remove_query_arg('tokk_member_login', $currentUrlForReturn);

$pageLoginStartUrl = add_query_arg('tokk_member_login', '1', $pageLoginGuideBaseUrl);

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
                .tokkSimplePageActions .tokkLoginGuideBtn,
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
                .tokkSimplePageActions .tokkLoginGuideBtn__label,
                .tokkSimplePageActions .privateUserBtn__title {
                    display: inline-block;
                    color: #3a3a3a !important;
                    -webkit-text-fill-color: #3a3a3a !important;
                    font-size: 16px;
                    line-height: 1.4;
                    opacity: 1 !important;
                    text-indent: 0;
                }
                @media only screen and (max-width: 767px) {
                    .tokkSimplePage {
                        padding-bottom: 150px;
                    }
                    .tokkSimplePageActions .tokkLoginGuideBtn {
                        width: 100%;
                        padding: 0 20px;
                    }
                    .tokkSimplePageActions .tokkLoginGuideBtn__label {
                        width: 100%;
                        text-align: center;
                    }
                }
            </style>

            <div class="tokkSimplePage">
            <h1 class="title fs--22 tokkSimplePageTitle">ログインのご案内</h1>
            <p class="tokkSimplePageLead">「TOKK関西の会員サービス」のご利用には、阪急阪神ホールディングスグループの各種サービスで使えるグループ共通ID「<a href="https://www.hhcross.hankyu-hanshin.jp/about/" target="_blank" rel="noopener noreferrer">HH cross ID</a>」へのログインが必要です。</p>
            <p class="tokkSimplePageActions">
                <a href="<?php echo esc_url($pageLoginStartUrl); ?>" class="tokkLoginGuideBtn" role="link" title="HH cross IDでログイン">
                    <span class="tokkLoginGuideBtn__label">HH cross IDでログイン</span>
                </a>
            </p>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
