<?php
/* Template Name: お気に入りリスト */

// --------------------------------------
// お気に入り一覧表示前ガード
// --------------------------------------
// 未ログインはログイン導線へリダイレクトする。
$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);
$isLoginStartRequested = isset($_GET['tokk_member_login'])
    && (string) $_GET['tokk_member_login'] === '1';

// --- ログイン状態確認API呼び出し開始 ---
$loginStatusResponse = function_exists('lutwiyo_get_or_fetch_login_status_response')
    ? lutwiyo_get_or_fetch_login_status_response()
    : lutwiyo_call_bridge_api('login_status');
// --- ログイン状態確認API呼び出し終了 ---
$isLoggedIn = is_array($loginStatusResponse)
    && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';

if (!$isLoggedIn && !$isLoginStartRequested) {
    lutwiyo_render_inline_login_guide_and_exit($currentUrlForReturn);
}

// --- お気に入り記事一覧取得開始 ---
// お気に入り記事一覧は新API（/member/favorites）を唯一の取得元として扱う。
$favoriteArticleIdList = lutwiyo_get_current_member_favorite_article_ids();
// --- お気に入り記事一覧取得終了 ---

get_header();
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
                        <span property="name">保存した記事一覧</span>
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
            <h2 class="title fs--22">保存した記事一覧</h2>
            <?php // 記事一覧モジュールを呼び出し
            // cookieベースのお気に入り取得（旧実装）は後続削除を視野にコメントアウトで保持。
            // $favlist_cookie = $_COOKIE[FAVORITE_LIST_COOKIE_NAME] ?? '';
            // $fav_ids = array_filter(array_map('intval', explode(',', $favlist_cookie)));

            $fav_ids = $favoriteArticleIdList;
            // ★必須：配列を連番キーにそろえる
            $fav_ids = array_values($fav_ids);
            $fav_ids = array_reverse($fav_ids);


            // ID がなければメッセージだけ表示して終了
            if (empty($fav_ids)) {
                get_template_part(
                    'partials/modules/article-list',
                    'noresult',
                    ['message' => '保存した記事がありません。']
                );
            } else {

                // WP_Query の args
                $args = [
                    'post_type' => 'articles',
                    'post__in' => $fav_ids,
                    'orderby' => 'post__in',
                    'posts_per_page' => -1,
                ];


                $favorite_query = new WP_Query($args);

                $article_info_list = PostModelHelper::get_posts_payload(
                    $favorite_query->posts,
                    [],
                    [],
                    $favorite_query
                );

                get_template_part(
                    'partials/modules/article-list',
                    'grid',
                    ['article_info_list' => $article_info_list]
                );
            }

            ?>
        </section>
        <!-- hash すべてみる -->
        <?php
        $cache_key = 'tag_all';
        $tag_all_list = get_transient($cache_key);
        if (!is_array($tag_all_list)) {
            $tags = get_terms([
                'taxonomy' => 'post_tag',
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
