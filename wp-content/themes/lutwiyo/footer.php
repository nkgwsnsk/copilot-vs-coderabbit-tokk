<?php wp_footer(); ?>
<?php //lutwiyo_debug_template(); ?>
<?php
// フッター単体で読み込まれるため、会員ナビ描画に必要な状態をここで初期化する。
$is_rank = 'not_login';
$header_member_menu_groups = [
    [
        'key' => 'mypage',
        'label' => 'マイページ',
        'url' => home_url('/mypage/'),
        'children' => [],
    ],
    [
        'key' => 'member_features',
        'label' => '会員機能',
        'url' => home_url('/mypage/'),
        'children' => [
            ['label' => '会員情報編集', 'url' => home_url('/mypage-edit/')],
            ['label' => 'お支払い履歴', 'url' => home_url('/mypage/')],
        ],
    ],
    [
        'key' => 'blog',
        'label' => 'ブログ',
        'url' => home_url('/memberblog-list/'),
        'children' => [
            ['label' => 'ブログを書く', 'url' => home_url('/member/blog/')],
            ['label' => '投稿したブログの一覧', 'url' => home_url('/memberblog-list/')],
        ],
    ],
    [
        'key' => 'member_benefits',
        'label' => '会員特典',
        'url' => lutwiyo_get_member_benefit_entry_url(home_url('/coupon/')),
        'children' => [
            ['label' => 'クーポン', 'url' => lutwiyo_get_member_benefit_entry_url(home_url('/coupon/'))],
            ['label' => 'プレゼント', 'url' => lutwiyo_get_member_benefit_entry_url(home_url('/present/'))],
            ['label' => 'ギャラリー', 'url' => lutwiyo_get_member_benefit_entry_url(home_url('/gallery/'))],
        ],
    ],
];
$header_logout_form_action = home_url('/mypage/');
$header_logout_form_nonce = wp_create_nonce('tokk_mypage_action');

$memberInfoResponse = function_exists('lutwiyo_get_member_info_response_with_login_status_retry')
    ? lutwiyo_get_member_info_response_with_login_status_retry()
    : lutwiyo_get_or_fetch_member_info_response();

if (is_array($memberInfoResponse) && ($memberInfoResponse['body']['result'] ?? '') === 'ok') {
    $is_rank = 'free';
    $localMember = $memberInfoResponse['body']['local_member'] ?? [];
    if (is_array($localMember) && (int) ($localMember['member_rank_id'] ?? 0) === 2) {
        $is_rank = 'standard';
    }
} else {
    $loginStatusResponse = lutwiyo_get_or_fetch_login_status_response();
    $isLoggedInViaStatus = is_array($loginStatusResponse)
        && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';
    if ($isLoggedInViaStatus) {
        $is_rank = 'free';
    }
}

?>

<?php
// -------------------------------------
//タグ一覧情報を取得
// -------------------------------------
// 1) タグ取得（空は除外 = hide_empty=true）
$post_tags = TermModelHelper::get_terms_payload(
    'post_tag',
    [
        'hide_empty' => true,     // ← 記事数0は表示しない
    ],
    [] // ACF不要なら空
);
// lutwiyo_debug('タグ一覧$post_tags', $post_tags);
$post_tags_list = [];
if (!empty($post_tags) && is_array($post_tags)) {
    foreach ($post_tags as $t) {
        $name = isset($t['name']) ? (string)$t['name'] : '';
        $slug = isset($t['slug']) ? (string)$t['slug'] : '';
        if ($name === '' || $slug === '') continue; // どちらか欠けてたらスキップ

        $post_tags_list[] = [
            'name' => $name,
            'slug' => $slug,
        ];
    }
}
// lutwiyo_debug('タグ一覧post_tags_list', $post_tags_list);

?>

<?php
// -------------------------------------
//エリア一覧情報（ページ最下部）を取得
// -------------------------------------
// 1) 並び順はプラグインの設定順（term_order ASC）で取得
$areas = TermModelHelper::get_terms_payload(
    'area',
    [
        'hide_empty' => false,
        'orderby' => 'term_order', // ★ Taxonomy Order/COTTO の並び順
        'order' => 'ASC',
    ],
    ['is_clickable'] // ACF 真偽フィールド
);

// 2) truthy 判定（True/False or チェックボックス両対応）
$truthy = function ($v): bool {
    if (is_array($v)) return !empty($v);
    return $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on' || $v === 'yes';
};

// 3) クリック可能のみ抽出（順序は保持するため usort しない）
$clickable_areas = array_values(array_filter($areas, fn($t) => $truthy($t['is_clickable'] ?? false)));
?>

<?php
//カテゴリー一覧情報（ページ最下部）はheaderで処理したデータを再利用
global $global_category_info_list;
$categories = $global_category_info_list;
?>

<!-- ==================================================================== ↓ footer ↓ -->
<!-- 全サイト共通 footer  -->
<!-- ------------------------------------- ↓ footer ↓　-->
<style>
    @media only screen and (max-width: 767px) {
        .footerNav__main button.memberNavSp__logoutButton {
            color: #fff;
            font-size: 13px;
        }
    }
</style>
<footer class="articlePT articlePB footer " data-boxBgColor="footer" role="contentinfo">
    <!-- ------------------------------------- ↓ カテゴリから探す ↓　-->
    <article class="footerArticle footerCate">
        <p class="gridWide title footerTitle">カテゴリから探す</p>
        <div class="btn btnShaped btnBgColor btnAll" data-shaped="120-28">
            <a class="flex--cc btnLink" href="<?= home_url('category/'); ?>" aria-label="すべてみる"
               title="すべてみる">
                <p class="btnTtext">すべてみる</p>
                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
            </a>
        </div>
        <section class="gridWide keenSlider__wrapper">
            <div class="sliderWrap" data-keen="true"
                 data-loop="false"
                 data-mode="snap"
                 data-rtl="false"
                 data-origin="auto"
                 data-per-view-pc="10"
                 data-spacing-pc="25"
                 data-per-view-tablet="7"
                 data-spacing-tablet="20"
                 data-per-view-sp="3.6"
                 data-spacing-sp="20">
                <div class="keen-slider">
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(5, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="グルメ">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>グルメ</title>
                                    <path class="st0"
                                          d="M16.7,26.6c1.5,1.8,3.5.7,3.5.7l12.8,13.7c.9.9,2.3.9,3.1,0h0c.9-.9.9-2.3,0-3.1L9.2,11.8s-3,2.1.2,6.5l7.4,8.4ZM37.2,13.3l-6.8,6.8M39.7,15.8l-6.8,6.9M24.4,26l3.3-3.3s-2.8-2.1-.9-4l7.9-7.9M23.9,31.7l-9.4,9.7c-.8.8-2.2.8-3,0h0c-.8-.8-.8-2.2,0-3l10-9.3M42.1,18.2l-7.9,7.9c-1.6,1.6-4-.9-4-.9l-3.2,3.3"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">グルメ</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(8, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="おでかけ">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>おでかけ</title>
                                    <path class="st0"
                                          d="M34.5,13.2c0,3.9-7.1,14.5-7.1,13.4,0,0-7.1-9.4-7.1-13.4s3.2-7.1,7.1-7.1,7.1,3.2,7.1,7.1ZM27.4,10.8c1.3,0,2.4,1.1,2.4,2.4s-1.1,2.4-2.4,2.4-2.4-1.1-2.4-2.4,1.1-2.4,2.4-2.4ZM33.6,16.7l6,2.4-.4,8,.4,9-8.8-2.5-7,4.6-11.8-2,.3-9.3-1.8-9.4,8.3,2.3,3.1-1.4"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">おでかけ</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(4, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="イベント">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>イベント</title>
                                    <path class="st0"
                                          d="M44.3,18.2c1.1,1.1,0,2.4-1.4,3.8l-21.3,21.3c-1.4,1.4-2.4,2.8-3.8,1.4l-11.9-11.9c-1.1-1.1,0-2.4,1.4-3.8L28.5,7.6c1.4-1.4,2.4-2.8,3.8-1.4l11.9,11.9ZM30.4,11.1h0M33.4,14.1h0M36.4,17h0M39.3,20h0M22.9,23.4h4.4v4.4M27.2,23.4l-6.1,6.1"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">イベント</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(3, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="ニュース">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>ニュース</title>
                                    <path class="st0"
                                          d="M18.7,22.6h12.4M18.7,28.4h12.4M18.7,34.3h12.4M36.8,16.6h-8.3V7.9l8.3,8.7ZM36.8,16.6v24.1H13.2V7.7h15.3"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">ニュース</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(7, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="エンタメ">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>エンタメ</title>
                                    <path class="st0"
                                          d="M11,12.6h35.5v27.9H11V12.6ZM23.7,12.6l16.4-5,1.8,5M11,40.6L3.5,19.7l7.5-2.5M31.4,18.4h9.8v10.8h-9.8v-10.8ZM26,18.4h-9.2M26,23.8h-9.2M26,29.1h-9.2M41.2,34.5h-24.4"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">エンタメ</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(6, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="ショッピング">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>ショッピング</title>
                                    <path class="st0"
                                          d="M12.3,34.6c1.6,0,3,1.3,3,3s-1.3,3-3,3-3-1.3-3-3,1.3-3,3-3ZM28.7,34.6c1.6,0,3,1.3,3,3s-1.3,3-3,3-3-1.3-3-3,1.3-3,3-3ZM44,9.7h-5.5l-6.6,20.7H10.1l-4.1-12.7h29.9M33.7,24.6H8.8M21.2,17.7v12.7M28.7,17.7l-2.4,12.7M13.5,17.7l2.7,12.7"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">ショッピング</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(194, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="暮らし">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>暮らし</title>
                                    <path class="st0"
                                          d="M15.9,7.3v5.8M34.1,7.3v5.8M10.6,18.6h28.9M25.7,41.8h-12c-1.7,0-3.1-1.4-3.1-3.1V13.5c0-1.7,1.4-3.1,3.1-3.1h22.6c1.7,0,3.1,1.4,3.1,3.1v20.2M22.1,26.1l2.8-2.8v10M39.5,34v-.5l-7.9-2.3c2.7,8.6-5.8,10.7-5.8,10.7,0,0,12.8-1.3,13.7-7.9h0Z"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">暮らし</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(269, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="百貨店">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>百貨店</title>
                                    <path class="st0"
                                          d="M16.6,10.1h16.8v32.7h-16.8V10.1ZM16.6,42.8H4.1M16.6,36.9l-12.5,1.3M16.6,34l-12.5,2M16.6,28.3l-12.5,3.2M16.6,22.5l-12.5,4.5M16.6,16.8l-12.5,5.8M16.6,10.1l-12.5,7.3M33.4,42.8h12.5M33.4,36.9l12.5,1.3M33.4,34l12.5,2M33.4,28.3l12.5,3.2M33.4,22.5l12.5,4.5M33.4,16.8l12.5,5.8M33.4,10.1l12.5,7.3M20.5,10v-5.2h9.1v5.2M13.7,11.7v-2.3l6.8-4.6v5.2M36.3,11.8v-2.5l-6.8-4.6v5.2M33.4,42.8h12.5v-25.4l-12.5-7.3v32.7ZM16.6,10.1h16.8v32.7h-16.8V10.1ZM16.6,34h16.8M16.6,36.9h16.8M16.6,42.8H4.1v-25.4l12.5-7.3v32.7ZM16.6,42.8H4.1M16.6,36.9l-12.5,1.3M16.6,34l-12.5,2M16.6,28.3l-12.5,3.2M16.6,22.5l-12.5,4.5M16.6,16.8l-12.5,5.8M16.6,10.1l-12.5,7.3M33.4,42.8h12.5M33.4,36.9l12.5,1.3M33.4,34l12.5,2M33.4,28.3l12.5,3.2M33.4,22.5l12.5,4.5M33.4,16.8l12.5,5.8M33.4,10.1l12.5,7.3M20.7,16.9h8.5M20.7,20.9h8.5M20.7,24.9h8.5M20.7,28.8h8.5"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">百貨店</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(10, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="宝塚歌劇">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>宝塚歌劇</title>
                                    <path class="st0"
                                          d="M42,15.9c-1.7,0-1.7-2.4-3.4-2.4s-1.7,2.4-3.4,2.4-1.7-2.4-3.4-2.4-1.7,2.4-3.4,2.4-1.7-2.4-3.4-2.4-1.7,2.4-3.4,2.4-1.7-2.4-3.4-2.4-1.7,2.4-3.4,2.4-1.7-2.4-3.4-2.4-1.7,2.4-3.4,2.4M8,13.2v-4.4h34v4.4M8,13.2l-2,28s2.8-1.6,4.9,0M42,13.2l2,28s-2.8-1.6-4.9,0M12.4,20l-1.5,21.3s2.8-1.6,4.9,0M17.4,20l-1.5,21.3s2.8-1.6,4.9,0M22.3,16.2l-1.5,25.1M27.7,16.2l1.5,25.1M32.6,20l1.5,21.3s-2.8-1.6-4.9,0M37.6,20l1.5,21.3s-2.8-1.6-4.9,0"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">宝塚歌劇</p>
                        </a>
                    </div>
                    <div class="keen-slider__slide">
                        <a class="cateBanner" href="<?php $link = get_term_link(12, 'category');
                        if (!is_wp_error($link)) {
                            echo esc_url($link);
                        } ?>" title="占い">
                            <div class="cateBanner__icon">
                                <svg class="cateBanner__iconSvg" role="img" viewBox="0 0 50 50">
                                    <title>占い</title>
                                    <path class="st0"
                                          d="M28.6,10.9c0,2.5-2.2,4.8-4.8,4.8M28.6,20.5c0-2.5-2.2-4.8-4.8-4.8M28.6,10.9c0,2.5,2.2,4.8,4.8,4.8M28.6,20.5c0-2.5,2.2-4.8,4.8-4.8M35.5,20.7c-1.1,1.1-3.2,1.2-4.4,0M31,25.1c1.1-1.1,1.2-3.2,0-4.4M35.5,20.7c-1.1,1.1-1.2,3.2,0,4.4M31,25.1c1.1-1.1,3.2-1.2,4.4,0M34.6,31.3c-2.5,2.4-5.9,3.8-9.6,3.8s-7.1-1.5-9.6-3.8c0,0-3.6,2.7-3.6,5.6s7.2,3.5,13.1,3.5,13.1-1.7,13.1-3.5c0-2.9-3.6-5.6-3.6-5.6ZM38.8,21.3c0,3.9-1.6,7.5-4.3,10-2.5,2.4-5.9,3.8-9.6,3.8s-7-1.4-9.5-3.8c-2.7-2.5-4.4-6.1-4.4-10.1,0-7.6,6.2-13.8,13.8-13.8s13.8,6.2,13.8,13.8Z"/>
                                </svg>
                            </div>
                            <p class="cateBanner__title">占い</p>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </article>
    <!-- ------------------------------------- ↓ タグから探す ↓　-->
    <article class="footerArticle footerTags footerSlideTags">
        <p class="gridWide title footerTitle">タグから探す</p>
        <div class="btn btnShaped btnBgColor btnAll" data-shaped="120-28">
            <a class="flex--cc btnLink" href="/tag/"
               aria-label="タグの一覧を見る"
               title="タグの一覧を見る">
                <p class="btnTtext">すべてみる</p>
                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
            </a>
        </div>
        <section class="gridWide footerTags__section">
            <?php // TODO タグ一覧ページへの遷移を実装する。一覧は/tag で、先頭はoptionページのrecommended_post_tag_list にして横断トップ同様に記事も先頭5件を表示する。それ以外はタグ一覧を出力する ?>
            <?php // タグから探す
            // 1) 正規化 & 空要素除外
            $items = is_array($post_tags_list) ? array_values($post_tags_list) : [];
            $items = array_values(array_filter($items, fn($t) => !empty($t['name']) && !empty($t['slug'])));

            // 2) ランダム化
            if (!empty($items)) {
                shuffle($items);
            }

            // 3) 最大20件に制限し、10件ごとに分割（→最大2ラッパ）
            $items = array_slice($items, 0, 20);
            $chunks = array_chunk($items, 10);

            foreach ($chunks as $chunk): ?>
                <div class="footerTags__sliderWrapper">
                    <ul class="tagsList">
                        <?php foreach ($chunk as $tag):
                            $name = trim((string)$tag['name']);
                            $slug = trim((string)$tag['slug']);
                            $label = '#' . $name;

                            // ▼ 検索URLではなく、タグ別一覧ページへ
                            //    /tag/{slug}/ に遷移させる
                            $href = home_url('/tag/' . $slug . '/');
                            ?>
                            <li class="tagsTarget">
                                <a class="flex--cc tagsLink"
                                   href="<?php echo esc_url($href); ?>"
                                   title="<?php echo esc_attr($label); ?>">
                                    <p class="tagsTarget--p"><?php echo esc_html($label); ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

        </section>
    </article>
    <!-- ------------------------------------- ↓ Instagramから探す ↓　-->
    <article class="footerArticle footerTags">
        <p class="gridWide title footerTitle">読者とのInstagram投稿一覧</p>
        <div class="btn btnShaped btnBgColor btnAll" data-shaped="120-28">
            <a class="flex--cc btnLink" href="<?= home_url('instagram/'); ?>" aria-label="すべてみる"
               title="すべてみる">
                <p class="btnTtext">すべてみる</p>
                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
            </a>
        </div>
        <section class="gridWide footerTags__section">
            <div class="footerTags__sliderWrapper">
                <ul class="tagsList">
                    <?php $instagram_feed_list = get_field('instagram_feed_list', 'option'); ?>
                    <?php foreach ($instagram_feed_list as $instagram_feed) :
                        $title = $instagram_feed['title'] ?? '';
                        $link = get_permalink($instagram_feed['page'] ?? 0);
                        ?>
                        <li class="tagsTarget">
                            <a class="flex--cc tagsLink"
                               href="<?= esc_url($link); ?>"
                               title="#<?= esc_html($title); ?>"><p class="tagsTarget--p">#<?= esc_html($title); ?></p>
                            </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </article>
    <!-- ------------------------------------- ↓ footerNav ↓　-->
    <article class="gridWide footerNav">
        <section class="footerNav__leader">
            <div class="globalLogo footer__globalLogo">
                <a class="flex--cc maskBgColor__hoverWrapper globalLogo__inner"
                   href="<?= esc_url('/'); ?>"
                   title="TOKK（トック）大阪京都神戸阪急沿線おでかけ情報メディア"
                   role="link">
                    <p class="globalLogo__mark"><span
                                class="mask globalLogo__markInner">TOKK（トック）大阪京都神戸阪急沿線おでかけ情報メディア</span>
                    </p>
                </a>
            </div>
            <div class="btn btnShaped btnBgColor footerSnsBtn footerSnsBtn--x" data-shaped="38-38">
                <a class="flex--cc btnLink" href="https://x.com/tokk_kansai" target="_blank" title="follow">
                    <div class="snsIcon snsIcon--x"><span class="mask snsIcon__inner"></span></div>
                </a>
            </div>
            <div class="btn btnShaped btnBgColor footerSnsBtn footerSnsBtn--ins" data-shaped="38-38">
                <a class="flex--cc btnLink" href="https://www.instagram.com/tokk_kansai/" target="_blank" title="follow">
                    <div class="snsIcon snsIcon--ins"><span class="mask snsIcon__inner"></span></div>
                </a>
            </div>
            <div class="btn btnShaped btnBgColor footerSnsBtn footerSnsBtn--fb" data-shaped="38-38">
                <a class="flex--cc btnLink" href="https://www.facebook.com/tokk.localmedia.kansai" target="_blank" title="follow">
                    <div class="snsIcon snsIcon--fb"><span class="mask snsIcon__inner"></span></div>
                </a>
            </div>
            <div class="btn btnShaped btnBgColor footerSnsBtn footerSnsBtn--fav" data-shaped="145-38">
                <a class="flex--cc btnLink" role="link" href="<?= home_url('favorite/'); ?>" title="お気に入り">
                    <p class="btnTtext">お気に入り</p>
                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                        <title>お気に入り</title>
                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                    </svg>
                    <?php
                    // お気に入り数の取得
                    $favorite_count = get_favorite_count();
                    ?>
                    <?php if ($favorite_count > 0): ?>
                        <p class="fontEn favBtn__num"><?= esc_html($favorite_count); ?></p>
                    <?php endif; ?>
                </a>
            </div>
        </section>
        <section class="footerNav__inner">
            <div class="footerNav__main" role="navigation">

                <?php if ($is_rank === "free" || $is_rank === "standard"): ?>
                    <dl class="navToggle__wrapper navToggle__wrapper--member">
                        <dt class="textHoverWrapper globalNav__target globalNav__target--member navToggle__btn">
                            <a class="globalNav__target--inner" href="<?= home_url('/mypage/'); ?>" title="マイページ">
                                <p class="globalNav__target--title">マイページ</p>
                            </a>
                            <div class="partsSp navToggle__icon">
                                <div class="navToggle__iconInner"></div>
                            </div>
                        </dt>
                        <dd class="navToggle__inner">
                            <ul class="fontW--r navToggle__innerList navToggle__innerList--member" role="list">
                                <?php foreach ($header_member_menu_groups as $header_member_menu_group): ?>
                                    <?php
                                    $groupLabel = trim((string) ($header_member_menu_group['label'] ?? ''));
                                    $groupUrl = trim((string) ($header_member_menu_group['url'] ?? ''));
                                    $groupChildren = is_array($header_member_menu_group['children'] ?? null) ? $header_member_menu_group['children'] : [];
                                    if ($groupLabel === '' || $groupUrl === '') {
                                        continue;
                                    }
                                    ?>
                                    <li class="navToggle__target navToggle__target--memberGroup" role="listitem">
                                        <a class="navToggle__link navToggle__link--memberGroup" href="<?= esc_url($groupUrl); ?>" title="<?= esc_attr($groupLabel); ?>"><?= esc_html($groupLabel); ?></a>
                                        <?php if (!empty($groupChildren)): ?>
                                            <ul class="memberNavSp__children" role="list">
                                                <?php foreach ($groupChildren as $header_member_menu_child): ?>
                                                    <?php
                                                    $childLabel = trim((string) ($header_member_menu_child['label'] ?? ''));
                                                    $childUrl = trim((string) ($header_member_menu_child['url'] ?? ''));
                                                    if ($childLabel === '' || $childUrl === '') {
                                                        continue;
                                                    }
                                                    ?>
                                                    <li class="memberNavSp__childItem" role="listitem">
                                                        <a class="navToggle__link navToggle__link--memberChild" href="<?= esc_url($childUrl); ?>" title="<?= esc_attr($childLabel); ?>"><?= esc_html($childLabel); ?></a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                                <li class="memberNavSp__childItem memberNavSp__childItem--logout" role="listitem">
                                    <form class="memberNavSp__logoutForm" action="<?= esc_url($header_logout_form_action); ?>" method="post">
                                        <input type="hidden" name="mypage_action" value="logout">
                                        <input type="hidden" name="mypage_nonce" value="<?= esc_attr($header_logout_form_nonce); ?>">
                                        <button class="navToggle__link navToggle__link--memberGroup memberNavSp__logoutButton" type="submit">ログアウト</button>
                                    </form>
                                </li>
                            </ul>
                        </dd>
                    </dl>
                <?php endif; ?>
                <dl class="navToggle__wrapper navToggle__wrapper--area">
                    <dt class="textHoverWrapper globalNav__target globalNav__target--area navToggle__btn">
                        <a class="globalNav__target--inner" href="<?= home_url('/area/'); ?>" title="エリアで探す">
                            <p class="globalNav__target--title">エリアで探す</p>
                        </a>
                        <div class="partsSp navToggle__icon">
                            <div class="navToggle__iconInner"></div>
                        </div>
                    </dt>
                    <dd class="navToggle__inner">
                        <ul class="fontW--r navToggle__innerList" role="list">
                            <?php //エリアで探す ?>
                            <?php if (!empty($clickable_areas)): ?>
                                <?php foreach ($clickable_areas as $t): ?>
                                    <?php
                                    $term_id = (int)($t['id'] ?? 0);
                                    $name = (string)($t['name'] ?? '');
                                    $slug = (string)($t['slug'] ?? '');
                                    if (!$term_id || $name === '') {
                                        continue;
                                    }

                                    // 公式のタームリンクを使用
                                    $url = get_term_link($term_id, 'area');
                                    if (is_wp_error($url)) {
                                        continue;
                                    }
                                    ?>
                                    <li class="navToggle__target" role="listitem">
                                        <a class="navToggle__link"
                                           href="<?= esc_url($url); ?>"
                                           title="<?= esc_attr($name); ?>">
                                            <?= esc_html($name); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </dd>
                </dl>
                <dl class="navToggle__wrapper navToggle__wrapper--cate">
                    <dt class="textHoverWrapper globalNav__target globalNav__target--area navToggle__btn">
                        <a class="globalNav__target--inner" href="<?= home_url('/category/'); ?>"
                           title="カテゴリーから探す">
                            <p class="globalNav__target--title">カテゴリーから探す</p>
                        </a>
                        <div class="partsSp navToggle__icon">
                            <div class="navToggle__iconInner"></div>
                        </div>
                    </dt>
                    <dd class="navToggle__inner">
                        <ul class="fontW--r navToggle__innerList" role="list">
                            <?php
                            if (!empty($categories)) :
                                foreach ($categories as $cat) :
                                    $name = (string)($cat['name'] ?? '');
                                    $slug = (string)($cat['slug'] ?? '');
                                    if ($name === '' || $slug === '') {
                                        continue;
                                    }

                                    $url = home_url('category/') . $slug;
                                    ?>
                                    <li class="navToggle__target" role="listitem">
                                        <a class="navToggle__link"
                                           href="<?= esc_url($url); ?>"
                                           title="<?= esc_attr($name); ?>">
                                            <?= esc_html($name); ?>
                                        </a>
                                    </li>
                                <?php
                                endforeach;
                            endif;
                            ?>
                        </ul>
                    </dd>
                </dl>
                <ul class="footerNav__list">
                    <li class="textHoverWrapper globalNav__target globalNav__target--coupon">
                        <a class="globalNav__target--inner" href="<?= home_url('coupon/'); ?>" title="クーポンを探す">
                            <p class="globalNav__target--title">クーポンを探す</p>
                        </a>
                    </li>
                    <li class="textHoverWrapper globalNav__target globalNav__target--present">
                        <a class="globalNav__target--inner" href="<?= home_url('present/'); ?>"
                           title="プレゼントを探す">
                            <p class="globalNav__target--title">プレゼントを探す</p>
                        </a>
                    </li>
                    <li class="textHoverWrapper globalNav__target globalNav__target--gallery">
                        <a class="globalNav__target--inner" href="<?= home_url('gallery/'); ?>" title="ギャラリー">
                            <p class="globalNav__target--title">ギャラリー</p>
                        </a>
                    </li>
                </ul>


            </div>
            <div class="footerCM">
                <a class="footerCM__link" href="/ad" target="_blank">
                    <img src="/assets/img/common/footer--ad.jpg" alt="TOKK 広告募集">
                </a>
            </div>
        </section>
    </article>
    <!-- ------------------------------------- ↓ footerBottom ↓　-->
    <article class="gridWide footerBottom">
        <ul class="footerBottom__list">
            <li class="footerBottom__target">
                <a class="footerBottom__target--link" href="/about/" title="TOKKとは"><p
                            class="fontW--r footerBottom__target--p">TOKKとは</p></a>
            </li>
            <li class="footerBottom__target">
                <a class="footerBottom__target--link" href="/about-company/" title="運営会社について"><p
                            class="fontW--r footerBottom__target--p">運営会社について</p></a>
            </li>
            <li class="footerBottom__target">
                <a class="footerBottom__target--link" href="/ad/" title="広告について"><p
                            class="fontW--r footerBottom__target--p">広告について</p></a>
            </li>
            <li class="arrowIcon__hoverWrapper footerBottom__target">
                <a class="footerBottom__target--link" href="/privacy/" title="プライバシーポリシー"><p
                            class="fontW--r footerBottom__target--p">プライバシーポリシー</p></a>
            </li>
            <li class="footerBottom__target">
                <a class="footerBottom__target--link" href="/cookie-policy/" title="クッキーポリシー"><p
                            class="fontW--r footerBottom__target--p">クッキーポリシー</p></a>
            </li>
            <li class="footerBottom__target">
                <a class="footerBottom__target--link" href="/tokushoho/" title="特定商取引法に基づく表示"><p
                            class="fontW--r footerBottom__target--p">特定商取引法に基づく表示</p></a>
            </li>
            <li class="footerBottom__target">
                <a class="footerBottom__target--link" href="/howto/" title="ご利用にあたって"><p
                            class="fontW--r footerBottom__target--p">ご利用にあたって</p></a>
            </li>
            <li class="footerBottom__target">
                <a class="footerBottom__target--link" href="/contact/" title="情報提供・お問い合わせ"><p
                            class="fontW--r footerBottom__target--p">情報提供・お問い合わせ</p></a>
            </li>
            <li class="arrowIcon__hoverWrapper footerBottom__target">
                <a class="footerBottom__target--link" href="https://www.hankyu.co.jp/" title="阪急電鉄ホームページ"
                   target="_blank"><p class="fontW--r footerBottom__target--p">阪急電鉄ホームページ
                    <div class="arrowBlank"></div>
                    </p></a>
            </li>
            <li class="arrowIcon__hoverWrapper footerBottom__target">
                <a class="footerBottom__target--link" href="https://www.hanshin.co.jp/" title="阪神電気鉄道ホームページ"
                   target="_blank"><p class="fontW--r footerBottom__target--p">阪神電気鉄道ホームページ
                    <div class="arrowBlank"></div>
                    </p></a>
            </li>
        </ul>
        <p class="fontEn footerCopy">© Hankyu Hanshin Marketing Solutions Inc.</p>
        <div class="btn btnShaped btnBgColor pageTop" role="button">
            <div class="flex--cc btnLink">
                <p class="fontEn btnTtext">PAGE TOP</p>
                <div class="btnArrow btnArrow--top" data-arrow="w-12"></div>
            </div>
        </div>
    </article>
</footer>
</body>
</html>
