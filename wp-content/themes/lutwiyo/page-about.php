<?php
/* Template Name: TOKKとは */
get_header();

//エリア一覧情報を取得
$area_info_list = TermModelHelper::get_terms_payload(
    'area',
    // ベースになるタームの配列(またはWP_Termに渡すクエリ)
    [],
    ['is_clickable'],
    // 各投稿に対して追加実行する関数セット
    []
);

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
                            <span property="name">TOKKとは</span>
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
                <h1 class="title fs--22">TOKKとは</h1>
				<div class="otherSection aboutSection">
                    <div class="otherSection__left">
                        <div class="aboutSection__img stickyTarget">
                            <img class="partsPc" src="/assets/img/contents/about/aboutImg--pc.jpg" alt="TOKKとは" width="940" height="1440">
                            <img class="partsSp" src="/assets/img/contents/about/aboutImg--sp.jpg" alt="TOKKとは" width="940" height="1440">
                            <p class="aboutSection__img--p">
                                つながる<span>、</span>広がる<span>。</span><br>
                                地元目線で選ぶ<span>、</span><br>
                                関西のお出かけ<span>、</span><br>
                                グルメ<span>、</span>暮らしの<br>
                                メディア<span>。</span>
                            </p>
                        </div>
                    </div>
                    <div class="otherSection__right">
                        <div class="aboutSection__block">
                            <h2 class="aboutSection__title">TOKK関西について</h2>
                            <p class="aboutSection__desc fontW--r">
                                関西で50年以上の実績を持つローカルメディア編集部が主となってスタートした「TOKK関西」。私たちは信頼できる情報源の確保、適切な情報発信などフリーペーパー制作の中で培った技術を基に、独自の視点で信頼性の高い情報を発信しています。<br>
                                毎日流れてくる無数の情報を編集部が独自の視点で丁寧に選定し、プロのライターが取材、記事執筆を行っています。また、第二の編集部として、現在約25名のエリアライターも活躍中。ライター自ら地元をパトロールし、日々キャッチアップするニッチなネタを発信しています。<br>
                                住む人には「暮らす楽しさ」をモットーにニッチで深い情報を、さらに観光で関西に来られる方には「関西で行くべき場所」「食べるべきグルメ」「知っておくべきイベント情報」といった広域の情報をお届けしています。
                            </p>
                            <div class="otherSection__infoBox pageAbout__infoBox pageAbout__infoBox--area">
                                <h3 class="pageAbout__infoBox--title">私たちがお届けするのは</h3>
                                <p class="fontW--r pageAbout__infoBox--text pageAbout__infoBox--strong">大阪府、京都府、兵庫県、奈良県、滋賀県など関西全域のお出かけ、グルメ情報</p>
                                <p class="fontW--r pageAbout__infoBox--text pageAbout__infoBox--strong">18エリアの地元のニッチな情報</p>
                                <ul class="pageAbout__infoArea--list">
                                    <?php $num = 1;?> 
                                    <?php foreach ($area_info_list as $area_info): ?>
                                        <li class="pageAbout__infoArea">
                                            <?php
                                                $is_clickable = !empty($area_info['is_clickable']) && $area_info['is_clickable'] !== '0'; 
                                                $href = $area_info['slug'] !== '' ? home_url('area/' . $area_info['slug'] . '/') : '';
                                            ?>
                                            <?php if ($is_clickable && $href) : ?>
                                                <a class="pageAbout__infoArea--inner" href="<?= $href; ?>" title="<?= esc_html($area_info['name']); ?>エリア">
                                                    <p class="fontEn pageAbout__infoArea--num"><?= $num; ?></p>
                                                    <p class="pageAbout__infoArea--title"><?= esc_html($area_info['name']); ?>エリア</p>
                                                </a>
                                            <?php else : ?>
                                                <div class="pageAbout__infoArea--inner">
                                                    <p class="fontEn pageAbout__infoArea--num"><?= $num; ?></p>
                                                    <p class="pageAbout__infoArea--title"><?= esc_html($area_info['name']); ?>エリア</p>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                        <?php $num++ ;?> 
                                    <?php endforeach; ?>
                                    <li class="pageAbout__infoArea pageAbout__infoArea--sub">
                                        <p class="pageAbout__infoArea--title">※16～19は順次拡大</p>
                                    </li>                                </ul>
                            </div>
                            <div class="flexColumn otherSection__infoBtn">
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                    <a class="flex--cc btnLink" href="/staff" title="TOKK編集部・ライター一覧">
                                        <p class="btnTtext">TOKK編集部・ライター一覧</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </a>
                                </div>
                            </div>
                        </div>
                     <div class="aboutSection__block">
                            <h2 class="aboutSection__title">運営ポリシー</h2>
                            <p class="aboutSection__desc fontW--r">
                                TOKK関西を運営する阪急阪神マーケティングソリューションズ株式会社の掲げる企業理念を遵守し、運営ポリシーとしております。
                            </p>
                            <div class="otherSection__infoBox pageAbout__infoBox pageAbout__infoBox--policy">
                                <div class="aboutPolicy__block">
                                    <h3 class="aboutPolicy__block--title">「新しい文化を、共に創る。」</h3>
                                    <p class="fontW--r pageAbout__infoBox--text">
                                        阪急阪神マーケティングソリューションズの母体である、 阪急阪神ホールディングスグループとエイチ・ツー・オー リテイリンググループは、 鉄道、百貨店、スーパーマーケット、住宅・都市開発、エンタテインメント、旅行、ホテルなどの事業を通じて、豊かな生活文化の発展に寄与してきました。 私たち阪急阪神マーケティングソリューションズも、価値創造のパートナーとして、 クライアントと共に、生活者と共に、新しい文化を創り、世の中を楽しくする存在でありたいと考えます。
                                    </p>
                                </div>
                                <div class="aboutPolicy__block">
                                    <h3 class="aboutPolicy__block--title">コンテンツの管理体制</h3>
                                    <p class="fontW--r pageAbout__infoBox--text">
                                        法令違反やとなる表現、根拠の無い優劣の表現、特定のメーカーの批判となりうるような表現、その他社会的受容性が低い表現を公開することが無いよう、編集部ライター、担当ディレクターに定期的な研修を実施しております。
                                    </p>
                                    <ul class="textIndent__list fontW--r">
                                        <li class="textIndent">・ 記事執筆の際には、レギュレーションを設け、記事品質が皆さまにご理解を得られるよう管理をしております。</li>
                                        <li class="textIndent">・ 記事のチェック体制は、社内および取材先との間で複数回のチェックをすることで適切な記事となりますよう取り組みをしております。</li>
                                        <li class="textIndent">・ 記事公開後は、情報の最新性、関係法規の変更等に配慮をいたしまして、定期的なサンプルチェックを実施しておりまして、適切な情報提供となりますよう管理をしております。</li>
                                        <li class="textIndent">・ 記事公開後も必要な場合は記事を再編集し、情報の最新性をできる限り保つよう努めております。</li>
                                    </ul>
                                </div>
                                <div class="aboutPolicy__block">
                                    <h3 class="aboutPolicy__block--title">信頼されるコンテンツのために</h3>
                                    <p class="fontW--r pageAbout__infoBox--text">
                                        掲載の情報については、編集部から店舗や施設に直接確認、連絡をし、掲載許可を得た情報を紹介をしております。<br>
                                        記事公開後も必要に応じて編集し直し、情報の最新性を維持できるよう努めております。<br>
                                        情報の透明性を高めるために景品表示法等を参考としまして、数値や認定等の表記をしております。<br>
                                        公序良俗に反する表現やコンテンツを公開することが無いよう、記事執筆のレギュレーションを設け、また、社内校正チームとの連携を図りながら、記事の品質維持に努めています。<br>
                                        記事管理体制は、編集部ライター、担当ディレクターによる精査および、必要な場合は社内の法務担当者のチェックも行い、適正かつ公正な情報提供に努めています。
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
				</div>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
