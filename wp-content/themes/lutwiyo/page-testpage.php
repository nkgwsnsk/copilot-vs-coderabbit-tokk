<?php
// =========================================
// page-testpage.php 専用ベーシック認証
// ドメインが tokk-kansai.jp のときのみ有効
// =========================================

// 現在のホスト名を取得
$current_domain = $_SERVER['HTTP_HOST'] ?? '';

// 対象ドメイン
$target_domain = 'tokk-kansai.jp';

// 認証情報
$valid_username = 'test';
$valid_password = '2025';

// ドメイン一致時のみ Basic 認証を要求
if (strcasecmp($current_domain, $target_domain) === 0) {

    // 認証チェック
    if (
        !isset($_SERVER['PHP_AUTH_USER']) ||
        $_SERVER['PHP_AUTH_USER'] !== $valid_username ||
        $_SERVER['PHP_AUTH_PW'] !== $valid_password
    ) {
        header('WWW-Authenticate: Basic realm="Protected Area"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'このページを表示するには認証が必要です。';
        exit;
    }
}
?>



<?php
/* Template Name: テストページ */
get_header();
?>
    <!-- ==================================================================== ↓ wrapper ↓ -->
    <main class="main wrapper" role="main">
        <!-- ------------------------------------- ↓ tag 休日スポット ↓　-->
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <section class="gridWide latestSection">
                <h1 class="title fs--22">テストページ</h1>
                <?php

//                $post_type = 'articles';
//                $pt = get_post_type_object($post_type);
//                lutwiyo_debug($pt);

                $post_type = 'articles';
                $pt = get_post_type_object($post_type);
//                lutwiyo_debug($pt);



                ?>
            </section>
        </article>
    </main>
<?php get_footer(); ?>
