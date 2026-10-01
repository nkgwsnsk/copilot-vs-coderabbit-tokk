<?php
/* Template Name: 運営会社について */
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
                            <span property="name">運営会社について</span>
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
                <h1 class="title fs--22">運営会社について</h1>
                <div class="otherSection">
                    <section class="otherSection__right">
                        <div class="aboutSection__block">
                            <p class="aboutSection__desc fontW--r">
                                当サイトは、阪急阪神東宝グループで、マーケティング・デジタル・クリエイティブ・メディアなど広告を一気通貫で手がける阪急阪神マーケティングソリューションズ株式会社が運営しています。地域の生活者、関西圏に観光で来られる方へ、有益かつ共感性の高いコンテンツを発信していきます。
                            </p>
                            <div class="otherSection__infoBox pageAbout__infoBox pageAbout__infoBox--detail">
                                <div class="textColor--textGray fontW--r spotInfo__list">
                                    <dl class="spotInfo__block">
                                        <dt class="spotInfo__block--title">商号</dt>
                                        <dd class="spotInfo__block--text"><p>阪急阪神マーケティングソリューションズ株式会社</p></dd>
                                    </dl>
                                    <dl class="spotInfo__block">
                                        <dt class="spotInfo__block--title">設立</dt>
                                        <dd class="spotInfo__block--text"><p>2019年12月26日</p></dd>
                                    </dl>
                                    <dl class="spotInfo__block">
                                        <dt class="spotInfo__block--title">資本金</dt>
                                        <dd class="spotInfo__block--text"><p>1,000万円</p></dd>
                                    </dl>
                                    <dl class="spotInfo__block">
                                        <dt class="spotInfo__block--title">従業員数</dt>
                                        <dd class="spotInfo__block--text"><p>315名（2025年4月現在）</p></dd>
                                    </dl>
                                    <dl class="spotInfo__block">
                                        <dt class="spotInfo__block--title">本社所在地</dt>
                                        <dd class="spotInfo__block--text"><p>大阪市北区中崎西 2- 4 -12 梅田センタービル 26Ｆ</p></dd>
                                    </dl>
                                    <dl class="spotInfo__block">
                                        <dt class="spotInfo__block--title">株主</dt>
                                        <dd class="spotInfo__block--text"><p>阪急阪神ホールディングス株式会社<br>エイチ・ツー・オー リテイリング株式会社</p></dd>
                                    </dl>
                                    <dl class="spotInfo__block">
                                        <dt class="spotInfo__block--title">URL</dt>
                                        <dd class="spotInfo__block--text"><p><a class="textLink" href="https://hhms.co.jp/" target="_blank" title="https://hhms.co.jp/">https://hhms.co.jp/</a></p></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
   
                    </section>
                </div>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
