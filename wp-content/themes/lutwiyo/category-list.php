<?php get_header(); ?>
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
                        <span property="name">カテゴリーから探す</span>
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
    <article class="articlePT articlePB cateArticle " data-boxBgColor="body">
        <?php
        // -------------------------------------
        // カテゴリー（TOP）
        // -------------------------------------
        $recommended_category_info_list = TermModelHelper::get_terms_payload(
            'category',
            // ベースになるタームの配列(またはWP_Termに渡すクエリ)
            get_field('recommended_category_list', 'option'),
            // 抽出したいACFフィールド名の配列
            [],
            // 各投稿に対して追加実行する関数セット
            []
        );
        // lutwiyo_debug($recommended_area_info_list);
        ?>

        <?php foreach ($recommended_category_info_list as $category): ?>
            <?php
            // ピン留め記事の数をカウントし、合計で最大5件分の記事を取得する
            $pinned_count = $count = is_countable($category['pinned_list']) ? count($category['pinned_list']) : 0;
            // ピン留めされた記事 $pinned_count
            // 追加で取得する記事数 (5 - $pinned_count)

            $additional_category_articles_info_list = PostModelHelper::get_posts_payload(
                [
                    'post_type' => 'articles',
                    'posts_per_page' => (5 - $pinned_count),
                    'tax_query' => [
                        [
                            'taxonomy' => 'category',
                            'field' => 'slug',
                            'terms' => $category['slug'],
                        ],
                    ],
                    'post__not_in' => wp_list_pluck($category['pinned_list'], 'ID'), // ピン留め記事を除外
                ],
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );
            // lutwiyo_debug($area['pinned_list']);

            // 固定記事にはis_pinnedフラグを追加しておく
            $category['pinned_articles_info_list'] = array_map(
                fn($item) => $item + ['is_pinned' => true],
                $category['pinned_articles_info_list'] ?? []
            );
            // ピン留め記事と追加記事を統合
            $integrated_category_articles_info_list = array_merge(
                $category['pinned_articles_info_list'],
                $additional_category_articles_info_list
            );
            //  リンク先
            $view_all_url = home_url('/category/') . $category['slug'] . '/';
            // テンプレート呼び出し
            get_template_part(
                'partials/modules/article-list',
                'category',
                [
                    'term_info' => $category,
                    'integrated_articles_info_list' => $integrated_category_articles_info_list,
                    'type' => 'category',
                    'view_all_url' => $view_all_url,
                ]
            );
            ?>
        <?php endforeach; ?>

        <?php
        $recommended_categories = get_field('recommended_category_list', 'option');
        $exclude_category_ids = [];
        if (is_array($recommended_categories)) {
            // post_tag の値だけ抽出して → 数値のみ → 整数化
            $exclude_category_ids = array_map(
                'intval',
                array_filter(array_column($recommended_categories, 'category'), 'is_numeric')
            );
        }

        $cache_key = 'category_all_without_recommended';
        $category_list = get_transient($cache_key);
        if (!is_array($category_list)) {
            $categories = get_terms([
                'taxonomy' => 'category',
                'hide_empty' => true,
                'exclude' => $exclude_category_ids,
            ]);
            if (is_wp_error($categories) || !is_array($categories)) {
                $categories = [];
            }
            set_transient($cache_key, $categories, 30 * MINUTE_IN_SECONDS);
            $category_list = $categories;
        }

        get_template_part(
            'partials/modules/more',
            'tags',
            [
                'tag_list' => $category_list,
                'type' => 'category',
            ]
        );
        ?>
    </article>
</main>
<?php get_footer(); ?>
