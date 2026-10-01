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
                            <span property="name">エリアから探す</span>
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
        <?php
        // -------------------------------------
        // エリア一覧
        // -------------------------------------
        global $global_header_area_info_list; // header.php で宣言しているものを再利用
        ?>
        <article class="gridWide articlePT articlePB recommendArticle recommendArticle--areaAll" data-boxBgColor="body">
            <?php foreach ($global_header_area_info_list as $area_info) {
                // lutwiyo_debug($area_info['pinned_list']);
                // ピン留め記事の数をカウントし、合計で最大3件分の記事を取得する
                $pinned_count = $count = is_countable($area_info['pinned_list']) ? count($area_info['pinned_list']) : 0;
                // ピン留めされた記事 $pinned_count
                // 追加で取得する記事数 (3 - $pinned_count)
                // 除外する記事IDリスト wp_list_pluck($area['pinned_list'], 'ID')
                $additional_articles_info_list = PostModelHelper::get_posts_payload(
                    [
                        'post_type' => 'articles',
                        'posts_per_page' => (3 - $pinned_count),
                        'tax_query' => [
                            [
                                'taxonomy' => 'area',
                                'field' => 'slug',
                                'terms' => $area_info['slug'],
                            ],
                        ],
                        'post__not_in' => wp_list_pluck($area_info['pinned_list'], 'ID'), // ピン留め記事を除外
                    ],
                    // 抽出したいACFフィールド名の配列
                    [],
                    // 各投稿に対して追加実行する関数セット
                    []
                );
                // 固定記事にはis_pinnedフラグを追加しておく
                $area_info['pinned_articles_info_list'] = array_map(
                    fn($item) => $item + ['is_pinned' => true],
                    $area_info['pinned_articles_info_list'] ?? []
                );
                // ピン留め記事と追加記事を統合
                $integrated_articles_info_list = array_merge(
                    $area_info['pinned_articles_info_list'],
                    $additional_articles_info_list
                );
                // テンプレート呼び出し
                get_template_part(
                    'partials/modules/article-list',
                    'area',
                    [
                        'area_info' => $area_info,
                        'integrated_articles_info_list' => $integrated_articles_info_list
                    ]
                );
            } ?>
        </article>
    </main>
<?php get_footer(); ?>