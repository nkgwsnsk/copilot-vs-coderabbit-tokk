<?php
/**
 * Module: article list new
 *
 * @param array $args {
 * @type array $area_info 現在のエリア情報の配列
 * @type array $new_articles_list 記事情報の配列
 * @type string $link_all_url すべてみるのリンク先URL
 * }
 */
//TODO:ファイルの命名規則を変更するnewarticlesじゃなくてarticle-list-newにする
$area_info = $args['area_info'] ?? [];
$new_articles_list = $args['new_articles_list'] ?? [];
$link_all_url = $args['link_all_url'] ?? '/articles/';
$shouldRenderListFavoriteButton = function_exists('tokk_member_service_should_render_list_favorite_button')
    ? tokk_member_service_should_render_list_favorite_button()
    : true;
if (empty($new_articles_list) || !is_array($new_articles_list)) {
    return; // データがない場合は何も出力しない
}
$area_name = is_array($area_info) ? (string) ($area_info['name'] ?? '') : '';
?>
<?php
$title = $area_name !== '' ? $area_name . 'の最新記事' : '最新記事';
$link_title = $area_name !== '' ? $area_name . 'の記事一覧を見る' : '記事一覧を見る';
?>
<article class="articlePT articlePB newArticle" data-boxBgColor="bodySub">
    <h2 class="gridWide title fs--22"><?= esc_html($title); ?></h2>
    <section class="gridWide keenSlider__wrapper keenSlider__parts--lefttop">
        <div class="sliderWrap" data-keen="true"
             data-loop="false"
             data-mode="snap"
             data-rtl="false"
             data-origin="auto"
             data-per-view-pc="1"
             data-spacing-pc="45"
             data-per-view-tablet="1"
             data-spacing-tablet="45"
             data-per-view-sp="1"
             data-spacing-sp="12">
            <div class="keen-slider">
                <?php for ($i = 0; $i < 2; $i++) : ?>
                    <div class="keen-slider__slide">
                        <div class="flexColumn flexColumn--2">
                            <?php for ($j = ($i * 8); $j < ($i * 8) + 8; $j++):
                                $article = $new_articles_list[$j] ?? null;
                                if (!$article) continue; ?>
                                <?php extract(PostViewHelper::prepare_articles($article), EXTR_OVERWRITE); ?>
                                <div class="flexColumnBox blockBox blockBox--flex <?= $is_new_class; ?>"
                                     data-boxBgColor="body">
                                    <a class="blockBox__link"
                                       href="<?= esc_url($link); ?>"
                                       title="<?= esc_attr($title); ?>"></a>
                                    <div class="blockBox__thum">
                                        <div class="thumImg__wrapper"><img
                                                    class="thumImg"
                                                    src="<?= esc_url($image_url); ?>"
                                                    alt="<?= esc_attr($title); ?>"
                                                    loading="lazy" width="1900" height="1270"></div>
                                        <?php if ($shouldRenderListFavoriteButton): ?>
                                            <!-- favBtn -->
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
                                        <p class="fs--15 textHover__target blockTitle"><?= esc_html($title); ?></p>
                                        <div class="blockBox__infoSub--list">
                                            <div class="blockBox__infoSub--target">
                                                <div class="icon iconTime">
                                                    <div class="mask iconInner"></div>
                                                </div>
                                                <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer"><?= $reading_time_label; ?></p>
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
                                                    <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                                        <?= esc_html($area_name); ?></p>
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
                                                    class="textColor--textGray date--p"><?= esc_html($display_date_label); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                <?php endfor; ?>
            </div>
            <!-- keen dots -->
            <div class="keen-dots fontEn" data-keen-dots></div>
            <!-- keen button -->
            <button type="button" data-keen-prev aria-label="prev">
                <div class="btnCircle btnShaped" data-shaped="38-38">
                    <div class="btnArrow btnArrow--prev" data-arrow="w-8"></div>
                </div>
            </button>
            <button type="button" data-keen-next aria-label="next">
                <div class="btnCircle btnShaped" data-shaped="38-38">
                    <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                </div>
            </button>
        </div>
        <div class="btn btnShaped btnBgColor btnAll" data-shaped="145-38">
            <a class="flex--cc btnLink"
               href="<?= esc_url($link_all_url); ?>"
               aria-label="<?= esc_attr($link_title); ?>"
               title="<?= esc_attr($link_title); ?>">
                <p class="btnTtext">すべてみる</p>
                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
            </a>
        </div>
    </section>

</article>
