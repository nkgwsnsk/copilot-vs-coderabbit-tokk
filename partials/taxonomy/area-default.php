<?php get_header(); ?>
<?php
// -------------------------------------
// エリア情報取得(ACFフィールド含む)
// -------------------------------------
global $global_queried_object;
$term = $global_queried_object;

$area_info_list = TermModelHelper::get_terms_payload(
    'area',
    // ベースになるタームの配列(またはWP_Termに渡すクエリ)
    [$term->term_taxonomy_id],
    [],
    // 各投稿に対して追加実行する関数セット
    [],
);

// 現在表示しているエリア情報
$current_area_info = $area_info_list[0] ?? [];

if (empty($current_area_info)) {
    // エリア情報が取得できなかった場合の処理（例：404ページへリダイレクト）
    wp_redirect(home_url('/404/'));
    exit;
}

// -------------------------------------
// 非公開のエリアの場合は、エリア別の記事一覧にリダイレクト
// -------------------------------------
if (!$current_area_info['is_clickable']) {
    wp_redirect(home_url('/?s=&area%5B%5D=' . $current_area_info['slug'] . '/'));
    exit;
}
?>
<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
    <?php
    get_template_part(
        'partials/modules/kv',
        null,
        ['area_info' => $current_area_info ?? []]
    );

    ?>
    <!-- ------------------------------------- ↓ areaNav ↓　-->
    <?php
    get_template_part(
        'partials/modules/area',
        'nav'
    );
    ?>

    <!-- ------------------------------------- ↓ mastheadArticle ↓　-->
    <?php
    // -------------------------------------
    // おすすめ記事カルーセル
    // -------------------------------------
    ?>
    <?php
    // フォールバック
    // おすすめ情報がエリア詳細情報に設定されていない場合は、新着記事から代替取得する
    $recommended_articles_list = $current_area_info['recommended_articles_list'] ?? null;
    if (!is_array($recommended_articles_list)) {
        $recommended_articles_list = get_field('recommended_articles_list', 'option');
    }
    if (!is_array($recommended_articles_list)) {
        $recommended_articles_list = [];
    }

    $recommended_articles_list = PostModelHelper::get_posts_payload(
    // ベースになる投稿オブジェクトの配列(またはWP_Postsに渡すクエリ)
        $recommended_articles_list,
        // 抽出したいACFフィールド名の配列
        [],
        // 各投稿に対して追加実行する関数セット
        []
    );
    ?>
    <?php get_template_part(
        'partials/modules/masthead',
        null,
        [
            'recommended_articles_list' => $recommended_articles_list
        ]
    ); ?>
    <!-- ------------------------------------- ↓ articleBanner 今月のプレゼント / TOKKクーポン ↓　-->
    <article class="gridWide articlePT articlePB articleBanner flexColumn flexColumn--2" data-boxBgColor="body">
        <?php
        //TODO: クーポン情報を埋め込む必要がある
        //TODO: プレゼント・クーポンはエリア別の処理があるのか確認する(想定はしていない)
        ?>
        <!-- ------------------------------------- ↓ presentSection ↓　-->
        <?php
        // -------------------------------------
        // プレゼント
        //-------------------------------------
        $current_jst_time = get_current_jst(); // 現在の日時をJSTで取得
        // lutwiyo_debug('Current JST Time:' . $current_jst_time);
        $args = [
            'post_type' => 'present', // 投稿タイプ
            'posts_per_page' => -1, // 取得する投稿数
            'post_status' => 'publish', // 投稿ステータス
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'start_date', // 開始日のカスタムフィールド
                    'value' => $current_jst_time, // 現在の日時（比較用）
                    'compare' => '<=', // 開始日は現在日時以前
                    'type' => 'DATETIME' // 日時タイプ
                ],
                [
                    'key' => 'end_date', // 終了日のカスタムフィールド
                    'value' => $current_jst_time, // 現在の日時（比較用）
                    'compare' => '>=', // 終了日は現在日時以降
                    'type' => 'DATETIME' // 日時タイプ
                ]
            ],
            'orderby' => [
                'campaign_end_date' => 'DESC', // campaign_end_date で降順
                'date' => 'DESC' // 投稿公開日で降順
            ]
        ];
        $present_info_list = PostModelHelper::get_posts_payload(
            $args,
            // 抽出したいACFフィールド名の配列
            [],
            // 各投稿に対して追加実行する関数セット
            []
        );
        get_template_part(
            'partials/modules/present-list',
            null,
            [
                'present_info_list' => $present_info_list
            ]
        ); ?>
        <!-- ------------------------------------- ↓ couponSection ↓　-->
        <section class="keenSlider__wrapper couponSection flexColumnBox borderBox">
            <h2 class="title fs--19">TOKK関西クーポン</h2>
            <div class="blockBox blockBox--flex blockBox--flex--half blockBox__coupon">
                <a class="blockBox__link" href="<?php echo esc_url(lutwiyo_get_member_benefit_entry_url(home_url('/coupon/'))); ?>" title="TOKK関西クーポン"></a>
                <div class="blockBox__thum">
                    <div class="thumImg__wrapper">
                        <img class="thumImg" src="/assets/img/sample/coupon.jpg" alt="TOKKクーポン" loading="lazy" width="1900" height="1270">
                    </div>
                </div>
                <div class="blockBox__info" data-boxBgColor="body">
                    <p class="fs--17 textHover__target blockTitle">お得なクーポン配布中</p>
                    <p class="fontW--r textColor--footer blockSubText">
                        無料でお使いいただける、お得なクーポンを配信中！TOKK関西で配信中のクーポンを要チェック。</p>
                    <div class="btn btnShaped btnShaped__border"data-shaped="auto-38">
                        <div class="flex--cc btnLink">
                            <p class="btnTtext">クーポンをチェック</p>
                            <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </article>
    <!-- ------------------------------------- ↓ newArticle 吹田の最新記事 ↓　-->
    <?php
    // TODO 横断トップの最新記事一覧は一体どこ？なければ検索結果一覧に飛ばす？
    $new_articles_list = PostModelHelper::get_posts_payload(
    // ベースになる投稿オブジェクトの配列(またはWP_Postsに渡すクエリ)
        [
            'post_type' => 'articles',
            'posts_per_page' => 16,
            'tax_query' => [
                'relation' => 'AND',
                // エリアを検索条件に追加
                [
                    'taxonomy' => 'area',
                    'field' => 'slug',
                    'terms' => [$term->slug],
                ],
            ],
        ],
        // 抽出したいACFフィールド名の配列
        [],
        // 各投稿に対して追加実行する関数セット
        []
    );
    ?>
    <?php get_template_part(
        'partials/modules/article-list',
        'new',
        [
            'area_info' => $current_area_info,
            'new_articles_list' => $new_articles_list,
            'link_all_url' => home_url('area/' . $current_area_info['slug'] . '/articles/'),
        ]
    );
    ?>
    <!-- ------------------------------------- ↓ tieupArticle タイアップ ↓　-->
    <article class="articlePT articlePB tieupArticle " data-boxBgColor="body">
        <h2 class="gridWide title fs--22">TOKK編集部タイアップ</h2>
        <?php
        // -------------------------------------
        // タイアップ(仮称)
        // -------------------------------------
        // フォールバック
        // おすすめ情報がエリア詳細情報に設定されていない場合は、トップから代替取得する
        $pinned_sponsored_articles_list =
                $current_area_info['pinned_sponsored_articles_list']
                ?? get_field('pinned_sponsored_articles_list', 'option');

        if (!is_array($pinned_sponsored_articles_list)) {
            $pinned_sponsored_articles_list = [];
        }
        
        $pinned_sponsored_articles_info_list = PostModelHelper::get_posts_payload(
        // ベースになる投稿オブジェクトの配列(またはWP_Postsに渡すクエリ)
            $pinned_sponsored_articles_list,
            // 抽出したいACFフィールド名の配列
            [],
            // 各投稿に対して追加実行する関数セット
            []
        );

        // lutwiyo_debug('$pinned_sponsored_articles_info_list', $pinned_sponsored_articles_info_list);
        $pinned_count = count($pinned_sponsored_articles_info_list ?? []);
        // (12-$pinned_count);
        // lutwiyo_debug('$pinned_count', $pinned_count);

        // 追加で取得するタイアップ
        $additional_sponsored_articles_info_list = PostModelHelper::get_posts_payload(
            [
                'post_type' => 'articles',
                'posts_per_page' => (10 - $pinned_count),
                'tax_query' => [
                    // エリアを限定する
                    [
                        'taxonomy' => 'area',
                        'field' => 'slug',
                        'terms' => [$current_area_info['slug']],
                    ],
                    [
                        'taxonomy' => 'category',
                        'field' => 'slug',
                        'terms' => 'tie-in',
                    ]
                ],
                'post__not_in' => wp_list_pluck(get_field('pinned_sponsored_articles_list', 'option'), 'ID'), // ピン留め記事を除外
            ],
            // 抽出したいACFフィールド名の配列
            [],
            // 各投稿に対して追加実行する関数セット
            []
        );

        // 固定記事にはis_pinnedフラグを追加しておく
        $pinned_sponsored_articles_info_list = array_map(
            fn($item) => $item + ['is_pinned' => true],
            $pinned_sponsored_articles_info_list ?? []
        );
        // ピン留め記事と追加記事を統合
        $integrated_sponsored_articles_info_list = array_merge(
            $pinned_sponsored_articles_info_list,
            $additional_sponsored_articles_info_list
        );
        // テンプレート呼び出し
        get_template_part(
            'partials/modules/article-list',
            'sponsored',
            [
                'integrated_articles_info_list' => $integrated_sponsored_articles_info_list
            ]
        );

        ?>
    </article>
    <!-- ------------------------------------- ↓ rankingArticle エリアのランキング ↓　-->
    <article class="articlePT articlePB rankingArticle " data-boxBgColor="bodySub">
        <?php
        // -------------------------------------
        //ランキング記事情報取得
        // -------------------------------------
        ?>
        <?php
        // lutwiyo_debug($current_area_info);
        // WPPのクエリで人気記事データ（オブジェクト配列）を取得
        $popular = new \WordPressPopularPosts\Query([
            'post_type' => 'articles',
            'taxonomy' => 'area',// エリア横断なので未指定
            'term_id' => $current_area_info['id'], // エリアのterm_idを指定
            'limit' => 5,
            'range' => RANKING_RANGE, // functions.phpにて定義
            // 'order_by'=> 'views',     // 既定は views
        ]);

        //プラグイン用クエリで取得した情報からランキング投稿IDリストを作成
        $popular_posts_id = [];
        foreach ($popular->get_posts() as $item) {
            $popular_posts_id[] = (int)$item->id;
        }
        $popular_post_count = count($popular_posts_id ?? []);

        //デバッグ用
        // lutwiyo_debug('エリア横断：ランキング記事数', $popular_post_count);
        // lutwiyo_debug('エリア横断：ランキング記事IDリスト', $popular_posts_id);

        //ランキング記事の各種詳細情報を取得
        $popular_articles = PostModelHelper::get_posts_payload(
        // エリア詳細情報で取得したピン留めタイアップ記事リスト
            $popular_posts_id,
            [],
            []
        );
        $display_date_label = sprintf('%s - %s',
            date('Y.n.j', strtotime('yesterday -6 days')),
            date('Y.n.j', strtotime('yesterday'))
        );
        // テンプレート呼び出し
        get_template_part(
            'partials/modules/article-list',
            'ranking',
            [
                'area_info' => $current_area_info,
                'articles_info_list' => $popular_articles,
                'display_date_label' => $display_date_label
            ]
        );
        ?>

        <!-- ------------------------------------- ↓ cmSection ↓　-->
        <?php
        // -------------------------------------
        // 広告エリア
        // -------------------------------------
        //管理画面のトップページ用の広告枠設定専用ACFから、投稿タイプ「広告」を選んで設定する
        //デザインでは、ネットワーク広告、純広、純広、純広の順に並んでいるが、任意に編集してもらってOK(ネットワーク広告が4本でもロジック上は問題なし)
        //掲載期限の概念があり、掲載期限が終わったものは自動的に非表示にする。
        //ACFには最大4件まで設定できる。 任意の個数を設定できるが、サイト状に表示されるのは最大4件まで
        ?>

        <?php
        $ad_list = $area_info_list[0]['ad_list'];
        // lutwiyo_debug("■広告リスト（エリア）：",$ad_list);
        $current_jst_time = get_current_jst(); // 現在の日時をJSTで取得

        if(!empty($ad_list)){
            $ad_info_list = PostModelHelper::get_posts_payload(
                [
                    'post_type' => 'advertisement',
                    'post__in' => wp_list_pluck($ad_list,'ID'),
                    'posts_per_page' => -1,
                    'post_status' => 'publish', // 投稿ステータス
                    'meta_query' => [
                        'relation' => 'AND',
                        [
                            'key' => 'start_date', // 開始日のカスタムフィールド
                            'value' => $current_jst_time, // 現在の日時（比較用）
                            'compare' => '<=', // 開始日は現在日時以前
                            'type' => 'DATETIME' // 日時タイプ
                        ],
                        [
                            'key' => 'end_date', // 終了日のカスタムフィールド
                            'value' => $current_jst_time, // 現在の日時（比較用）
                            'compare' => '>=', // 終了日は現在日時以降
                            'type' => 'DATETIME' // 日時タイプ
                        ]
                    ],
                    //'orderby' => 'post__in', // 指定した順序で取得
                ],
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );

        }else{
            //エリアの広告が未設定だった場合は横断TOPの広告と同じものを取得する
            $ad_list = get_field('ad_list', 'option'); // 広告リストを取得
            // lutwiyo_debug("■広告リスト（横断TOP）：",$ad_list);
            $ad_info_list = PostModelHelper::get_posts_payload(
                [
                    'post_type' => 'advertisement',
                    'post__in' => wp_list_pluck($ad_list,'ID'),
                    'posts_per_page' => -1,
                    'post_status' => 'publish', // 投稿ステータス
                    'meta_query' => [
                        'relation' => 'AND',
                        [
                            'key' => 'start_date', // 開始日のカスタムフィールド
                            'value' => $current_jst_time, // 現在の日時（比較用）
                            'compare' => '<=', // 開始日は現在日時以前
                            'type' => 'DATETIME' // 日時タイプ
                        ],
                        [
                            'key' => 'end_date', // 終了日のカスタムフィールド
                            'value' => $current_jst_time, // 現在の日時（比較用）
                            'compare' => '>=', // 終了日は現在日時以降
                            'type' => 'DATETIME' // 日時タイプ
                        ]
                    ],
                    //'orderby' => 'post__in', // 指定した順序で取得
                ],
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );
        }

        //TODO: 広告の並び替えがうまくいかないため並び替えを行う
        if(!empty($ad_info_list)){
            // 1. 並び順の基準となる ID 配列を取得
            $order_ids = wp_list_pluck($ad_list, 'ID'); // 例: [116966, 116910, ...]

            // 2. ID => 並び順インデックス のマップを作成
            $order_map = [];
            foreach ($order_ids as $index => $id) {
                // 数値or文字列ズレ防止のためキャストしておくと安心
                $order_map[(string) $id] = $index;
            }

            // 3. $ad_info_list から、order_map に存在しないIDを除外
            $ad_info_list = array_filter($ad_info_list, function ($item) use ($order_map) {
                return isset($order_map[(string) $item['id']]);
            });

            // array_filter でキーが飛ぶので、インデックスを振り直し
            $ad_info_list = array_values($ad_info_list);

            // 4. 並び替え（order_map の順番にソート）
            usort($ad_info_list, function ($a, $b) use ($order_map) {
                $posA = $order_map[(string) $a['id']];
                $posB = $order_map[(string) $b['id']];

                return $posA <=> $posB;
            });
        }
        // テンプレート呼び出し
        get_template_part(
            'partials/modules/ad-list',
            null,
            [
                'ad_info_list' => $ad_info_list
            ]
        );
        ?>
    </article>

    <!-- ------------------------------------- ↓ cateArticle カテゴリ / banner ↓　-->
    <article class="articlePT articlePB cateArticle " data-boxBgColor="body">
        <?php
        // -------------------------------------
        // カテゴリー（エリア）
        // -------------------------------------
        // lutwiyo_debug($current_area_info['recommended_category_articles_list']);

        foreach ($current_area_info['recommended_category_and_articles_list'] as $recommended_category_and_articles) {
            $category_info_list = TermModelHelper::get_terms_payload(
                'category',
                [$recommended_category_and_articles['category']], // ここはIDで渡ってくるので注意
                [],
                []
            );
            $category_info = $category_info_list[0] ?? [];
            if (!is_array($category_info)) {
                $category_info = [];
            }

            if (empty($category_info)) continue;
            $pinned_list = $recommended_category_and_articles['pinned_list'] ?? [];
            if (!is_array($pinned_list)) {
                $pinned_list = [];
            }
            $pinned_articles_info_list = PostModelHelper::get_posts_payload(
                $pinned_list,
                [],
                []
            );

            // lutwiyo_debug($pinned_articles_info_list);
            $pinned_count = $count = is_countable($pinned_articles_info_list) ? count($pinned_articles_info_list) : 0;
            // lutwiyo_debug($pinned_count);

            $additional_category_articles_info_list = [];
            $remained_articles_count = ARTICLE_LIST_DISPLAY_LIMIT - $pinned_count;

            if ($remained_articles_count > 0) {
            $additional_category_articles_info_list = PostModelHelper::get_posts_payload(
                [
                    'post_type' => 'articles',
                    'posts_per_page' => ($remained_articles_count),
                    'tax_query' => [
                        [
                            'taxonomy' => 'category',
                            'field' => 'slug',
                            'terms' => $category_info['slug'],
                        ],
                        [
                            'taxonomy' => 'area',
                            'field' => 'slug',
                            'terms' => [$current_area_info['slug']],
                        ],
                    ],
                    'post__not_in' => wp_list_pluck($pinned_articles_info_list, 'ID'), // ピン留め記事を除外
                ],
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );
            }
            // lutwiyo_debug($area['pinned_list']);

            $pinned_articles_info_list = array_map(
                fn($item) => $item + ['is_pinned' => true],
                $pinned_articles_info_list ?? []
            );

            // ピン留め記事と追加記事を統合
            $integrated_category_articles_info_list = array_merge(
                $pinned_articles_info_list,
                $additional_category_articles_info_list
            );
            // すべてみるリンク
            $view_all_url = home_url('/area/' . $current_area_info['slug'] . '/' . $category_info['slug'] . '/');
            // テンプレート呼び出し
            get_template_part(
                'partials/modules/article-list',
                'category',
                [
                    'term_info' => $category_info,
                    'integrated_articles_info_list' => $integrated_category_articles_info_list,
                    'type' => 'category',
                    'view_all_url' => $view_all_url
                ]
            );
        }
        ?>
        <!-- bannerSection バナー -->
        <?php
        // -------------------------------------
        // バナーエリア
        // -------------------------------------
        $banner_list = get_field('banner_list', 'option'); // バナーリストを取得
        ?>
        <?php
        // テンプレート呼び出し
        get_template_part(
            'partials/modules/banner',
            'top',
            [
                'banner_list' => $banner_list,
            ]
        );
        ?>
    </article>
    <!-- ------------------------------------- ↓ hashArticle ハッシュタグ ↓　-->

    <!-- ------------------------------------- ↓ hashArticle ↓　-->
    <article class="articlePT articlePB hashArticle " data-boxBgColor="bodySub">
        <?php
        // -------------------------------------
        // タグ（エリア）
        // -------------------------------------
        foreach ($current_area_info['recommended_tag_and_articles_list'] as $recommended_tag_and_articles) {
            $tag_info_list = TermModelHelper::get_terms_payload(
                'post_tag',
                [$recommended_tag_and_articles['post_tag']], // ここはIDで渡ってくるので注意
                [],
                []
            );
            $tag_info = $tag_info_list[0] ?? [];
            if (!is_array($tag_info)) {
                $tag_info = [];
            }
            if (empty($tag_info)) continue;
            // lutwiyo_debug($tag);
            $pinned_list = $recommended_tag_and_articles['pinned_list'] ?? [];
            if (!is_array($pinned_list)) {
                $pinned_list = [];
            }
            $pinned_articles_info_list = PostModelHelper::get_posts_payload(
                $pinned_list,
                [],
                []
            );
            // lutwiyo_debug($pinned_articles_info_list);


            // lutwiyo_debug($pinned_articles_info_list);
            $pinned_count = $count = is_countable($pinned_articles_info_list) ? count($pinned_articles_info_list) : 0;
            // lutwiyo_debug($pinned_count);

            $additional_tag_articles_info_list = PostModelHelper::get_posts_payload(
                [
                    'post_type' => 'articles',
                    'posts_per_page' => (5 - $pinned_count),
                    'tax_query' => [
                        [
                            'taxonomy' => 'post_tag',
                            'field' => 'slug',
                            'terms' => $tag_info['slug'],
                        ],
                        [
                            'taxonomy' => 'area',
                            'field' => 'slug',
                            'terms' => [$current_area_info['slug']],
                        ],
                    ],
                    'post__not_in' => wp_list_pluck($pinned_articles_info_list, 'ID'), // ピン留め記事を除外
                ],
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );
            // lutwiyo_debug($area['pinned_list']);

            $pinned_articles_info_list = array_map(
                fn($item) => $item + ['is_pinned' => true],
                $pinned_articles_info_list ?? []
            );

            // ピン留め記事と追加記事を統合
            $integrated_tag_articles_info_list = array_merge(
                $pinned_articles_info_list,
                $additional_tag_articles_info_list
            );
            // テンプレート呼び出し
            get_template_part(
                'partials/modules/article-list',
                'tag',
                [
                    'term_info' => $tag_info,
                    'integrated_articles_info_list' => $integrated_tag_articles_info_list,
                    'type' => 'category',
                    'view_all_url' => '' // TODO: リンク先を動的に設定する
                ]
            );
        }
        ?>
    </article>
    <?php
    if (function_exists('tokk_memberblog_render_public_list_section_from_filters')) {
        tokk_memberblog_render_public_list_section_from_filters([
            'area_slug' => (string) ($current_area_info['slug'] ?? ''),
            'page' => 1,
            'per_page' => 4,
        ], [
            'show_filter_form' => false,
            'heading' => (string) ($current_area_info['name'] ?? 'このエリア') . 'の会員ブログ',
            'view_all_url' => home_url('/memberblog-public-area-list/?area_slug=' . rawurlencode((string) ($current_area_info['slug'] ?? ''))),
            'empty_message' => 'このエリアの公開中の会員ブログ投稿はありません。',
        ]);
    }
    ?>
</main>


<?php get_footer(); ?>
