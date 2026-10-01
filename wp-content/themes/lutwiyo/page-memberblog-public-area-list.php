<?php
/**
 * Template Name: Member Blog Public Area List
 *
 * エリアslug指定の公開ブログ一覧ページ
 */

get_header();

$areaSlug = isset($_GET['area_slug']) ? sanitize_text_field(wp_unslash((string) $_GET['area_slug'])) : '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(50, (int) $_GET['per_page'])) : 20;

$context = $areaSlug === ''
    ? [
        'status' => 'missing_filter',
        'items' => [],
        'total' => 0,
        'filters' => [
            'member_uid' => '',
            'area_slug' => '',
            'tag_slug' => '',
            'page' => $page,
            'per_page' => $perPage,
        ],
    ]
    : tokk_memberblog_get_public_posts_context_from_filters([
        'area_slug' => $areaSlug,
        'page' => $page,
        'per_page' => $perPage,
    ]);

$areaName = $areaSlug;
if ($areaSlug !== '' && function_exists('tokk_memberblog_get_term_name_by_slug')) {
    $areaName = tokk_memberblog_get_term_name_by_slug('area', $areaSlug);
}
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
                        <a class="breadcrumbs__link" href="<?php echo esc_url(home_url('/memberblog-public-list/')); ?>" property="item" typeof="WebPage">
                            <span property="name">会員ブログ公開一覧</span>
                        </a>
                        <meta property="position" content="2">
                    </li>
                    <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                        <span property="name">エリア別ブログ一覧</span>
                        <meta property="position" content="3">
                    </li>
                </ol>
            </nav>
        </div>
    </article>
    <?php get_template_part('partials/modules/area', 'nav'); ?>
    <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
        <section class="gridWide latestSection memberblog-page memberblog-page--wide memberblog-public-page memberblog-public-page--area">
            <h1 class="title fs--22 memberblog-page__title memberblog-public-page__title">エリア別ブログ一覧</h1>
            <?php if ($areaSlug === '') : ?>
                <p class="memberblog-public-page__message">エリアslugが指定されていません。`?area_slug=...` を付けてアクセスしてください。</p>
            <?php else : ?>
                <p class="memberblog-public-page__scope">対象エリア: <?php echo esc_html($areaName); ?>（<?php echo esc_html($areaSlug); ?>）</p>
            <?php endif; ?>
        </section>
        <?php if ($areaSlug !== '') : ?>
            <?php tokk_memberblog_render_public_list($context, [
                'show_filter_form' => false,
                'heading' => '公開中の会員ブログ',
                'hide_heading' => true,
                'view_all_url' => home_url('/memberblog-public-list/'),
            ]); ?>
        <?php endif; ?>
    </article>
</main>
<?php get_footer(); ?>
