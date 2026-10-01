<?php
/**
 * Template Name: Member Blog Editor
 *
 * 会員ブログ新規追加・編集ページ用テンプレート
 */

get_header();

$managementAccess = function_exists('tokk_memberblog_get_management_access_context')
    ? tokk_memberblog_get_management_access_context()
    : ['status' => 'unknown'];
$managementStatus = (string) ($managementAccess['status'] ?? 'unknown');

$actionResult = $managementStatus === 'standard'
    ? tokk_memberblog_handle_actions(['create', 'update', 'delete'])
    : ['status' => 'idle', 'message' => ''];

$uid = function_exists('lutwiyo_get_current_member_uid_from_bridge')
    ? lutwiyo_get_current_member_uid_from_bridge()
    : tokk_get_current_member_uid();
$uid = is_string($uid) ? trim($uid) : '';
if ($uid === '') {
    $uid = null;
}

$editItem = tokk_memberblog_resolve_editor_item($actionResult);
$isEditing = is_array($editItem) && (int) ($editItem['id'] ?? 0) > 0;
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
                            <a class="breadcrumbs__link" href="<?php echo esc_url(tokk_memberblog_get_list_page_url()); ?>" property="item" typeof="WebPage">
                                <span property="name">会員ブログ一覧</span>
                            </a>
                            <meta property="position" content="2">
                        </li>
                        <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                            <span property="name"><?php echo esc_html($isEditing ? '会員ブログ編集' : '会員ブログを書く'); ?></span>
                            <meta property="position" content="3">
                        </li>
                    </ol>
                </nav>
            </div>
        </article>
        <?php get_template_part('partials/modules/area', 'nav'); ?>
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <section class="gridWide latestSection memberblog-page memberblog-editor-page">
                <div class="memberblog-page__header">
                    <h1 class="title fs--22 memberblog-page__title"><?php echo esc_html($isEditing ? '会員ブログ編集' : '会員ブログを書く'); ?></h1>
                    <?php if ($managementStatus === 'standard') : ?>
                    <?php /* クライアント指定によりコメントアウト
                    <div class="btn btnShaped btnBgColor memberblog-page__create memberblog-page__create--list" data-shaped="auto-38">
                        <a class="flex--cc btnLink" href="<?php echo esc_url(tokk_memberblog_get_list_page_url()); ?>" aria-label="投稿したブログの一覧" title="投稿したブログの一覧">
                            <p class="btnTtext">投稿したブログの一覧</p>
                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                        </a>
                    </div> */ ?>
                    <?php endif; ?>
                </div>
                <?php if ($managementStatus !== 'standard') : ?>
                    <?php tokk_memberblog_render_management_access_notice($managementStatus); ?>
                <?php elseif ($uid === null) : ?>
                    <p>ログインが必要です。<a href="/login/">会員ログイン</a>を行ってください。</p>
                <?php else : ?>
                    <?php if (($actionResult['status'] ?? 'idle') === 'success') : ?>
                        <p class="memberblog-notice memberblog-notice--success"><?php echo esc_html((string) ($actionResult['message'] ?? '')); ?></p>
                    <?php elseif (($actionResult['status'] ?? 'idle') === 'error') : ?>
                        <p class="memberblog-notice memberblog-notice--error"><?php echo esc_html((string) ($actionResult['message'] ?? '')); ?></p>
                    <?php endif; ?>
                    <?php tokk_memberblog_render_editor_form($editItem); ?>
                <?php endif; ?>
            </section>
        </article>
    </main>
    <script src="<?php echo esc_url(home_url('/assets/js/memberblog-editor.js')); ?>"></script>
<?php get_footer(); ?>
