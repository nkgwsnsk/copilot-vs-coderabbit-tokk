<?php
/* Template Name: 特定商取引法に基づく表示 */

$isNakedMode = function_exists('lutwiyo_is_static_page_naked_mode') && lutwiyo_is_static_page_naked_mode('tokushoho');

ob_start();
?>
                <div class="otherSection">
                    <section class="otherSection__right">
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">事業者の名称</h2>
                            <p class="otherSection__p fontW--r">阪急阪神マーケティングソリューションズ株式会社</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">代表者</h2>
                            <p class="otherSection__p fontW--r">上田均</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">所在地</h2>
                            <p class="otherSection__p fontW--r">〒530-0015　大阪府大阪市北区中崎西2-4-12　梅田センタービル26階</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">全般に関する問い合わせ</h2>
                            <p class="otherSection__p fontW--r">tokk@hhms.co.jp</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">ID、決済に関するお問い合わせ</h2>
                            <p class="otherSection__p fontW--r">HHcrossカスタマーサポート</p>
                            <p class="otherSection__p fontW--r">050-3185-6774（月～金曜10:00～18:00〈土・日曜と祝日、GW、お盆、年末年始は除く〉）</p>
                            <p class="otherSection__p fontW--r">問い合わせフォーム</p>
                            <p class="otherSection__p fontW--r"><a href="https://help.hhcross.hankyu-hanshin.jp/hc/ja/requests/new?ticket_form_id=5076744546969" target="_blank" rel="noopener noreferrer" style="display:inline-block;margin-top:4px;overflow-wrap:anywhere;word-break:break-all;">https://help.hhcross.hankyu-hanshin.jp/hc/ja/requests/new?ticket_form_id=5076744546969</a></p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">申込み期間</h2>
                            <p class="otherSection__p fontW--r">2026年3月31日以降随時</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">役務の対価</h2>
                            <p class="otherSection__p fontW--r">月額280円（税込）</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">役務の対価以外の必要料金</h2>
                            <p class="otherSection__p fontW--r">消費税</p>
                            <p class="otherSection__p fontW--r">会費のほか、イベント参加費が発生する場合があります。</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">役務の対価の支払時期</h2>
                            <p class="otherSection__p fontW--r">クレジットカード会社規定による</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">支払方法</h2>
                            <p class="otherSection__p fontW--r">クレジットカード払い</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">役務の提供時期</h2>
                            <p class="otherSection__p fontW--r">会員登録完了後、直ちにご利用いただけます。</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">役務の提供条件</h2>
                            <p class="otherSection__p fontW--r">阪急阪神グループ共通ID会員利用規約及びHH cross PAY利用規約、並びに阪急阪神マーケティングソリューションズ株式会社が定めるコミュニティガイドラインへの同意</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">キャンセルに関する特約</h2>
                            <p class="otherSection__p fontW--r">契約終了日の前日の終了時点まで（23:59まで）に解約申し込みをお願いいたします。</p>
                            <p class="otherSection__p fontW--r">中途解約による返金は承っておりません。</p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">動作環境</h2>
                            <p class="otherSection__p fontW--r">当サイトでは、下記環境でのご利用を推奨いたします。</p>
                            <p class="otherSection__p fontW--r">・パソコン：Edge（最新版）、Google Chrome（最新版）、Mozilla Firefox（最新版）、Safari（最新版）</p>
                            <p class="otherSection__p fontW--r">・タブレット・スマートフォン：iOS端末（Safari）、Android端末（Chrome）について、最新バージョンおよびその2つ前まで</p>
                        </div>
                    </section>
                </div>
<?php
$tokushohoBody = ob_get_clean();

if ($isNakedMode) :
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php bloginfo('name'); ?> | 特定商取引法に基づく表示</title>
    <style>
        body{margin:0;padding:24px;background:#fff;color:#333;font:400 16px/1.8 "Yu Gothic","Hiragino Sans","Noto Sans JP",sans-serif}
        .nakedPolicy{max-width:960px;margin:0 auto}
        .nakedPolicy h1{margin:0 0 24px;font-size:28px;line-height:1.4}
        .otherSectionBlock + .otherSectionBlock{margin-top:24px}
        .otherSection__leader{margin:0 0 12px;font-size:22px;line-height:1.5}
        .otherSection__p{margin:0}
        .otherSection__list{margin:0;padding-left:1.4em}
        .otherSection__list .listTarget + .listTarget{margin-top:8px}
        .otherSection__list span{display:none}
        @media (max-width:767px){body{padding:16px;font-size:15px}.nakedPolicy h1{font-size:24px}.otherSection__leader{font-size:20px}}
    </style>
</head>
<body>
<main class="nakedPolicy" role="main">
    <h1>特定商取引法に基づく表示</h1>
    <?php echo $tokushohoBody; ?>
</main>
</body>
</html>
<?php
return;
endif;

get_header();
?>
    <!-- ==================================================================== ↓ wrapper ↓ -->
    <main class="main wrapper" role="main">
        <!-- ------------------------------------- ↓ kv ↓　-->
        <article class="grid kv">
            <div class="kvInfo__inner">
                <nav class="breadcrumbs" aria-label="Breadcrumbs" role="navigation">
                    <ol class="breadcrumbs__list" vocab="http://schema.org/" typeof="BreadcrumbList">
                        <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                            <a class="breadcrumbs__link" href="/" property="item" typeof="WebPage">
                                <span property="name">トップ</span>
                            </a>
                            <meta property="position" content="1">
                        </li>
                        <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                            <span property="name">特定商取引法に基づく表示</span>
                            <meta property="position" content="2">
                        </li>
                    </ol>
                </nav>
            </div>
        </article>
        <!-- ------------------------------------- ↓ areaNav ↓　-->
        <?php
        get_template_part(
            'partials/modules/area',
            'nav'
        );
        ?>
        <!-- ------------------------------------- ↓ tag 休日スポット ↓　-->
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <section class="gridWide latestSection">
                <h1 class="title fs--22">特定商取引法に基づく表示</h1>
                <?php echo $tokushohoBody; ?>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
