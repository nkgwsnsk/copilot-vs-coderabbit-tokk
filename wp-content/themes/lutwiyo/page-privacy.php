<?php
/* Template Name: プライバシーポリシー */

$isNakedMode = function_exists('lutwiyo_is_static_page_naked_mode') && lutwiyo_is_static_page_naked_mode('privacy');

ob_start();
?>
				<div class="otherSection">
					<section class="otherSection__left">
						<h2 class="otherSection__leftTitle stickyTarget">個人情報保護方針</h2>
					</section>
					<section class="otherSection__right">
						<p class="otherSection__p fontW--r">
							阪急阪神マーケティングソリューションズ株式会社（以下「当社」）は、「新しい文化を、共に創る。」を企業理念としております。お客様の価値創造のパートナーとして、新しい文化を創り、世の中を楽しくする存在であり続けることが、当社の使命です。その中で、個人情報の適切な管理・保護を行うことについては、当社の様々なサービスをお客様に安心してご利用いただくために果たすべき、企業としての重大責務と認識しております。当社では、本「個人情報保護方針」を定め、役員及び従業員が一体となってこれを遵守し、もって個人情報の保護及び個人の権利・利益の保護に万全を尽くしてまいります。
						</p>
						<ol class="otherSection__list otherSection__list--num otherSection__list--privacy fontW--r">
							<li class="listTarget"><span>1.</span>個人情報を取得するに当たっては、その利用目的をできる限り特定し、その目的の達成に必要な限度において個人情報を取得いたします。</li>
							<li class="listTarget"><span>2.</span>個人情報を、本人から直接、書面によって取得する場合には、弊社名、個人情報保護管理者名及び連絡先、利用目的等をお知らせした上で、必要な範囲で個人情報を取得いたします。</li>
							<li class="listTarget"><span>3.</span>個人情報の利用は、本人が同意を与えた利用目的の範囲内で行います。また、目的外利用を行わないため、必要な対策を講じる手順を確立し、実施いたします。</li>
							<li class="listTarget"><span>4.</span>保有する個人情報を適切な方法で管理し、本人の同意なしに第三者に開示・提供いたしません。</li>
							<li class="listTarget"><span>5.</span>保有する個人情報を利用目的に応じた必要な範囲内において、正確、かつ、最新の状態で管理し、個人情報の漏えい、滅失又は毀損などのおそれに対して、合理的な安全対策を講じ、予防並びに是正に努めます。また、保有する個人情報を利用する必要がなくなったときは、当該個人情報を遅滞なく消去または廃棄いたします。</li>
							<li class="listTarget"><span>6.</span>個人情報の処理を外部へ委託する場合は、漏えいや第三者への提供を行わない等を契約により義務づけ、委託先に対する適切な管理を実施いたします。</li>
							<li class="listTarget"><span>7.</span>保有する個人情報についての苦情・相談は、弊社の問合せ窓口に連絡頂くことにより、これに対応いたします。</li>
							<li class="listTarget"><span>8.</span>個人情報の取扱いに関する法令、国が定める指針その他の規範を遵守いたします。</li>
							<li class="listTarget"><span>9.</span>個人情報保護活動を推進する部署を設置すると共に、個人情報を管理する部署毎に管理者を置き、適切な管理を行います。</li>
							<li class="listTarget"><span>10.</span>個人情報保護マネジメントシステムを定め、これを定期的に見直し、継続的に改善いたします。</li>
						</ol>
						<div class="otherSectionBlock--flex">
							<div class="otherSection__thum"><img src="/assets/img/contents/privacy/privacy.png" alt="プライバシーマーク" width="250" height="250"></div>
							<p class="otherSection__p fontW--r">
								阪急阪神マーケティングソリューションズ株式会社は、<br class="brPc">
								2020年10月に一般財団法人日本情報経済社会推進協会よりプライバシーマークの付与認定を受けました。<br>
								制定日：2020年4月1日<br>
								改定日：2021年4月1日<br>
								阪急阪神マーケティングソリューションズ株式会社<br>
								代表取締役社長　上田 均
							</p>
						</div>
					</section>
				</div>
<?php
$privacyBody = ob_get_clean();

if ($isNakedMode) :
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php bloginfo('name'); ?> | プライバシーポリシー</title>
    <style>
        body{margin:0;padding:24px;background:#fff;color:#333;font:400 16px/1.8 "Yu Gothic","Hiragino Sans","Noto Sans JP",sans-serif}
        .nakedPolicy{max-width:960px;margin:0 auto}
        .nakedPolicy h1{margin:0 0 24px;font-size:28px;line-height:1.4}
        .otherSection{display:block}
        .otherSection__left{margin-bottom:20px}
        .otherSection__leftTitle{margin:0;font-size:22px;line-height:1.5}
        .otherSection__p{margin:0}
        .otherSection__list{margin:20px 0 0;padding-left:1.5em}
        .otherSection__list .listTarget + .listTarget{margin-top:10px}
        .otherSectionBlock--flex{display:flex;gap:24px;align-items:flex-start;margin-top:24px}
        .otherSection__thum{flex:0 0 180px}
        .otherSection__thum img{display:block;max-width:100%;height:auto}
        @media (max-width:767px){body{padding:16px;font-size:15px}.nakedPolicy h1{font-size:24px}.otherSection__leftTitle{font-size:20px}.otherSectionBlock--flex{display:block}.otherSection__thum{width:160px;margin:0 0 16px}}
    </style>
</head>
<body>
<main class="nakedPolicy" role="main">
    <h1>プライバシーポリシー</h1>
    <?php echo $privacyBody; ?>
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
                            <span property="name">プライバシーポリシー</span>
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
                <h1 class="title fs--22">プライバシーポリシー</h1>
				<?php echo $privacyBody; ?>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
