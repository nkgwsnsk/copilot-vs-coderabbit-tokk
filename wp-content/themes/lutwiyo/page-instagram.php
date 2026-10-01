<?php
/* Template Name: インスタグラムテンプレート */
get_header();
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
                        <span property="name">Instagram</span>
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
    <!-- ------------------------------------- ↓ tag 休日スポット ↓　-->
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <section class="gridWide latestSection">
            <h1 class="title fs--22">Instagram</h1>
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : the_post(); ?>
                    <div class="instagramEmbed">
                        <?php the_content(); ?>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </section>
    </article>
</main>
<?php get_footer(); ?>