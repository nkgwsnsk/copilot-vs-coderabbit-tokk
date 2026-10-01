<?php
/* Template Name: 広告掲載について */
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
                            <span property="name">広告について</span>
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
                <h1 class="title fs--22">広告について</h1>
				<div class="otherSection">
                    <div class="otherSection__right">
                        <div class="otherSectionBlock">
                            <p class="otherSection__p fontW--r">
                                TOKK関西は、アフィリエイトサービスおよび広告により、収益を得ております。<br>
                                純広告（バナー等）、記事広告による特定商品の広告を行う場合には、商品情報に「PR」表記を付けておりますユーザー開示をせずにアイキャッチ画像に広告に該当する商品画像を設定するなどは行いません。
                            </p>
                            <div class="otherSection__infoBox pageAd__infoBox">
                                <dl class="otherSection__infoBox--block">
                                    <dt class="otherSection__infoBox--blockTitle">アフィリエイト</dt>
                                    <dd class="otherSection__infoBox--blockDetail">
                                        <div class="pageAd__infoBox--company">OZMALL</div>
                                        <div class="pageAd__infoBox--company">バリューコマース</div>
                                        <div class="pageAd__infoBox--company">A8</div>
                                    </dd>
                                </dl>
                                <dl class="otherSection__infoBox--block">
                                    <dt class="otherSection__infoBox--blockTitle">レコメンドウィジェット広告</dt>
                                    <dd class="otherSection__infoBox--blockDetail">
                                        <div class="pageAd__infoBox--company">LOGLY</div>
                                    </dd>
                                </dl>
                                <dl class="otherSection__infoBox--block">
                                    <dt class="otherSection__infoBox--blockTitle">インストリーム広告</dt>
                                    <dd class="otherSection__infoBox--blockDetail">
                                        <div class="pageAd__infoBox--company">GliaStudio</div>
                                    </dd>
                                </dl>
                                <dl class="otherSection__infoBox--block">
                                    <dt class="otherSection__infoBox--blockTitle">アドセンス</dt>
                                    <dd class="otherSection__infoBox--blockDetail">
                                        <div class="pageAd__infoBox--company">Googleアドセンス</div>
                                    </dd>
                                </dl>
                            </div>
                            <div class="flexColumn otherSection__infoBtn">
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="https://hhms.co.jp/solutions/hhms-media/" title="詳細情報・媒体資料ダウンロードはこちら" target="_blank">
                                        <p class="btnTtext">詳細情報・媒体資料ダウンロードはこちら</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="https://go.hhms.co.jp/l/911332/2021-03-02/f5h" title="お問い合わせはこちら" target="_blank">
                                        <p class="btnTtext">お問い合わせはこちら</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
				</div>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
