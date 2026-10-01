<?php get_header(); ?>
<?php
global $global_queried_object;
// lutwiyo_debug($global_queried_object);
global $wp_query;
// lutwiyo_debug($wp_query);

// lutwiyo_debug($areacat_query);
$tag_info_list = TermModelHelper::get_terms_payload('post_tag', ['slug' => $global_queried_object->slug], [], []);
// 現在表示しているエリア情報
$current_tag_info = $tag_info_list[0] ?? [];

if (empty($current_tag_info)) {
    // タグ情報が取得できなかった場合の処理（例：404ページへリダイレクト）
    wp_redirect(home_url('/404/'));
    exit;
}

$tag_name = $current_tag_info['name'];
$tag_link = home_url('tag/') . $current_tag_info['link'];
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
                            <span property="name">#<?= esc_html($tag_name); ?></span>
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
                <h2 class="title fs--22"><?= $tag_name; ?></h2>
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
            $cache_key = 'tag_all';
            $tag_all_list = get_transient($cache_key);
            if (!is_array($tag_all_list)) {
                $tags = get_terms([
                    'taxonomy'   => 'post_tag',
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
