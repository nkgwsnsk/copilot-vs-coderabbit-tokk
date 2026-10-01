<?php
/**
 * Template Name: Member Blog Public List
 *
 * 会員ブログ公開一覧ページ（会員UID/エリアslug/タグslugで絞り込み）
 */

get_header();

$context = tokk_memberblog_get_public_posts_context();
?>
<main class="main wrapper" role="main">
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
                        <span property="name">会員ブログ公開一覧</span>
                        <meta property="position" content="2">
                    </li>
                </ol>
            </nav>
        </div>
    </article>
    <?php get_template_part('partials/modules/area', 'nav'); ?>
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <section class="gridWide latestSection memberblog-page memberblog-page--wide memberblog-public-page">
            <h1 class="title fs--22 memberblog-page__title memberblog-public-page__title">会員ブログ公開一覧</h1>
        </section>
        <?php tokk_memberblog_render_public_list($context, [
            'heading' => '新着の会員ブログ',
            'hide_heading' => true,
            'show_filter_form' => true,
        ]); ?>
    </article>
</main>
<?php get_footer(); ?>
