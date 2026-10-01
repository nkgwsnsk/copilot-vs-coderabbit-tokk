<?php
/**
 * Module: article list area
 *
 * @param array $args {
 * @type array $area_info エリア情報
 * @type array $integrated_articles_info_list 記事情報の配列(ピン留め記事＋追加記事)
 * }
 */

$area_info = $args['area_info'] ?? [];
$integrated_articles_info_list = $args['integrated_articles_info_list'] ?? [];
if (empty($area_info) || !is_array($area_info)) {
    return; // データがない場合は何も出力しない
} ?>
<?php
$area_name = $area_info['name'] ?? '';
$area_link = home_url('/area/') . $area_info['slug'] . '/';
?>
<section class="recommendSection" data-boxBgColor="white">
    <div class="rsHeader">
        <h3 class="title fs--23"><?= esc_html($area_name); ?></h3>
        <div class="btn btnShaped btnBgColor btnAll" data-shaped="120-28">
            <a class="flex--cc btnLink"
               href="<?= esc_url($area_link); ?>"
               aria-label="<?= esc_attr($area_name); ?>エリアの記事一覧を見る"
               title="<?= esc_attr($area_name); ?>エリアの記事一覧を見る">
                <p class="btnTtext">すべてみる</p>
                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
            </a>
        </div>
    </div>
    <div class="rsContents">
        <div class="sliderWrap" data-keen="true"
             data-loop="false"
             data-mode="snap"
             data-rtl="false"
             data-origin="auto"
             data-per-view-pc="3"
             data-spacing-pc="0"
             data-per-view-tablet="2.2"
             data-spacing-tablet="0"
             data-per-view-sp="1.2"
             data-spacing-sp="0">
            <div class="keen-slider">
                <?php foreach ($integrated_articles_info_list as $article) : ?>
                    <?php extract(PostViewHelper::prepare_articles($article), EXTR_OVERWRITE); ?>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--area">
                            <a class="blockBox__link"
                               href="<?= esc_url($link); ?>"
                               title="<?= esc_attr($title); ?>"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img
                                            class="thumImg"
                                            src="<?= esc_url($image_url); ?>"
                                            alt="<?= esc_attr($title); ?>"
                                            loading="lazy" width="1900" height="1270"></div>
                            </div>
                            <div class="blockBox__info">
                                <?php
                                get_template_part('partials/modules/article-access', 'badge', [
                                    'visible' => $is_paid_member_limited,
                                    'label' => $access_badge_label,
                                    'modifier_class' => $access_badge_modifier_class,
                                    'context' => 'list-card',
                                ]);
                                ?>
                                <p class="fs--13 textHover__target blockTitle">
                                    <?= esc_html($title); ?></p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">
                                            <?= esc_html($reading_time_label); ?></p>
                                    </div>
                                    <?php foreach ($article['area_info'] as $area) :
                                        $area_link = home_url('/area/') . $area['slug'] . '/';
                                        $area_name = $area['name'];
                                        ?>
                                        <a class="textHoverWrapper blockBox__infoSub--target"
                                           href="<?= esc_url($area_link); ?>"
                                           title="<?= esc_attr($area_name); ?>">
                                            <div class="icon iconMap">
                                                <div class="mask iconInner"></div>
                                            </div>
                                            <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text"><?= esc_html($area_name); ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <ul class="hashList">
                                    <?php foreach ($article['tag_info'] as $tag) :
                                        $tag_name = $tag['name'];
                                        $tag_link = $tag['link'];
                                        ?>
                                        <li class="hashTarget">
                                            <a class="textHoverWrapper hashLink"
                                               href="<?= esc_url($tag_link); ?>"
                                               title="<?= esc_attr($tag_name); ?>">
                                                <p class="textHover__target hashTarget--p"><?= esc_html($tag_name); ?></p>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="fontEn date"><p
                                            class="textColor--textGray date--p"><?= $display_date_label; ?></p></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
