<?php
/**
 * Module: Masthead Carousel
 *
 * @param array $args {
 * @type array $recommended_articles_list 記事情報の配列
 * }
 */

$recommended_articles_list = $args['recommended_articles_list'] ?? [];
$shouldRenderListFavoriteButton = function_exists('tokk_member_service_should_render_list_favorite_button')
    ? tokk_member_service_should_render_list_favorite_button()
    : true;

if (empty($recommended_articles_list) || !is_array($recommended_articles_list)) {
    return; // データがない場合は何も出力しない
}
?>

<article class="keenSlider__wrapper mastheadArticle">
    <div class="masthead__slider sliderWrap"
         data-keen="true"
         data-loop="true"
         data-mode="snap"
         data-rtl="false"
         data-origin="center"
         data-per-view-pc="1.5"
         data-spacing-pc="0"
         data-per-view-tablet="1.5"
         data-spacing-tablet="0"
         data-per-view-sp="1.15"
         data-spacing-sp="5">

        <div class="keen-slider">
            <?php foreach ($recommended_articles_list as $i => $article) : ?>
                <?php extract(PostViewHelper::prepare_articles($article), EXTR_OVERWRITE); ?>
                <div class="keen-slider__slide">
                    <div class="blockBox mastheadBlock <?= $has_present_class ?>">
                        <a class="blockBox__link"
                           href="<?= esc_url($link); ?>"
                           title="<?= esc_attr($title); ?>"></a>

                        <!-- サムネイル -->
                        <div class="blockBox__thum">
                            <div class="thumImg__wrapper">
                                <img class="thumImg"
                                     src="<?= esc_url($image_url); ?>"
                                     alt="<?= esc_attr($title); ?>"
                                     loading="lazy" width="1900" height="1270">
                            </div>

                            <?php if ($shouldRenderListFavoriteButton): ?>
                                <div class="favBtn js--favBtn <?= esc_attr($is_favorite_class); ?>"
                                     role="button"
                                     data-id="<?= esc_attr($id); ?>"
                                     data-resource-type="tokk_article"
                                     data-resource-id="<?= esc_attr((string) $id); ?>"
                                     aria-pressed="<?= esc_attr(strpos($is_favorite_class, 'active') !== false ? 'true' : 'false'); ?>"
                                     tabindex="0">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            <?php if ($has_present) : ?>
                                <div class="blockBox__pinIcon cornerCover__wrapper blockBox__pinIcon--special"
                                     data-boxbgcolor="body">
                                    <div class="icon iconPin iconPinSpecial">
                                        <div class="mask iconInner">特典あり</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- 情報 -->
                        <div class="blockBox__info" data-boxBgColor="white">
                            <?php
                            get_template_part('partials/modules/article-access', 'badge', [
                                'visible' => $is_paid_member_limited,
                                'label' => $access_badge_label,
                                'modifier_class' => $access_badge_modifier_class,
                                'context' => 'list-card',
                            ]);
                            ?>
                            <p class="fs--21 textHover__target blockTitle"><?= esc_html($title); ?></p>

                            <div class="blockBox__infoSub--list">
                                <!-- 読了時間 -->
                                <div class="blockBox__infoSub--target">
                                    <div class="icon iconTime">
                                        <div class="mask iconInner"></div>
                                    </div>
                                    <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">
                                        <?= esc_html($reading_time_label); ?>
                                    </p>
                                </div>

                                <!-- エリア情報 -->
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
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            <?= esc_html($area_name); ?>
                                        </p>
                                    </a>
                                <?php endforeach; ?>

                                <!-- スタッフ情報 -->
                                <?php foreach ($article['staff_info'] as $staff) :
                                    $staff_name = $staff['title'];
                                    $staff_image_url = $staff['image_url'];
                                    if ($staff_name === '') continue;
                                    ?>
                                    <div class="blockBox__infoSub--target">
                                        <div class="iconPostPerson">
                                            <?php if ($staff_image_url) : ?>
                                                <img class="thumImg"
                                                     src="<?= esc_attr($staff_image_url); ?>"
                                                     alt="<?= esc_attr($staff_name); ?>"
                                                     width="40" height="40" loading="lazy">
                                            <?php endif; ?>
                                        </div>
                                        <p class="fontW--r blockBox__infoSub--text"><?= esc_attr($staff_name); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- タグ情報 -->
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
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Navigation Buttons -->
            <button type="button" data-keen-prev aria-label="prev">
                <div class="btnShaped" data-shaped="95-50">
                    <div class="btnArrow btnArrow--prev" data-arrow="w-15"></div>
                </div>
            </button>
            <button type="button" data-keen-next aria-label="next">
                <div class="btnShaped" data-shaped="95-50">
                    <div class="btnArrow btnArrow--next" data-arrow="w-15"></div>
                </div>
            </button>
        </div>
    </div>
</article>
