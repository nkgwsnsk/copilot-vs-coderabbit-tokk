<?php
/**
 * Template Name: Member Blog List
 *
 * 会員ブログ一覧ページ用テンプレート
 */

get_header();

// 一覧画面は閲覧導線のみ。操作系はポリシー確定まで停止する
$actionResult = ['status' => 'idle', 'message' => ''];
$managementAccess = function_exists('tokk_memberblog_get_management_access_context')
    ? tokk_memberblog_get_management_access_context()
    : ['status' => 'unknown'];
$managementStatus = (string) ($managementAccess['status'] ?? 'unknown');

// 会員UIDは member-service ブリッジを優先して取得する
$uid = function_exists('lutwiyo_get_current_member_uid_from_bridge')
    ? lutwiyo_get_current_member_uid_from_bridge()
    : tokk_get_current_member_uid();
$uid = is_string($uid) ? trim($uid) : '';
if ($uid === '') {
    $uid = null;
}

// 取得結果から画面表示用のコンテキストを生成する
$context = $managementStatus === 'standard'
    ? tokk_memberblog_get_posts_context($uid)
    : ['status' => 'idle', 'items' => []];
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
                            <span property="name">会員ブログ</span>
                            <meta property="position" content="2">
                        </li>
                    </ol>
                </nav>
            </div>
        </article>
        <?php
        get_template_part(
            'partials/modules/area',
            'nav'
        );
        ?>
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <section class="gridWide latestSection memberblog-page memberblog-page--wide">
                <div class="memberblog-page__header">
                    <h1 class="title fs--22 memberblog-page__title">会員ブログ一覧</h1>
                    <?php if ($managementStatus === 'standard') : ?>
                    <div class="btn btnShaped btnBgColor memberblog-page__create" data-shaped="auto-38">
                        <a class="flex--cc btnLink" href="<?php echo esc_url(tokk_memberblog_get_editor_page_url()); ?>" aria-label="新しいブログを書く" title="新しいブログを書く">
                            <p class="btnTtext">新しいブログを書く</p>
                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (($actionResult['status'] ?? 'idle') === 'success') : ?>
                    <p class="memberblog-notice memberblog-notice--success"><?php echo esc_html((string) ($actionResult['message'] ?? '')); ?></p>
                <?php elseif (($actionResult['status'] ?? 'idle') === 'error') : ?>
                    <p class="memberblog-notice memberblog-notice--error"><?php echo esc_html((string) ($actionResult['message'] ?? '')); ?></p>
                <?php endif; ?>
                <?php if ($managementStatus === 'standard') : ?>
                    <?php tokk_memberblog_render_post_list($context); ?>
                <?php else : ?>
                    <?php tokk_memberblog_render_management_access_notice($managementStatus); ?>
                <?php endif; ?>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
