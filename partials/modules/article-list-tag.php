<?php
/**
 * Module: article list category
 *
 * @param array $args {
 * @type array $term_info ターム情報
 * @type array $integrated_articles_info_list 記事情報の配列(ピン留め記事＋追加記事)
 * @type string $view_all_url すべてみるリンク
 * }
 */

$term_info = $args['term_info'] ?? [];
$integrated_articles_info_list = $args['integrated_articles_info_list'] ?? [];
$shouldRenderListFavoriteButton = function_exists('tokk_member_service_should_render_list_favorite_button')
    ? tokk_member_service_should_render_list_favorite_button()
    : true;
if (empty($term_info) || !is_array($term_info)) {
    return; // データがない場合は何も出力しない
} ?>
<?php
// TODO : すべてみるのリンク先を動的に変更する(エリア配下のときはエリア配下のカテゴリー別記事一覧へ)
$term_name = $term_info['name'] ?? '';
$term_link = home_url('/tag/') . $term_info['slug'] . '/';
?>
<!-- hashSection #アウトドア -->
<section class="gridWide hashSection">
    <h2 class="title fs--22">#<?= esc_html($term_name); ?></h2>
    <div class="hashSection__list">
        <?php foreach ($integrated_articles_info_list as $article) : ?>
            <?php extract(PostViewHelper::prepare_articles($article), EXTR_OVERWRITE); ?>
            <div class="blockBox blockBox--hash <?= $is_pinned_class; ?>" data-boxBgColor="white">
                <a class="blockBox__link"
                   href="<?= esc_url($link); ?>"
                   title="<?= esc_attr($title); ?>"></a>
                <div class="blockBox__thum">
                    <div class="thumImg__wrapper"><img class="thumImg"
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
                    <?php if ($is_pinned): ?>
                        <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="body">
                            <div class="icon iconPin">
                                <div class="mask iconInner">固定</div>
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
                <div class="blockBox__info">
                    <?php
                    get_template_part('partials/modules/article-access', 'badge', [
                        'visible' => $is_paid_member_limited,
                        'label' => $access_badge_label,
                        'modifier_class' => $access_badge_modifier_class,
                        'context' => 'list-card',
                    ]);
                    ?>
                    <p class="fs--13 textHover__target blockTitle"><?= esc_html($title); ?></p>
                    <div class="blockBox__infoSub--list">
                        <div class="blockBox__infoSub--target">
                            <div class="icon iconTime">
                                <div class="mask iconInner"></div>
                            </div>
                            <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer"><?= esc_html($reading_time_label); ?></p>
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
                                   title="<?= esc_attr($title); ?>"><p
                                            class="textHover__target hashTarget--p"><?= esc_html($tag_name); ?></p></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="fontEn date"><p
                                class="textColor--textGray date--p"><?= esc_html($display_date_label); ?></p></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="btn btnShaped btnBgColor btnAll" data-shaped="145-38">
        <a class="flex--cc btnLink" href="<?= esc_url($term_link); ?>" aria-label="梅田エリアの記事一覧を見る"
           title="梅田エリアの記事一覧を見る">
            <p class="btnTtext">すべてみる</p>
            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
        </a>
    </div>
</section>
