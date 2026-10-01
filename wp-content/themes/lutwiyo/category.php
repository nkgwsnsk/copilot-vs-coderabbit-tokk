<?php get_header(); ?>
<?php
// ターム情報をヘッダから取得
global $global_queried_object;
// クエリを取得
global $wp_query;

// 現在表示しているカテゴリー情報を取得
$category_info_list = TermModelHelper::get_terms_payload(
    'category',
    [
        'slug' => $global_queried_object->slug
    ],
    [],
    []
);
$current_category_info = $category_info_list[0] ?? [];

if (empty($current_category_info)) {
    // タグ情報が取得できなかった場合の処理（例：404ページへリダイレクト）
    wp_redirect(home_url('/404'));
    exit;
}

$category_name = $current_category_info['name'];
$category_link = home_url('tag/') . $current_category_info['link'] . '/';
?>

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
                            <span property="name">#<?= esc_html($category_name); ?></span>
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
        <!-- ------------------------------------- ↓ tag  ↓　-->
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <section class="gridWide latestSection">
                <h2 class="title fs--22"><?= $category_name; ?></h2>
                <?php // 記事一覧モジュールを呼び出し
                $article_info_list = PostModelHelper::get_posts_payload($wp_query->posts);
                get_template_part('partials/modules/article-list', 'grid', ['article_info_list' => $article_info_list]);
                ?>

                <?php // ページング
                $paged = get_query_var('paged') ?: 1;// 現在ページ
                echo Pagination::render($paged, $wp_query->max_num_pages); // ページネーション出力
                ?>
            </section>
            <!-- hash すべてみる -->
            <?php
            $cache_key = 'category_all';
            $category_list = get_transient($cache_key);
            if (!is_array($category_list)) {
                $tags = get_terms([
                    'taxonomy' => 'category',
                    'hide_empty' => true,
                ]);
                if (is_wp_error($tags) || !is_array($tags)) {
                    $tags = [];
                }
                set_transient($cache_key, $tags, 30 * MINUTE_IN_SECONDS);
                $category_list = $tags;
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
