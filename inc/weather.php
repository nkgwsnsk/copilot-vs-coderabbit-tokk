<?php
/**
 * Weather JSON fetcher for OpenWeatherMap
 *
 * できること:
 *  - get_weather_json_api(): 全エリア分の天気を取得して /wp-content/json/weather.json に保存（全成功時のみ上書き）
 *  - エリア代表点は ACF 優先（lat/lon を持つタームのみ）。無ければ静的フォールバック（箕面含む）を使用
 *  - WP-CLI: wp weather update
 *
 * カスタマイズ:
 *  - 保存先を変更: filter 'my_weather_json_path' で JSON ファイルパスを差し替え可能
 */

/** =========================
 *  ACF タームメタの安全取得
 *  ========================= */
if ( ! function_exists('my_get_acf_term_field') ) {
    function my_get_acf_term_field($field, $term, $taxonomy = 'area') {
        // 1) "area_{$term_id}"（ACF推奨）
        $v = function_exists('get_field') ? get_field($field, "{$taxonomy}_{$term->term_id}") : null;
        if ($v !== null && $v !== '') return $v;

        // 2) "term_{$term_id}"（互換用）
        $v = function_exists('get_field') ? get_field($field, "term_{$term->term_id}") : null;
        if ($v !== null && $v !== '') return $v;

        // 3) タームオブジェクト（環境依存で通る場合あり）
        $v = function_exists('get_field') ? get_field($field, $term) : null;
        return ($v !== null && $v !== '') ? $v : null;
    }
}

/** ================================================
 *  taxonomy=area (+ ACF: lat/lon/memo) → 代表点配列
 *  ================================================ */
if ( ! function_exists('my_weather_region_points_from_taxonomy') ) {
    function my_weather_region_points_from_taxonomy() {
        $points = [];

        $terms = get_terms([
            'taxonomy'   => 'area',
            'hide_empty' => false,
        ]);
        if ( is_wp_error($terms) || empty($terms) ) {
            return $points;
        }

        foreach ($terms as $t) {
            $lat  = my_get_acf_term_field('lat',  $t, 'area');
            $lon  = my_get_acf_term_field('lon',  $t, 'area');
            $memo = my_get_acf_term_field('memo', $t, 'area'); // 使わないが保持

            if ($lat === null || $lon === null || $lat === '' || $lon === '') {
                continue; // 座標が無ければスキップ
            }

            $slug = sanitize_title($t->slug ?: $t->name);
            $points[$slug] = [
                'label' => $t->name,
                'lat'   => (float)$lat,
                'lon'   => (float)$lon,
                'memo'  => $memo,
            ];
        }

        return $points;
    }
}

/** =======================================================
 *  フォールバック代表点（ACF未設定時に使用）
 *  ======================================================= */
if ( ! function_exists('my_weather_region_points_static_fallback') ) {
    function my_weather_region_points_static_fallback() {
        return [
            'arashiyama_katsura'   => ['label'=>'嵐山・桂',   'lat'=>35.0090,    'lon'=>135.6670],
            'toyonaka_itami'       => ['label'=>'豊中・伊丹', 'lat'=>34.7850,    'lon'=>135.4380], // 伊丹空港付近
            'rokko_nada'           => ['label'=>'六甲・灘',   'lat'=>34.7200,    'lon'=>135.2400],
            'okamoto_mikage'       => ['label'=>'岡本・御影', 'lat'=>34.7260,    'lon'=>135.2680],
            'amagasaki_nishiyodo'  => ['label'=>'尼崎・西淀川','lat'=>34.7340,   'lon'=>135.4280],
            'minami_namba'         => ['label'=>'ミナミ（なんば・心斎橋・堀江）','lat'=>34.6670,'lon'=>135.5010],
            'kyoto_core'           => ['label'=>'京都（河原町・鳥丸・京都駅）','lat'=>35.0030,'lon'=>135.7630],
            'kansai_wide'          => ['label'=>'関西広域',   'lat'=>34.6940,    'lon'=>135.5020], // 大阪中心
            'kobe_sannomiya'       => ['label'=>'神戸三宮・新開地','lat'=>34.6950,'lon'=>135.1970],
            'nishinomiya_ashiya'   => ['label'=>'西宮・芦屋', 'lat'=>34.7500,    'lon'=>135.3600],
            'otsukuni'             => ['label'=>'乙訓',       'lat'=>34.9260,    'lon'=>135.6960], // 長岡京
            'umeda'                => ['label'=>'梅田',       'lat'=>34.7040,    'lon'=>135.4980],
            'takatsuki_shimamoto'  => ['label'=>'高槻・島本', 'lat'=>34.8480,    'lon'=>135.6170],
            'ibaraki_settsu'       => ['label'=>'茨木・摂津', 'lat'=>34.8160,    'lon'=>135.5680],
            'suita'                => ['label'=>'吹田',       'lat'=>34.7650,    'lon'=>135.5150],
            'juso_awaji'           => ['label'=>'十三・淡路', 'lat'=>34.7180,    'lon'=>135.4860],
            'takarazuka'           => ['label'=>'宝塚',       'lat'=>34.7990,    'lon'=>135.3570],
            'kawanishi_ikeda'      => ['label'=>'川西・池田', 'lat'=>34.8270,    'lon'=>135.4160],
            'minoh'                => ['label'=>'箕面',       'lat'=>34.826942,  'lon'=>135.470439],// ★箕面市役所基準
        ];
    }
}

/** =========================================================
 *  代表点の最終ソース: ACF優先 / 無ければフォールバック
 *  ========================================================= */
if ( ! function_exists('my_weather_region_points') ) {
    function my_weather_region_points() {
        $from_acf = my_weather_region_points_from_taxonomy();
        if (!empty($from_acf)) {
            return $from_acf;
        }
        return my_weather_region_points_static_fallback();
    }
}

/** ==========================================
 *  単一点の現在天気を OpenWeatherMap から取得
 *  ========================================== */
if ( ! function_exists('my_fetch_weather_for_point') ) {
    function my_fetch_weather_for_point( $lat, $lon, $timeout = 10 ) {
        if ( ! defined('OWM_API_KEY') || ! OWM_API_KEY ) {
            return new WP_Error('owm_key_missing', 'OWM_API_KEY が未設定です（wp-config.php を確認）');
        }

        $endpoint = 'https://api.openweathermap.org/data/2.5/weather';
        $url = add_query_arg([
            'lat'   => $lat,
            'lon'   => $lon,
            'appid' => OWM_API_KEY,
            'units' => 'metric', // 摂氏
            'lang'  => 'ja',
        ], $endpoint);

        $res = wp_remote_get( $url, [
            'timeout' => $timeout,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if ( is_wp_error($res) ) {
            return $res;
        }
        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        if ( $code !== 200 || ! $body ) {
            return new WP_Error('owm_bad_response', 'OpenWeatherMapからの応答が不正です', ['code' => $code, 'body' => $body]);
        }

        $json = json_decode($body, true);
        if ( ! is_array($json) ) {
            return new WP_Error('owm_json_error', 'OpenWeatherMapのJSONを解釈できません');
        }


        //アイコン設定
        $weather = $json['weather'][0]['main'];

        switch ($weather) {
            case 'Clear':
                $icon = 'weather--Clear'; // 晴れ
                break;

            case 'Clouds':
                $icon = 'weather--cloud'; // 曇り
                break;

            case 'Rain':
            case 'Drizzle':
            case 'Thunderstorm':
                $icon = 'weather--rain'; // 雨・雷雨系まとめて
                break;

            case 'Snow':
                $icon = 'weather--snow'; // 雪
                break;

            case 'Mist':
            case 'Fog':
            case 'Haze':
            case 'Smoke':
            case 'Dust':
            case 'Sand':
            case 'Ash':
            case 'Squall':
            case 'Tornado':
                $icon = 'weather--cloud'; // 霧・もや・強風系まとめ
                break;

            default:
                $icon = 'weather--suncloud'; // その他（気温計など）
                break;
        }



        // 必要最小限 + 後続利用しやすい形へ整形
        return [
            // 'coord'      => $json['coord'] ?? ['lat' => (float)$lat, 'lon' => (float)$lon],
            'weather'    => [
                'main'        => $json['weather'][0]['main']        ?? null,
                'description' => $json['weather'][0]['description'] ?? null,
                'icon'        => $icon        ?? null,
            ],
            'temp'       => isset($json['main']['temp'])     ? (float)$json['main']['temp']     : null,
            // 'temp_min'   => isset($json['main']['temp_min']) ? (float)$json['main']['temp_min'] : null,
            // 'temp_max'   => isset($json['main']['temp_max']) ? (float)$json['main']['temp_max'] : null,
            // 'humidity'   => $json['main']['humidity']    ?? null,
            // 'pressure'   => $json['main']['pressure']    ?? null,
            // 'wind'       => $json['wind']                ?? null,
            // 'clouds'     => $json['clouds']['all']       ?? null,
            // 'rain_1h'    => $json['rain']['1h']          ?? null,
            // 'snow_1h'    => $json['snow']['1h']          ?? null,
            // 'dt'         => $json['dt']                  ?? null,
            // 'timezone'   => $json['timezone']            ?? null,
            // 'sys'        => $json['sys']                 ?? null,
            'name'       => $json['name']                ?? null,
            'source'     => 'openweathermap_current',
        ];
    }
}

/** ===========================================================
 *  仕様①: 全エリアの天気を取得→/wp-content/json/weather.json 保存
 *  - すべて成功したときのみ上書き（失敗時は既存を保持）
 *  - ディレクトリが無ければ自動作成
 *  - 保存先は filter 'my_weather_json_path' で変更可能
 *  =========================================================== */
if ( ! function_exists('get_weather_json_api') ) {
    function get_weather_json_api() {
        $points = my_weather_region_points();

        $all = [
            'updated_at'     => current_time('timestamp', true), // UTC
            'updated_at_iso' => gmdate('c'),
            'regions'        => [],
        ];

        foreach ( $points as $slug => $info ) {
            $one = my_fetch_weather_for_point($info['lat'], $info['lon']);
            if ( is_wp_error($one) ) {
                return $one; // 一つでも失敗→中断＆上書きしない
            }
            $all['regions'][$slug] = [
                'label' => $info['label'],
                'lat'   => (float)$info['lat'],
                'lon'   => (float)$info['lon'],
                // 'memo'  => isset($info['memo']) ? $info['memo'] : null, // 使わないが保存しておく
                'data'  => $one,
            ];
        }

        // 保存先: /wp-content/json/weather.json（filterで差し替え可）
        $default_path = WP_CONTENT_DIR . '/json/weather.json';
        $file = apply_filters('my_weather_json_path', $default_path);

        $dir = dirname($file);
        if ( ! is_dir($dir) ) {
            wp_mkdir_p($dir);
        }

        $tmp  = $file . '.' . uniqid('tmp_', true);
        $json = wp_json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ( $json === false ) {
            return new WP_Error('json_encode_failed', 'JSONエンコードに失敗しました');
        }

        $bytes = file_put_contents($tmp, $json, LOCK_EX);
        if ( $bytes === false ) {
            @unlink($tmp);
            return new WP_Error('write_failed', '一時ファイルの書き込みに失敗しました');
        }
        @chmod($tmp, 0644);

        if ( ! @rename($tmp, $file) ) {
            @unlink($tmp);
            return new WP_Error('rename_failed', 'weather.json の置き換えに失敗しました');
        }

        return ['saved' => true, 'file' => $file, 'count' => count($all['regions'])];
    }
}

/** =====================
 *  WP-CLI からの実行
 *  ===================== */
if ( defined('WP_CLI') && WP_CLI ) {
    WP_CLI::add_command('weather update', function() {
        $res = get_weather_json_api();
        if ( is_wp_error($res) ) {
            WP_CLI::error( $res->get_error_message() );
        } else {
            WP_CLI::success( sprintf('Saved %d regions to %s', $res['count'], $res['file']) );
        }
    });
}

//天気予報APIのJSON保存用の処理はここまで
//以下、WordPress内でJSONから情報を取得する処理

/**
 * weather.json（/wp-content/json/weather.json）の読込ヘルパ
 * - パスは filter 'my_weather_json_path' で差し替え可能
 * - 同一リクエスト内で1回だけ読み込む（staticキャッシュ）
 */
if ( ! function_exists('my_load_weather_json') ) {
    function my_load_weather_json() {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $default_path = WP_CONTENT_DIR . '/json/weather.json';
        $file = apply_filters('my_weather_json_path', $default_path);

        if ( ! is_readable($file) ) {
            return new WP_Error('weather_json_missing', 'weather.json が見つからないか読めません: ' . $file);
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return new WP_Error('weather_json_read_failed', 'weather.json の読込に失敗しました: ' . $file);
        }

        $json = json_decode($raw, true);
        if ( ! is_array($json) || empty($json['regions']) || ! is_array($json['regions']) ) {
            return new WP_Error('weather_json_invalid', 'weather.json の構造が不正です（regions が無い）');
        }

        $cache = $json;
        return $cache;
    }
}

/**
 * エリア指定（ID/ターム/slug/名前）から、weather.json のリージョンキー（slug）を解決
 * - 基本は area タクソノミーの "slug" をそのまま使用
 * - 追加の手動マッピングが必要な場合は、下の $manual_map に追記
 * - さらに filter 'my_area_to_region_map' でも差し替え可能
 */
if ( ! function_exists('my_resolve_area_region_slug') ) {
    function my_resolve_area_region_slug( $area ) {
        $term = null;
        // 1) まずはターム取得を試みる
        if ( is_numeric($area) ) {
            $term = get_term( (int)$area, 'area' );
        } elseif ( is_object($area) && isset($area->term_id) ) {
            $term = $area;
        } elseif ( is_string($area) && $area !== '' ) {
            // スラッグ一致 → だめなら名前一致（部分一致はしない）
            $by_slug = get_terms([
                'taxonomy'   => 'area',
                'hide_empty' => false,
                'slug'       => sanitize_title($area),
            ]);
            if ( ! is_wp_error($by_slug) && ! empty($by_slug) ) {
                $term = $by_slug[0];
            } else {
                $by_name = get_terms([
                    'taxonomy'   => 'area',
                    'hide_empty' => false,
                    'name'       => $area,
                ]);
                if ( ! is_wp_error($by_name) && ! empty($by_name) ) {
                    $term = $by_name[0];
                }
            }
        }

        // 2) タームが取れた場合は、その slug を第一候補に
        if ( $term && ! is_wp_error($term) ) {
            $slug = $term->slug ? sanitize_title($term->slug) : sanitize_title($term->name);
        } else {
            // タームが取れなければ、そのまま slug 文字列として扱う
            $slug = is_string($area) ? sanitize_title($area) : null;
        }

        // 3) 手動マッピング（必要に応じてここでエリア名→リージョンキーを差し替え）
        //    例: 'minami' エリアを weather.json の 'minami_namba' に寄せたい場合など
        $manual_map = [
            // 'minami' => 'minami_namba',
            // 'sannomiya' => 'kobe_sannomiya',
        ];
        $manual_map = apply_filters('my_area_to_region_map', $manual_map);

        if ( $slug && isset($manual_map[$slug]) && is_string($manual_map[$slug]) ) {
            return $manual_map[$slug];
        }
        return $slug;
    }
}

/**
 * 指定エリアの天気情報を返す共通関数
 * - 引数 $area: タームID / タームオブジェクト / スラッグ / 名前 のいずれか
 * - 返り値: 成功時は weather.json で該当リージョンの配列（label/lat/lon/memo/data）をそのまま返す
 *           見つからない/失敗時は WP_Error
 */
if ( ! function_exists('get_weather') ) {
    function get_weather( $area ) {
        $json = my_load_weather_json();
        if ( is_wp_error($json) ) {
            return $json;
        }

        $slug = my_resolve_area_region_slug( $area );
        if ( ! $slug ) {
            return new WP_Error('weather_area_unresolved', 'エリアをリージョンに解決できませんでした');
        }

        // まずはキー一致（slug一致）
        if ( isset($json['regions'][$slug]) ) {
            return $json['regions'][$slug];
        }

        // 次に label 一致（ターム名＝保存された label が同じケースの救済）
        // ※ 日本語名で一致する可能性を考慮
        $label_candidate = null;
        if ( is_object($area) && isset($area->name) ) {
            $label_candidate = trim((string)$area->name);
        } elseif ( is_numeric($area) ) {
            $t = get_term((int)$area, 'area');
            if ( $t && ! is_wp_error($t) ) {
                $label_candidate = trim((string)$t->name);
            }
        } elseif ( is_string($area) ) {
            $label_candidate = $area;
        }

        if ( $label_candidate ) {
            foreach ( $json['regions'] as $region ) {
                if ( isset($region['label']) && (string)$region['label'] === (string)$label_candidate ) {
                    return $region;
                }
            }
        }

        // 見つからない場合
        return new WP_Error('weather_region_not_found', '該当リージョンが weather.json に見つかりませんでした（slug: ' . esc_html($slug) . '）');
    }
}

