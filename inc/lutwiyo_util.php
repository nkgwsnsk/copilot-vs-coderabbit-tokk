<?php
// -------------------------------------
// デバッグ・ユーティリティ
// -------------------------------------
/**
 * lutwiyo_debug()
 *
 * WP_DEBUG または強制デバッグモードが有効な場合に、
 * 引数の内容を整形して出力するデバッグ用関数。
 *
 * - 引数は可変。複数指定可能。
 * - 文字列は echo、それ以外は <pre> 付き print_r で出力。
 * - WP_DEBUG が true の場合または強制モード時のみ動作。
 *
 * 使用例:
 * lutwiyo_debug('Start debug', $post, $_GET);
 *
 * @param mixed ...$args 可変長引数
 * @return void
 */
function lutwiyo_debug(...$args)
{
    // 強制デバッグフラグ（必要に応じてfalseに）
    $is_force_debug = true;

    // WP_DEBUG または強制フラグが有効でなければ出力しない
    if (!$is_force_debug && !(defined('WP_DEBUG') && WP_DEBUG)) {
        return;
    }

    // 可変長引数を順に処理
    foreach ($args as $data) {
        if (is_string($data)) {
            echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . "<br>\n";
        } else {
            echo "<pre style='background:#f6f8fa;padding:10px;border-radius:4px;'>";
            print_r($data);
            echo "</pre>\n";
        }
    }
}

/**
 * ACFテキストフィールドを安全に出力する
 *
 * @param string|null $text ACFで取得した文字列（nullでも安全）
 * @return string 改行を <br> に変換した安全なHTML
 */
function esc_text_with_br(?string $text): string
{
    return $text ? nl2br(esc_html($text)) : '';
}


function lutwiyo_debug_template()
{
    global $template;
    echo '<div style="margin-bottom:100px;">Template : ' . esc_html(basename($template)) . '</div>';
}

/**
 * ACF画像配列から安全に画像URLを取得する
 */
function get_image_url(
    ?array  $image_array,
    ?string $size = null,
    string  $default_image_url = PLACEHOLDER_IMAGE_URL
): string
{
    if (empty($image_array) || !is_array($image_array)) {
        return $default_image_url;
    }

    if ($size === 'original' && !empty($image_array['url'])) {
        return $image_array['url'];
    }

    if (!empty($image_array['sizes'][$size])) {
        return $image_array['sizes'][$size];
    }

    if (!empty($image_array['url'])) {
        return $image_array['url'];
    }

    return $default_image_url;
}


/**
 * JSTを返却する
 * @return string
 * @throws Exception
 */
function get_current_jst()
{
    $timestamp = time(); // 現在のUNIXタイムスタンプ
    $utc_datetime = new DateTime('@' . $timestamp, new DateTimeZone('UTC'));
    $utc_datetime->setTimezone(new DateTimeZone('Asia/Tokyo'));
    $jst_time = $utc_datetime->format('Y-m-d H:i:s');
    return $jst_time;
}


/**
 * @param $date_string
 * @return string
 * 和式の日付を返却する
 */
function format_japanese_date($date_string)
{

    if (empty($date_string)) {
        return '';
    }

    // ACF が返す "d/m/Y h:i a" に合わせてフォーマット指定（例：10/01/2025 12:00 am）
    $dt = DateTime::createFromFormat('d/m/Y h:i a', $date_string);

    if (!$dt) {
        return ''; // パース失敗
    }

    // 日本語曜日
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

    return sprintf(
        '%s年%s月%s日（%s）',
        $dt->format('Y'),
        $dt->format('n'),
        $dt->format('j'),
        $weekdays[(int)$dt->format('w')]
    );
}


/**
 * Body クラスと data-area を取得して返す
 * 以下の条件わけで、bodyに付与するclassと属性を設定
 * 横断TOP、エリアTOP、記事詳細（エリア配下、エリア配下外）、それ以外
 *
 * @return array [$bodyclass, $bodyclass_area]
 */
function lutwiyo_get_body_info()
{
    // inc/global.php で初期化されるグローバル変数を利用
    global $global_is_area_context, $global_queried_object;

    $body_class = '';
    $body_data_area = '';
    // フェーズ2 既存不具合を修正: data-area未設定時でも未定義変数にならないよう初期化
    $data_area_attr = '';

    // 横断TOP
    if ((is_front_page() || is_home()) && !preg_match('#^/(tag|category|area)(/)?$#', $_SERVER['REQUEST_URI'])) {
        $body_class = "top is--scrol is--caution";
        // エリアTOP
        // エリアページの場合、global_is_area_contextがON(1)であるため、slugを含めたclass名とdata-areaを設定する。
        // data-areaはエリア配下ページのみ設定。

    } elseif ($global_is_area_context && !empty($global_queried_object->slug)) {
        //エリアページの場合、global_is_area_contextがON(1)であるため、slugを含めたclass名とdata-areaを設定する。
        //data-areaはエリア配下ページのみ設定。
        $slug = sanitize_html_class($global_queried_object->slug);

        $body_class = "page page-area-{$slug} is--caution";
        $body_data_area = $slug;

        // 記事詳細
    } elseif (is_singular('articles')) {
        //もしエリアありの詳細ページの場合、global_is_area_contextがON(1)であるため、slugを含めたclass名とdata-areaを設定する。
        //data-areaはエリア配下ページのみ設定。

        if ($global_is_area_context && !empty($global_queried_object->slug)) {
            $slug = sanitize_html_class($global_queried_object->slug);

            $body_class = "page pageDetail page-area-{$slug}";
            $body_data_area = $slug;
            //エリア配下ではない場合はエリアの属性を付与しない
        } else {
            $body_class = "page pageDetail is--caution";
        }

        // スタッフ一覧
    } elseif (is_post_type_archive('staff')) {
        $body_class = "page subPage subPageWriter is--caution";
        // マイページ系
    } elseif (is_page(array('mypage', 'mypage-edit'))) {
        // CSS が body.mypage を起点に定義されているため、固定ページでもクラスを合わせる
        $body_class = "mypage is--caution";
        // その他
    } else {
        $body_class = "page subPage is--caution";
    }

    // ★ data-area があれば属性文字列を完成させる
    if (!empty($body_data_area)) {
        $data_area_attr = ' data-area="' . esc_attr($body_data_area) . '"';
    }
    return [$body_class, $body_data_area, $data_area_attr];
}


/**
 * 検索条件（taxonomy）を画面表示用に整形
 *
 * return [
 *   'category' => ['ニュース', 'イベント'],
 *   'area'     => ['宝塚', '高槻・島本'],
 *   'post_tag' => ['B級グルメ', '子連れOK']
 * ]
 */
function lutwiyo_get_search_tax_labels()
{
    global $wp_query;

    $labels = [];

    if (empty($wp_query->tax_query) || empty($wp_query->tax_query->queries)) {
        return $labels;
    }

    foreach ($wp_query->tax_query->queries as $query) {

        if (empty($query['taxonomy']) || empty($query['terms'])) {
            continue;
        }

        $taxonomy = $query['taxonomy'];

        foreach ((array)$query['terms'] as $slug) {

            $term = get_term_by('slug', $slug, $taxonomy);

            if ($term && !is_wp_error($term)) {
                $labels[$taxonomy][$term->slug] = $term->name;
            }
        }
    }

    return $labels;
}
