<?php get_header(); ?>
<?php
global $global_queried_object;
// lutwiyo_debug($global_queried_object);
global $wp_query;
// lutwiyo_debug($wp_query->posts);

$areacat_query = get_query_var('areacat');
// lutwiyo_debug($areacat_query);
$category_info = TermModelHelper::get_terms_payload('category', ['slug' => $areacat_query], [], []);
// lutwiyo_debug($category_info);
?>

<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
    <article class="grid kv">
        <section class="kvInner">
            <div class="kvBg">
                <img class="kvBg__img" src="/assets/img/sample/page--kv.jpg" alt="TOKK 吹田" loading="lazy" width="2650" height="410">
            </div>
            <h1 class="kvTitle">
                <?php //TODO 英語表記をACFに追加する必要あり ?>
                <p class="fontEn topKv__title topKv__title--en"><?= $global_queried_object->slug; ?></p>
                <p class="topKv__title topKv__title--jp"><?= $global_queried_object->title; ?></p>
            </h1>
            <a class="kvLink" href="/suita/" title="吹田"></a>
            <!-- weather -->
            <div class="flex--cc weather cornerCover__wrapper" data-boxBgColor="body">
                <div class="cornerCover cornerCover--lb cornerCover--outside">
                    <div class="mask cornerCover__inner"></div>
                </div>
                <div class="cornerCover cornerCover--rt cornerCover--outside">
                    <div class="mask cornerCover__inner"></div>
                </div>
                <div class="fontEn fontEn--sb weather__inner">
                    <div class="weatherInfo weather--sunny">
                        <div class="weatherIcon">
                            <div class="mask mask__bgColor--text weatherIcon__inner"></div>
                        </div>
                        <p class="weatherInfo__title">SUNNY</p>
                    </div>
                    <p class="weatherInfo__num">30°</p>
                </div>
            </div>
            <!-- kvInfo -->
            <div class="flex--cc kvInfo cornerCover__wrapper" data-boxBgColor="body">
                <div class="cornerCover cornerCover--lb">
                    <div class="mask cornerCover__inner"></div>
                </div>
                <div class="cornerCover cornerCover--rt">
                    <div class="mask cornerCover__inner"></div>
                </div>
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
                                <a class="breadcrumbs__link" href="/suita/" property="item" typeof="WebPage">
                                    <span property="name">吹田</span>
                                </a>
                                <meta property="position" content="2">
                            </li>
                            <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                                <span property="name">吹田のグルメ</span>
                                <meta property="position" content="3">
                            </li>
                        </ol>
                    </nav>
                </div>
            </div>
        </section>
    </article>
    <!-- ------------------------------------- ↓ areaNav ↓　-->
    <?php
    get_template_part(
        'partials/modules/area',
        'nav'
    );
    ?>
    <!-- ------------------------------------- ↓ latestArticle ↓　-->
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <section class="gridWide latestSection">
            <h2 class="title fs--22"><?= $category_info['title']; ?></h2>
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
        // lutwiyo_debug($category_list);

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
