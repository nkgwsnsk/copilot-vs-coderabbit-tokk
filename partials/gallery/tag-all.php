<?php get_header(); ?>
<?php
// -----------------------------
//  タグ指定なし：gallery 投稿に紐づくpost_tag一覧を取得
// TODO: デフォルトクエリを書き換えて、ギャラリーの紐づいたタグ一覧が表示されるようにする
// -----------------------------
$tag_slug = $args['tag_slug'] ?? '';
$tag_info = $args['tag_info'] ?? [];

// lutwiyo_debug($tag_slug, $tag_info);
// global $wp_query;
// lutwiyo_debug($wp_query);
$tag_info_list = TermModelHelper::get_terms_payload(
    'post_tag',
    [
        'hide_empty' => true,
        'object_ids' => get_posts([
            'post_type' => 'gallery',
            'numberposts' => -1,
            'fields' => 'ids',
        ]),
    ],
    // 抽出したいACFフィールド名の配列
    [
        'image'
    ],
    [
        // ACFで設定したエリア画像を取得する
        [
            'source' => 'image',
            'callback' => function ($value, $term) {
                return $value['url'] ?? null;
            },
            'target' => 'image_url'
        ],
    ]
);
// lutwiyo_debug($tag_info_list);
?>
<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
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
                        <span property="name">ギャラリー</span>
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
        'nav',
        [
            'area_info' => null,
        ]
    );
    ?>
    <!-- ------------------------------------- ↓ tag 休日スポット ↓　-->
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <section class="gridWide latestSection">
            <h1 class="title fs--22">ギャラリー</h1>
            <section class="gallerySection">
                <div class="latestSection__list">
                    <?php foreach ($tag_info_list as $tag_info):
                        $tag_name = $tag_info['name'] ?? '';
                        $tag_link = home_url('/gallery/' . $tag_info['slug'] . '/');
                        $image_url = $tag_info['image_url'] ?? '';
                        ?>

                        <div class="blockBox galleryBlock" data-boxBgColor="white">
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper">
                                    <img class="thumImg"
                                         src="<?= esc_url($image_url); ?>"
                                         alt="<?= esc_attr($tag_name); ?>"
                                         loading="lazy" width="528" height="470">
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink"
                                           href="<?= esc_url($tag_link); ?>"
                                           title="<?= esc_attr($tag_name); ?>"><p
                                                    class="textHover__target hashTarget--p"><?= esc_attr($tag_name); ?></p>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php // TODO:ページングの動作不良を確認して修正する
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

<?php get_footer(); ?>
