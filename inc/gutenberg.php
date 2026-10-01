<?php
/**
 * スポット情報埋め込み
 * - 記事(post_type=articles)のエディタでのみ使用可
 * - place を1件選択し、以下の2モードで出力を切替
 *    1) テーブル展開（place の Gutenberg 本文→ google_place_id があれば地図 iframe）
 *    2) タグだけ挿入（[spot_info place_id="xxxx"] をそのまま本文に挿入）
 * - タグ（ショートコード）を add_shortcode で処理
 *
 * 事前：wp-config.php に GOOGLE_MAPS_EMBED_API_KEY を定義済み
 */

if (!defined('ABSPATH')) exit;

/* ===============================
 * 共通：place展開HTMLを生成（本文→地図）
 * =============================== */
/**
 * Gutenberg テーブル → .spotInfo 構造に変換して出力
 * 住所行から Google Maps ボタンも自動生成
 */
function mytheme_render_place_expanded_html($place_id)
{
    $place_id = (int)$place_id;
    if (!$place_id) return '';

    $place_post = get_post($place_id);
    if (!$place_post) {
        return '<div>選択したスポットが見つかりませんでした。</div>';
    }

    global $post;
    $prev_post = $post;
    $post = $place_post;
    setup_postdata($post);

    // === 1. Gutenberg table → データ抽出 ===
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $place_post->post_content);
    libxml_clear_errors();

    $rows = $dom->getElementsByTagName('tr');
    if ($rows->length === 0) {
        wp_reset_postdata();
        $post = $prev_post;
        return '<div>スポット情報のテーブルが見つかりません。</div>';
    }

    $fields = [];
    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('td');
        if ($cells->length < 2) continue;
        $key = trim($cells->item(0)->textContent);
        $value_html = '';
        foreach ($cells->item(1)->childNodes as $child) {
            $value_html .= $dom->saveHTML($child);
        }
        $fields[$key] = $value_html;
    }

    // === 2. 地図URL or google_place_id の取得 ===
    $map_iframe_html = '';

    // google_place_id フィールドがある場合
    $google_place_id = get_field('google_place_id', $place_id);
    if (!empty($google_place_id) && defined('GOOGLE_MAPS_EMBED_API_KEY') && GOOGLE_MAPS_EMBED_API_KEY) {
        $src = sprintf(
            'https://www.google.com/maps/embed/v1/place?key=%s&q=%s',
            rawurlencode(GOOGLE_MAPS_EMBED_API_KEY),
            rawurlencode('place_id:' . $google_place_id)
        );
        $map_iframe_html = sprintf(
            '<iframe src="%s" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>',
            esc_url($src)
        );
    }

    // === 3. タイトル・dl構造生成 ===
    $spot_title = esc_html($fields['店名'] ?? $fields['名称'] ?? get_the_title($place_post));
    unset($fields['店名'], $fields['名称']);

    $dl_html = '';
    foreach ($fields as $label => $value_html) {
        // <br>や<p>を保持したまま埋め込む
        $dl_html .= sprintf(
            '<dl class="spotInfo__block"><dt class="spotInfo__block--title">%s</dt><dd class="spotInfo__block--text">%s</dd></dl>',
            esc_html($label),
            $value_html
        );
    }

    // === 4. Google Mapsボタンの生成 ===
    $maps_link = '#';
    if (!empty($fields['住所'])) {
        // 住所中のリンクやURLを抽出
        if (preg_match('/https?:\/\/[^\s"\']+/', $fields['住所'], $match)) {
            $maps_link = esc_url($match[0]);
        }
    }
    $google_map_button = sprintf(
        '<div class="btn btnShaped btnBgColor btn__googleMap" data-shaped="145-38">
            <a class="flex--cc btnLink" href="%s" title="Google maps" target="_blank">
                <p class="fontEn btnTtext">Google maps</p>
                <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
            </a>
        </div>',
        $maps_link
    );

    // === 5. 全体構造組み立て ===
    $html = <<<HTML
<div class="spotInfo">
    <div class="spotInfo__map">
        {$map_iframe_html}
    </div>
    <div class="spotInfo__detail">
        <h3 class="spotInfo__title">{$spot_title}</h3>
        <div class="textColor--textGray fontW--r spotInfo__list">
            {$dl_html}
            {$google_map_button}
        </div>
    </div>
</div>
HTML;

    wp_reset_postdata();
    $post = $prev_post;

    return $html;
}


/* ===============================
 * 1) ブロック登録
 * =============================== */
add_action('acf/init', function () {
    if (!function_exists('acf_register_block_type')) return;

    acf_register_block_type([
        'name' => 'place-embed',
        'title' => 'スポット情報埋め込み',
        'description' => 'スポットで登録した店舗・イベント情報を挿入します',
        'category' => 'widgets',
        'icon' => 'location',
        'keywords' => ['スポット', 'place', '埋め込み', '地図'],
        'supports' => [
            'align' => ['wide', 'full'],
            'anchor' => true,
            'multiple' => true,
        ],
        'post_types' => ['articles'],
        'mode' => 'preview',
        'render_callback' => 'mytheme_render_block_place_embed_switchable',
        'example' => [
            'attributes' => ['mode' => 'preview'],
        ],
    ]);
});

/* ===============================
 * 2) フィールド定義（place選択 + 出力形式）
 * =============================== */
add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) return;

    acf_add_local_field_group([
        'key' => 'group_place_embed_switchable',
        'title' => 'スポット情報埋め込み：設定',
        'fields' => [
            [
                'key' => 'field_place_post_switch',
                'label' => 'スポットを選択',
                'name' => 'place_post',
                'type' => 'post_object',
                'required' => 1,
                'post_type' => ['place'],
                'return_format' => 'id',
                'ui' => 1,
                'ajax' => 1,
                'placeholder' => 'スポット名で検索',
                'multiple' => 0,
                'allow_null' => 0,
            ],
            [
                'key' => 'field_place_output_mode',
                'label' => '出力形式',
                'name' => 'output_mode',
                'type' => 'radio',
                'layout' => 'horizontal',
                'choices' => [
                    'expand' => 'テスト用：HTML展開（本文→地図）',
                    'tag' => 'タグ挿入（[spot_info place_id="xxxx"]）',
                ],
                'default_value' => 'expand',
                'return_format' => 'value',
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/place-embed',
                ],
            ],
        ],
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
    ]);
});

/* ===============================
 * 3) ブロックのレンダリング（切替）
 * =============================== */
function mytheme_render_block_place_embed_switchable($block, $content = '', $is_preview = false, $post_id = 0)
{
    $place_id = (int)get_field('place_post');
    $output_mode = (string)(get_field('output_mode') ?: 'expand');

    $block_id = 'place-embed-' . ($block['id'] ?? uniqid());
    $class = 'acf-block-place-embed';
    if (!empty($block['className'])) $class .= ' ' . $block['className'];
    if (!empty($block['align'])) $class .= ' align' . $block['align'];

    echo '<div id="' . esc_attr($block_id) . '" class="' . esc_attr($class) . '">';

    // 未選択時の簡易プレースホルダー
    if (!$place_id) {
        echo '<div style="padding:12px;border:1px dashed #c3c4c7;border-radius:6px;color:#555;">スポット情報を埋め込み</div>';
        echo '</div>';
        return;
    }

    // タグだけ挿入（ショートコード文字列をそのまま出力）
    if ($output_mode === 'tag') {
        echo '[spot_info place_id="' . esc_attr($place_id) . '"]';
        echo '</div>';
        return;
    }

    // テーブル展開（既存動作）
    echo mytheme_render_place_expanded_html($place_id);

    echo '</div>'; // .acf-block-place-embed
}

/* ===============================
 * 4) ショートコード登録：[spot_info place_id="123"]
 * =============================== */
add_shortcode('spot_info', function ($atts = [], $content = '', $shortcode_tag = '') {
    $atts = shortcode_atts([
        'place_id' => 0,
    ], $atts, $shortcode_tag);

    $place_id = (int)$atts['place_id'];
    if (!$place_id) return '';

    // 共通の描画関数を使って本文→地図を出力
    return mytheme_render_place_expanded_html($place_id);
});


/**
 * 広告ブロック埋め込み v2（ACF Blocks）
 * - 記事(post_type=articles) の Gutenberg で使用
 * - 広告(advertisement) を1件選択して埋め込み
 * - ad_type（tag / banner）で候補を絞り込み（Select2 の AJAX 同送 + pre_get_posts で強制）
 * - 掲載期間(start_date～end_date) 内のみ表示
 * - ad_type=tag はショートコード [adsense id="..."] で挿入（プレビューはショートコード文字を表示）
 */

if (!defined('ABSPATH')) {
    exit;
}

/* =========================================================
 * 0) 期間判定（start/end は空なら無制限）— v2
 * ========================================================= */
if (!function_exists('myad2_is_in_period')) {
    function myad2_is_in_period($start_raw, $end_raw)
    {
        $now = current_time('timestamp');
        $start_ts = $start_raw ? strtotime($start_raw) : null;
        $end_ts = $end_raw ? strtotime($end_raw) : null;
        if ($start_ts !== null && $start_ts !== false && $now < $start_ts) return false;
        if ($end_ts !== null && $end_ts !== false && $now > $end_ts) return false;
        return true;
    }
}

/* =========================================================
 * 1) ブロック登録（広告ブロック埋め込み）— v2
 * ========================================================= */
add_action('acf/init', function () {
    if (!function_exists('acf_register_block_type')) return;

    acf_register_block_type([
        'name' => 'ad-embed',
        'title' => '広告ブロック埋め込み',
        'description' => '「広告(advertisement)」で登録した広告を本文中に挿入します',
        'category' => 'widgets',
        'icon' => 'megaphone',
        'keywords' => ['広告', 'ad', 'banner', 'adsense'],
        'supports' => [
            'align' => ['wide', 'full'],
            'anchor' => true,
            'multiple' => true,
        ],
        'post_types' => ['articles'],
        'mode' => 'preview',
        'render_callback' => 'myad2_render_block_ad_embed',
        'example' => [
            'attributes' => ['mode' => 'preview'],
        ],
    ]);
});

/* =========================================================
 * 2) ブロック用フィールド定義（ad_typeフィルタ + Post Object）— v2
 *    ※ fieldキーは JS / フィルタ側と一致させる
 * ========================================================= */
add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) return;

    acf_add_local_field_group([
        'key' => 'group_ad_embed_v2',
        'title' => '広告ブロック埋め込み：設定',
        'fields' => [
            [
                'key' => 'field_ad_type_filter_for_ad_block_v2',
                'label' => '広告タイプで絞り込み',
                'name' => 'ad_type_filter',
                'type' => 'select',
                'choices' => [
                    '' => '（指定なし）',
                    'tag' => 'タグ広告（AdSense等）',
                    'banner' => 'バナー広告（画像＋リンク）',
                ],
                'default_value' => '',
                'ui' => 1,
                'allow_null' => 1,
                'return_format' => 'value', // 'tag' / 'banner'
            ],
            [
                'key' => 'field_ad_post_embed_v2',
                'label' => '広告を選択',
                'name' => 'ad_post',
                'type' => 'post_object',
                'required' => 1,
                'post_type' => ['advertisement'],
                'return_format' => 'id',
                'ui' => 1,
                'ajax' => 1, // タイトルで動的検索
                'placeholder' => '広告名で検索',
                'multiple' => 0,
                'allow_null' => 0,
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'block',
                    'operator' => '==',
                    'value' => 'acf/ad-embed',
                ],
            ],
        ],
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
    ]);
});

/* =========================================================
 * 3) Gutenberg側：Select2 の AJAX に ad_type_filter を同送（ブロック内スコープ）— v2
 *    ※ NOWDOC (<<<'JS') で $ を含むJSのPHP展開を防止
 * ========================================================= */
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;

    $handle = 'myad2-acf-ad-filter';
    wp_register_script($handle, '', [], null, true);
    wp_enqueue_script($handle);

    $inline = <<<'JS'
    (function($){
      if (typeof acf === 'undefined') return;

      function findSiblingFieldInSameBlock(fieldKey, fromField){
        var $block = fromField.$el.closest('.acf-block-component, .acf-block-fields, .acf-fields');
        var target = null;
        if ($block.length){
          $block.find('[data-key="'+fieldKey+'"]').each(function(){
            var f = acf.getField($(this));
            if (f){ target = f; return false; }
          });
        }
        if (!target){
          target = acf.getField(fieldKey) || null; // fallback
        }
        return target;
      }

      // Post Object の Select2 AJAX に ad_type_filter を同送
      acf.addFilter('select2_ajax_data', function(data, args, $input, field){
        try{
          if (!field) return data;
          if (field.get('type') === 'post_object' && field.get('key') === 'field_ad_post_embed_v2') {
            var adTypeField = findSiblingFieldInSameBlock('field_ad_type_filter_for_ad_block_v2', field);
            var val = adTypeField ? (adTypeField.val() || '') : '';
            data.ad_type_filter = val; // PHP側で $_POST['ad_type_filter'] として参照
          }
        }catch(e){}
        return data;
      });

      // 絞り込み変更時：候補クリア＆再検索
      acf.addAction('change_field/key=field_ad_type_filter_for_ad_block_v2', function(field){
        var po = findSiblingFieldInSameBlock('field_ad_post_embed_v2', field);
        if (po && po.$input) {
          po.$input.val(null).trigger('change');
        }
      });
    })(jQuery);
    JS;

    wp_add_inline_script($handle, $inline);
});

/* =========================================================
 * 4) 候補検索：pre_get_posts で WP_Query に meta_query を強制注入（最優先）— v2
 * ========================================================= */
add_action('pre_get_posts', function ($q) {
    if (!defined('DOING_AJAX') || !DOING_AJAX) return;
    if (empty($_POST['action']) || $_POST['action'] !== 'acf/ajax/query') return;

    $field_key = isset($_POST['field_key']) ? sanitize_text_field(wp_unslash($_POST['field_key'])) : '';
    if ($field_key !== 'field_ad_post_embed_v2') return;

    if (!($q instanceof WP_Query)) return;

    // ベース条件
    $q->set('post_type', ['advertisement']);
    $q->set('posts_per_page', 20);
    $q->set('no_found_rows', true);
    $q->set('update_post_meta_cache', false);
    $q->set('update_post_term_cache', false);

    // ad_type_filter を取得（JS 同送）
    $selected_type = isset($_POST['ad_type_filter']) ? sanitize_text_field(wp_unslash($_POST['ad_type_filter'])) : '';

    // フォールバック（環境によっては空）
    if ($selected_type === '' && isset($_POST['acf']['field_ad_type_filter_for_ad_block_v2'])) {
        $raw = $_POST['acf']['field_ad_type_filter_for_ad_block_v2'];
        $selected_type = is_array($raw) ? (string)reset($raw) : (string)$raw;
        $selected_type = sanitize_text_field(wp_unslash($selected_type));
    }

    if ($selected_type === 'tag' || $selected_type === 'banner') {
        $q->set('meta_query', [
            [
                'key' => 'ad_type',
                'value' => $selected_type, // 'tag' or 'banner'
                'compare' => '=',
            ],
        ]);
    }
}, 7);

/* =========================================================
 * 5) 保険：返却直前に候補を落とす（上書き対策）— v2
 * ========================================================= */
add_filter('acf/fields/post_object/result/key=field_ad_post_embed_v2', function ($text, $post, $field, $post_id) {
    if (!defined('DOING_AJAX') || !DOING_AJAX) return $text;
    if (empty($_POST['action']) || $_POST['action'] !== 'acf/ajax/query') return $text;

    $selected_type = isset($_POST['ad_type_filter']) ? sanitize_text_field(wp_unslash($_POST['ad_type_filter'])) : '';
    if ($selected_type !== 'tag' && $selected_type !== 'banner') return $text;

    $post_ad_type = get_post_meta($post->ID, 'ad_type', true);
    if ($post_ad_type !== $selected_type) {
        return ''; // 空で候補から除外
    }
    return $text;
}, 10, 4);

/* =========================================================
 * 6) 旧フック（args 加工）— v2（環境によっては未使用・残してOK）
 * ========================================================= */
if (!function_exists('myad2_filter_ad_post_object_query')) {
    function myad2_filter_ad_post_object_query($args, $field, $post_id)
    {
        $args['post_type'] = ['advertisement'];
        $args['posts_per_page'] = 20;
        $args['no_found_rows'] = true;
        $args['update_post_meta_cache'] = false;
        $args['update_post_term_cache'] = false;

        $selected_type = isset($_POST['ad_type_filter']) ? sanitize_text_field(wp_unslash($_POST['ad_type_filter'])) : '';

        if ($selected_type === '' && isset($_POST['acf']['field_ad_type_filter_for_ad_block_v2'])) {
            $raw = $_POST['acf']['field_ad_type_filter_for_ad_block_v2'];
            $selected_type = is_array($raw) ? (string)reset($raw) : (string)$raw;
            $selected_type = sanitize_text_field(wp_unslash($selected_type));
        }

        if ($selected_type === 'tag' || $selected_type === 'banner') {
            $args['meta_query'] = [
                [
                    'key' => 'ad_type',
                    'value' => $selected_type,
                    'compare' => '=',
                ]
            ];
        } else {
            unset($args['meta_query']);
        }

        return $args;
    }
}
add_filter('acf/fields/post_object/query/name=ad_post', 'myad2_filter_ad_post_object_query', 10, 3);
add_filter('acf/fields/post_object/query/key=field_ad_post_embed_v2', 'myad2_filter_ad_post_object_query', 10, 3);

/* =========================================================
 * 7) ショートコード：[adsense id="123" test="on|off"] — v2
 *    - id  : advertisement の投稿ID（必須）
 *    - test: on の場合 <ins class="adsbygoogle"> に data-adtest="on" を付与
 * ========================================================= */
add_shortcode('adsense', function ($atts = []) {
    if (function_exists('lutwiyo_can_view_advertisement') && !lutwiyo_can_view_advertisement()) {
        return '';
    }

    $a = shortcode_atts([
        'id' => 0,
        'test' => 'off',
    ], $atts, 'adsense');

    $ad_id = (int)$a['id'];
    if (!$ad_id) return '';

    // 該当広告が ad_type=tag であることを確認
    $ad_type = function_exists('get_field') ? get_field('ad_type', $ad_id) : get_post_meta($ad_id, 'ad_type', true);
    if ($ad_type !== 'tag') return '';

    $code = function_exists('get_field') ? (string)get_field('code', $ad_id) : (string)get_post_meta($ad_id, 'code', true);
    if ($code === '') return '';

    // test=on のときだけ data-adtest を付与（既にあればスキップ）
    if (strtolower($a['test']) === 'on') {
        $code = preg_replace_callback(
            '#<ins\s+[^>]*class=["\']?[^"\']*adsbygoogle[^"\']*["\']?[^>]*>#i',
            function ($m) {
                $tag = $m[0];
                if (stripos($tag, 'data-adtest=') !== false) return $tag;
                $tag = rtrim($tag, '>');
                return $tag . ' data-adtest="on">';
            },
            $code
        );
    }

    // そのまま返す（ローダー重複はAdSense的に許容。気になる場合は最適化可能）
    return $code . '<script>(adsbygoogle=window.adsbygoogle||[]).push({});</script>';
});

/* =========================================================
 * 8) ブロックのレンダリング — v2
 *    - tag: ショートコード方式
 *    - banner: 画像＋リンク
 * ========================================================= */
if (!function_exists('myad2_render_block_ad_embed')) {
    function myad2_render_block_ad_embed($block, $content = '', $is_preview = false, $post_id = 0)
    {
        if (function_exists('lutwiyo_can_view_advertisement') && !lutwiyo_can_view_advertisement()) {
            return;
        }

        $ad_id = (int)get_field('ad_post');

        $block_id = 'ad-embed-' . ($block['id'] ?? uniqid());
        $class = 'acf-block-ad-embed';
        if (!empty($block['className'])) $class .= ' ' . $block['className'];
        if (!empty($block['align'])) $class .= ' align' . $block['align'];

        echo '<div id="' . esc_attr($block_id) . '" class="' . esc_attr($class) . '">';

        if (!$ad_id) {
            echo '<div style="padding:12px;border:1px dashed #c3c4c7;border-radius:6px;color:#555;">広告を選択してください</div></div>';
            return;
        }

        $ad_post = get_post($ad_id);
        if (!$ad_post) {
            if ($is_preview) echo '<div style="padding:12px;border:1px dashed #c3c4c7;border-radius:6px;color:#555;">選択した広告が見つかりませんでした</div>';
            echo '</div>';
            return;
        }

        $ad_type = get_field('ad_type', $ad_id);   // 'tag' or 'banner'
        $start_date = get_field('start_date', $ad_id);
        $end_date = get_field('end_date', $ad_id);

        // 期間外は非表示（プレビュー時のみ注意喚起）
        if (!myad2_is_in_period($start_date, $end_date)) {
            if ($is_preview) {
                echo '<div style="padding:12px;border:1px dashed #c3c4c7;border-radius:6px;color:#555;">現在は非表示期間（掲載期間外）です</div>';
            }
            echo '</div>';
            return;
        }

        echo '<div class="ad-embed">';

        if ($ad_type === 'tag') {
            // 1) エディタ/プレビューではショートコード文字を見せる
            if ($is_preview || is_admin()) {
                echo '<code>[adsense id="' . esc_attr($ad_id) . '"]</code>';
            } else {
                // 2) フロントではショートコードを実行
                echo do_shortcode('[adsense id="' . (int)$ad_id . '"]');
            }

        } elseif ($ad_type === 'banner') {
            $image = get_field('image', $ad_id); // 返り値ID推奨（ACF設定）
            $url = get_field('url', $ad_id);

            if ($image && $url) {
                echo '<a class="ad-embed__link" href="' . esc_url($url) . '" target="_blank" rel="noopener nofollow">';
                if (is_numeric($image)) {
                    echo wp_get_attachment_image((int)$image, 'large', false, ['loading' => 'lazy', 'class' => 'ad-embed__img']);
                } elseif (is_array($image) && !empty($image['ID'])) {
                    echo wp_get_attachment_image((int)$image['ID'], 'large', false, ['loading' => 'lazy', 'class' => 'ad-embed__img']);
                }
                echo '</a>';
            } else {
                if ($is_preview) echo '<div style="padding:8px;border:1px solid #eee;">画像またはリンクURLが未設定です</div>';
            }

        } else {
            if ($is_preview) echo '<div style="padding:8px;border:1px solid #eee;">ad_type が未設定です（tag / banner）</div>';
        }

        echo '</div></div>'; // .ad-embed / .acf-block-ad-embed
    }
}

/* =========================================================
 * 9) 有料境界ブロック（TOKK記事向け）
 *    - articles の Gutenberg に挿入し、本文公開境界を示す
 * ========================================================= */
add_action('acf/init', function () {
    if (!function_exists('acf_register_block_type')) {
        return;
    }

    acf_register_block_type([
        'name' => 'tokk-paywall-gate',
        'title' => '有料境界（paywall-gate）',
        'description' => 'このブロック以降を有料会員向け本文として扱います。',
        'category' => 'widgets',
        'icon' => 'lock',
        'keywords' => ['paywall', '有料', '会員限定'],
        'post_types' => ['articles'],
        'supports' => [
            'align' => false,
            'anchor' => false,
            'multiple' => false,
        ],
        'render_callback' => 'lutwiyo_render_block_tokk_paywall_gate',
    ]);
});

if (!function_exists('lutwiyo_render_block_tokk_paywall_gate')) {
    function lutwiyo_render_block_tokk_paywall_gate($block, $content = '', $is_preview = false, $post_id = 0)
    {
        if ($is_preview || is_admin()) {
            echo '<div style="padding:12px;border:1px dashed #c3c4c7;border-radius:6px;color:#555;background:#fff8f5;">'
                . 'ここから先は有料会員向け本文として表示されます（paywall-gate）'
                . '</div>';
            return;
        }

        // 画面表示時は境界マーカーのみ出力し、テンプレート側で split する。
        echo '<!-- tokk-paywall-gate -->';
    }
}
