<?php
/* Template Name: プレゼント応募 */
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
                            <span property="name">プレゼント応募</span>
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
        <?php //TODO: 現状デザインが崩れているので調整等が必要 ?>
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <section class="gridWide latestSection">
                <h1 class="title fs--22">プレゼント応募</h1>
                <div class="otherSection contactSection">
                    <div class="otherSection__right">
                        <div class="otherSectionBlock">
                            <div class="entryLeader">
                                <?php
                                // <h2 class="entryLeader__title">阪急沿線情報紙　TOKK　プレゼント応募</h2>
                                ?>
                                <p class="otherSection__p fontW--r">
                                    読者プレゼントは、TOKKが運営するフリーペーパー、WEBサイト、X（Twitter）、Instagramのいずれかに<br class="brPc">
                                    関するアンケートにお答えいただいた後、ご応募いただけます。<br><br>

                                    また応募画面に記載の注意事項、個人情報の取扱いに同意の上、ご応募をいただきますようお願いいたします。
                                </p>
                                <aside class="textIndent entryLeader__aside fontW--r">※賞品を配送後、宛先不明、当選者による受取不能または拒否、<br>保管期間経過その他の理由により当該取得賞品が配送されず、弊社に返送された場合、当選は無効となります。 </aside>
                            </div>
                            <div class="flexColumn otherSection__infoBtn pageContact__infoBtn peBtnList">
                                <div>
                                    <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                        <?php $presentSurveyActionGate = lutwiyo_get_member_benefit_action_gate('https://hhms-research.surveys.jp/tokk', 'present'); ?>
                                        <?php $presentSurveyLinkAttrs = lutwiyo_get_member_benefit_action_attrs($presentSurveyActionGate); ?>
                                        <a class="flex--cc btnLink<?php echo ($presentSurveyActionGate['mode'] ?? 'direct') === 'direct' ? '' : ' js--memberBenefitActionGate'; ?>" href="<?php echo esc_url((string) ($presentSurveyActionGate['href'] ?? 'https://hhms-research.surveys.jp/tokk')); ?>" <?php echo $presentSurveyLinkAttrs; ?> title="フリーペーパーのアンケートに答えてプレゼントに応募する" <?php if (($presentSurveyActionGate['mode'] ?? 'direct') === 'direct') : ?>target="_blank"<?php endif; ?>>
                                            <p class="btnTtext">フリーペーパーのアンケートに答えて応募</p>
                                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                        </a>
                                    </div>
                                </div>
                                <div>
                                    <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                        <?php $presentSurveyActionGate = lutwiyo_get_member_benefit_action_gate('https://hhms-research.surveys.jp/webtokk', 'present'); ?>
                                        <?php $presentSurveyLinkAttrs = lutwiyo_get_member_benefit_action_attrs($presentSurveyActionGate); ?>
                                        <a class="flex--cc btnLink<?php echo ($presentSurveyActionGate['mode'] ?? 'direct') === 'direct' ? '' : ' js--memberBenefitActionGate'; ?>" href="<?php echo esc_url((string) ($presentSurveyActionGate['href'] ?? 'https://hhms-research.surveys.jp/webtokk')); ?>" <?php echo $presentSurveyLinkAttrs; ?> title="Webのアンケートに答えてプレゼントに応募する" <?php if (($presentSurveyActionGate['mode'] ?? 'direct') === 'direct') : ?>target="_blank"<?php endif; ?>>
                                            <p class="btnTtext">Webのアンケートに答えて応募</p>
                                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                        </a>
                                    </div>
                                </div>
                                <div>
                                    <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                        <?php $presentSurveyActionGate = lutwiyo_get_member_benefit_action_gate('https://hhms-research.surveys.jp/TOKK-X', 'present'); ?>
                                        <?php $presentSurveyLinkAttrs = lutwiyo_get_member_benefit_action_attrs($presentSurveyActionGate); ?>
                                        <a class="flex--cc btnLink<?php echo ($presentSurveyActionGate['mode'] ?? 'direct') === 'direct' ? '' : ' js--memberBenefitActionGate'; ?>" href="<?php echo esc_url((string) ($presentSurveyActionGate['href'] ?? 'https://hhms-research.surveys.jp/TOKK-X')); ?>" <?php echo $presentSurveyLinkAttrs; ?> title="X（Twitter）のアンケートに答えてプレゼントに応募する" <?php if (($presentSurveyActionGate['mode'] ?? 'direct') === 'direct') : ?>target="_blank"<?php endif; ?>>
                                            <p class="btnTtext">X（Twitter）のアンケートに答えて応募</p>
                                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                        </a>
                                    </div>
                                </div>
                                <div>
                                    <div class="btn btnShaped btnBgColor btnAll" data-shaped="320-45">
                                        <?php $presentSurveyActionGate = lutwiyo_get_member_benefit_action_gate('https://hhms-research.surveys.jp/tokk-instagram', 'present'); ?>
                                        <?php $presentSurveyLinkAttrs = lutwiyo_get_member_benefit_action_attrs($presentSurveyActionGate); ?>
                                        <a class="flex--cc btnLink<?php echo ($presentSurveyActionGate['mode'] ?? 'direct') === 'direct' ? '' : ' js--memberBenefitActionGate'; ?>" href="<?php echo esc_url((string) ($presentSurveyActionGate['href'] ?? 'https://hhms-research.surveys.jp/tokk-instagram')); ?>" <?php echo $presentSurveyLinkAttrs; ?> title="Instagramのアンケートに答えてプレゼントに応募する" <?php if (($presentSurveyActionGate['mode'] ?? 'direct') === 'direct') : ?>target="_blank"<?php endif; ?>>
                                            <p class="btnTtext">Instagramのアンケートに答えて応募</p>
                                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
