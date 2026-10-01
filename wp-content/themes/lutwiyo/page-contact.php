<?php
/* Template Name: お問い合わせ・情報提供 */
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
                            <span property="name">お問い合わせ・情報提供</span>
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
                <h1 class="title fs--22">お問い合わせ・情報提供</h1>
				<div class="otherSection contactSection">
                    <div class="otherSection__right">
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">TOKK関西全般に関するお問い合わせ</h2>
                            <p class="otherSection__p fontW--r">
                                tokk★hhms.co.jp（★を＠に変更してください）
                            </p>
                        </div>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">HHcross会員IDやお支払いに関するお問い合わせ</h2>
                            <p class="otherSection__p fontW--r">
                                <a href="https://help.hhcross.hankyu-hanshin.jp/hc/ja/requests/new?ticket_form_id=5076744546969" target="_blank" rel="noopener noreferrer" style="text-decoration: underline;">お問い合わせフォーム</a><br>
                                ※TOKKに関するお問い合わせはお受けできませんので、上記メールアドレス宛にお願いします。<br>
                                受付時間：月～金曜10:00～18:00<br>
                                ※土・日曜・祝日、GW、お盆、年末年始などは除く
                            </p>
                        </div>
                        <?php
                        /*
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">FAQ</h2>
                            <p class="otherSection__p fontW--r">
                                FAQ
                            </p>
                        </div>
                        */
                        ?>
                        <div class="otherSectionBlock">
                            <h2 class="otherSection__leader">情報提供</h2>
                            <p class="otherSection__p fontW--r">
                                TOKKでは、関西エリア（特に阪急沿線）のニュースや新店オープン、イベントなどの情報提供を受け付けております。<br>
                                掲載や取材を希望される場合は、各エリアのフォームから必要資料をご用意の上、ご連絡ください。
                            </p>
                            <div class="otherSection__infoBox pageContact__infoBox">
                                <dl class="otherSection__infoBox--block">
                                    <dt class="otherSection__infoBox--blockTitle">必要資料</dt>
                                    <dd class="fontW--r otherSection__infoBox--blockDetail">
                                        <div class="contactSection__desc">
                                            <p class="textIndent">・ ニュースやオープンする新店舗、イベントの基本情報がわかるもの</p>
                                            <p class="textIndent">・ イメージ画像（横1200px以上、あれば複数枚）</p>
                                        </div>
                                        <aside class="textColor--textGray contactSection__block--aside">なお、いただいた情報は内容を精査したうえで掲載の場合のみご連絡いたします。掲載に至らない場合もございますので、予めご了承いただきますようお願いします。</aside>
                                    </dd>
                                </dl>
                            </div>
                            <p class="otherSection__p fontW--r">
                                (★マークを@に変更してください)＊メールアプリが立ち上がります
                            </p>
                            <div class="flexColumn otherSection__infoBtn pageContact__infoBtn">
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:umeda_tokk★hhms.co.jp?subject=梅田エリアの情報提供" title="梅田エリアの情報提供" target="_blank">
                                        <p class="btnTtext">梅田エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:tokk★hhms.co.jp?subject=なんば、心斎橋、堀江エリアの情報提供" title="なんば、心斎橋、堀江エリアの情報提供" target="_blank">
                                        <p class="btnTtext">なんば、心斎橋、堀江エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:takarazuka_tokk★hhms.co.jp?subject=宝塚エリアの情報提供" title="宝塚エリアの情報提供" target="_blank">
                                        <p class="btnTtext">宝塚エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:tokk★hhms.co.jp?subject=三宮、新開地エリアの情報提供" title="三宮、新開地エリアの情報提供" target="_blank">
                                        <p class="btnTtext">三宮、新開地エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:tokk★hhms.co.jp?subject=西宮市、芦屋市エリアの情報提供" title="西宮市、芦屋市エリアの情報提供" target="_blank">
                                        <p class="btnTtext">西宮市、芦屋市エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:suita_tokk★hhms.co.jp?subject=吹田市エリアの情報提供" title="吹田市エリアの情報提供" target="_blank">
                                        <p class="btnTtext">吹田市エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:takatsuki_shimamoto_tokk★hhms.co.jp?subject=高槻市、島本町エリアの情報提供" title="高槻市、島本町エリアの情報提供" target="_blank">
                                        <p class="btnTtext">高槻市、島本町エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:toyonaka_itami_tokk★hhms.co.jp?subject=豊中市、伊丹市エリアの情報提供" title="豊中市、伊丹市エリアの情報提供" target="_blank">
                                        <p class="btnTtext">豊中市、伊丹市エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:ibaraki_settsu_tokk★hhms.co.jp?subject=茨木市、摂津市エリアの情報提供" title="茨木市、摂津市エリアの情報提供" target="_blank">
                                        <p class="btnTtext">茨木市、摂津市エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:juso_awaji_kamishinjou_tokk★hhms.co.jp?subject=十三、淡路、上新庄エリアの情報提供" title="十三、淡路、上新庄エリアの情報提供" target="_blank">
                                        <p class="btnTtext">十三、淡路、上新庄エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:minoh_tokk★hhms.co.jp?subject=箕面市エリアの情報提供" title="箕面市エリアの情報提供" target="_blank">
                                        <p class="btnTtext">箕面市エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:katsura_arashiyama_tokk★hhms.co.jp?subject=桂、嵐山エリアの情報提供" title="桂、嵐山エリアの情報提供" target="_blank">
                                        <p class="btnTtext">桂、嵐山エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:nagaokakyou_ooyamazaki_mukou_tokk★hhms.co.jp?subject=長岡京市、大山崎町、向日市エリアの情報提供" title="長岡京市、大山崎町、向日市エリアの情報提供" target="_blank">
                                        <p class="btnTtext">長岡京市、大山崎町、向日市エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:kawanishi_ikeda_tokk★hhms.co.jp?subject=川西市、池田市エリアの情報提供" title="川西市、池田市エリアの情報提供" target="_blank">
                                        <p class="btnTtext">川西市、池田市エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:okamoto_mikage_tokk★hhms.co.jp?subject=岡本、御影エリアの情報提供" title="岡本、御影エリアの情報提供" target="_blank">
                                        <p class="btnTtext">岡本、御影エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:rokko_nada_tokk★hhms.co.jp?subject=六甲、灘エリアの情報提供" title="六甲、灘エリアの情報提供" target="_blank">
                                        <p class="btnTtext">六甲、灘エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:amagasaki_tokk★hhms.co.jp?subject=尼崎市エリアの情報提供" title="尼崎市エリアの情報提供" target="_blank">
                                        <p class="btnTtext">尼崎市エリアの情報提供</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="mailto:tokk★hhms.co.jp?subject=岡本、御影エリアの情報提供" title="岡本、御影エリアの情報提供" target="_blank">
                                        <p class="btnTtext">岡本、御影エリアの情報提供</p>
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
