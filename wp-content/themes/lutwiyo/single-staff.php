<?php get_header(); ?>
<?php //スタッフ詳細情報  ?>
<?php
// TODO:ざっくりコードレビューする。
// TODO:肩書きパーツを確認しておく。[263]になっているものもあるのであとで修正しておく
// TODO:画像右側部分はグループが入るはずなのに肩書きが入っているのでロジックを修正する
// TODO:ページネーションを追加する
// TODO:2ページ目以降のURLもルーティングに追加しておく
/**
 * 基本：現在のスタッフID
 */
$staff_id = get_the_ID();
if (!$staff_id) {
    // lutwiyo_debug('スタッフIDが取得できませんでした', null);
    get_footer();
    exit;
}

/**
 * 1) スタッフ情報（ACF含む）を取得
 *    - 取得項目: image, role, text_detail, url_instagram, url_x, url_facebook
 */
$staff_result = PostModelHelper::get_posts_payload(
    [
        'p' => $staff_id,
        'post_type' => 'staff',
        'post_status' => 'publish',
        'posts_per_page' => 1,
    ],
    ['image', 'role', 'text_profile', 'text_detail', 'url_instagram', 'url_x', 'url_facebook']
);
$staff_info = $staff_result[0] ?? [];

/**
 * 2) スタッフが属するグループを取得（名称表示用）
 */
$group_ids = wp_get_post_terms($staff_id, 'staff_group', ['fields' => 'ids']);
$groups = !empty($group_ids)
    ? TermModelHelper::get_terms_payload('staff_group', array_map('intval', $group_ids), [])
    : [];
$group_names = array_map(fn($g) => $g['name'] ?? '', $groups);

/**
 * 3) スタッフ情報のデバッグ表示
 *    - imageは image_url 優先、なければ image（ACF）からURL抽出
 *    - roleは配列/文字列どちらでも出力
 */
$img_url = $staff_info['image_url'] ?? '';
if (!$img_url) {
    $img_raw = $staff_info['image'] ?? '';
    if (is_array($img_raw)) {
        $img_url = $img_raw['url'] ?? '';
    } elseif (is_string($img_raw)) {
        $img_url = $img_raw;
    }
}
$role_display = (function ($role_raw) {
    if (is_array($role_raw)) {
        return implode(', ', array_map(fn($v) => is_scalar($v) ? (string)$v : wp_json_encode($v), $role_raw));
    }
    return (string)($role_raw ?? '');
})($staff_info['role'] ?? '');

?>


    <!-- ==================================================================== ↓ wrapper ↓ -->
    <main class="main wrapper" role="main">

<?php

//表示確認用ロジックです。こちらをベースにテンプレートに埋め込んでください。
//スタッフ詳細情報の参考です。スタッフ情報の全量は$staff_infoに格納されています。
// $staff_debug = [
//     'スタッフグループ名' => implode(' / ', array_filter($group_names)),
//     'スタッフ画像URL' => $img_url,
//     'スタッフロール' => $role_display,
//     'スタッフ名' => $staff_info['title'] ?? get_the_title($staff_id),
//     'スタッフ詳細' => $staff_info['text_detail'] ?? '',
//     'Instagram' => $staff_info['url_instagram'] ?? '',
//     'X' => $staff_info['url_x'] ?? '',
//     'Facebook' => $staff_info['url_facebook'] ?? '',
// ];
// lutwiyo_debug('■スタッフ情報', $staff_debug);

/**
 * 4) 関連記事一覧（post_type=articles）
 *    - ACF: staff_list に当該スタッフIDが含まれる記事を抽出
 *    - 15件/ページ、?pg=2 以降でページング
 *    - PostModelHelper 第4引数 $raw に WP_Query が入る前提（$raw->found_posts で総件数）
 */
$per_page = 15;
$page = max(1, (int)(get_query_var('pg') ?: 1));
$offset = ($page - 1) * $per_page;

$article_query_args = [
    'post_type' => 'articles',
    'post_status' => 'publish',
    'posts_per_page' => $per_page,
    'offset' => $offset,
    'meta_query' => [
        [
            'key' => 'staff_list',
            'value' => '"' . $staff_id . '"', // ACF Relationshipのシリアライズにヒットさせる
            'compare' => 'LIKE',
        ],
    ],
];
//
$raw_articles = null; // ← ここに WP_Query が入る（found_posts を参照する）
$articles_info_list = PostModelHelper::get_posts_payload(
    $article_query_args,
    [],   // 一覧デバッグ用なのでACFは不要
    [],   // 追加transformerなし
    $raw_articles
);
//
// 総件数（WP_Query::found_posts はリミット無視の全件数）
$total_found = (int)($raw_articles->found_posts ?? count($articles_info_list));
$total_pages = (int)ceil($total_found / $per_page);


//表示確認用ロジックです。こちらをベースにテンプレートに埋め込んでください。
//記事一覧表示用の参考です。実際のカード表示の内容に合わせて適宜調整してください。
// lutwiyo_debug('■スタッフが属する記事の一覧', [
//     'page' => $page,
//     'per_page' => $per_page,
//     'this_count' => count($articles_info_list),
//     'total_found' => $total_found,
//     'total_pages' => $total_pages,
// ]);

// lutwiyo_debug('ページング', [
//     'page' => $page,
//     'total_pages' => $total_pages,
// ]);
?>

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
                    <a class="breadcrumbs__link" href="/staff/#<?php echo esc_html(implode(' / ', array_filter($group_names))); ?>" property="item" typeof="WebPage">
                        <span property="name"><?php echo esc_html(implode(' / ', array_filter($group_names))); ?></span>
                    </a>
                    <meta property="position" content="2">
                </li>
                <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                    <span property="name"><?php echo esc_html($staff_info['title'] ?? get_the_title($staff_id)); ?></span>
                    <meta property="position" content="3">
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
        <h1 class="title fs--22"><?php echo esc_html(implode(' / ', array_filter($group_names))); ?>：<?php echo esc_html($staff_info['title'] ?? get_the_title($staff_id)); ?></h1>
        <div class="writerDetail">
            <div class="blockBox__pinIcon cornerCover__wrapper" data-boxbgcolor="body">
                <div class="icon writerDetail__pin"><div class="textColor--textGray fontW--r iconInner writerDetail__pin--p"><?php echo esc_html($role_display); ?></div></div>
                <div class="cornerCover cornerCover--lb cornerCover--outside"><div class="mask cornerCover__inner"></div></div>
                <div class="cornerCover cornerCover--rt cornerCover--outside"><div class="mask cornerCover__inner"></div></div>
            </div>
            <div class="postPerson writerDetail__inner">
                <div class="writerDetail__innerLeader">
                    <div class="iconPostPerson"><div class="iconPostPerson__thum"><img class="thumImg" src="<?php echo esc_html($img_url); ?>" alt="<?php echo esc_html($staff_info['title'] ?? get_the_title($staff_id)); ?>" width="40" height="40" loading="lazy"></div></div>
                    <div class="writerDetail__innerInfo">
                        <p class="fontW--r blockBox__infoSub--personTitle"><?php echo esc_html(implode(' / ', array_filter($group_names))); ?></p>
                        <p class="fontW--r blockBox__infoSub--text"><?php echo esc_html($staff_info['title'] ?? get_the_title($staff_id)); ?></p>
                        <ul class="navSns__list">
                            <li class="navSns__target">
                                <?php if (!empty($staff_info['url_instagram'] ?? '')): ?>
                                    <a class="snsLink" href="<?php echo esc_html($staff_info['url_instagram'] ?? ''); ?>" target="_blank" title="Instagram">
                                        <div class="snsIcon snsIcon--ins"><div class="mask mask__bgColor--btnBg snsIcon__inner">Instagram</div></div>
                                    </a>
                                <?php else: ?>
                                    <a class="snsLink" target="_blank" title="Instagram">
                                        <div class="snsIcon snsIcon--ins"><div class="mask mask__bgColor--btnBg snsIcon__inner">Instagram</div></div>
                                    </a>
                                <?php endif; ?>
                            </li>
                            <li class="navSns__target">
                                <?php if (!empty($staff_info['url_x'] ?? '')): ?>
                                    <a class="snsLink" href="<?php echo esc_html($staff_info['url_x'] ?? ''); ?>" target="_blank" title="X">
                                        <div class="snsIcon snsIcon--x"><div class="mask mask__bgColor--btnBg snsIcon__inner">X(Twitter)</div></div>
                                    </a>
                                <?php else: ?>
                                    <a class="snsLink" target="_blank" title="X">
                                        <div class="snsIcon snsIcon--x"><div class="mask mask__bgColor--btnBg snsIcon__inner">X(Twitter)</div></div>
                                    </a>
                                <?php endif; ?>
                            </li>
                            <li class="navSns__target">
                                <?php if (!empty($staff_info['url_facebook'] ?? '')): ?>
                                    <a class="snsLink" href="<?php echo esc_html($staff_info['url_facebook'] ?? ''); ?>" target="_blank" title="Facebook">
                                        <div class="snsIcon snsIcon--fb"><div class="mask mask__bgColor--btnBg snsIcon__inner">Facebook</div></div>
                                    </a>
                                <?php else: ?>
                                    <a class="snsLink" target="_blank" title="Facebook">
                                        <div class="snsIcon snsIcon--fb"><div class="mask mask__bgColor--btnBg snsIcon__inner">Facebook</div></div>
                                    </a>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="writerDetail__innerDetail">
                    <p class="fontW--r textColor--textGray blockBox__infoSub--profile"><?php echo esc_html($staff_info['text_profile'] ?? ''); ?></p>
                    <p class="fontW--r textColor--textGray blockBox__infoSub--profile blockBox__infoSub--comment"><?php echo esc_html($staff_info['text_detail'] ?? ''); ?></p>
                </div>
            </div>
        </div>

<?php
// ----------------------------------
// テンプレート呼び出し
// ----------------------------------
$variant = 'grid';
get_template_part('partials/modules/article-list', $variant, [
    'article_info_list' => $articles_info_list,
    'query' => $raw_articles ?? null,
]);
?>

<?php
// ページネーション（/staff/slug/page/2/ 形式）
echo Pagination::render_pretty(
    $page,
    $total_pages,
    get_permalink($staff_id),
    'page'
);
?>

            </section>
        </article>
    </main>


<?php get_footer(); ?>
