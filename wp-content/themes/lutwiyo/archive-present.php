<?php
global $wp_query;
$present_info_list = PostModelHelper::get_posts_payload(
    $wp_query->posts,
    // 抽出したいACFフィールド名の配列
    [],
    // 各投稿に対して追加実行する関数セット
    []
);

//lutwiyo_debug($present_info_list);
?>
<?php get_header(); ?>
<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
    <article class="grid kv">
        <div class="kvInfo__inner">
            <nav class="breadcrumbs" aria-label="Breadcrumbs" role="navigation">
                <ol class="breadcrumbs__list" vocab="http://schema.org/" typeof="BreadcrumbList">
                    <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                        <a class="breadcrumbs__link" href="<?= home_url('/'); ?>" property="item" typeof="WebPage">
                            <span property="name">トップ</span>
                        </a>
                        <meta property="position" content="1">
                    </li>
                    <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                        <span property="name">プレゼントを探す</span>
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
            <h1 class="title fs--22">プレゼントを探す</h1>
            <section class="presentSection">
                <div class="flexColumn flexColumn--3">
                    <?php foreach ($present_info_list as $present_info): ?>
                        <?php
                        $title = $present_info['title'] ?? '';
                        $is_new = $present_info['is_new'] ?? false;
                        $is_new_class = $is_new ? 'is--new' : '';
                        $description = $present_info['text'] ?? '';
                        $image_url = $present_info['image_url'] ?? '';
                        $link = $present_info['url'] ?? '';
                        $link_type = $present_info['link_type'];
                        switch ($link_type) {
                            case 'present_detail':
                                // 記事詳細にリンク
                                $link_label = '記事を読んでプレゼントに応募する';
                                break;
                            case 'present_list':
                                // プレゼント一覧にリンク
                                $link_label = 'プレゼントに応募する';
                                break;
                            case 'expired':
                                // 応募期間終了
                                $link_label = '終了しました';
                                break;
                            default:
                                // デフォルトは内部リンクとして扱う
                                $link = home_url($link);
                                break;
                        }
                        if ($link_type === 'present_list') {
                            $presentActionGate = lutwiyo_get_member_benefit_action_gate($link, 'present');
                            $link = (string) ($presentActionGate['href'] ?? $link);
                            $presentLinkAttrs = lutwiyo_get_member_benefit_action_attrs($presentActionGate);
                        } else {
                            $presentLinkAttrs = '';
                        }
                        $end_date_label = $present_info['end_date_label'] ?? '';
                        ?>
                        <div class="blockBox presentBlock <?= esc_attr($is_new_class); ?>"
                             data-boxBgColor="white">
                            <a class="blockBox__link<?php echo $presentLinkAttrs === '' ? '' : ' js--memberBenefitActionGate'; ?>"
                               href="<?= esc_url($link); ?>"
                               <?php echo $presentLinkAttrs; ?>
                               title="<?= esc_attr($title); ?>"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper">
                                    <img class="thumImg"
                                         src="<?= esc_attr($image_url); ?>"
                                         alt="<?= esc_attr($title); ?>"
                                         loading="lazy" width="1900" height="1270">
                                    <?php /*
                                    <div class="flex--cc presentBlock__info cornerCover__wrapper"
                                         data-boxbgcolor="white">
                                        <div class="cornerCover cornerCover--lb">
                                            <div class="mask cornerCover__inner"></div>
                                        </div>
                                        <div class="cornerCover cornerCover--rt">
                                            <div class="mask cornerCover__inner"></div>
                                        </div>
                                        <p class="fontW--r presentBlock__info--p">2 名様</p>
                                    </div> */ ?>
                                </div>
                                <!-- favBtn -->
                                <?php // プレゼントにはいいねはつけない。 ?>
                                <?php /*
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div> */ ?>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle"><?= esc_html($description); ?></p>
                                <dl class="blockBoxInfo__detail">
                                    <dt class="fontW--r textColor--textGray blockBoxInfo__detail--title">応募締切</dt>
                                    <dd class="blockBoxInfo__detail--text">
                                        <p><?= esc_html($end_date_label); ?></p>
                                    </dd>
                                </dl>
                                <div class="btn btnShaped btnBgColor btnAll" data-shaped="auto-38">
                                    <div class="flex--cc btnLink">
                                        <p class="btnTtext"><?= esc_html($link_label); ?></p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- paging -->
            <?php // ページング
            $paged = get_query_var('paged') ?: 1;// 現在ページ
            echo Pagination::render($paged, $wp_query->max_num_pages); // ページネーション出力
            ?>

        </section>
    </article>
</main>

<?php get_footer(); ?>
