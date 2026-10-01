<?php

/**
 * Class PostModelHelper
 *
 * WordPress投稿を扱う共通ユーティリティクラス（拡張版）。
 *
 * 責務:
 * - WP_Query の安全なラッパ
 * - 投稿タイプ別ACFフィールドプリセットの適用
 * - 投稿タイプ別transformers（変換ルール）プリセットの適用
 * - データ変換処理の一元管理
 * - JSON出力用ユーティリティ
 *
 * 設計方針:
 * - TermModelHelper と対称構造を保ち、取得・整形・変換を明確に分離。
 * - 投稿タイプごとの構造差異をプリセットとしてPostModelHelper内に保持。
 * - transformers の呼び出し側ブレを抑え、UI層を簡潔に保つ。
 *
 * 使用例:
 * ```php
 * // 投稿タイプごとのプリセットを登録
 * PostModelHelper::set_post_type_config('articles', [
 *     'acf_fields' => ['article_img', 'reading_time'],
 *     'transformers' => [
 *         [
 *             'source'   => 'article_img',
 *             'callback' => fn($v) => is_array($v) ? $v['url'] : null,
 *             'target'   => 'article_img_url',
 *         ],
 *     ],
 * ]);
 *
 * PostModelHelper::set_post_type_config('ad', [
 *     'acf_fields' => ['type', 'image', 'url'],
 *     'transformers' => [
 *         [
 *             'source'   => 'url',
 *             'callback' => fn($v) => esc_url($v),
 *             'target'   => 'safe_url',
 *         ],
 *     ],
 * ]);
 *
 * // 呼び出し（自動的にプリセットを反映）
 * $articles = PostModelHelper::get_posts_payload(['post_type' => 'articles']);
 * $ads = PostModelHelper::get_posts_payload(['post_type' => 'ad'], ['cta_text']);
 * ```
 *
 * @package WordPress\Utilities
 */
class PostModelHelper
{
    //----------------------------------------------------------------------
    // プリセット管理
    //----------------------------------------------------------------------

    /**
     * 投稿タイプ別の設定マップ
     *
     * 例:
     * [
     *   'articles' => [
     *       'acf_fields' => ['article_img', 'reading_time'],
     *       'transformers' => [
     *           ['source' => 'article_img', 'callback' => ..., 'target' => 'article_img_url']
     *       ]
     *   ],
     *   'ad' => [
     *       'acf_fields' => ['type', 'image', 'url'],
     *       'transformers' => [
     *           ['source' => 'url', 'callback' => ..., 'target' => 'safe_url']
     *       ]
     *   ]
     * ]
     *
     * @var array<string, array<string, array>>
     */
    private static $post_type_config = [];

    /**
     * 投稿タイプ別プリセットを取得
     *
     * @param string|null $post_type 投稿タイプ（nullで全体を取得）
     * @return array 投稿タイプに対応する設定配列、または全体マップ
     */
    public static function get_post_type_config(?string $post_type = null): array
    {
        if ($post_type === null) {
            return self::$post_type_config;
        }
        return self::$post_type_config[$post_type] ?? [];
    }

    /**
     * 投稿タイプ別プリセットを設定（上書き）
     *
     * @param string $post_type 投稿タイプ
     * @param array $config ['acf_fields' => [...], 'transformers' => [...]] の形式
     * @return void
     */
    public static function set_post_type_config(string $post_type, array $config): void
    {
        self::$post_type_config[$post_type] = [
            'acf_fields' => array_values(array_unique($config['acf_fields'] ?? [])),
            'transformers' => $config['transformers'] ?? [],
        ];
    }

    /**
     * 投稿タイプ別プリセットにACFフィールドを追加
     *
     * @param string $post_type 投稿タイプ
     * @param array $fields 追加するACFフィールド配列
     * @return void
     */
    public static function add_acf_preset(string $post_type, array $fields): void
    {
        $current = self::$post_type_config[$post_type]['acf_fields'] ?? [];
        self::$post_type_config[$post_type]['acf_fields'] = array_values(array_unique(array_merge($current, $fields)));
    }

    /**
     * 投稿タイプ別プリセットにtransformersを追加
     *
     * @param string $post_type 投稿タイプ
     * @param array $transformers 追加するtransformers配列
     * @return void
     */
    public static function add_transformers_preset(string $post_type, array $transformers): void
    {
        $current = self::$post_type_config[$post_type]['transformers'] ?? [];
        self::$post_type_config[$post_type]['transformers'] = array_merge($current, $transformers);
    }

    //----------------------------------------------------------------------
    // 投稿データ取得処理
    //----------------------------------------------------------------------

    /**
     * get_posts()
     *
     * WP_Query の薄いラッパとして、純粋な WP_Post[] を返す。
     *
     * @param array $args WP_Query に渡す引数。
     * @return WP_Post[] 投稿オブジェクトの配列。
     */
    public static function get_posts(array $args = [], &$raw_query = null): array
    {
        $default_args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ];
        $query_args = wp_parse_args($args, $default_args);

        $query = new WP_Query($query_args);

        // 呼び出し元にクエリを渡す
        $raw_query = $query;

        return $query->have_posts() ? $query->posts : [];
    }


    /**
     * get_posts_payload()
     *
     * WP_Query または ACF で取得した投稿データを整形し、
     * 投稿タイプ別プリセットを自動的に適用した上で返す。
     *
     * @param array|WP_Post[]|int[] $source WP_Query引数または投稿配列またはID配列。
     * @param array $acf_fields 呼び出し側で追加したいACFフィールド（任意）。
     * @param array $transformers 呼び出し側で追加したい変換ルール（任意）。
     * @return array ACF情報付き・変換後の投稿データ配列。
     */
    public static function get_posts_payload($source = [], array $acf_fields = [], array $transformers = [], &$raw_query = null): array
    {
        // --- 1️⃣ 投稿を取得 ---
        $is_post_array = is_array($source) && isset($source[0]) && $source[0] instanceof WP_Post;
        $is_id_array = is_array($source) && isset($source[0]) && is_numeric($source[0]);

        if ($is_post_array) {
            $posts = $source;
        } elseif ($is_id_array) {
            $posts = array_map('get_post', $source);
        } else {
            // get_posts() を経由して取得（WP_Queryは参照で返す）
            $posts = self::get_posts($source, $raw_query);
        }

        if (empty($posts)) {
            return [];
        }

        if (function_exists('lutwiyo_preload_current_member_favorite_states_for_articles')) {
            $articleIds = [];
            foreach ($posts as $post) {
                if (!($post instanceof WP_Post) || $post->post_type !== 'articles') {
                    continue;
                }

                $articleIds[] = (string) $post->ID;
            }

            if (!empty($articleIds)) {
                lutwiyo_preload_current_member_favorite_states_for_articles($articleIds);
            }
        }

        $payload = [];

        // --- 2️⃣ 各投稿を整形 ---
        foreach ($posts as $post) {
            if (!($post instanceof WP_Post)) {
                continue;
            }

            $post_id = $post->ID;
            $post_type = $post->post_type;

            // 投稿タイプ別プリセットを取得
            $preset_config = self::$post_type_config[$post_type] ?? [];
            $preset_fields = $preset_config['acf_fields'] ?? [];
            $preset_trans = $preset_config['transformers'] ?? [];

            // ACFフィールドマージ
            $acf_fields_merged = array_values(array_unique(array_merge($preset_fields, $acf_fields)));

            // 変換ルールマージ
            $transformers_merged = array_merge($preset_trans, $transformers);

            // --- 🧱 基本情報 ---
            $post_data = [
                'id' => $post_id,
                'slug' => $post->post_name,
                'title' => get_the_title($post_id),
                'excerpt' => get_the_excerpt($post_id),
                'content' => apply_filters('the_content', $post->post_content),
                'permalink' => get_permalink($post_id),
                'date' => get_the_date('c', $post_id),
                'modified' => get_the_modified_date('c', $post_id),
                'post_type' => $post_type,
                'status' => $post->post_status,
            ];

            // --- 🧩 ACFフィールドの取得 ---
            $acf_data = [];
            if (function_exists('get_field')) {
                foreach ($acf_fields_merged as $field_name) {
                    $acf_data[$field_name] = get_field($field_name, $post_id);
                }
            }

            // --- 🔄 統合＋変換適用 ---
            $merged = array_merge($post_data, $acf_data);
            $merged = self::apply_transform_rules($merged, $transformers_merged);

            $payload[] = $merged;
        }

        return $payload;
    }


    /**
     * apply_post_transforms()
     *
     * get_posts_payload() の結果に対して、任意の変換関数を適用。
     *
     * @param array $payload get_posts_payload() の返り値。
     * @param array $transformers 各投稿に対して実行する変換ルールの配列。
     * @return array 変換後の投稿配列。
     */
    public static function apply_post_transforms(array $payload, array $transformers = []): array
    {
        if (empty($transformers)) {
            return $payload;
        }

        $transformed = [];

        foreach ($payload as $post_data) {
            $post_data = self::apply_transform_rules($post_data, $transformers);
            $transformed[] = $post_data;
        }

        return $transformed;
    }

    /**
     * get_posts_payload_json()
     *
     * get_posts_payload() の結果を JSON 形式で返す。
     *
     * @param array|WP_Post[]|int[] $source WP_Query引数または投稿配列またはID配列。
     * @param array $acf_fields 抽出したいACFフィールド名の配列。
     * @param array $transformers 各投稿に対して実行する変換ルールの配列。
     * @return string JSON文字列。
     */
    public static function get_posts_payload_json($source = [], array $acf_fields = [], array $transformers = []): string
    {
        $payload = self::get_posts_payload($source, $acf_fields, $transformers);
        return wp_json_encode($payload);
    }

    //----------------------------------------------------------------------
    // 内部ユーティリティ
    //----------------------------------------------------------------------
    /**
     * format_date_ymd()
     *
     * 内部ユーティリティ:
     * - 与えられた日付（文字列またはUNIXタイム）を「yy.mm.dd」形式に変換する。
     * - transformers内から ['PostModelHelper', 'format_date_ymd'] で呼び出す想定。
     *
     * @param string|int|null $date 日付文字列またはUNIXタイムスタンプ。
     * @return string フォーマット済み日付（例: "24.05.03"）または空文字。
     */
    /**
     * format_date_ymd()
     *
     * 与えられた日付を「yy.mm.dd」形式にフォーマット。
     * - UNIXタイム・MySQL DATETIME・ACF日付文字列（例: "22/10/2025 8:00 am"）など、
     *   よくある入力フォーマットすべてに対応。
     * - strtotime() は内部C実装で軽量。DateTime生成より高速。
     *
     * @param string|int|null $date 日付文字列またはUNIXタイムスタンプ。
     * @return string フォーマット済み日付（例: "25.10.22"）または空文字。
     */
    public static function format_date_ymd($date): string
    {
        if (empty($date)) {
            return '';
        }

        // ✅ ACFなどで "22/10/2025 8:00 am" のような文字列も想定
        // strtotime() はこの形式にも対応するが、空白やスラッシュなどを事前整形
        $normalized = trim((string)$date);

        // 数値ならUNIXタイムとして扱う
        if (is_numeric($normalized)) {
            $timestamp = (int)$normalized;
        } else {
            $timestamp = null;

            // ACF date_time_picker の return_format: "d/m/Y g:i a" を明示パース
            $dt = DateTime::createFromFormat('d/m/Y g:i a', $normalized, wp_timezone());
            if ($dt instanceof DateTime) {
                $timestamp = $dt->getTimestamp();
            }

            // strtotimeが失敗するケースに備えてフォールバック
            if (!$timestamp) {
                $timestamp = strtotime($normalized);
            }

            // strtotimeが失敗した場合に「日/月/年」形式を強制解釈（海外ロケール環境の保険）
            if (!$timestamp && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $normalized, $m)) {
                [$all, $d, $mth, $y] = $m;
                $timestamp = strtotime(sprintf('%04d-%02d-%02d', $y, $mth, $d));
            }
        }

        if (!$timestamp) {
            return '';
        }

        // ✅ yy.mm.dd形式（例: 25.10.22）に統一
        // WP 5.3+ なら wp_date を推奨
        return wp_date('y.m.d', $timestamp);

        // もし古いWPで wp_date が使えない場合は date_i18n でもOK
        // return date_i18n('y.m.d', $timestamp);
    }



    /**
     * apply_transform_rules()
     *
     * 内部ユーティリティ:
     * - 変換ルールを単一の投稿データ配列に適用する。
     *
     * 注意:
     * - privateメソッドとして定義し、テンプレートからは呼び出さない。
     * - 非破壊的に変換を行い、新しい配列を返す。
     *
     * @param array $post_data 投稿情報（ACF含む）。
     * @param array $transformers 変換ルールの配列。
     * @return array 変換後の投稿データ配列。
     */
    private static function apply_transform_rules(array $post_data, array $transformers = []): array
    {
        if (empty($transformers)) {
            return $post_data;
        }

        foreach ($transformers as $rule) {
            if (empty($rule['source']) || empty($rule['target']) || empty($rule['callback'])) {
                continue;
            }

            $source_value = $post_data[$rule['source']] ?? null;
            $callback = $rule['callback'];

            $result = is_callable($callback)
                ? $callback($source_value, $post_data)
                : (function_exists($callback) ? $callback($source_value) : null);

            if (is_array($rule['target'])) {
                // target が配列の場合は、result が配列であることを想定
                if (is_array($result)) {
                    foreach ($rule['target'] as $key) {
                        if (array_key_exists($key, $result)) {
                            $post_data[$key] = $result[$key];
                        }
                    }
                }
            } else {
                // 従来通りの単一ターゲット処理
                $post_data[$rule['target']] = $result;
            }
        }
        return $post_data;
    }
}
