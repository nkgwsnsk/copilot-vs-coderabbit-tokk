<?php
global $wp_query;
/**
 * Module: gallery tag detail
 *
 * @param array $args {
 * @type string $tag_slug タグのスラッグ
 * @type array $tag_info タグ情報
 * }
 */
// TODO:デフォルトクエリを用いた処理に書き換える
$tag_slug = $args['tag_slug'] ?? '';
$tag_info = $args['tag_info'] ?? [];
if (empty($tag_info) || !is_array($tag_info)) {
    return; // データがない場合は何も出力しない
} ?>
<?php get_header(); ?>
<?php
// タグ指定あり：該当タグを持つ gallery 投稿を取得
$gallery_info_list = PostModelHelper::get_posts_payload(
    [
        'post_type' => 'gallery',
        'posts_per_page' => POSTS_PER_PAGE,
        'tax_query' => [
            [
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $tag_slug,
            ],
        ],
    ],
    ['text', 'post_tag']
);
?>
<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
    <?php
    $tag_name = $tag_info['name'] ?? '';
    ?>
    <article class="grid kv">
        <div class="kvInfo__inner">
            <nav class="breadcrumbs" aria-label="Breadcrumbs" role="navigation">
                <ol class="breadcrumbs__list" vocab="http://schema.org/" typeof="BreadcrumbList">
                    <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                        <a class="breadcrumbs__link" href="<?= home_url('/'); ?>" property="item" typeof="WebPage">
                            <span property="name">トップ</span>
                        </a>
                        <meta property="position" content="1">
                    </li>
                    <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                        <a class="breadcrumbs__link" href="<?= home_url('/gallery/'); ?>" property="item"
                           typeof="WebPage">
                            <span property="name">ギャラリー</span>
                        </a>
                        <meta property="position" content="2">
                    </li>
                    <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                        <span property="name">#<?= esc_html($tag_name); ?></span>
                        <meta property="position" content="3">
                    </li>
                </ol>
            </nav>
        </div>
    </article>
    <!-- ------------------------------------- ↓ areaNav ↓　-->
    <?php
    get_template_part(
        'partials/modules/area',
        'nav',
        [
            'area_info' => null,
        ]
    );
    ?>
    <!-- ------------------------------------- ↓ tag 休日スポット ↓　-->
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <section class="gridWide latestSection">
            <h1 class="hashLink title fs--22"><?= esc_html($tag_name); ?></h1>
            <section class="gallerySection">
                <div class="latestSection__list">
                    <?php foreach ($gallery_info_list as $gallery_info) : ?>
                        <?php
                        $gallery_title_text = $gallery_info['text'] ?? '';
                        $title = $gallery_info['title'] ?? '';
                        $image_url = $gallery_info['image_url'] ?? '';
                        $gallery_acf_list = $gallery_info['gallery'] ?? [];
                        ?>
                        <?php
                        $data_images = [];
                        foreach ($gallery_acf_list as $gallery_acf) :
                            $gallery_image_url = $gallery_acf['image']['url'] ?? '';
                            $gallery_text = $gallery_acf['text'] ?? '';

                            if ($gallery_image_url) {
                                $data_images[] = [
                                    'url' => $gallery_image_url,
                                    'alt' => $gallery_text,
                                ];
                            }
                        endforeach;
                        // HTML属性に安全に出力できる JSON 文字列へ変換
                        $data_images_attr = esc_attr(json_encode($data_images));
                        $tag_json_attr = esc_attr(json_encode($gallery_info['tag_info']));
                        // lutwiyo_debug($data_images_attr);
                        ?>

                        <div class="blockBox galleryBlock" data-boxBgColor="white">
                            <div class="blockBox__thum js-galleryThumb"
                                 data-images="<?= $data_images_attr; ?>"
                                 data-tags="<?= $tag_json_attr; ?>">
                                <div class="thumImg__wrapper">
                                    <img class="thumImg"
                                         src="<?= esc_url($image_url); ?>"
                                         alt="<?= esc_attr($title); ?>"
                                         loading="lazy" width="528" height="470">
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p><?= esc_html($gallery_title_text); ?></p>
                            </div>
                            <?php /*
                                <div class="blockBox__info">
                                    <ul class="hashList">
                                        <?php foreach ($gallery_info['tag_info'] as $tag) :
                                            $tag_name = $tag['name'];
                                            $tag_link = home_url('/gallery/') . $tag['slug'];
                                            ?>
                                            <li class="hashTarget">
                                                <a class="textHoverWrapper hashLink"
                                                   href="<?= esc_url($tag_link); ?>"
                                                   title="<?= esc_attr($tag_name); ?>"><p
                                                            class="textHover__target hashTarget--p"><?= esc_html($tag_name); ?></p>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
 */ ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php // ページング
                // $paged = get_query_var('paged') ?: 1;// 現在ページ
                // echo Pagination::render($paged, $wp_query->max_num_pages); // ページネーション出力
                ?>
            </section>

        </section>
        <!-- hash すべてみる -->
        <?php
        $cache_key = 'tag_all';
        $tag_all_list = get_transient($cache_key);
        if (!is_array($tag_all_list)) {
            $tags = get_terms([
                'taxonomy' => 'post_tag',
                'hide_empty' => true,
            ]);
            if (is_wp_error($tags) || !is_array($tags)) {
                $tags = [];
            }
            set_transient($cache_key, $tags, 30 * MINUTE_IN_SECONDS);
            $tag_all_list = $tags;
        }
        get_template_part(
            'partials/modules/more',
            'tags',
            ['tag_list' => $tag_all_list]
        );
        ?>
    </article>
</main>
<?php // 共通ギャラリーモーダル（ページに1つだけ）?>
<div class="galleryModal" id="galleryModal">
    <div class="galleryModal__inner">
        <section class="keenSlider__wrapper">
            <div class="sliderWrap" data-keen="true"
                 data-loop="false"
                 data-mode="snap"
                 data-rtl="false"
                 data-origin="auto"
                 data-per-view-pc="10"
                 data-spacing-pc="25"
                 data-per-view-tablet="7"
                 data-spacing-tablet="20"
                 data-per-view-sp="3.6"
                 data-spacing-sp="20">
                <? // JSで中身を生成 ?>
                <div class="keen-slider" id="galleryModalSlider"></div>
                <!-- keen button -->
                <button type="button" data-keen-prev aria-label="prev">
                    <div class="btnCircle btnShaped btnBgColor" data-shaped="95-50">
                        <div class="btnArrow btnArrow--prev" data-arrow="w-15"></div>
                    </div>
                </button>
                <button type="button" data-keen-next aria-label="next">
                    <div class="btnCircle btnShaped btnBgColor" data-shaped="95-50">
                        <div class="btnArrow btnArrow--next" data-arrow="w-15"></div>
                    </div>
                </button>
            </div>
        </section>
    </div>
    <div class="galleryModal__closeBtn">
        <div class="galleryModal__closeBtn--inner">
            <p class="fontW--r galleryModal__closeBtn--p">閉じる</p>
            <div class="closeIcon"></div>
        </div>
    </div>
</div>
<?php get_footer(); ?>
