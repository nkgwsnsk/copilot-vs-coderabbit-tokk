<?php
/**
 * RSS2 Feed Template (NTT / dmenu / goo)
 */

function get_area_codes_from_slug(string $slug): array
{
    // エリアコード対応表
    static $AREA_CODE_MAP = [
        'umeda' => [
            'prefecture' => ['27'],
            'city' => ['27127'],
        ],
        'sannomiya-shinkaichi' => [
            'prefecture' => ['28'],
            'city' => ['28204', '28205'],
        ],
        'nishinomiya-ashiya' => [
            'prefecture' => ['28'],
            'city' => ['28105', '28102'],
        ],
        'kawaramachi-karasuma-kyotoeki' => [
            'prefecture' => ['26'],
            'city' => ['26106', '26104'],
        ],
        'namba-shinsaibashi-horie' => [
            'prefecture' => ['27'],
            'city' => ['27128', '27111'],
        ],
        'amagasaki' => [
            'prefecture' => ['28'],
            'city' => ['28202'],
        ],
        'okamoto-mikage' => [
            'prefecture' => ['28'],
            'city' => ['28101'],
        ],
        'rokko-nada' => [
            'prefecture' => ['28'],
            'city' => ['28102'],
        ],
        'toyonaka-itami' => [
            'prefecture' => ['27', '28'],
            'city' => ['27203', '28207'],
        ],
        'minoh' => [
            'prefecture' => ['27'],
            'city' => ['27218'],
        ],
        'kawanishi-ikeda' => [
            'prefecture' => ['27', '28'],
            'city' => ['28217', '27204'],
        ],
        'takarazuka' => [
            'prefecture' => ['28'],
            'city' => ['28214'],
        ],
        'juso-awaji-kamishinjo' => [
            'prefecture' => ['27'],
            'city' => ['27123', '27124'],
        ],
        'suita' => [
            'prefecture' => ['27'],
            'city' => ['27205'],
        ],
        'ibaraki-settsu' => [
            'prefecture' => ['27'],
            'city' => ['27210', '27224'],
        ],
        'takatsuki-shimamoto' => [
            'prefecture' => ['27'],
            'city' => ['27207', '27301'],
        ],
        'nagaokakyo-oyamazaki-muko' => [
            'prefecture' => ['26'],
            'city' => ['26209', '26303', '26210'],
        ],
        'katsura-arashiyama' => [
            'prefecture' => ['26'],
            'city' => ['26111', '26108'],
        ],
        'kansai' => [
            'prefecture' => ['25', '26', '27', '28', '29', '30'],
            'city' => [], // 市区町村なし
        ],
    ];

    $slug = (string)$slug;

    if (isset($AREA_CODE_MAP[$slug])) {
        // prefecture / city を必ず配列として返す（型を保証）
        $pref = (array)($AREA_CODE_MAP[$slug]['prefecture'] ?? []);
        $city = (array)($AREA_CODE_MAP[$slug]['city'] ?? []);
        return [
            'prefecture' => array_values($pref),
            'city' => array_values($city),
        ];
    }

    // 未定義スラッグの場合は空配列を返す
    return [
        'prefecture' => [],
        'city' => [],
    ];
}

function date_to_rfc2822(string $iso8601): string
{
    try {
        // ISO8601（2025-10-24T17:34:57+09:00）をパース
        $dt = new DateTime($iso8601);

        // RFC2822形式に変換
        return $dt->format(DateTime::RFC2822);
        // 結果：Fri, 24 Oct 2025 17:34:57 +0900
    } catch (Exception $e) {
        // パースできない場合は空文字を返すなど
        return '';
    }
}

header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . '>';

do_action('rss_tag_pre', 'rss2');
?>
<rss version="2.0"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:goonews="http://news.goo.ne.jp/rss/2.0/news/goonews/"
     xmlns:smp="http://news.goo.ne.jp/rss/2.0/news/smp/"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
    <?php do_action('rss2_ns'); ?>
>
    <channel>
        <title><?php wp_title_rss(); ?></title>
        <link><?php bloginfo_rss('url'); ?></link>
        <description>TOKK（トック）えき、まち、くらし。阪急沿線おでかけ情報</description>
        <language><?php bloginfo_rss('language'); ?></language>
        <?php
        /**
         * NTTフィード記事削除用
         */
        ?>
        <?php $fields = get_field('feed_excluded_articles_list', 'option'); ?>
        <?php foreach ($fields as $field) : ?>
            <item>
                <guid isPermaLink="false"><?= esc_attr($field->ID) ?></guid>
                <goonews:delete>1</goonews:delete>
            </item>
        <?php endforeach; ?>
        <?php
        /**
         * 記事本体の取得
         */
        // $cache_key  = 'ntt_feed_posts_v1';
        // $cache_time = 60 * 60 * 24;

        // $feed_query = get_transient($cache_key);

        // if ( false === $feed_query ) {
        //     $args = [
        //         'posts_per_page' => 10,
        //         'post_type'      => 'articles', // ← posts
        //         'post_status'    => 'publish',
        //     ];
        //     $feed_query = new WP_Query($args);
        //     set_transient($cache_key, $feed_query, $cache_time);
        // }


        $post_list = PostModelHelper::get_posts_payload(
            [
                'post_type' => 'articles',
                'posts_per_page' => 10,
                'tax_query' => [
                    [
                        'taxonomy' => 'articles_hide',
                        'field' => 'slug',
                        'terms' => ['goo-hide'],
                        'operator' => 'NOT IN',
                    ],
                ],
            ],
            // 抽出したいACFフィールド名の配列
            ['area'],
            // 各投稿に対して追加実行する関数セット
            []
        );
        // print_r($post_list);

        //取得した$post_listについてmodified順に並び替える
        //get_posts_payloadでorderbyを指定してもその並び順にはならないため
        usort($post_list, function ($a, $b) {
            // modified が無い場合の保険
            $a_mod = $a['modified'] ?? '';
            $b_mod = $b['modified'] ?? '';

            // どちらかが空なら、空の方を「古い」とみなす
            if ($a_mod === '' && $b_mod === '') {
                return 0;
            } elseif ($a_mod === '') {
                return 1;   // $a が古い → 後ろへ
            } elseif ($b_mod === '') {
                return -1;  // $b が古い → 後ろへ
            }
            // ISO8601 なので文字列比較でも時系列順になるが、
            // 念のため strtotime で比較（DESC：新しい方を先頭）
            return strtotime($b_mod) <=> strtotime($a_mod);
        });

        $category_f = array();

        if (!empty($post_list)) :
            foreach ($post_list as $p) :

                ?>
                <item>
                    <title><?= $p['title']; ?></title>
                    <link><?= $p['permalink']; ?></link>

                    <?php //TODO:どの日付をセットすべきか。更新日はACFフィールドの方？？また、modifiedの順に並び替える必要がありそう
                    ?>
                    <?php //TODO:ただし現行サイトだと日付はどちらも更新日（get_post_modified_time）をセットしているのでそれに倣ってセット
                    ?>
                    <pubDate><?= date_to_rfc2822($p['modified']); ?></pubDate>
                    <goonews:modified><?= date_to_rfc2822($p['modified']); ?></goonews:modified>

                    <?php /* エリアの出力ここから-----------
                    RSSの仕様で、都道府県コードは重複不可、かつ市区町村は重複ありの仕様である。
                    同じエリアを複数指定した場合を考慮もして、重複を削除した上で都道府県コード毎に出力するような処理を実施している。
                    例えば、吹田と梅田エリアを設定した場合、都道府県コードはそれぞれ27で、cityはそれぞれ27127、27205なので、
                    <area><prefecture>27</prefecture><city>27127</city><city>27205</city></area>
                    という形で<area>は1個のみで、中に<city>を複数含ませる形にしている。
                    */

                    // ① 都道府県ごとに市区町村コードを溜めるバッファ
                    $pref_city_map = [];

                    // ② エリア情報（ACF）は空の場合や複数ある場合があることを考慮
                    if (!empty($p['area']) && is_array($p['area'])) {

                        foreach ($p['area'] as $ar) {

                            // TermModelHelperでエリア情報を取得（slug など）
                            $area_info_list = TermModelHelper::get_terms_payload(
                                'area',
                                [$ar],
                                [],
                                []
                            );

                            // area_infoが空でない & slugが取れた場合のみ処理
                            if (!empty($area_info_list[0]['slug'])) {
                                $slug = $area_info_list[0]['slug'];

                                // ③ スラッグから都道府県コード・市区町村コードを取得
                                $codes = get_area_codes_from_slug($slug);
                                $pref_list = (array)($codes['prefecture'] ?? []);
                                $city_list = (array)($codes['city'] ?? []);

                                // ④ 都道府県ごとに city をマージしていく
                                foreach ($pref_list as $pref_code) {
                                    $pref_code = (string)$pref_code;
                                    if ($pref_code === '') {
                                        continue;
                                    }

                                    if (!isset($pref_city_map[$pref_code])) {
                                        $pref_city_map[$pref_code] = [];
                                    }

                                    // 市区町村コードを重複なしで保持（連想配列をsetとして使う）
                                    foreach ($city_list as $city_code) {
                                        $city_code = (string)$city_code;
                                        if ($city_code === '') {
                                            continue;
                                        }
                                        $pref_city_map[$pref_code][$city_code] = true;
                                    }
                                }
                            }
                        }
                    }

                    // ⑤ 重複削除済みの $pref_city_map を元に <area> を出力
                    // 例）pref=27, city=[27127,27205] のような構造
                    if (!empty($pref_city_map)) {
                        foreach ($pref_city_map as $pref_code => $city_set) {

                            // city_set のキーがそのまま市区町村コードになる
                            $cities = array_keys($city_set);

                            echo "<area>\n";
                            echo '  <prefecture>' . esc_html($pref_code) . "</prefecture>\n";

                            foreach ($cities as $city_code) {
                                echo '  <city>' . esc_html($city_code) . "</city>\n";
                            }

                            echo "</area>\n";
                        }
                    }
                    ?>
                    <?php //エリアの掲出ここまで
                    ?>


                    <?php //TODO:クリエイターはTOKK固定。ライターは入れるか？
                    ?>
                    <dc:creator>TOKK</dc:creator>

                    <?php //TODO:カテゴリーは複数設定OKだが先頭のみ使用される模様
                    ?>
                    <?php //TODO:検証ツール結果：カテゴリが複数指定されていますが、先頭の値のみが使用されます
                    ?>
                    <?php if (!empty($p['category'])): ?>
                        <?php foreach ($p['category'] as $c): ?>
                            <?php
                            // TermModelHelperでエリア情報を取得（slug など）
                            $cat_info = TermModelHelper::get_terms_payload(
                                'category',
                                [$c],
                                [],
                                []
                            );
                            ?>
                            <?php if (!empty($cat_info[0]['name'])): ?>
                                <category><?= $cat_info[0]['name']; ?></category>
                                <?php $category_f[] = $cat_info[0]['slug']; ?>
                            <?php else: ?>
                                <?php //TODO:カテゴリーは必須なので、もしカテゴリがなければ未分類にして良いか ?>
                                <category>未分類</category>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php //TODO:カテゴリーは必須なので、もしカテゴリがなければ未分類にして良いか ?>
                        <category>未分類</category>
                    <?php endif; ?>


                    <?php //TODO:guidは記事IDで良い？
                    ?>
                    <guid isPermaLink="false"><?= $p['id']; ?></guid>

                    <?php
                    // description（リンク除去・不要タグ除去）
                    $content = (string) ($p['content'] ?? '');
                    $post_id = (int) ($p['id'] ?? 0);
                    if ($post_id > 0) {
                        $post_object = get_post($post_id);
                        if ($post_object instanceof WP_Post && function_exists('lutwiyo_should_mask_paid_article_content') && lutwiyo_should_mask_paid_article_content($post_object)) {
                            $content = lutwiyo_build_paid_article_masked_content($post_object);
                        } elseif (function_exists('lutwiyo_strip_paywall_gate_placeholder')) {
                            $content = lutwiyo_strip_paywall_gate_placeholder($content);
                        }
                    }
                    $content = preg_replace('/<a .*?>(.*?)<\/a>/', "$1", $content);
                    $content = preg_replace('/<script[\s\S]*?>[\s\S]*?<\/script>/', '', $content);
                    $content = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $content);
                    $content = preg_replace('/<blockquote class="instagram-media"[\s\S]*?>[\s\S]*?<\/blockquote>/', '', $content);
                    $content = preg_replace('/<p(?:\s+[^>]*)?>\s*<\/p>/i', '', $content);

                    // RSSの仕様により、6000バイトを超えた分は切り捨てる
                    // （UTF-8前提。mb_strcut があればバイト単位で安全にカット）
                    // $max_bytes = 6000;
                    // if (function_exists('mb_strcut')) {
                    //     if (strlen($content) > $max_bytes) { // strlen はバイト数
                    //         $content = mb_strcut($content, 0, $max_bytes, 'UTF-8');
                    //     }
                    // } else {
                    //     // mbstring が無い場合のフォールバック（マルチバイト途中で切れる可能性あり）
                    //     if (strlen($content) > $max_bytes) {
                    //         $content = substr($content, 0, $max_bytes);
                    //     }
                    // }

                    // 空だった場合は「本文なし」を設定（事実上あり得ないと思うがRSSの仕様により念のためセット）
                    if (trim($content) === '') {
                        $content = '本文なし';
                    }
                    ?>

                    <description><![CDATA[<?php echo $content; ?>]]></description>

                    <?php rss_enclosure(); ?>

                    <?php //記事選出で取得したカテゴリー一覧全てをOR条件で指定して関連記事を3件取得
                    ?>
                    <?php if (!empty($category_f)): ?>
                        <?php
                        $related_post_list = PostModelHelper::get_posts_payload(
                            [
                                'post_type' => 'articles',
                                'posts_per_page' => 3,
                                'tax_query' => [
                                    [
                                        'taxonomy' => 'category',
                                        'field' => 'slug',
                                        'terms' => $category_f,
                                        'operator' => 'IN',
                                    ],
                                ],
                                'post__not_in' => [(int) $p['id']],
                            ],
                        )
                        ?>
                        <!-- ===============================
                             関連記事（同じ category から 5 件）
                        ================================ -->
                        <!-- dmenu -->
                        <?php if (!empty($related_post_list)): ?>
                            <?php foreach ($related_post_list as $r): ?>
                                <smp:relation>
                                    <smp:caption><?= $r['title']; ?></smp:caption>
                                    <smp:url><?= $r['permalink']; ?></smp:url>
                                </smp:relation>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </item>
            <?php
            endforeach;
        else :
            echo 'NODATA';
        endif;

        ?>
    </channel>
</rss>
