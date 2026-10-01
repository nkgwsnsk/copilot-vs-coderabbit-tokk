<?php
/* Template Name: クッキーポリシー */
get_header();
$shouldLoadOneTrust = lutwiyo_is_onetrust_enabled_host();

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
                            <span property="name">クッキーポリシー</span>
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
                <h1 class="title fs--22">クッキーポリシー</h1>
				<div class="otherSection">
					<section class="otherSection__right">
						<div class="otherSectionBlock">
							<h2 class="otherSection__leader">クッキー（cookie）及びWebビーコンの使用によるアクセス情報の収集</h2>
							<p class="otherSection__p fontW--r">
								当Webサイト（tokk-kansai.jp）では、お客様のホームページ利用状況を、クッキー（※1）とWebビーコン（※2）という技術を用いて取得しています。これらの技術により取得した情報はいずれもお客様のブラウザを識別する情報であり、特定のお客様個人を識別するものではありません。
							</p>
							<p class="otherSection__p fontW--r">
								お客様は当社が国外のプライバシー保護規制により要求される範囲において、当サイトで表示されるクッキーバナーやプライバシー設定センターから、当サイトもしくは第三者（解析ツール提供企業や広告配信事業者等）によるクッキーの利用の許可・拒否を選択することが可能です。ただし、ウェブサイトの機能に厳格に必要となるクッキーを除きます。
							</p>
							<ol class="otherSection__list fontW--r">
								<li class="listTarget"><span>※1</span>クッキーとはお客様が当サイトをご覧になったという情報を、そのお客様のコンピューター（またはスマートフォンやタブレットなどのインターネット接続可能な機器）内に記憶させておく機能のことです。クッキーを利用することによりご利用のコンピューターのウェブサイト訪問回数や訪問したページなどの情報を取得することができます。なお、クッキーを通じて収集する情報には当社が「お客様個人を識別できる情報」は一切含まれておりません。 また、お客様のブラウザの設定によりクッキーの機能を無効にすることもできます。クッキーの機能を無効にしても当サイトのご利用には問題ありません。</li>
								<li class="listTarget"><span>※2</span>Webビーコンとは、クッキーと併用することにより、お客様のブラウザからのアクセス情報を収集し、当Webサイトへの訪問の有無、訪問回数や閲覧履歴等を把握することが可能となる技術です。</li>
							</ol>
							<?php if ($shouldLoadOneTrust): ?>
								<!-- OneTrust Cookie 設定ボタンの始点 -->
								<div class="btn btnShaped btnBgColor btnAll btnAll--cookie" data-shaped="320-45">
									<button id="ot-sdk-btn" class="ot-sdk-show-settings" type="button">Cookie 設定</button>
									<div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
								</div>
								<!-- OneTrust Cookie 設定ボタンの終点 -->
							<?php endif; ?>
						</div>
						<div class="otherSectionBlock">
							<h2 class="otherSection__leader">アクセス情報の利用目的</h2>
							<p class="otherSection__p fontW--r">
								当サイトのコンテンツ閲覧状況を把握することにより、サービス向上およびお客様の興味やニーズにより適したサービス・コンテンツを提供するための参考としてクッキーを利用します。 当サイトでは、利用状況を把握するため、主に以下のツールを使用します。 各ツール提供企業により収集、記録、分析される情報には、特定の個人を識別する情報は一切含まれません。また、それらの情報は、各ツール提供企業のプライバシーポリシーに基づいて管理されます。 各ツール提供企業のオプトアウトページ、または利用するブラウザの設定からお客様自身で無効化することにより、クッキーの利用を停止することができます。
							</p>
							<p class="otherSection__p fontW--r">
								Microsoft Clarity<br>
								ツール提供者：Microsoft Corporation<br>
								利用規約：<a class="textLink" href="https://clarity.microsoft.com/terms" target="_blank" title="Clarity利用規約">Clarity利用規約</a><br>
								プライバシーポリシー：<br class="brSp"><a class="textLink" href="https://privacy.microsoft.com/ja-jp/privacystatement" target="_blank" title="Microsoftプライバシーステートメント">Microsoftプライバシーステートメント</a><br>
								無効設定：ブラウザの設定を変更しCookieを無効にしてください。
							</p>
						</div>
						<div class="otherSectionBlock">
							<h2 class="otherSection__leader">Cookieを利用した広告配信</h2>
							<p class="otherSection__p fontW--r">
								当社ではGoogleやYahoo！などの広告配信事業者の広告サービス（リターゲティング機能など）を活用し、過去に当サイトを訪問された方が特定のページを訪問した際にお知らせ（広告）を配信しております。その際、当サイトの訪問履歴情報を取得するためにクッキーを利用します。 なお、こうしたお知らせ（広告）をご希望でない場合は、お手数ですが下記ページにアクセスし、それぞれのクッキーの使用を無効にしてください。 ブラウザの変更、Cookieの削除および新しいPCへ変更を行なった場合には再度設定が必要となります。
							</p>
							<p class="otherSection__p fontW--r">
								<a class="textLink" href="https://www.google.com/settings/ads" target="_blank" title="Googleのオプトアウトページ">Googleのオプトアウトページ</a><br>
								<a class="textLink" href="https://btoptout.yahoo.co.jp/optout/index.html" target="_blank" title="Yahoo！のオプトアウトページ">Yahoo！のオプトアウトページ</a><br>
								<a class="textLink" href="https://www.facebook.com/legal/terms" target="_blank" title="Facebookのオプトアウトページ">Facebookのオプトアウトページ</a><br>
								<a class="textLink" href="https://help.instagram.com/519522125107875" target="_blank" title="Instagramのオプトアウトページ">Instagramのオプトアウトページ</a><br>
								<a class="textLink" href="https://terms.line.me/line_rules_optimize" target="_blank" title="LINEのオプトアウトページ">LINEのオプトアウトページ</a>
							</p>
						</div>
						<?php if ($shouldLoadOneTrust): ?>
							<!-- OneTrust Cookie リストの始点 -->
							<div id="ot-sdk-cookie-policy"></div>
							<!-- OneTrust Cookie リストの終点 -->
						<?php endif; ?>
					</section>
				</div>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
