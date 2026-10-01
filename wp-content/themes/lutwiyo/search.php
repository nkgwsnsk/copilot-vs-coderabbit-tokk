<?php
get_header();
global $wp_query;
$article_info_list = PostModelHelper::get_posts_payload($wp_query->posts);
?>
<!-- ==================================================================== ↓ wrapper ↓ -->
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
                        <span property="name">検索結果</span>
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
    <?php
    $tax_labels = lutwiyo_get_search_tax_labels();
    $keywords = preg_split('/[\s　]+/u', get_search_query(), -1, PREG_SPLIT_NO_EMPTY);
    $keyword_text = $keywords ? implode(' ', $keywords) : '-';
    $search_member_access_plan = isset($_GET['member_access_plan'])
        ? sanitize_key(wp_unslash((string) $_GET['member_access_plan']))
        : '';
    $search_member_access_plan_label = $search_member_access_plan === 'paid_member'
        ? '有料会員限定の記事のみ'
        : '-';
    ?>
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <section class="gridWide latestSection">
            <h1 class="title fs--22">検索結果</h1>
            <div class="searchResult">
                <div class="textColor--textGray searchResult__target--title">フリーワード</div>
                <div class="searchResult__target--text"><?= esc_html($keyword_text); ?></div>
                <div class="textColor--textGray searchResult__target--title">タグ</div>
                <?php if (!empty($tax_labels['post_tag'])) : ?>
                    <?php foreach ($tax_labels['post_tag'] as $name) : ?>
                        <div class="hashLink searchResult__target--text"><?= esc_html($name); ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="searchResult__target--text">-</div>
                <?php endif; ?>
                <div class="textColor--textGray searchResult__target--title">カテゴリー</div>
                <?php if (!empty($tax_labels['category'])) : ?>
                    <?php foreach ($tax_labels['category'] as $name) : ?>
                        <div class="searchResult__target--text"><?= esc_html($name); ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="searchResult__target--text">-</div>
                <?php endif; ?>
                <div class="textColor--textGray searchResult__target--title">エリア</div>
                <?php if (!empty($tax_labels['area'])) : ?>
                    <?php foreach ($tax_labels['area'] as $name) : ?>
                        <div class="searchResult__target--text"><?= esc_html($name); ?></div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="searchResult__target--text">-</div>
                <?php endif; ?>
                <div class="textColor--textGray searchResult__target--title">会員向け記事</div>
                <div class="searchResult__target--text"><?= esc_html($search_member_access_plan_label); ?></div>
            </div>

            <?php if (!empty($article_info_list)): ?>
                <?php // 記事一覧モジュールを呼び出し
                get_template_part('partials/modules/article-list', 'grid', ['article_info_list' => $article_info_list]);
                /**
                 * デバッグ用スクリプトのためコメントアウト
                 */
                /*
                if(have_posts()){
                    while(have_posts()){
                        the_post();
                        the_title();
                        echo '<br>';
                    }
                }
                */
                ?>
                <?php // ページング
                $paged = max(1, (int) get_query_var('paged'));
                echo Pagination::render($paged, $wp_query->max_num_pages); // ページネーション出力
                ?>
            <?php else: ?>
                <p style="margin-top:30px;">該当する投稿は見つかりませんでした。</p>
            <?php endif; ?>
        </section>
    </article>
</main>
<?php get_footer(); ?>
