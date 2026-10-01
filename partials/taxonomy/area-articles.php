<?php get_header(); ?>
<?php
// -------------------------------------
// エリア情報取得(ACFフィールド含む)
// -------------------------------------
global $global_queried_object;
$term = $global_queried_object;

$area_info_list = TermModelHelper::get_terms_payload(
    'area',
    // ベースになるタームの配列(またはWP_Termに渡すクエリ)
    [$term->term_taxonomy_id],
    [],
    // 各投稿に対して追加実行する関数セット
    [],
);

// 現在表示しているエリア情報
$area_info = $area_info_list[0] ?? [];

if (empty($area_info)) {
    // エリア情報が取得できなかった場合の処理（例：404ページへリダイレクト）
    wp_redirect(home_url('/404/'));
    exit;
}

// -------------------------------------
// TODO: そのうち正しいページを実装する。
// 現状は、エリアで絞り込んだ検索結果にリダイレクト
// -------------------------------------

    wp_redirect(home_url('/?s=&area%5B%5D=' . $area_info['slug'] . '/'));
    exit;




global $wp_query;
// lutwiyo_debug($wp_query->posts);
$article_info_list = PostModelHelper::get_posts_payload($wp_query->posts);

?>
<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
    <?php
    get_template_part(
        'partials/modules/kv',
        null,
        ['area_info' => $area_info ?? []]
    );

    ?>
    <!-- ------------------------------------- ↓ areaNav ↓　-->
    <?php
    get_template_part(
        'partials/modules/area',
        'nav'
    );
    ?>
    <!-- ------------------------------------- ↓ latestArticle 吹田の最新一覧 ↓　-->
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <!-- 吹田の最新一覧 -->
        <section class="gridWide latestSection">
            <h2 class="title fs--22">吹田の最新一覧</h2>
            <?php
            // 記事一覧モジュールを呼び出し
            $article_info_list = PostModelHelper::get_posts_payload($wp_query->posts);
            get_template_part('partials/modules/article-list', 'grid', ['article_info_list' => $article_info_list]);

            // ページング
            $paged = get_query_var('paged') ?: 1;// 現在ページ
            echo Pagination::render($paged, $wp_query->max_num_pages); // ページネーション出力
            ?>
        </section>
        <!-- hash すべてみる -->
        <?php
        // キャッシュキーを生成（エリアスラッグごとにユニーク）
        $cache_key = 'tag_within_area_' . $global_queried_object->slug;

        // トランジェントキャッシュを取得
        $tag_within_area_list = get_transient($cache_key);

        // キャッシュが存在しない場合のみ再取得
        if ($tag_within_area_list === false) {
            // エリア配下のカテゴリ基盤データ(エリア=XXXが付与されている記事のカテゴリ一覧)
            $tag_within_area_list = ($article_ids = get_posts([
                'post_type' => 'articles',
                'fields' => 'ids',
                'posts_per_page' => -1,
                'tax_query' => [[
                    'taxonomy' => 'area',
                    'field' => 'slug',
                    'terms' => $global_queried_object->slug,
                ]],
            ])) ? wp_get_object_terms($article_ids, 'post_tag', ['hide_empty' => true]) : [];

            // 30分間キャッシュ保存（必要に応じて変更可）
            set_transient($cache_key, $tag_within_area_list, 30 * MINUTE_IN_SECONDS);
        }

        get_template_part(
            'partials/modules/more',
            'tags',
            ['tag_list' => $tag_within_area_list]
        );
        ?>
    </article>
</main>
<?php get_footer(); ?>

