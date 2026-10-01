<?php
/* Template Name: 有料会員登録エラー */

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
                }
                .tokkSimplePageActions .privateUserBtn {
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
                    text-decoration: none;
                    position: static;
                    margin: 0;
                }
            </style>

            <div class="tokkSimplePage">
                <h1 class="title fs--22 tokkSimplePageTitle">有料会員登録エラー</h1>

                <div class="tokkSimplePageCard">
                    <p class="tokkSimplePageLead">すでにスタンダード会員として登録されています。お手数ですが、以下のお問い合わせページからご連絡ください。</p>
                </div>

                <p class="tokkSimplePageActions">
                    <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="privateUserBtn" role="link" title="情報提供・お問い合わせ">
                        <span class="loginBtn__title">情報提供・お問い合わせへ</span>
                    </a>
                </p>
            </div>
        </div>
    </article>
</main>

<?php get_footer(); ?>
