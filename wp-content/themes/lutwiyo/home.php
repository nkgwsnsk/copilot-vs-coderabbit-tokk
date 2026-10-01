<?php get_header(); ?>
<?php
// -------------------------------------
// 天気予報情報取得
//get_weatherを呼び出して取得可能な要素サンプル
//array(5) { ["label"]=> string(15) "豊中・伊丹" ["lat"]=> float(34.785) ["lon"]=> float(135.438) ["memo"]=> NULL ["data"]=> array(4) { ["weather"]=> array(3) { ["main"]=> string(6) "Clouds" ["description"]=> string(3) "雲" ["icon"]=> string(3) "03d" } ["temp"]=> float(21.91) ["name"]=> string(6) "久代" ["source"]=> string(22) "openweathermap_current" } }
// -------------------------------------
// $terms = get_the_terms(get_the_ID(), 'area');
// $weather = get_weather('toyonaka');
// echo "天気予報情報<br>";
// echo "豊中・伊丹の天気：".$weather["data"]["weather"]["main"]."<br>";
// echo "豊中・伊丹の気温：".$weather["data"]["temp"];
?>

<?php //ここから実際のHTML部分
//TODO 表示確認用に/wp-content/themes/lutwiyo/assets/img/sampleの画像を設定しているため、最終的には全て書き換わっていることを確認すること
?>
<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
    <article class="grid kv">
        <section class="kvInner">
            <div class="kvBg"><img class="kvBg__img" src="/assets/img/contents/top/kv.jpg" alt="TOKK関西"
                                   loading="lazy"
                                   width="2650" height="410"></div>
            <div class="kvTitle">
                <p class="topKv__title topKv__title--jp">TOKK関西</p>
            </div>
            <div class="flex--cc kvInfo cornerCover__wrapper" data-boxBgColor="body">
                <div class="cornerCover cornerCover--lb">
                    <div class="mask cornerCover__inner"></div>
                </div>
                <div class="cornerCover cornerCover--rt">
                    <div class="mask cornerCover__inner"></div>
                </div>
                <div class="kvInfo__inner">
                    <div class="textHoverWrapper btnHoverWrapper topKvInfo__btn" role="button">
                        <div class="btnCircle" data-circle="w-15">
                            <div class="btnArrow btnArrow--down" data-arrow="w-6"></div>
                        </div>
                        <p class="textHover__target topKvInfo__btn--p">好きなエリアを探してみよう</p>
                    </div>
                </div>
            </div>
        </section>
    </article>
    <!-- ------------------------------------- ↓ areaNav ↓　-->
    <?php get_template_part(
        'partials/modules/area',
        'nav'
    ); ?>
    <!-- ------------------------------------- ↓ mastheadArticle ↓　-->
    <?php
    // -------------------------------------
    // おすすめ記事カルーセル
    // -------------------------------------
    ?>
    <?php
    $recommended_articles_list = PostModelHelper::get_posts_payload(
    // ベースになる投稿オブジェクトの配列(またはWP_Postsに渡すクエリ)
        get_field('recommended_articles_list', 'option') ?? [],
        // 抽出したいACFフィールド名の配列
        ['area'],
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

    <?php
    //TODO: クーポン情報を埋め込む必要がある
    //TODO: プレゼント・クーポンはエリア別の処理があるのか確認する(想定はしていない)
    ?>
    <!-- ------------------------------------- ↓ articleBanner 今月のプレゼント / TOKKクーポン ↓　-->
    <article class="gridWide articlePT articlePB articleBanner flexColumn flexColumn--2" data-boxBgColor="body">
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
        // lutwiyo_debug('Present Info', $present_info_list);
        ?>
        <?php get_template_part(
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
                <a class="blockBox__link" href="<?php echo esc_url(lutwiyo_get_member_benefit_entry_url(home_url('/coupon/'))); ?>"
                   title="TOKKクーポン"></a>
                <div class="blockBox__thum">
                    <div class="thumImg__wrapper"><img class="thumImg"
                                                       src="/assets/img/sample/coupon.jpg"
                                                       alt="TOKK関西クーポン"
                                                       loading="lazy" width="1900" height="1270"></div>
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
    <!-- ------------------------------------- ↓ newArticle 最新記事 ↓　-->
    <?php
    $new_articles_list = PostModelHelper::get_posts_payload(
    // ベースになる投稿オブジェクトの配列(またはWP_Postsに渡すクエリ)
        [
            'post_type' => 'articles',
            'posts_per_page' => 16,
            'tax_query' => [
                [
                    'taxonomy' => 'articles_hide',
                    'field' => 'slug',
                    'terms' => ['list-hide'],
                    'operator' => 'NOT IN',
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
            'new_articles_list' => $new_articles_list,
            'link_all_url' => home_url('/?s='),
        ]
    ); ?>
    <!-- ------------------------------------- ↓ areaArticle エリア一覧 ↓　-->
    <?php
    // -------------------------------------
    // エリア一覧情報
    // -------------------------------------
    ?>
    <?php
    //ペエリア情報の詳細をまとめて取得します。
    $area_all_info_list = TermModelHelper::get_terms_payload(
        'area',
        // ベースになるタームの配列(またはWP_Termに渡すクエリ)
        [],
        ['is_clickable'],
        // 各投稿に対して追加実行する関数セット
        []
    );
    // lutwiyo_debug("area_all_info_list",$area_all_info_list);
    ?>
    <article class="articlePT articlePB areaArticle">
        <section class="areaArticle__bg"></section>
        <section class="gridWide areaArticle__inner">
            <div class="areaConcept">
                <h2 class="areaConcept__logo"><img src="/assets/img/contents/top/areaConcept__logo.svg"
                                                   alt="TOKK kansai">
                </h2>
                <ul class="fontW--b areaConcept__list">
                    <li class="areaConcept--p">関西で50年以上の実績を持つローカルメディア編集部が</li>
                    <li class="areaConcept--p">無数の情報を独自の視点で選定、信頼性の高い情報を発信しています。</li>
                    <li class="areaConcept--p">関西にお住いの方、観光へ来られる方の両方に役立つ情報をお届けしています。</li>
                </ul>
                <div class="btn btnShaped btnBgColor btnAll" data-shaped="145-38">
                    <a class="flex--cc btnLink" href="/area" aria-label="エリア一覧" title="エリア一覧">
                        <p class="btnTtext">エリア一覧</p>
                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                    </a>
                </div>
            </div>
            <div class="areaMapDetail">
                <div class="areaMapDetail__bg"></div>
                <ul class="areaMapDetail__list" id="topMapArea--target">
                    <?php //一覧への表示が有効のものはaタグ、無効のものはdivタグ出力 ?>
                    <?php foreach ((array)($area_all_info_list ?? []) as $area) :
                        $name = (string)($area['name'] ?? '');
                        $slug = (string)($area['slug'] ?? '');
                        $is_clickable = !empty($area['is_clickable']) && $area['is_clickable'] !== '0';
                        $href = $slug !== '' ? home_url('area/' . $slug . '/') : '';
                        ?>
                        <li class="areaMap__linkBtn">
                            <?php if ($is_clickable && $href) : ?>
                                <a class="areaMap__linkBtn--inner" href="<?= esc_url($href); ?>">
                                    <p class="areaMap__linkBtn--p"><?= esc_html($name); ?></p>
                                </a>
                            <?php else : ?>
                                <div class="areaMap__linkBtn--inner">
                                    <p class="areaMap__linkBtn--p"><?= esc_html($name); ?></p>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>

                </ul>
            </div>
        </section>
    </article>
    <!-- ------------------------------------- ↓ recommendArticle おすすめエリア ↓　-->
    <?php
    // -------------------------------------
    // おすすめエリア（TOP）
    // -------------------------------------
    $recommended_area_info_list = TermModelHelper::get_terms_payload(
        'area',
        // ベースになるタームの配列(またはWP_Termに渡すクエリ)
        get_field('recommended_area_list', 'option'),
        // 抽出したいACFフィールド名の配列
        [],
        // 各投稿に対して追加実行する関数セット
        []
    );
    ?>
    <article class="gridWide articlePT articlePB recommendArticle " data-boxBgColor="bodySub">
        <h2 class="title fs--22">おすすめエリア</h2>
        <?php foreach ($recommended_area_info_list as $area_info) {
            // lutwiyo_debug($area_info['pinned_list']);
            // ピン留め記事の数をカウントし、合計で最大3件分の記事を取得する
            $pinned_count = $count = is_countable($area_info['pinned_list']) ? count($area_info['pinned_list']) : 0;
            // ピン留めされた記事 $pinned_count
            // 追加で取得する記事数 (3 - $pinned_count)
            // 除外する記事IDリスト wp_list_pluck($area['pinned_list'], 'ID')
            $additional_articles_info_list = PostModelHelper::get_posts_payload(
                [
                    'post_type' => 'articles',
                    'posts_per_page' => (3 - $pinned_count),
                    'tax_query' => [
                        [
                            'taxonomy' => 'area',
                            'field' => 'slug',
                            'terms' => $area_info['slug'],
                        ],
                    ],
                    'post__not_in' => wp_list_pluck($area_info['pinned_list'], 'ID'), // ピン留め記事を除外
                ],
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );
            // 固定記事にはis_pinnedフラグを追加しておく
            $area_info['pinned_articles_info_list'] = array_map(
                fn($item) => $item + ['is_pinned' => true],
                $area_info['pinned_articles_info_list'] ?? []
            );
            // ピン留め記事と追加記事を統合
            $integrated_articles_info_list = array_merge(
                $area_info['pinned_articles_info_list'],
                $additional_articles_info_list
            );
            // テンプレート呼び出し
            get_template_part(
                'partials/modules/article-list',
                'area',
                [
                    'area_info' => $area_info,
                    'integrated_articles_info_list' => $integrated_articles_info_list
                ]
            );
        } ?>
    </article>
    <!-- ------------------------------------- ↓ tieupArticle TOKK編集部タイアップ ↓　-->
    <article class="articlePT articlePB tieupArticle " data-boxBgColor="body">
        <h2 class="gridWide title fs--22">TOKK編集部タイアップ</h2>
        <?php //TODO:get_field('pinned_sponsored_articles_list', 'option')でFatal error: Uncaught TypeErrorが発生 ?>
        <?php
        // -------------------------------------
        // TOKK編集部タイアップ
        // -------------------------------------
        $pinned_sponsored_list = get_field('pinned_sponsored_articles_list', 'option');
        if (!is_array($pinned_sponsored_list)) {
            $pinned_sponsored_list = [];
        }
        $pinned_sponsored_articles_info_list = PostModelHelper::get_posts_payload(
        // ベースになる投稿オブジェクトの配列(またはWP_Postsに渡すクエリ)
            $pinned_sponsored_list,
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
                    [
                        'taxonomy' => 'category',
                        'field' => 'slug',
                        'terms' => 'tie-in',
                    ],
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
    <!-- ------------------------------------- ↓ rankingArticle ランキング ↓　-->
    <article class="articlePT articlePB rankingArticle " data-boxBgColor="bodySub">
        <?php
        // -------------------------------------
        //ランキング記事情報取得
        // -------------------------------------
        ?>
        <?php
        // WPPのクエリで人気記事データ（オブジェクト配列）を取得
        $popular = new \WordPressPopularPosts\Query([
            'post_type' => 'articles',
            //'taxonomy'  => 'area',// エリア横断なので未指定
            // 'term_id'   => (string) $term->term_taxonomy_id, // エリア横断なので未指定
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
                'articles_info_list' => $popular_articles,
                'display_date_label' => $display_date_label
            ]
        );
        ?>
        <?php foreach ($popular_articles as $article) : ?>
            <?php
            // =========== エリア別ランキング記事 ===========
            // サムネイル画像：$article['image_url']
            // 公開日：$article['date_label']
            // 更新日：$article['update_date_label']
            // タイトル：$article['title']
            // lutwiyo_debug('抽出したランキング記事のタイトル：' . $article['title']);
            // 記事を読むのにかかる時間：$article['reading_time_label']
            // 記者リスト：$article['staff_info']
            // タグ：$article['tag_info']
            ?>
        <?php endforeach; ?>


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
        $ad_list = get_field('ad_list', 'option'); // 広告リストを取得
        // lutwiyo_debug(wp_list_pluck($ad_list, 'ID'));
        $current_jst_time = get_current_jst(); // 現在の日時をJSTで取得
        $ad_info_list = PostModelHelper::get_posts_payload(
            [
                'post_type' => 'advertisement',
                'post__in' => wp_list_pluck($ad_list, 'ID'),
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
                'orderby' => 'post__in', // 指定した順序で取得
            ],
            // 抽出したいACFフィールド名の配列
            [],
            // 各投稿に対して追加実行する関数セット
            []
        );

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
        // カテゴリー（TOP）
        // -------------------------------------
        $recommended_category_info_list = TermModelHelper::get_terms_payload(
            'category',
            // ベースになるタームの配列(またはWP_Termに渡すクエリ)
            get_field('recommended_category_list', 'option'),
            // 抽出したいACFフィールド名の配列
            [],
            // 各投稿に対して追加実行する関数セット
            []
        );
        // lutwiyo_debug($recommended_area_info_list);
        ?>

        <?php foreach ($recommended_category_info_list as $category): ?>
            <?php
            // ピン留め記事の数をカウントし、合計で最大5件分の記事を取得する
            $pinned_count = $count = is_countable($category['pinned_list']) ? count($category['pinned_list']) : 0;
            // ピン留めされた記事 $pinned_count
            // 追加で取得する記事数 (5 - $pinned_count)

            $additional_category_articles_info_list = PostModelHelper::get_posts_payload(
                [
                    'post_type' => 'articles',
                    'posts_per_page' => (5 - $pinned_count),
                    'tax_query' => [
                        [
                            'taxonomy' => 'category',
                            'field' => 'slug',
                            'terms' => $category['slug'],
                        ],
                    ],
                    'post__not_in' => wp_list_pluck($category['pinned_list'], 'ID'), // ピン留め記事を除外
                ],
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );
            // lutwiyo_debug($area['pinned_list']);

            // 固定記事にはis_pinnedフラグを追加しておく
            $category['pinned_articles_info_list'] = array_map(
                fn($item) => $item + ['is_pinned' => true],
                $category['pinned_articles_info_list'] ?? []
            );
            // ピン留め記事と追加記事を統合
            $integrated_category_articles_info_list = array_merge(
                $category['pinned_articles_info_list'],
                $additional_category_articles_info_list
            );
            //  リンク先
            $view_all_url = home_url('/category/') . $category['slug'] . '/';
            // テンプレート呼び出し
            get_template_part(
                'partials/modules/article-list',
                'category',
                [
                    'term_info' => $category,
                    'integrated_articles_info_list' => $integrated_category_articles_info_list,
                    'type' => 'category',
                    'view_all_url' => $view_all_url,    
                ]
            );
            ?>
        <?php endforeach; ?>

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
    <article class="articlePT articlePB hashArticle " data-boxBgColor="bodySub">
        <?php
        // -------------------------------------
        // タグ
        // -------------------------------------
        // lutwiyo_debug(get_field('recommended_post_tag_list', 'option'));
        $recommended_post_tag_info_list = TermModelHelper::get_terms_payload(
            'post_tag',
            // ベースになるタームの配列(またはWP_Termに渡すクエリ)
            get_field('recommended_post_tag_list', 'option'),
            // 抽出したいACFフィールド名の配列
            [],
            // 各投稿に対して追加実行する関数セット
            []
        );
        // lutwiyo_debug($recommended_post_tag_info_list);
        ?>

        <?php foreach ($recommended_post_tag_info_list as $tag): ?>
            <?php
            // =========== タグ  ===========
            // タグ名：$tag['name']
            // lutwiyo_debug($tag['name']);
            // タグ画像URL：$tag['image_url']
            // リンク先URL：/tag/$tag['slug']
            // ピン留め記事：$tag['pinned_articles_info_list']
            // lutwiyo_debug($tag['pinned_articles_info_list']);
            ?>
            <?php
            // -------------------------------------
            // ピン留め記事の数をカウントし、合計で最大3件分の記事を取得する
            // -------------------------------------
            $pinned_count = $count = is_countable($tag['pinned_list']) ? count($tag['pinned_list']) : 0;
            // ピン留めされた記事 $pinned_count
            // 追加で取得する記事数 (3 - $pinned_count)
            // 除外する記事IDリスト wp_list_pluck($area['pinned_list'], 'ID')
            $additional_tag_articles_info_list = PostModelHelper::get_posts_payload(
                [
                    'post_type' => 'articles',
                    'posts_per_page' => (5 - $pinned_count),
                    'tax_query' => [
                        [
                            'taxonomy' => 'post_tag',
                            'field' => 'slug',
                            'terms' => $tag['slug'],
                        ],
                    ],
                    'post__not_in' => wp_list_pluck($tag['pinned_list'], 'ID'), // ピン留め記事を除外
                ],
                // 抽出したいACFフィールド名の配列
                [],
                // 各投稿に対して追加実行する関数セット
                []
            );
            // lutwiyo_debug($tag['pinned_list']);
            // 固定記事にはis_pinnedフラグを追加しておく
            $tag['pinned_articles_info_list'] = array_map(
                fn($item) => $item + ['is_pinned' => true],
                $tag['pinned_articles_info_list'] ?? []
            );
            // ピン留め記事と追加記事を統合
            $integrated_tag_articles_info_list = array_merge(
                $tag['pinned_articles_info_list'],
                $additional_tag_articles_info_list
            );
            // テンプレート呼び出し
            get_template_part(
                'partials/modules/article-list',
                'tag',
                [
                    'term_info' => $tag,
                    'integrated_articles_info_list' => $integrated_tag_articles_info_list,
                    'view_all_url' => '', // TODO:リンク先を動的に設定する
                ]
            );

            ?>
        <?php endforeach; ?>
    </article>
    <?php
    if (function_exists('tokk_memberblog_render_public_list_section_from_filters')) {
        tokk_memberblog_render_public_list_section_from_filters([
            'page' => 1,
            'per_page' => 4,
        ], [
            'show_filter_form' => false,
            'heading' => '新着の会員ブログ',
            'view_all_url' => home_url('/memberblog-public-list/'),
            'empty_message' => '公開中の会員ブログ投稿はありません。',
        ]);
    }
    ?>
</main>


<?php get_footer(); ?>
