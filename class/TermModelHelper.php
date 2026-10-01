<?php
/**
 * Class TermModelHelper
 *
 * WordPressのタクソノミータームを扱う共通ユーティリティクラス（拡張版）。
 *
 * 責務:
 * - get_terms() の安全なラッパ
 * - タクソノミー別ACFフィールド・transformersプリセットの適用
 * - ターム配列の整形（payload化）と追加変換（transformers）の一元管理
 * - JSON出力用ユーティリティ
 *
 * 設計方針:
 * - PostModelHelper と対称構造を保ち、「取得 → 整形 → 変換 → JSON化」を明確に分離
 * - タクソミーごとの差異をプリセットとして保持し、自動適用
 * - get_terms_payload() は「get_terms引数 / WP_Term配列 / term_id配列」をすべて受け付ける
 *
 * 使用例:
 * ```php
 * // タクソノミーごとのプリセット登録
 * TermModelHelper::set_taxonomy_config('area', [
 *     'acf_fields' => ['image', 'color', 'is_clickable'],
 *     'transformers' => [
 *         [
 *             'source'   => 'image',
 *             'callback' => fn($v) => is_array($v) ? ($v['url'] ?? null) : null,
 *             'target'   => 'image_url',
 *         ],
 *     ],
 * ]);
 *
 * // 取得（get_terms引数を渡すケース）
 * $areas = TermModelHelper::get_terms_payload('area', ['hide_empty' => false]);
 *
 * // 取得（WP_Term配列を渡すケース）
 * $terms = get_terms(['taxonomy' => 'area', 'hide_empty' => false]);
 * $areas = TermModelHelper::get_terms_payload('area', $terms);
 *
 * // 取得（term_id配列を渡すケース）
 * $ids   = [12, 34, 56];
 * $areas = TermModelHelper::get_terms_payload('area', $ids, ['image']);
 * ```
 *
 * @package WordPress\Utilities
 */
class TermModelHelper
{
    //----------------------------------------------------------------------
    // プリセット管理
    //----------------------------------------------------------------------

    /**
     * タクソノミー別の設定マップ
     *
     * 例:
     * [
     *   'area' => [
     *       'acf_fields'   => ['image', 'color', 'is_clickable'],
     *       'transformers' => [
     *           ['source' => 'image', 'callback' => ..., 'target' => 'image_url']
     *       ],
     *   ],
     *   'genre' => [
     *       'acf_fields'   => ['icon'],
     *       'transformers' => [],
     *   ],
     * ]
     *
     * @var array<string, array<string, array>>
     */
    private static array $taxonomy_config = [];

    /**
     * タクソノミー別プリセットを取得
     *
     * @param string|null $taxonomy タクソノミースラッグ（nullで全体）
     * @return array 対応する設定配列、または全体マップ
     */
    public static function get_taxonomy_config(?string $taxonomy = null): array
    {
        if ($taxonomy === null) {
            return self::$taxonomy_config;
        }
        return self::$taxonomy_config[$taxonomy] ?? [];
    }

    /**
     * タクソノミー別プリセットを設定（上書き）
     *
     * @param string $taxonomy タクソノミースラッグ
     * @param array  $config   ['acf_fields' => [...], 'transformers' => [...]] の形式
     * @return void
     */
    public static function set_taxonomy_config(string $taxonomy, array $config): void
    {
        self::$taxonomy_config[$taxonomy] = [
            'acf_fields'   => array_values(array_unique($config['acf_fields'] ?? [])),
            'transformers' => $config['transformers'] ?? [],
        ];
    }

    /**
     * タクソノミー別プリセットにACFフィールドを追加
     *
     * @param string $taxonomy タクソノミースラッグ
     * @param array  $fields   追加するACFフィールド配列
     * @return void
     */
    public static function add_acf_preset(string $taxonomy, array $fields): void
    {
        $current = self::$taxonomy_config[$taxonomy]['acf_fields'] ?? [];
        self::$taxonomy_config[$taxonomy]['acf_fields'] = array_values(array_unique(array_merge($current, $fields)));
    }

    /**
     * タクソノミー別プリセットにtransformersを追加
     *
     * @param string $taxonomy タクソノミースラッグ
     * @param array  $transformers 追加するtransformers配列
     * @return void
     */
    public static function add_transformers_preset(string $taxonomy, array $transformers): void
    {
        $current = self::$taxonomy_config[$taxonomy]['transformers'] ?? [];
        self::$taxonomy_config[$taxonomy]['transformers'] = array_merge($current, $transformers);
    }

    //----------------------------------------------------------------------
    // ターム取得処理
    //----------------------------------------------------------------------

    /**
     * get_terms()
     *
     * 役割:
     * - get_terms() の薄いラッパとして、純粋な WP_Term[] を返す。
     *
     * @param string $taxonomy 取得するタクソノミースラッグ
     * @param array  $args     get_terms() に渡す引数
     * @return WP_Term[]       取得したターム配列（エラー時は空配列）
     */
    public static function get_terms(string $taxonomy, array $args = []): array
    {
        $default_args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ];
        $query_args = wp_parse_args($args, $default_args);

        $terms = get_terms($query_args);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        // WP_Term[] のみ返す
        return array_values(array_filter($terms, fn($t) => $t instanceof WP_Term));
    }

    /**
     * get_terms_payload()
     *
     * 役割:
     * - タクソノミータームに ACF フィールドを付与し、テンプレートやAPIで扱いやすい配列に整形。
     * - タクソノミー別プリセット（acf_fields / transformers）を自動適用。
     * - ソースは「get_terms引数 / WP_Term配列 / term_id配列」のいずれでも可。
     *
     * @param string               $taxonomy     対象タクソノミースラッグ
     * @param array|WP_Term[]|int[] $source      get_terms引数 or WP_Term配列 or term_id配列
     * @param array                $acf_fields   呼び出し側で追加したいACFフィールド（任意）
     * @param array                $transformers 呼び出し側で追加したい変換ルール（任意）
     * @return array                             ACF情報付き・変換後のターム配列
     */
    public static function get_terms_payload(string $taxonomy, $source = [], array $acf_fields = [], array $transformers = []): array
    {
        // --- 1️⃣ タームを取得（ソース種別を判定） ---
        $terms = [];

        $is_term_array = is_array($source) && !empty($source) && ($source[0] ?? null) instanceof WP_Term;
        $is_id_array   = is_array($source) && !empty($source) && is_numeric($source[0] ?? null);

        // ✅ 新ロジック: ACFなどから渡された「[ [area] => 226 ]」形式を判定して整形
        if (
            is_array($source)
            && !empty($source)
            // フェーズ2 既存不具合を修正: 連想配列入力時の Undefined array key 0 を回避
            && is_array($source[0] ?? null)
            && isset($source[0][$taxonomy])
        ) {
            // [[area] => 226, ...] → [226, 235, 234, ...]
            $source = array_values(array_map(
                fn($row) => (int)($row[$taxonomy] ?? 0),
                $source
            ));
            $is_id_array = true;
        }

        if ($is_term_array) {
            /** @var WP_Term[] $source */
            $terms = $source;
        } elseif ($is_id_array) {
            // term_id配列からWP_Termを解決（taxonomy必須）
            $terms = array_values(array_filter(array_map(
                fn($id) => get_term((int)$id, $taxonomy),
                $source
            ), fn($t) => $t instanceof WP_Term));
        } else {
            // get_terms引数として扱う
            $terms = self::get_terms($taxonomy, is_array($source) ? $source : []);
        }

        if (empty($terms)) {
            return [];
        }

        // --- 2️⃣ プリセットを読み込み・マージ ---
        $preset_config = self::$taxonomy_config[$taxonomy] ?? [];
        $preset_fields = $preset_config['acf_fields']   ?? [];
        $preset_trans  = $preset_config['transformers'] ?? [];

        $acf_fields_merged   = array_values(array_unique(array_merge($preset_fields, $acf_fields)));
        $transformers_merged = array_merge($preset_trans, $transformers);

        // --- 3️⃣ 整形処理 ---
        $payload = [];

        foreach ($terms as $term) {
            $term_id = $term->term_id;
            $term_key = "{$taxonomy}_{$term_id}"; // ACFのタームコンテキストキー

            // 基本データ
            $term_data = [
                'id'          => $term_id,
                'taxonomy'    => $taxonomy,
                'slug'        => $term->slug,
                'name'        => $term->name,
                'description' => $term->description,
                'count'       => (int) $term->count,
            ];

            // ACFフィールドの取得
            $acf_data = [];
            if (function_exists('get_field') && !empty($acf_fields_merged)) {
                foreach ($acf_fields_merged as $field_name) {
                    $acf_data[$field_name] = get_field($field_name, $term_key);
                }
            }

            // 統合＋変換適用
            $merged = array_merge($term_data, $acf_data);
            $merged = self::apply_transform_rules($merged, $transformers_merged);

            $payload[] = $merged;
        }

        return $payload;
    }


    /**
     * apply_term_transforms()
     *
     * 役割:
     * - get_terms_payload() の返り値（配列）に対して、任意の変換ルールを適用する。
     *
     * @param array $payload      get_terms_payload() の返り値
     * @param array $transformers 各タームに対して実行する変換ルールの配列
     * @return array              変換後のターム配列
     */
    public static function apply_term_transforms(array $payload, array $transformers = []): array
    {
        if (empty($transformers)) {
            return $payload;
        }

        $out = [];
        foreach ($payload as $term_data) {
            $out[] = self::apply_transform_rules($term_data, $transformers);
        }
        return $out;
    }

    /**
     * get_terms_payload_json()
     *
     * 役割:
     * - get_terms_payload() の結果を JSON 形式で返す（API / Ajax 用）。
     *
     * @param string               $taxonomy     対象タクソノミースラッグ
     * @param array|WP_Term[]|int[] $source      get_terms引数 or WP_Term配列 or term_id配列
     * @param array                $acf_fields   追加ACFフィールド
     * @param array                $transformers 追加変換ルール
     * @return string                            JSON文字列
     */
    public static function get_terms_payload_json(string $taxonomy, $source = [], array $acf_fields = [], array $transformers = []): string
    {
        $payload = self::get_terms_payload($taxonomy, $source, $acf_fields, $transformers);
        return wp_json_encode($payload);
    }

    //----------------------------------------------------------------------
    // 内部ユーティリティ
    //----------------------------------------------------------------------

    /**
     * apply_transform_rules()
     *
     * 内部ユーティリティ:
     * - 変換ルールを単一のターム配列に適用する。
     * - 非破壊的に変換を行い、新しい配列を返す（ただし配列再代入の形）。
     *
     * ルール仕様:
     * - ['source' => 'キー名', 'callback' => callable|string, 'target' => '格納先キー名']
     * - callback は無名関数/関数名/ ['Class','method'] など is_callable であればOK
     * - callback($source_value, $term_array) のシグネチャを推奨
     *
     * @param array $term_data    ターム情報（ACF含む）
     * @param array $transformers 変換ルールの配列
     * @return array              変換後のターム配列
     */
    private static function apply_transform_rules(array $term_data, array $transformers = []): array
    {
        if (empty($transformers)) {
            return $term_data;
        }

        foreach ($transformers as $rule) {
            if (empty($rule['source']) || empty($rule['target']) || empty($rule['callback'])) {
                continue;
            }

            $source_value = $term_data[$rule['source']] ?? null;
            $callback     = $rule['callback'];

            $result = is_callable($callback)
                ? $callback($source_value, $term_data)
                : (function_exists($callback) ? $callback($source_value) : null);
            
            if (is_array($rule['target'])) {
                // target が配列なら、result も配列であることを想定して展開
                if (is_array($result)) {
                    foreach ($rule['target'] as $key) {
                        if (array_key_exists($key, $result)) {
                            $term_data[$key] = $result[$key];
                        }
                    }
                }
            } else {
                // 従来通り単一ターゲット処理
                $term_data[$rule['target']] = $result;
            }
        }

        return $term_data;
    }
}
