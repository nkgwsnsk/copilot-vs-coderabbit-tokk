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
                            <span property="name">タグから探す</span>
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
        <!-- ------------------------------------- ↓ hashArticle ハッシュタグ ↓　-->
        <article class="articlePT articlePB hashArticle ">
            <?php
            // -------------------------------------
            // タグ
            // -------------------------------------
            // lutwiyo_debug(get_field('recommended_post_tag_list', 'option'));
            $recommended_post_tag_info_list = TermModelHelper::get_terms_payload(
                'post_tag',
                // ベースになるタームの配列(またはWP_Termに渡すクエリ)
                get_field('recommended_post_tag_list', 'option'),
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );
            // lutwiyo_debug($recommended_post_tag_info_list);
            ?>

            <?php foreach ($recommended_post_tag_info_list as $tag): ?>
                <?php
                // =========== タグ  ===========
                // タグ名：$tag['name']
                // lutwiyo_debug($tag['name']);
                // タグ画像URL：$tag['image_url']
                // リンク先URL：/tag/$tag['slug']
                // ピン留め記事：$tag['pinned_articles_info_list']
                // lutwiyo_debug($tag['pinned_articles_info_list']);
                ?>
                <?php
                // -------------------------------------
                // ピン留め記事の数をカウントし、合計で最大3件分の記事を取得する
                // -------------------------------------
                $pinned_count = $count = is_countable($tag['pinned_list']) ? count($tag['pinned_list']) : 0;
                // ピン留めされた記事 $pinned_count
                // 追加で取得する記事数 (3 - $pinned_count)
                // 除外する記事IDリスト wp_list_pluck($area['pinned_list'], 'ID')
                $additional_tag_articles_info_list = PostModelHelper::get_posts_payload(
                    [
                        'post_type' => 'articles',
                        'posts_per_page' => (5 - $pinned_count),
                        'tax_query' => [
                            [
                                'taxonomy' => 'post_tag',
                                'field' => 'slug',
                                'terms' => $tag['slug'],
                            ],
                        ],
                        'post__not_in' => wp_list_pluck($tag['pinned_list'], 'ID'), // ピン留め記事を除外
                    ],
                    // 抽出したいACFフィールド名の配列
                    [],
                    // 各投稿に対して追加実行する関数セット
                    []
                );
                // lutwiyo_debug($tag['pinned_list']);
                // 固定記事にはis_pinnedフラグを追加しておく
                $tag['pinned_articles_info_list'] = array_map(
                    fn($item) => $item + ['is_pinned' => true],
                    $tag['pinned_articles_info_list'] ?? []
                );
                // ピン留め記事と追加記事を統合
                $integrated_tag_articles_info_list = array_merge(
                    $tag['pinned_articles_info_list'],
                    $additional_tag_articles_info_list
                );
                // テンプレート呼び出し
                get_template_part(
                    'partials/modules/article-list',
                    'tag',
                    [
                        'term_info' => $tag,
                        'integrated_articles_info_list' => $integrated_tag_articles_info_list,
                        'view_all_url' => '', // TODO:リンク先を動的に設定する
                    ]
                );

                ?>
            <?php endforeach; ?>
            <!-- hash すべてみる -->
            <?php
            $recommended_tags = get_field('recommended_post_tag_list', 'option');
            $exclude_tag_ids = [];
            if (is_array($recommended_tags)) {
                // post_tag の値だけ抽出して → 数値のみ → 整数化
                $exclude_tag_ids = array_map(
                    'intval',
                    array_filter(array_column($recommended_tags, 'post_tag'), 'is_numeric')
                );
            }

            $cache_key = 'tag_all_without_recommended';
            $tag_list = get_transient($cache_key);
            if (!is_array($tag_list)) {
                $tags = get_terms([
                    'taxonomy' => 'post_tag',
                    'hide_empty' => true,
                    'exclude' => $exclude_tag_ids,
                ]);
                if (is_wp_error($tags) || !is_array($tags)) {
                    $tags = [];
                }
                set_transient($cache_key, $tags, 30 * MINUTE_IN_SECONDS);
                $tag_list = $tags;
            }

            get_template_part(
                'partials/modules/more',
                'tags',
                ['tag_list' => $tag_list]
            );
            ?>
        </article>
    </main>


<?php get_footer(); ?>
