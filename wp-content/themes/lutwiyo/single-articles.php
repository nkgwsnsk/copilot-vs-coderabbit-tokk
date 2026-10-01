<?php get_header(); ?>
<?php
// TODO:広告を表示しないフラグが立っている時は広告を表示しない
// TODO:特典ありラベルの動作を実装する(プレゼントとは別?)
// TODO:地図は新しくするページを選定して新しくする
// TODO:広告は新しくするページを選定して新しくする
// TODO:古いサイトで埋め込んでいる注意文言を新しいサイトに反映する(GutenbergのPostSnippetsで実装しているものをそのまま反映する)
// TODO:文末の編集者情報を確認しておく
// TODO:吹田のおすすめ記事一覧をLOGLYで実装する
// TODO:おすすめエリア記事一覧を実装する(近藤が作ります)
// TODO:サイドバーは詳細デザインがフィックスしたら再度実装する
// TODO:エリアごとのお問い合わせアドレスを設定する( https://docs.google.com/spreadsheets/d/1P54bwmckmpPQZRS2ykHt-Bw7zeOsVacvdGLMaY89gQ0/edit?gid=1845886466#gid=1845886466&range=E113 )
// TODO:管理画面株の補足文章をすべての記事に出力する
// TODO：記事詳細ページ配下のおすすめエリアの動作を確認する

/**
 * Template: single-articles.php
 */


/**
 * 日付ラベルを作成
 * 例: "29/11/2025 12:00 am" → "2025年11月29日（土）"
 */
function format_present_date_label($date_str)
{
    if (empty($date_str)) {
        return '';
    }

    // WordPressのタイムゾーンを利用（なければ Asia/Tokyo）
    if (function_exists('wp_timezone')) {
        $tz = wp_timezone();
    } else {
        $tz = new DateTimeZone('Asia/Tokyo');
    }

    // フォーマット: d/m/Y h:i a （例: 29/11/2025 12:00 am）
    $dt = DateTime::createFromFormat('d/m/Y h:i a', $date_str, $tz);

    if (!$dt) {
        // パースに失敗した場合は空文字にするなど
        return '';
    }

    // 曜日を日本語に変換
    $week_map = array('日', '月', '火', '水', '木', '金', '土');
    $w = (int)$dt->format('w'); // 0 (日) 〜 6 (土)

    // 例: 2025年11月29日（土）
    return $dt->format('Y年n月j日') . '（' . $week_map[$w] . '）';
}

//記事表示用データを $article_data に集約
if (have_posts()) :
    while (have_posts()) : the_post();
        $post_id = get_the_ID();

        // 1) 対象記事のデータ取得
        $articles = PostModelHelper::get_posts_payload(
            [
                'p' => $post_id,
                'post_type' => 'articles',
            ],
            [
                'reading_time',
                'area',
                'category',
                'tag',
                'update_date',
                'staff_list',
                'index_is_open',
                'articles_pr',
                'articles_img',
                'coupon_list',
                'can_show_ad',
            ],
            []
        );

        if (empty($articles)) {
            // lutwiyo_debug('■記事取得エラー', ['post_id' => $post_id]);
            break;
        }

        $a = $articles[0];

        // 汎用ユーティリティ
        $as_bool = function ($v): bool {
            if (is_bool($v)) return $v;
            if (is_numeric($v)) return ((int)$v) === 1;
            if (is_string($v)) return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
            return false;
        };

        $image_to_url = function ($img) {
            if (empty($img)) return '';
            if (is_numeric($img)) return wp_get_attachment_image_url((int)$img, 'full') ?: '';
            if (is_string($img)) return esc_url_raw($img);
            if (is_array($img)) {
                if (!empty($img['url'])) return esc_url_raw($img['url']);
                if (!empty($img['ID'])) return wp_get_attachment_image_url((int)$img['ID'], 'full') ?: '';
            }
            return '';
        };

        $terms_to_payload = function ($ids, string $taxonomy): array {
            $out = [];
            if (empty($ids) || !is_array($ids)) return $out;
            foreach ($ids as $id) {
                $t = get_term((int)$id, $taxonomy);
                if ($t && !is_wp_error($t)) {
                    $out[] = [
                        'id' => (int)$t->term_id,
                        'name' => $t->name,
                        'slug' => $t->slug,
                        'link' => get_term_link($t, $taxonomy),
                    ];
                }
            }
            return $out;
        };

        // 基本情報
        $title = (string)($a['title'] ?? get_the_title($post_id));
        $created_iso = get_the_date('c', $post_id);
        $created_disp = get_the_date(get_option('date_format'), $post_id);

        // 画像
        $top_image_url = '';
        if (!empty($a['image_url'])) {
            $top_image_url = (string)$a['image_url'];
        } elseif (!empty($a['articles_img'])) {
            $top_image_url = $image_to_url($a['articles_img']);
        } else {
            $top_image_url = get_the_post_thumbnail_url($post_id, 'full') ?: '';
        }

        // 読了時間
        $reading_time = isset($a['reading_time']) ? (int)$a['reading_time'] : null;

        // 各タクソノミー
        $area_list = $terms_to_payload($a['area'] ?? [], 'area');
        $category_list = $terms_to_payload($a['category'] ?? [], 'category');
        $tag_list = $terms_to_payload($a['tag'] ?? [], 'post_tag');

        // 更新日
        $update_date_raw = $a['update_date'] ?? '';
        $update_date_iso = '';
        $update_date_disp = '';
        if (!empty($update_date_raw)) {
            $ts = strtotime((string)$update_date_raw);
            if ($ts) {
                $update_date_iso = date_i18n('c', $ts);
                $update_date_disp = date_i18n(get_option('date_format'), $ts);
            }
        }

        // スタッフ情報
        $staff_payload = [];
        if (!empty($a['staff_list']) && is_array($a['staff_list'])) {
            foreach ($a['staff_list'] as $s) {
                if (!($s instanceof WP_Post)) continue;

                $sid = (int)$s->ID;
                $stitle = get_the_title($sid);
                $slink = get_permalink($sid);

                $s_image = get_field('image', $sid);
                $s_text_short = get_field('text_short', $sid);
                $s_text_profile = get_field('text_profile', $sid);
                $s_group = get_field('staff_group', $sid);
                $s_role = get_field('role', $sid);

                $s_image_url = $image_to_url($s_image);
                if ($s_image_url === '') {
                    $s_image_url = get_the_post_thumbnail_url($sid, 'full') ?: '';
                }

                // staff_group
                $staff_group_terms = [];
                if (empty($s_group)) {
                    $terms = wp_get_post_terms($sid, 'staff_group');
                    foreach ($terms as $t) {
                        if ($t && !is_wp_error($t)) {
                            $staff_group_terms[] = [
                                'id' => (int)$t->term_id,
                                'name' => $t->name,
                                'slug' => $t->slug,
                                'link' => get_term_link($t, 'staff_group'),
                            ];
                        }
                    }
                } else {
                    $ids = is_array($s_group) ? $s_group : [$s_group];
                    $staff_group_terms = $terms_to_payload($ids, 'staff_group');
                }

                // role
                $role_display = [];
                if (is_array($s_role)) {
                    foreach ($s_role as $rv) {
                        if (is_numeric($rv)) {
                            $rt = get_term((int)$rv, 'staff_role');
                            $role_display[] = ($rt && !is_wp_error($rt)) ? $rt->name : (string)$rv;
                        } else {
                            $role_display[] = is_scalar($rv) ? (string)$rv : wp_json_encode($rv);
                        }
                    }
                } else {
                    $role_display[] = (string)$s_role;
                }
                $role_display = array_filter(array_map('trim', $role_display));

                $staff_payload[] = [
                    'id' => $sid,
                    'title' => $stitle,
                    'slug' => $s->post_name,
                    'permalink' => $slink,
                    'image_url' => $s_image_url,
                    'text_short' => is_string($s_text_short) ? $s_text_short : '',
                    'text_profile' => is_string($s_text_profile) ? $s_text_profile : '',
                    'staff_group' => $staff_group_terms,
                    'role' => $role_display,
                ];
            }
        }
        // lutwiyo_debug('スタッフ情報', $staff_payload);

        // フラグ類
        $index_is_open = $as_bool($a['index_is_open'] ?? false);
        $articles_pr = $as_bool($a['articles_pr'] ?? false);

        // クーポン情報取得処理ここから
        $coupon_list = $a['coupon_list'] ?? [];
        // 1) 取り出したい ID の整形（重複除去・数値化）
        $wanted_ids = [];
        if (!empty($coupon_list) && is_array($coupon_list)) {
            foreach ($coupon_list as $row) {
                if (isset($row['id']) && is_numeric($row['id'])) {
                    $wanted_ids[] = (int)$row['id'];
                }
            }
            $wanted_ids = array_values(array_unique($wanted_ids));
        }

        // 2) JSON の読み込み
        $coupon_details_list = []; // ← 最終出力
        if (!empty($wanted_ids)) {

            $json_path = WP_CONTENT_DIR . '/json/coupon.json';
            if (is_readable($json_path)) {
                $raw = file_get_contents($json_path);
                $json = json_decode($raw, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    $records = isset($json['data']) && is_array($json['data']) ? $json['data'] : [];

                    // サイトのタイムゾーンで判定
                    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Asia/Tokyo');
                    $now = new DateTimeImmutable('now', $tz);

                    // 3) 有効期間内のものだけを ID→データにマップ
                    $active_by_id = [];
                    foreach ($records as $rec) {
                        $id = isset($rec['id']) ? (int)$rec['id'] : 0;
                        if ($id === 0) {
                            continue;
                        }
                        if (!in_array($id, $wanted_ids, true)) {
                            continue;
                        }

                        $start_s = (string)($rec['start_date'] ?? '');
                        $end_s = (string)($rec['end_date'] ?? '');
                        if ($start_s === '' || $end_s === '') {
                            continue;
                        }

                        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start_s, $tz) ?: null;
                        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end_s, $tz) ?: null;
                        if (!$start || !$end) {
                            continue;
                        }

                        // 期間内のみ
                        if ($now >= $start && $now <= $end) {
                            $active_by_id[$id] = [
                                'id' => $id,
                                'name' => (string)($rec['name'] ?? ''),
                                'detail' => (string)($rec['detail'] ?? ''),
                                'image_url' => (string)($rec['image_url'] ?? ''),
                                'url' => (string)($rec['url'] ?? ''),
                            ];
                        }
                    }

                    // 4) $coupon_list で指定された順番で配列へ積む
                    foreach ($wanted_ids as $id) {
                        if (isset($active_by_id[$id])) {
                            $coupon_details_list[] = $active_by_id[$id];
                        }
                    }
                }
            }
        }
        // lutwiyo_debug('クーポン情報', $coupon_details_list);
        // クーポン情報取得処理ここまで

        // 広告ブロック表示制御（有料会員は広告非表示）。
        $show_ad = $a['can_show_ad'] ?? "";
        if (function_exists('lutwiyo_can_view_advertisement') && !lutwiyo_can_view_advertisement()) {
            $show_ad = false;
        }

        // 読了時間（秒）→ 表示用ラベルに変換
        $rt_sec = isset($a['reading_time']) ? (int)$a['reading_time'] : 0;
        $reading_time_label = '';
        if ($rt_sec > 0) {
            if ($rt_sec < 60) {
                $reading_time_label = $rt_sec . 'sec';   // 例: 53sec
            } else {
                $reading_time_label = ceil($rt_sec / 60) . 'min'; // 例: 61→2分, 119→2分
            }
        }

        // 表示用の日付 'yy.mm.dd' へ変換
        $to_yy_mm_dd = function ($v): string {
            // 配列なら iso / label を優先順で拾う
            if (is_array($v)) {
                $v = $v['iso'] ?? ($v['label'] ?? '');
            }
            $s = trim((string)$v);
            if ($s === '') return '';

            // 数値=UNIX, 文字列=strtotime、日/月/年の保険もあり
            if (is_numeric($s)) {
                $ts = (int)$s;
            } else {
                $ts = strtotime($s);
                if (!$ts && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) {
                    $ts = strtotime(sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]));
                }
            }
            return $ts ? date_i18n('y.m.d', $ts) : '';
        };


        $normalize = function (array $s): array {
            // 名前
            $name = trim((string)($s['title'] ?? ''));

            // 役職（文字列/配列/ID配列すべて対応）
            $role_raw = $s['role'] ?? '';
            if (is_array($role_raw)) {
                $parts = [];
                foreach ($role_raw as $r) {
                    if (is_numeric($r)) {
                        $t = get_term((int)$r, 'staff_role');
                        $parts[] = ($t && !is_wp_error($t)) ? $t->name : (string)$r;
                    } else {
                        $parts[] = is_scalar($r) ? (string)$r : '';
                    }
                }
                $role = implode(' / ', array_filter(array_map('trim', $parts)));
            } else {
                $role = trim((string)$role_raw);
            }

            // 画像URL解決（image_url → image['url'] → 画像ID → フォールバック）
            $img = '';
            if (!empty($s['image_url'])) {
                $img = (string)$s['image_url'];
            } elseif (!empty($s['image']) && is_array($s['image'])) {
                $img = (string)($s['image']['url'] ?? '');
            } elseif (!empty($s['image']) && is_numeric($s['image'])) {
                $img = wp_get_attachment_image_url((int)$s['image'], 'thumbnail') ?: '';
            }
            if ($img === '') {
                $img = get_stylesheet_directory_uri() . '/assets/img/sample/people--1.jpg';
            }

            // プロフィール
            $profile_short = trim((string)($s['text_short'] ?? ''));
            $profile_full = trim((string)($s['text_profile'] ?? ''));

            // 個別ページへのリンク
            $id = isset($s['id']) && is_numeric($s['id']) ? (int)$s['id'] : 0;
            $link = !empty($s['permalink']) ? (string)$s['permalink'] : ($id ? (get_permalink($id) ?: '') : '');

            // ▼ 追加：グループ（staff_group）整形
            $group_list = [];
            $group_names = [];

            if (!empty($s['staff_group']) && is_array($s['staff_group'])) {
                foreach ($s['staff_group'] as $g) {
                    // 形式A: ['id'=>..,'name'=>..,'slug'=>..,'link'=>..]
                    if (is_array($g) && isset($g['id'])) {
                        $gid = (int)($g['id'] ?? 0);
                        $gname = (string)($g['name'] ?? '');
                        $gslug = (string)($g['slug'] ?? '');
                        $glink = (string)($g['link'] ?? '');

                        // name無い場合はIDから補完
                        if ($gname === '' && $gid) {
                            $t = get_term($gid, 'staff_group');
                            if ($t && !is_wp_error($t)) {
                                $gname = $t->name;
                                $gslug = $gslug ?: $t->slug;
                                $glink = $glink ?: get_term_link($t, 'staff_group');
                            }
                        }

                        if ($gname !== '') {
                            $group_names[] = $gname;
                        }
                        $group_list[] = [
                            'id' => $gid,
                            'name' => $gname,
                            'slug' => $gslug,
                            'link' => $glink,
                        ];
                    } // 形式B: 数値IDのみ
                    elseif (is_numeric($g)) {
                        $t = get_term((int)$g, 'staff_group');
                        if ($t && !is_wp_error($t)) {
                            $group_names[] = $t->name;
                            $group_list[] = [
                                'id' => (int)$t->term_id,
                                'name' => $t->name,
                                'slug' => $t->slug,
                                'link' => get_term_link($t, 'staff_group'),
                            ];
                        }
                    } // 形式C: 文字列（名前）だけ
                    elseif (is_string($g) && $g !== '') {
                        $group_names[] = $g;
                        $group_list[] = [
                            'id' => 0,
                            'name' => $g,
                            'slug' => '',
                            'link' => '',
                        ];
                    }
                }
            }
            // 表示用（roleと同様の区切りで）
            $group = implode(' / ', array_filter(array_map('trim', $group_names)));

            return [
                'id' => $id,
                'name' => $name,
                'role' => $role,
                'img' => $img,
                'profile_short' => $profile_short,
                'profile_full' => $profile_full,
                'link' => $link,
                'group' => $group,       // 表示用 "tokk編集部 / 〇〇班"
                'group_list' => $group_list,  // 各グループの配列（id/name/slug/link）
            ];
        };

        /*
         * スタッフ情報を整形し、2箇所用にそれぞれ最適化した配列を作る
         */
        $staff_ui_compact = []; // スタッフ情報1（上部・コンパクト）
        $staff_ui_full = []; // スタッフ情報2（下部・フル）

        foreach ($staff_payload as $s) {
            if (!is_array($s)) continue;
            $n = $normalize($s);

            // コンパクト用：短文 > なければ長文
            $staff_ui_compact[] = [
                'img' => $n['img'],
                'role' => $n['role'],
                'name' => $n['name'],
                'profile' => ($n['profile_short'] !== '' ? $n['profile_short'] : $n['profile_full']),
                'link' => $n['link'],
            ];

            // フル用：長文（なければ短文）
            $staff_ui_full[] = [
                'img' => $n['img'],
                'role' => $n['role'],
                'name' => $n['name'],
                'profile' => ($n['profile_full'] !== '' ? $n['profile_full'] : $n['profile_short']),
                'link' => $n['link'],
                'group' => $n['group'],
            ];
        }

        // $article_data に “2箇所専用” として格納（元の staff_list はもう不要なら入れない）
        $article_data['staff_ui'] = [
            'compact' => $staff_ui_compact,
            'full' => $staff_ui_full,
        ];

        // データまとめ
        $article_data = [
            'title' => $title,
            'reading_time' => $reading_time_label,
            'area' => $area_list,
            'category' => $category_list,
            'tag' => $tag_list,
            'created_at' => [
                'iso' => $created_iso,
                'label' => $created_disp,
                'view' => $to_yy_mm_dd($created_iso)
            ],
            'update_date' => [
                'iso' => $update_date_iso,
                'label' => $update_date_disp,
                'view' => $to_yy_mm_dd($update_date_iso)
            ],
            //'staff_list'    => $staff_payload,
            'staff_ui' => [
                'compact' => $staff_ui_compact,
                'full' => $staff_ui_full,
            ],
            'index_is_open' => $index_is_open,
            'articles_pr' => $articles_pr,
            'articles_img' => $top_image_url,
            'coupon_list' => $coupon_list,
        ];

        // デバッグ出力
        // echo "<br><br><br><br><br><br>";
        // lutwiyo_debug('記事表示用データ', $article_data);

        // 本文は直接出力
        //the_content();

    endwhile;
else :
    status_header(404);
    // lutwiyo_debug('■404', '記事が見つかりません');
endif;
?>
<?php
global $wp_query;
$article_info_list = PostModelHelper::get_posts_payload(
    $wp_query->posts,
    [
        'index_is_open',
        'supplementText',
        'station'
    ],
    [
                    // エリア情報を設定する
        [
            'source' => 'station',
            'callback' => function ($station_terms) {
                if (empty($station_terms) || !is_array($station_terms)) {
                    return [];
                }
                $station_info = [];
                foreach ($station_terms as $station_id) {
                    $term = get_term($station_id);
                    $station_info[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'link' => get_term_link($term),
                    ];
                }
                return $station_info;
            },
            'target' => 'station_info'
        ],
]
);
$article_info = $article_info_list[0] ?? [];
// lutwiyo_debug($article_info);
// 'acf_fields' => ['articles_img', 'area', 'category', 'tag', 'staff_list', 'reading_time', 'update_date'],
extract(PostViewHelper::prepare_articles($article_info), EXTR_OVERWRITE);
//return [
//    'title' => $title,
//    'link' => $link,
//    'image_url' => $image_url,
//    'reading_time_label' => $reading_time_label,
//    'display_date_label' => $display_date_label,
//    'is_pinned' => $is_pinned,
//    'is_new' => $is_new,
//    'is_pinned_class' => $is_pinned_class,
//    'is_new_class' => $is_new_class,
//    'is_pinned_result' => $is_pinned_result,
//];
$date_label = $article_info['date_label'] ?? '';
$update_date_label = $article_info['update_date_label'] ?? '';
$articleIdForReaction = (string) ($article_info['id'] ?? '');
$commentViewAcl = function_exists('lutwiyo_normalize_comment_view_acl')
    ? lutwiyo_normalize_comment_view_acl(function_exists('get_field') ? get_field('comment_view_acl', $post_id) : get_post_meta($post_id, 'comment_view_acl', true))
    : 'public';
$commentPostAcl = function_exists('lutwiyo_normalize_comment_post_acl')
    ? lutwiyo_normalize_comment_post_acl(function_exists('get_field') ? get_field('comment_post_acl', $post_id) : get_post_meta($post_id, 'comment_post_acl', true))
    : 'enabled';
$articleViewerContextResponse = $articleIdForReaction !== '' && function_exists('lutwiyo_get_or_fetch_article_viewer_context_response')
    ? lutwiyo_get_or_fetch_article_viewer_context_response('tokk_article', $articleIdForReaction, [
        'comment_view_acl' => $commentViewAcl,
        'comment_post_acl' => $commentPostAcl,
    ])
    : null;
$articleViewerViewer = function_exists('lutwiyo_extract_article_viewer_context_viewer')
    ? lutwiyo_extract_article_viewer_context_viewer($articleViewerContextResponse)
    : null;
$articleViewerComment = function_exists('lutwiyo_extract_article_viewer_context_comment')
    ? lutwiyo_extract_article_viewer_context_comment($articleViewerContextResponse)
    : null;
$articleViewerFavoriteState = function_exists('lutwiyo_extract_article_viewer_context_favorite_state')
    ? lutwiyo_extract_article_viewer_context_favorite_state($articleViewerContextResponse)
    : null;
$articleViewerLikeState = function_exists('lutwiyo_extract_article_viewer_context_like_state')
    ? lutwiyo_extract_article_viewer_context_like_state($articleViewerContextResponse)
    : null;
$isFavorited = is_bool($articleViewerFavoriteState)
    ? $articleViewerFavoriteState
    : ($articleIdForReaction !== ''
        ? lutwiyo_get_current_member_favorite_state('tokk_article', $articleIdForReaction)
        : false);
$isLiked = is_bool($articleViewerLikeState)
    ? $articleViewerLikeState
    : ($articleIdForReaction !== ''
        ? lutwiyo_get_current_member_like_state('tokk_article', $articleIdForReaction)
        : false);

$favoriteActiveClass = $isFavorited ? 'active' : '';
$favoriteAriaPressed = $isFavorited ? 'true' : 'false';
$likeActiveClass = $isLiked ? 'active' : '';
$likeAriaPressed = $isLiked ? 'true' : 'false';
$memberAccessPlan = function_exists('lutwiyo_get_article_member_access_plan')
    ? lutwiyo_get_article_member_access_plan((int) $post_id)
    : 'public';
$memberAccessContext = function_exists('lutwiyo_get_member_access_context')
    ? lutwiyo_get_member_access_context()
    : ['is_logged_in' => false, 'plan' => 'guest', 'uid' => ''];
$canViewPaidArticle = function_exists('lutwiyo_can_view_paid_limited_article')
    ? lutwiyo_can_view_paid_limited_article($memberAccessContext, (int) $post_id)
    : true;
$shouldApplyPaidGate = $memberAccessPlan === 'paid_member' && !$canViewPaidArticle;
$paidGateViewerLevel = 'guest';
if (($memberAccessContext['is_logged_in'] ?? false) === true) {
    $currentMemberPlan = (string) ($memberAccessContext['plan'] ?? '');
    if ($currentMemberPlan === 'paid') {
        $paidGateViewerLevel = 'paid';
    } elseif ($currentMemberPlan === 'free') {
        $paidGateViewerLevel = 'free';
    } else {
        $paidGateViewerLevel = 'unknown';
    }
}

$commentCurrentMemberUid = is_array($articleViewerViewer)
    ? trim((string) ($articleViewerViewer['uid'] ?? ''))
    : lutwiyo_get_current_member_uid_from_bridge();
$commentCurrentMemberDisplayName = is_array($articleViewerViewer)
    ? trim((string) ($articleViewerViewer['nickname'] ?? ''))
    : '';
$commentCurrentMemberProfileImageUrl = is_array($articleViewerViewer)
    ? trim((string) ($articleViewerViewer['profile_image_url'] ?? ''))
    : '';
if ($commentCurrentMemberDisplayName === '' && function_exists('lutwiyo_get_current_member_nickname_from_member_info_response')) {
    $commentCurrentMemberDisplayName = lutwiyo_get_current_member_nickname_from_member_info_response();
}
if ($commentCurrentMemberDisplayName === '') {
    $commentCurrentMemberDisplayName = '会員';
}
$commentViewerPlan = (string) ($memberAccessContext['plan'] ?? 'guest');
$isCommentLoggedIn = ($memberAccessContext['is_logged_in'] ?? false) === true
    && in_array($commentViewerPlan, ['free', 'paid'], true);
$isCommentFreeViewer = $isCommentLoggedIn && $commentViewerPlan === 'free';
$showCommentBlock = $commentViewAcl !== 'private';
$canShowCommentList = function_exists('lutwiyo_can_member_context_view_comments')
    ? lutwiyo_can_member_context_view_comments($memberAccessContext, $commentViewAcl)
    : ($showCommentBlock && $isCommentLoggedIn);
$requiresCommentLoginForView = $showCommentBlock && !$canShowCommentList;
if (is_array($articleViewerComment)) {
    $canShowCommentList = $showCommentBlock && (($articleViewerComment['can_view'] ?? false) === true);
    $requiresCommentLoginForView = $showCommentBlock && !$canShowCommentList;
}
$isCommentPostingEnabled = $commentPostAcl === 'enabled';
$canSubmitComment = function_exists('lutwiyo_can_member_context_post_comments')
    ? lutwiyo_can_member_context_post_comments($memberAccessContext, $commentViewAcl, $commentPostAcl)
    : ($canShowCommentList && $isCommentPostingEnabled && $commentViewerPlan === 'paid');
if (is_array($articleViewerComment)) {
    $canSubmitComment = $showCommentBlock && (($articleViewerComment['can_post'] ?? false) === true);
}
$commentFormDisabled = !$canSubmitComment;
$commentLoginUrl = home_url('/login/');
$commentRegisterUrl = home_url('/regist/');
$commentUpgradeUrl = home_url('/paid-exp/');
$commentPostingStateMessage = $canSubmitComment
    ? ''
    : (function_exists('lutwiyo_get_comment_post_unavailable_message')
        ? lutwiyo_get_comment_post_unavailable_message($memberAccessContext, $commentPostAcl)
        : (!$isCommentPostingEnabled ? '現在コメント投稿は停止中です。' : '会員にログインするとコメント投稿できます。'));
$commentListResponse = [
    'ok' => false,
    'status' => 0,
    'code' => '',
    'message' => '',
    'total' => 0,
    'items' => [],
];
if ($canShowCommentList && $articleIdForReaction !== '' && function_exists('lutwiyo_get_member_comment_items')) {
    $commentListResponse = lutwiyo_get_member_comment_items('tokk_article', $articleIdForReaction, 1, 20, 'posted_at:desc');
}
$commentItems = is_array($commentListResponse['items'] ?? null) ? $commentListResponse['items'] : [];
$commentTotalCount = max((int) ($commentListResponse['total'] ?? 0), count($commentItems));
$commentFetchFailed = $canShowCommentList && !$commentListResponse['ok'];
$commentListNoticeMessage = '';
$commentListNoticeModifierClass = 'commentBlock__notice--info';
if (!$requiresCommentLoginForView) {
    if (!$isCommentPostingEnabled) {
        $commentListNoticeMessage = 'この記事ではコメントが停止されています。';
    } elseif ($commentFetchFailed) {
        $commentListNoticeMessage = 'コメントを読み込めませんでした。時間をおいて再度お試しください。';
    } elseif ($commentTotalCount === 0) {
        $commentListNoticeMessage = '記事にコメントを追加して盛り上がりましょう！';
        $commentListNoticeModifierClass = 'commentBlock__notice--success';
    }
}
$formatCommentPostedAtLabel = static function ($rawPostedAt): string {
    $normalized = trim((string) $rawPostedAt);
    if ($normalized === '') {
        return '';
    }

    try {
        $datetime = new DateTime($normalized);
    } catch (Exception $exception) {
        return '';
    }

    return $datetime->format('Y.m.d H:i');
};
?>

<?php //ここから記事詳細 ?>
<div class="hoverCover"></div>
<!-- ==================================================================== ↓ wrapper ↓ -->
<main class="main wrapper" role="main">
    <!-- ------------------------------------- ↓ kv ↓　-->
    <?php
    $area_info_source_slug = $article_data['area'][0]['slug'] ?? null;

    //広告処理で$area_info_listを使うため初期化
    $area_info_list = [];
    if (!empty($area_info_source_slug)) {
        $area_info_list = TermModelHelper::get_terms_payload(
            'area',
            [
                'slug' => $area_info_source_slug,
            ],
            // 抽出したいACFフィールド名の配列
            ['recommended_area_and_articles_list'],
            // 各投稿に対して追加実行する関数セット
            []
        );
        $area_info = $area_info_list[0] ?? null;
    } ?>

    <?php
    if ($area_info) {
        get_template_part(
            'partials/modules/kv',
            null,
            ['area_info' => $area_info]
        );
    }
    ?>
    <!-- ------------------------------------- ↓ areaNav ↓　-->
    <?php
    get_template_part(
        'partials/modules/area',
        'nav',
        [
            'area_info' => $area_info
        ]
    );
    ?>
    <!-- ------------------------------------- ↓ 記事詳細 ↓　-->
    <article class="pdArticle">
        <!-- ------------------------------------- ↓ pdSection ↓　-->
        <section class="pdSection" data-boxBgColor="bodySub">
            <div class="pdSection__grid">
                <!-- pdContents__leader -->
                <div class="pdContents__leader">
                    <h1 class="fs--32 blockTitle"><?= esc_html($title); ?></h1>
                    <div class="blockBox__infoSub--list">
                        <div class="blockBox__infoSub--target">
                            <div class="icon iconTime">
                                <div class="mask iconInner"></div>
                            </div>
                            <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer"><?= esc_html($reading_time_label); ?></p>
                        </div>

                        <?php foreach ($article_info['area_info'] as $area) :
                            $area_link = home_url('/area/') . $area['slug'] . '/';
                            $area_name = $area['name'];
                            ?>
                            <a class="textHoverWrapper blockBox__infoSub--target"
                               href="<?php echo esc_url($area_link); ?>"
                               title="<?php echo esc_attr($area_name); ?>">
                                <div class="icon iconMap">
                                    <div class="mask iconInner"></div>
                                </div>
                                <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                    <?php echo esc_html($area_name); ?>
                                </p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    // --------------------------------------
                    // 最寄駅
                    // --------------------------------------
                    ?>
                    <?php /**
                            * 2026.01.28 本番リリースまでにOKが出なかったため一時的にコメントアウト
                            */
                    /*
                    <div class="blockBox__infoSub--list nearestStation">
                        <div class="blockBox__infoSub--target nearestStation__title">
                            <div class="icon iconStation"><div class="mask iconInner"></div></div>
                            <p class="fontW--r blockBox__infoSub--text textColor--footer">最寄り駅</p>
                        </div>
                        <div class="textColor--footer fontW--r nearestStation__list">
                            <?php foreach ($article_info['station_info'] as $station): ?>
                                <p class="nearestStation--p"><?= esc_html($station['name']) ; ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div> */ ?>
                    <ul class="hashList">
                        <?php foreach ($article_info['tag_info'] as $tag) :
                            $tag_name = $tag['name'];
                            $tag_link = $tag['link'];
                            ?>
                            <li class="hashTarget">
                                <a class="textHoverWrapper hashLink"
                                   href="<?= esc_url($tag_link); ?>"
                                   title="<?= esc_attr($tag_name); ?>">
                                    <p class="textHover__target hashTarget--p"><?php echo esc_html($tag_name); ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="fontEn date"><p
                                class="textColor--textGray date--p"><?= esc_html($date_label); ?></p>
                    </div>
                    <?php if (!empty($update_date_label)): ?>
                        <div class="fontEn date date--reset"><p
                                    class="textColor--textGray date--p"><?= esc_html($update_date_label); ?></p>
                        </div>
                    <?php endif; ?>
                    <!-- プロフィール 簡略版 -->
                    <? // TODO: スタッフ情報整備 ?>
                    <?php if (!empty($article_data['staff_ui']['compact'])): ?>
                        <?php foreach ($article_data['staff_ui']['compact'] as $st): ?>
                            <div class="postPerson">
                                <div class="iconPostPerson">
                                    <div class="iconPostPerson__thum">
                                        <img class="thumImg"
                                             src="<?php echo esc_url($st['img']); ?>"
                                             alt="<?php echo esc_attr($st['name'] ?: 'スタッフ'); ?>"
                                             width="40" height="40" loading="lazy">
                                    </div>
                                </div>
                                <div class="postPerson__detail">
                                    <?php if ($st['role'] !== ''): ?>
                                        <p class="fontW--r blockBox__infoSub--personTitle"><?php echo esc_html($st['role']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($st['name'] !== ''): ?>
                                        <p class="fontW--r blockBox__infoSub--text"><?php echo esc_html($st['name']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($st['profile'] !== ''): ?>
                                        <p class="fontW--r textColor--textGray blockBox__infoSub--profile">
                                            <?php echo esc_html($st['profile']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <!-- 特典あり -->
                <div class="pdSpecial">
                    <div class="pdSpecial__thum"><img
                                src="<?= esc_url($article_info['image_url']); ?>"
                                alt="<?= esc_attr($title); ?>"
                                width="1680" height="1140" loading="lazy"></div>
                    <div class="favBtn js--favBtn <?= esc_attr($favoriteActiveClass); ?>"
                         role="button"
                         data-id="<?= esc_attr($article_info['id']); ?>"
                         data-resource-type="tokk_article"
                         data-resource-id="<?= esc_attr((string) $article_info['id']); ?>"
                         aria-pressed="<?= esc_attr($favoriteAriaPressed); ?>"
                         tabindex="0">
                        <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                            <title>お気に入り</title>
                            <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                        </svg>
                    </div>
                    <div class="favBtn likeBtn js--likeBtn <?= esc_attr($likeActiveClass); ?>"
                         role="button"
                         data-resource-type="tokk_article"
                         data-resource-id="<?= esc_attr((string) $article_info['id']); ?>"
                         aria-pressed="<?= esc_attr($likeAriaPressed); ?>"
                         tabindex="0">
                        <svg class="favIcon" aria-label="いいね" role="img" viewBox="0 0 20 20">
                            <title>いいね</title>
                            <path d="M1.35981 9.49502V16.652H4.38981L4.54281 16.67C7.35581 17.326 9.21981 17.799 10.1488 18.092C11.3828 18.481 11.8428 18.576 12.6798 18.632C13.3058 18.675 14.0168 18.434 14.3408 18.104C14.5198 17.922 14.6538 17.548 14.7068 16.968C14.7177 16.8464 14.7611 16.7299 14.8326 16.6308C14.904 16.5317 15.0008 16.4538 15.1128 16.405C15.3618 16.297 15.5688 16.121 15.7418 15.865C15.9018 15.631 16.0058 15.195 16.0248 14.564C16.028 14.448 16.0608 14.3347 16.1201 14.2349C16.1795 14.1351 16.2633 14.0522 16.3638 13.994C16.9458 13.657 17.2338 13.277 17.2938 12.831C17.3598 12.338 17.1998 11.783 16.7808 11.151C16.6825 11.0028 16.6459 10.8221 16.6788 10.6473C16.7116 10.4725 16.8114 10.3174 16.9568 10.215C17.3578 9.93302 17.5778 9.54102 17.6328 8.98502C17.7208 8.09902 17.1558 7.44402 15.8768 7.31302C14.7377 7.1999 13.5875 7.29581 12.4828 7.59602C12.3575 7.62877 12.2254 7.62504 12.1021 7.58529C11.9789 7.54553 11.8695 7.47139 11.787 7.37159C11.7044 7.27179 11.652 7.15049 11.6361 7.02195C11.6201 6.89341 11.6412 6.76299 11.6968 6.64602C12.1968 5.58802 12.4748 4.71502 12.5398 4.03902C12.6248 3.14202 12.4178 2.49202 11.9338 1.95602C11.5668 1.55002 10.9798 1.31802 10.7598 1.36602C10.4698 1.42802 10.2808 1.59602 10.0348 2.18402C9.88981 2.53202 9.81981 2.82802 9.69981 3.51902C9.58481 4.17502 9.52181 4.47102 9.39081 4.85902C8.99581 6.03502 8.02681 7.25402 6.72581 8.09502C5.81326 8.68239 4.82525 9.14326 3.78881 9.46502C3.72394 9.48465 3.65657 9.49475 3.58881 9.49502H1.35981ZM1.31781 18.015C0.994807 18.024 0.704807 17.952 0.461807 17.782C0.151807 17.565 0.00580697 17.223 0.00280697 16.829L0.00580697 9.50602C-0.028193 9.11602 0.086807 8.75802 0.358807 8.49202C0.613807 8.24202 0.946807 8.12402 1.29881 8.13202H3.48381C4.36769 7.85045 5.21035 7.45299 5.98981 6.95002C7.03781 6.27202 7.80981 5.30002 8.10481 4.42402C8.20581 4.12202 8.25981 3.87202 8.36181 3.28402C8.49981 2.49502 8.58581 2.12802 8.78381 1.65602C9.19381 0.674018 9.73181 0.194018 10.4738 0.0330184C11.2038 -0.124982 12.2668 0.296018 12.9388 1.04002C13.6838 1.86402 14.0128 2.89502 13.8908 4.16902C13.8381 4.71569 13.6868 5.33035 13.4368 6.01302C14.2906 5.8885 15.1564 5.8697 16.0148 5.95702C18.0218 6.16202 19.1488 7.46902 18.9848 9.12102C18.9128 9.83302 18.6548 10.438 18.2158 10.913C18.5848 11.624 18.7318 12.327 18.6398 13.013C18.5338 13.803 18.0938 14.461 17.3618 14.972C17.3048 15.665 17.1458 16.218 16.8638 16.632C16.6409 16.9659 16.3511 17.2499 16.0128 17.466C15.9048 18.15 15.6778 18.685 15.3068 19.061C14.6918 19.687 13.5928 20.06 12.5888 19.992C11.6358 19.928 11.0718 19.812 9.74181 19.392C8.86481 19.115 7.04881 18.655 4.31181 18.015H1.31781ZM3.01881 9.18402C3.01854 9.09455 3.03594 9.00591 3.06999 8.92318C3.10405 8.84045 3.1541 8.76525 3.21727 8.70189C3.28044 8.63854 3.35549 8.58827 3.43812 8.55397C3.52075 8.51967 3.60934 8.50202 3.69881 8.50202C3.78811 8.50228 3.87648 8.52013 3.95888 8.55454C4.04128 8.58896 4.1161 8.63927 4.17905 8.7026C4.24201 8.76593 4.29188 8.84104 4.32581 8.92364C4.35974 9.00624 4.37707 9.09472 4.37681 9.18402V16.862C4.37694 16.9513 4.35948 17.0398 4.32543 17.1223C4.29138 17.2049 4.2414 17.2799 4.17835 17.3431C4.1153 17.4064 4.04041 17.4566 3.95796 17.4909C3.8755 17.5252 3.78711 17.5429 3.69781 17.543C3.60851 17.5429 3.52011 17.5252 3.43766 17.4909C3.35521 17.4566 3.28032 17.4064 3.21727 17.3431C3.15422 17.2799 3.10424 17.2049 3.07019 17.1223C3.03613 17.0398 3.01868 16.9513 3.01881 16.862V9.18402Z"/>
                        </svg>
                    </div>
                    <?php if ($article_info['has_present']): ?>
                        <div class="blockBox__pinIcon cornerCover__wrapper blockBox__pinIcon--special"
                             data-boxBgColor="bodySub">
                            <div class="icon iconPin iconPinSpecial">
                                <div class="mask iconInner">特典あり</div>
                            </div>
                            <div class="cornerCover cornerCover--lb cornerCover--outside">
                                <div class="mask cornerCover__inner"></div>
                            </div>
                            <div class="cornerCover cornerCover--rt cornerCover--outside">
                                <div class="mask cornerCover__inner"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- 目次 -->
                <?php
                /**
                 * Gutenberg本文中の <h1>〜<h4> を自動検出して目次を生成する
                 * - h1 が親
                 * - h2〜h4 がその直前の h1 の子としてネスト
                 */

                // 投稿本文を取得（Gutenbergブロックを反映したHTML）
                $rawPostContent = (string) get_post_field('post_content', $post_id);
                $content = apply_filters('the_content', $rawPostContent);
                $paywallGateAnalysis = [
                    'count' => 0,
                    'first_index' => null,
                    'is_valid_position' => false,
                    'fallback_mode' => 'all_paid',
                ];
                $showInlinePaidGate = false;

                if ($shouldApplyPaidGate) {
                    $showInlinePaidGate = true;
                    $content = '';

                    if (function_exists('parse_blocks') && function_exists('serialize_blocks')) {
                        $parsedBlocks = parse_blocks($rawPostContent);
                        if (function_exists('lutwiyo_analyze_paywall_gate_blocks')) {
                            $paywallGateAnalysis = lutwiyo_analyze_paywall_gate_blocks($parsedBlocks);
                        }

                        $gateCount = (int) ($paywallGateAnalysis['count'] ?? 0);
                        $fallbackMode = (string) ($paywallGateAnalysis['fallback_mode'] ?? 'all_paid');
                        $firstIndex = (int) ($paywallGateAnalysis['first_index'] ?? -1);

                        if ($gateCount > 0 && $fallbackMode === 'restrict_after_break' && $firstIndex > 0) {
                            $publicBlocks = array_slice($parsedBlocks, 0, $firstIndex);
                            $content = apply_filters('the_content', serialize_blocks($publicBlocks));
                        }
                    }
                }

                // paywall マーカー文字列は本文表示から除外する。
                $content = str_replace('<!-- tokk-paywall-gate -->', '', $content);
                if (function_exists('lutwiyo_strip_article_adsense_markup_for_paid_members')) {
                    $content = lutwiyo_strip_article_adsense_markup_for_paid_members($content);
                }

                // --- ① 本文から <h1>〜<h4> を抽出 ---
                $pattern = '/<(h[1-4])[^>]*>(.*?)<\/\1>/is';
                preg_match_all($pattern, $content, $matches_raw, PREG_SET_ORDER);

                // 見出し情報を保持する配列
                $headings = [];
                $h1_index = 0; // 親(h1)のカウンタ
                $child_index = 0; // 子(h2〜h4)のカウンタ（h1ごとにリセット）

                if (!empty($matches_raw)) {
                    foreach ($matches_raw as $m) {
                        $tag = $m[1];               // h1 / h2 / h3 / h4
                        $level = (int)substr($tag, 1);
                        $title = strip_tags($m[2]);

                        if ($level === 1) {
                            // 親見出し（h1）
                            $h1_index++;
                            $child_index = 0;
                            $id = 'i-' . $h1_index;
                        } else {
                            // 子見出し（h2〜h4）は、直前の h1 にぶら下げる
                            if ($h1_index === 0) {
                                // 念のため：先に h2〜h4 が出てきた場合 → 親なしとして top レベル扱い
                                $h1_index++;
                                $child_index = 0;
                                $id = 'i-' . $h1_index;
                                // level はそのまま保持しておく（出力時に top レベルとして扱う）
                            } else {
                                $child_index++;
                                $id = 'i-' . $h1_index . '-' . $child_index;
                            }
                        }

                        $headings[] = [
                            'tag' => $tag,
                            'level' => $level,
                            'title' => $title,
                            'id' => $id,
                        ];
                    }
                }

                // --- ② 見出しタグに id を強制付与 ---
                $heading_index = 0;
                $content_with_ids = preg_replace_callback($pattern, function ($m) use (&$heading_index, $headings) {
                    if (!isset($headings[$heading_index])) {
                        // 念のため範囲外はそのまま返す
                        return $m[0];
                    }

                    $heading = $headings[$heading_index];
                    $heading_index++;

                    $tag_name = $heading['tag']; // h1〜h4
                    $id = $heading['id'];

                    // classなど他属性を保持しつつ id を差し替える
                    // 既存の id 属性を削除
                    $tag = preg_replace('/id\s*=\s*"[^"]*"/i', '', $m[0]);
                    // 先頭の <h1>〜<h4> に id 属性を挿入
                    $tag = preg_replace(
                        '/<' . $tag_name . '\b/i',
                        sprintf('<%s id="%s"', $tag_name, esc_attr($id)),
                        $tag
                    );

                    return $tag;
                }, $content);
                ?>

                <?php if (!empty($headings)) :
                    $index_is_open_class = !empty($article_info['index_is_open']) ? 'table--open' : '';
                    ?>
                    <div class="table <?= esc_attr($index_is_open_class); ?>" role="navigation">
                        <p class="textColor--textGray tableTitle">
                            目次<span class="tableBtn"></span>
                        </p>
                        <ul class="tableList" role="list">
                            <?php
                            $in_h1_li = false; // 現在 h1 の <li> を開いているか
                            $in_sub_ul = false; // 現在 子用 <ul> を開いているか

                            foreach ($headings as $heading) :
                                $level = $heading['level'];
                                $title = $heading['title'];
                                $id = $heading['id'];

                                // h1: 親見出し
                                if ($level === 1) {

                                    // 直前の h1 ブロックを閉じる
                                    if ($in_sub_ul) {
                                        echo '</ul>';
                                        $in_sub_ul = false;
                                    }
                                    if ($in_h1_li) {
                                        echo '</li>';
                                        $in_h1_li = false;
                                    }

                                    // 新しい h1 の <li> を開始
                                    $in_h1_li = true;
                                    ?>
                                    <li class="tableTarget" role="listitem">
                                    <a class="tableLink"
                                       href="#<?= esc_attr($id); ?>"
                                       title="<?= esc_attr($title); ?>">
                                        <p class="tableTarget--p"><?= esc_html($title); ?></p>
                                        <div class="btnArrow btnArrow--down" data-arrow="w-6"></div>
                                    </a>
                                    <?php
                                } else {
                                    // h2〜h4: 子見出し
                                    if (!$in_h1_li) {
                                        // 念のため、親なしで出てきたときは top レベルとして出す
                                        ?>
                                        <li class="tableTarget" role="listitem">
                                            <a class="tableLink"
                                               href="#<?= esc_attr($id); ?>"
                                               title="<?= esc_attr($title); ?>">
                                                <p class="tableTarget--p"><?= esc_html($title); ?></p>
                                                <div class="btnArrow btnArrow--down" data-arrow="w-6"></div>
                                            </a>
                                        </li>
                                        <?php
                                        continue;
                                    }

                                    // 子用 <ul> がまだなら開く
                                    if (!$in_sub_ul) {
                                        echo '<ul>';
                                        $in_sub_ul = true;
                                    }
                                    ?>
                                    <li class="tableTarget" role="listitem">
                                        <a class="tableLink"
                                           href="#<?= esc_attr($id); ?>"
                                           title="<?= esc_attr($title); ?>">
                                            <p class="tableTarget--p"><?= esc_html($title); ?></p>
                                            <div class="btnArrow btnArrow--down" data-arrow="w-6"></div>
                                        </a>
                                    </li>
                                    <?php
                                }
                            endforeach;

                            // ループ終了後に開きっぱなしを閉じる
                            if ($in_sub_ul) {
                                echo '</ul>';
                            }
                            if ($in_h1_li) {
                                echo '</li>';
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>


                <!-- 詳細 -->
                <div class="wordpress__block">
                    <?= $content_with_ids; ?>
                    <?php if (!empty($article_info['supplementText'])): ?>
                        <ul class="p-article__remarksList">
                            <?= $article_info['supplementText']; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <?php if (!empty($showInlinePaidGate)): ?>
                    <?php
                    $paidLoginUrl = home_url('/login/');
                    $paidUpgradeUrl = add_query_arg(
                        'return_to',
                        lutwiyo_get_current_frontend_url_for_return(),
                        home_url('/paid-exp/')
                    );
                    $paidSignupUrl = home_url('/regist/');
                    $isGuestViewer = $paidGateViewerLevel === 'guest';
                    $isFreeViewer = $paidGateViewerLevel === 'free';
                    $primaryCtaUrl = $isGuestViewer ? $paidLoginUrl : ($isFreeViewer ? $paidUpgradeUrl : '');
                    $primaryCtaLabel = $isGuestViewer
                        ? 'ログインして記事を読む'
                        : ($isFreeViewer ? 'スタンダード会員にアップグレード' : '');
                    $primaryCtaNote = $isGuestViewer
                        ? '*既にスタンダード会員のかたはログインしてお読みいただけます。'
                        : ($isFreeViewer ? '*スタンダード会員へのアップグレードで続きをお読みいただけます。' : '*会員状態を確認できないため、時間をおいて再度お試しください。');
                    ?>
                    <div class="guestBlock hasBgColor">
                        <div class="guestBlock__title-1">
                            <h3 class="title">
                                <svg class="titleIcon" aria-label="こちらの記事はスタンダード（有料・月額280円）会員限定の記事です。" role="img" viewBox="0 0 20 20">
                                    <path d="M15.7,8.9h-.8v-2.7c0-2-1.7-3.7-3.7-3.7h-2.5c-2,0-3.7,1.7-3.7,3.7v2.7h-.8c-.3,0-.5.2-.5.5v5.1c0,1.7,1.4,3,3,3h6.4s0,0,0,0c1.7,0,3-1.4,3-3v-5.1c0-.3-.2-.5-.5-.5ZM10.5,14.3c0,.3-.2.5-.5.5s-.5-.2-.5-.5v-1c0-.3.2-.5.5-.5s.5.2.5.5v1ZM14,8.9h-7.9v-2.7c0-1.5,1.2-2.7,2.7-2.7h2.5c1.5,0,2.7,1.2,2.7,2.7v2.7Z"/>
                                </svg>
                                <p class="title__text">こちらの記事はスタンダード（有料・月額280円）会員限定の記事です。</p>
                            </h3>
                            <div class="text">下記より登録すると続きを読むことができます。</div>
                        </div>
                        <div class="guestBlock__buttons">
                            <?php if ($primaryCtaUrl !== '' && $primaryCtaLabel !== ''): ?>
                                <div class="button">
                                    <a href="<?= esc_url($primaryCtaUrl); ?>" class="login">
                                        <p class="login__text"><?= esc_html($primaryCtaLabel); ?></p>
                                        <svg class="loginIcon" aria-label="<?= esc_attr($primaryCtaLabel); ?>" role="img" viewBox="0 0 20 20">
                                            <path d="M14.1,3s0,0,0,0,0,0,0,0h-7.1c-.3,0-.5.2-.5.5s.2.5.5.5h7.1s0,0,0,0,0,0,0,0c.4,0,.7.3.7.7v10.6c0,.4-.3.7-.7.7s0,0,0,0,0,0,0,0h-7.1c-.3,0-.5.2-.5.5s.2.5.5.5h7.1s0,0,0,0,0,0,0,0c.9,0,1.7-.8,1.7-1.7V4.7c0-.9-.8-1.7-1.7-1.7Z"/>
                                            <path d="M8.5,12.6c-.2.2-.2.5,0,.7s.2.1.4.1.3,0,.4-.1l2.9-2.9s0-.1.1-.2c0-.1,0-.3,0-.4,0,0,0-.1-.1-.2l-2.9-2.9c-.2-.2-.5-.2-.7,0s-.2.5,0,.7l2.1,2.1h-5.9c-.3,0-.5.2-.5.5s.2.5.5.5h5.9l-2.1,2.1Z"/>
                                        </svg>
                                    </a>
                                    <div class="buttonText"><?= esc_html($primaryCtaNote); ?></div>
                                </div>
                            <?php else: ?>
                                <div class="button">
                                    <div class="buttonText"><?= esc_html($primaryCtaNote); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($isGuestViewer): ?>
                                <div class="button">
                                    <a href="<?= esc_url($paidSignupUrl); ?>" class="default">
                                        <p class="default__text">TOKK会員に新規会員登録</p>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="guestBlock hasBorder<?= $isFreeViewer ? ' isFreeViewer' : ''; ?>">
                        <div class="guestBlock__title-2">
                            <h3 class="title">関西の“今”を、深く、まとめて楽しむ</h3>
                            <div class="text">
                                <svg class="textIcon" aria-label="スタンダード会員" role="img" viewBox="0 0 20 20">
                                    <path d="M15.7,8.9h-.8v-2.7c0-2-1.7-3.7-3.7-3.7h-2.5c-2,0-3.7,1.7-3.7,3.7v2.7h-.8c-.3,0-.5.2-.5.5v5.1c0,1.7,1.4,3,3,3h6.4s0,0,0,0c1.7,0,3-1.4,3-3v-5.1c0-.3-.2-.5-.5-.5ZM10.5,14.3c0,.3-.2.5-.5.5s-.5-.2-.5-.5v-1c0-.3.2-.5.5-.5s.5.2.5.5v1ZM14,8.9h-7.9v-2.7c0-1.5,1.2-2.7,2.7-2.7h2.5c1.5,0,2.7,1.2,2.7,2.7v2.7Z"/>
                                </svg>
                                <p class="text__text">スタンダード会員になると、編集部が届ける記事や限定特集を、広告に邪魔されず心地よく楽しめます。</p>
                            </div>
                        </div>
                        <div class="guestBlock__boxContents">
                            <div class="content">
                                <div class="box">
                                    <div class="boxIcon"><img src="/assets/img/common/articleGuestIcon_1.svg" alt="有料記事読み放題"></div>
                                    <div class="boxText">有料記事<br>読み放題</div>
                                </div>
                            </div>
                            <div class="content">
                                <div class="box">
                                    <div class="boxIcon"><img src="/assets/img/common/articleGuestIcon_2.svg" alt="広告なし"></div>
                                    <div class="boxText">広告なし</div>
                                </div>
                            </div>
                            <div class="content">
                                <div class="box">
                                    <div class="boxIcon"><img src="/assets/img/common/articleGuestIcon_3.svg" alt="いいね・コメント"></div>
                                    <div class="boxText">いいね<br>・コメント</div>
                                </div>
                            </div>
                            <div class="content">
                                <div class="box">
                                    <div class="boxIcon"><img src="/assets/img/common/articleGuestIcon_4.svg" alt="ブログ投稿"></div>
                                    <div class="boxText">ブログ投稿</div>
                                </div>
                            </div>
                        </div>
                        <div class="guestBlock__buttons">
                            <?php if ($isGuestViewer): ?>
                                <div class="button">
                                    <a href="<?= esc_url($paidSignupUrl); ?>" class="default">
                                        <p class="default__text">TOKK会員に新規会員登録</p>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- クーポン情報 -->
                <?php if (!empty($coupon_details_list)): ?>
                    <?php foreach ($coupon_details_list as $co): ?>
                        <?php $couponActionGate = lutwiyo_get_member_benefit_action_gate((string) ($co['url'] ?? ''), 'coupon'); ?>
                        <div class="couponInfo">
                            <div class="couponInfo__thum"><img src="<?= esc_url($co['image_url']); ?>"
                                                               alt="<?= esc_attr($co['name']); ?>" width="604"
                                                               height="450"
                                                               loading="lazy"></div>
                            <div class="couponInfo__detail">
                                <div class="couponInfo__detail--leader">クーポン情報</div>
                                <h3 class="couponInfo__detail--title"><?= esc_attr($co['name']); ?></h3>
                                <p class="fontW--r textColor--textGray couponInfo__detail--text">
                                    <?= esc_attr($co['detail']); ?></p>
                                <div class="btn btnShaped btnBgColor" data-shaped="auto-38">
                                    <a class="flex--cc btnLink<?php echo ($couponActionGate['mode'] ?? 'direct') === 'direct' ? '' : ' js--memberBenefitActionGate'; ?>" href="<?= esc_url((string) ($couponActionGate['href'] ?? ($co['url'] ?? ''))); ?>" <?php echo lutwiyo_get_member_benefit_action_attrs($couponActionGate); ?> <?php if (($couponActionGate['mode'] ?? 'direct') === 'direct') : ?>target="_blank"<?php endif; ?>
                                       title="クーポンを取得する">
                                        <p class="btnTtext">クーポンを取得する</p>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>


                <?php //記事に紐づくプレゼントを一覧表示
                $present_info_list = PostModelHelper::get_posts_payload(
                    [
                        'post_type' => 'present'
                    ],
                    // 抽出したいACFフィールド名の配列
                    ['info', 'present_url'],
                    // 各投稿に対して追加実行する関数セット
                    []
                );

                $present_list = [];

                foreach ($present_info_list as $present) {

                    // related_articles がなければスキップ
                    if (!isset($present['related_articles']) || empty($present['related_articles'])) {
                        continue;
                    }

                    $related = $present['related_articles'];
                    $matched = false;

                    // パターン1: 単一の WP_Post オブジェクト
                    if ($related instanceof WP_Post) {
                        if ((int)$related->ID === (int)$post_id) {
                            $matched = true;
                        }

                        // パターン2: 配列（WP_Post や ID の配列を想定）
                    } elseif (is_array($related)) {
                        foreach ($related as $rel_item) {
                            if ($rel_item instanceof WP_Post && (int)$rel_item->ID === (int)$post_id) {
                                $matched = true;
                                break;
                            } elseif (is_numeric($rel_item) && (int)$rel_item === (int)$post_id) {
                                $matched = true;
                                break;
                            }
                        }
                    }

                    if ($matched) {
                        // related_articles 要素は空にして格納（キーごと消したいなら unset に変更）
                        $item = $present;
                        // $item['related_articles'] = null;  // 空にする場合
                        unset($item['related_articles']);    // キーごと削除する場合はこちら


                        $present_list[] = $item;
                    }
                }
                // lutwiyo_debug($present_list);


                ?>
                <!-- プレゼント情報 -->
                <?php if (!empty($present_list)): ?>
                    <?php foreach ($present_list as $pl): ?>
                        <?php $presentActionGate = lutwiyo_get_member_benefit_action_gate('/present-entry/', 'present'); ?>
                        <div id="i-3">
                            <div class="couponInfo presentInfo">
                                <div class="couponInfo__thum"><img src="<?= esc_url($pl['image_url']) ?>"
                                                                   alt="<?= esc_attr($pl['text']) ?>" width="604"
                                                                   height="450" loading="lazy"></div>
                                <div class="couponInfo__detail">
                                    <div class="couponInfo__detail--leader">プレゼント情報</div>
                                    <h3 class="couponInfo__detail--title"><?= esc_attr($pl['text']) ?></h3>
                                    <dl class="textColor--textGray fontW--r spotInfo__block">
                                        <dt class="spotInfo__block--title">応募締切</dt>
                                        <dd class="spotInfo__block--text"><?= format_present_date_label($pl['end_date']) ?></dd>
                                    </dl>
                                    <p class="presentInfo__aside"><?= esc_attr($pl['info']) ?></p>
                                    <div class="btn btnShaped btnBgColor" data-shaped="auto-38">
                                        <a class="flex--cc btnLink<?php echo ($presentActionGate['mode'] ?? 'direct') === 'direct' ? '' : ' js--memberBenefitActionGate'; ?>" href="<?php echo esc_url((string) ($presentActionGate['href'] ?? home_url('/present-entry/'))); ?>" <?php echo lutwiyo_get_member_benefit_action_attrs($presentActionGate); ?> title="プレゼントに応募する">
                                            <p class="btnTtext">プレゼントに応募する</p>
                                            <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- プロフィール full -->
                <div class="postPerson__fullWrapper">
                    <?php //ページ下部のスタッフ一覧情報 ?>
                    <?php if (!empty($article_data['staff_ui']['full'])): ?>
                        <?php foreach ($article_data['staff_ui']['full'] as $st): ?>
                            <div class="postPerson postPerson__full">
                                <div class="iconPostPerson">
                                    <div class="iconPostPerson__thum">
                                        <img class="thumImg"
                                             src="<?php echo esc_url($st['img']); ?>"
                                             alt="<?php echo esc_attr($st['name'] ?: 'スタッフ'); ?>"
                                             width="40" height="40" loading="lazy">
                                    </div>
                                    <?php if ($st['group'] !== ''): ?>
                                        <p class="fontW--r textColor--textGray blockBox__infoSub--personTitle">
                                            <?php echo esc_html($st['group']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="postPerson__detail">
                                    <?php if ($st['role'] !== ''): ?>
                                        <p class="fontW--r blockBox__infoSub--personTitle"><?php echo esc_html($st['role']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($st['name'] !== ''): ?>
                                        <p class="fontW--r blockBox__infoSub--text"><?php echo esc_html($st['name']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($st['profile'] !== ''): ?>
                                        <p class="fontW--r textColor--textGray blockBox__infoSub--profile">
                                            <?php echo esc_html($st['profile']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($st['link'])): ?>
                                        <div class="btn btnShaped btnBgColor" data-shaped="auto-38">
                                            <a class="flex--cc btnLink" href="<?php echo esc_url($st['link']); ?>"
                                               title="プロフィールをみる">
                                                <p class="btnTtext">プロフィールをみる</p>
                                                <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
                <?php if ($showCommentBlock): ?>
                    <div class="commentBlock js--commentRoot"
                         data-resource-type="tokk_article"
                         data-resource-id="<?= esc_attr((string) $articleIdForReaction); ?>"
                         data-comment-view-acl="<?= esc_attr((string) $commentViewAcl); ?>"
                         data-comment-post-acl="<?= esc_attr((string) $commentPostAcl); ?>"
                         data-comment-can-view="<?= $canShowCommentList ? '1' : '0'; ?>"
                         data-comment-can-post="<?= $canSubmitComment ? '1' : '0'; ?>"
                         data-comment-post-disabled-message="<?= esc_attr($commentPostingStateMessage !== '' ? $commentPostingStateMessage : 'コメント投稿は現在ご利用いただけません。'); ?>"
                         data-comment-current-member-display-name="<?= esc_attr($commentCurrentMemberDisplayName); ?>"
                         data-comment-current-member-profile-image-url="<?= esc_url($commentCurrentMemberProfileImageUrl); ?>"
                         data-comment-login-url="<?= esc_url($commentLoginUrl); ?>">
                        <h3 class="commentBlock__title">
                            <svg class="titleIcon" aria-label="コメント一覧" role="img" viewBox="0 0 22 22">
                                <path d="M1.8,19.5c-.6,0-1-.4-1-1v-9.2c0-.6.4-1,1-1s1,.4,1,1v9.2c0,.6-.4,1-1,1Z"/>
                                <path d="M14.3,19.5H1.8c-.6,0-1-.4-1-1s.4-1,1-1h12.5c.6,0,1,.4,1,1s-.4,1-1,1Z"/>
                                <path d="M20.2,13.7c-.6,0-1-.4-1-1v-3.3c0-.6.4-1,1-1s1,.4,1,1v3.3c0,.6-.4,1-1,1Z"/>
                                <path d="M14.3,4.5h-6.7c-.6,0-1-.4-1-1s.4-1,1-1h6.7c.6,0,1,.4,1,1s-.4,1-1,1Z"/>
                                <path d="M1.8,10.3c-.6,0-1-.4-1-1C.8,5.6,3.9,2.5,7.7,2.5s1,.4,1,1-.4,1-1,1c-2.7,0-4.8,2.2-4.8,4.8s-.4,1-1,1Z"/>
                                <path d="M20.2,10.3c-.6,0-1-.4-1-1,0-2.7-2.2-4.8-4.8-4.8s-1-.4-1-1,.4-1,1-1c3.8,0,6.8,3.1,6.8,6.8s-.4,1-1,1Z"/>
                                <path d="M14.3,19.5c-.6,0-1-.4-1-1s.4-1,1-1c2.7,0,4.8-2.2,4.8-4.8s.4-1,1-1,1,.4,1,1c0,3.8-3.1,6.8-6.8,6.8Z"/>
                                <path d="M15.2,12c-.3,0-.5-.1-.7-.3,0,0-.2-.2-.2-.3,0-.1,0-.2,0-.4,0-.3.1-.5.3-.7.3-.3.7-.4,1.1-.2.1,0,.2.1.3.2.2.2.3.4.3.7s-.1.5-.3.7c-.2.2-.5.3-.7.3Z"/>
                                <path d="M11,12c-.1,0-.3,0-.4,0-.1,0-.2-.1-.3-.2-.2-.2-.3-.5-.3-.7s0-.1,0-.2c0,0,0-.1,0-.2,0,0,0-.1,0-.2,0,0,0-.1.1-.2.1,0,.2-.2.3-.2.2-.1.5-.1.8,0,.1,0,.2.1.3.2,0,0,0,0,.1.2,0,0,0,.1,0,.2,0,0,0,.1,0,.2,0,0,0,.1,0,.2,0,.3-.1.5-.3.7-.1,0-.2.2-.3.2-.1,0-.2,0-.4,0Z"/>
                                <path d="M6.8,12c-.1,0-.3,0-.4,0-.1,0-.2-.1-.3-.2-.2-.2-.3-.5-.3-.7s0-.1,0-.2c0,0,0-.1,0-.2,0,0,0-.1,0-.2,0,0,0-.1.1-.2,0,0,.2-.2.3-.2.4-.2.8,0,1.1.2,0,0,0,0,.1.2,0,0,0,.1,0,.2,0,0,0,.1,0,.2,0,0,0,.1,0,.2,0,.3-.1.5-.3.7-.2.2-.5.3-.7.3Z"/>
                            </svg>
                            <p class="title__text js--commentCountText">コメント一覧(<span
                                        class="js--commentCount"><?= esc_html((string) $commentTotalCount); ?></span>件)</p>
                        </h3>

                        <?php if ($requiresCommentLoginForView): ?>
                            <div class="commentBlock__loginOnly js--commentLoginOnly">
                                <p class="commentBlock__loginOnlyMessage">この記事のコメントはログインすると閲覧できます。</p>
                                <div class="commentBlock__loginOnlyActions">
                                    <a class="commentBlock__loginOnlyButton commentBlock__loginOnlyButton--primary" href="<?= esc_url($commentLoginUrl); ?>">ログインはこちら</a>
                                    <a class="commentBlock__loginOnlyButton commentBlock__loginOnlyButton--secondary" href="<?= esc_url($commentRegisterUrl); ?>">新規会員登録はこちら</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="commentBlock__row">
                                <div class="commentCol">
                                    <?php if ($commentListNoticeMessage !== ''): ?>
                                        <p class="commentBlock__notice js--commentListNotice <?= esc_attr($commentListNoticeModifierClass); ?>">
                                            <?= esc_html($commentListNoticeMessage); ?>
                                        </p>
                                    <?php endif; ?>
                                    <ul class="commentList js--commentList">
                                        <?php foreach ($commentItems as $commentItem): ?>
                                            <?php
                                            $commentId = max(0, (int) ($commentItem['comment_id'] ?? 0));
                                            if ($commentId <= 0) {
                                                continue;
                                            }
                                            $commentStatus = trim((string) ($commentItem['status'] ?? 'visible'));
                                            if ($commentStatus === 'deleted') {
                                                continue;
                                            }
                                            $commentBody = trim((string) ($commentItem['body'] ?? ''));
                                            $commentDisplayName = trim((string) ($commentItem['member_display_name'] ?? ''));
                                            if ($commentDisplayName === '') {
                                                $commentDisplayName = '会員';
                                            }
                                            $commentProfileImageUrl = trim((string) ($commentItem['member_profile_image_url'] ?? ''));
                                            $postedAtLabel = $formatCommentPostedAtLabel($commentItem['posted_at'] ?? '');
                                            $commentItemClassList = [];
                                            if ($commentStatus === 'pending_approval') {
                                                $commentItemClassList[] = 'is-pending';
                                            }
                                            if ($commentStatus === 'delete_requested') {
                                                $commentItemClassList[] = 'is-delete-requested';
                                            }
                                            $commentItemClass = implode(' ', $commentItemClassList);
                                            $isCommentMine = (bool) ($commentItem['is_mine'] ?? false);
                                            $reportReasonInputName = 'comment_report_reason_' . $commentId;
                                            ?>
                                            <li class="<?= esc_attr($commentItemClass); ?>"
                                                data-comment-id="<?= esc_attr((string) $commentId); ?>"
                                                data-comment-status="<?= esc_attr($commentStatus); ?>"
                                                data-comment-is-mine="<?= $isCommentMine ? '1' : '0'; ?>">
                                                <div class="user">
                                                    <div class="icon">
                                                        <?php if ($commentProfileImageUrl !== ''): ?>
                                                            <img class="commentUserImage" src="<?= esc_url($commentProfileImageUrl); ?>" alt="<?= esc_attr($commentDisplayName); ?>">
                                                        <?php else: ?>
                                                            <svg class="userIcon" aria-label="<?= esc_attr($commentDisplayName); ?>"
                                                                 role="img"
                                                                 viewBox="0 0 20 20">
                                                                <path d="M12.4,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM6.9,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM8.8,11.1h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7H4.6v-.7c0-2.3,1.9-4.2,4.2-4.2Z"/>
                                                            </svg>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="name"><?= esc_html($commentDisplayName); ?></div>
                                                </div>
                                                <div class="comment">
                                                    <div class="commentInner"><?= nl2br(esc_html($commentBody)); ?></div>
                                                    <?php if ($postedAtLabel !== ''): ?>
                                                        <p class="commentMeta"><?= esc_html($postedAtLabel); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($commentStatus === 'pending_approval'): ?>
                                                        <p class="commentMeta commentMeta--pending">承認待ちのコメントです。</p>
                                                    <?php endif; ?>
                                                    <?php if ($commentStatus === 'delete_requested'): ?>
                                                        <p class="commentMeta commentMeta--delete-requested">削除依頼を受け付けています。</p>
                                                    <?php endif; ?>
                                                    <?php if ($isCommentMine && $commentStatus !== 'delete_requested'): ?>
                                                        <button type="button"
                                                                class="commentMenu js--commentDeleteRequestToggle"
                                                                data-comment-id="<?= esc_attr((string) $commentId); ?>"
                                                                aria-label="コメントの削除依頼メニューを開く">
                                                            <span class="dot"></span>
                                                            <span class="dot"></span>
                                                            <span class="dot"></span>
                                                        </button>
                                                        <div class="commentWidget widgetDelete js--commentDeleteRequestWidget"
                                                             data-comment-id="<?= esc_attr((string) $commentId); ?>"
                                                             hidden>
                                                            <form class="js--commentDeleteRequestForm"
                                                                  data-comment-id="<?= esc_attr((string) $commentId); ?>">
                                                                <p class="commentDeleteNote">このコメントの削除を依頼しますか？</p>
                                                                <p class="commentDeleteError js--commentDeleteError" aria-live="polite"></p>
                                                                <div class="button">
                                                                    <button type="submit">
                                                                        <p class="submit__text">削除依頼する</p>
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <button type="button"
                                                                class="commentMenu js--commentReportToggle"
                                                                data-comment-id="<?= esc_attr((string) $commentId); ?>"
                                                                aria-label="コメントを通報する">
                                                            <span class="dot"></span>
                                                            <span class="dot"></span>
                                                            <span class="dot"></span>
                                                        </button>
                                                        <div class="commentWidget widgetReport js--commentReportWidget"
                                                             data-comment-id="<?= esc_attr((string) $commentId); ?>"
                                                             hidden>
                                                            <form class="js--commentReportForm"
                                                                  data-comment-id="<?= esc_attr((string) $commentId); ?>">
                                                                <?php foreach (lutwiyo_get_comment_report_reasons() as $reasonOption) : ?>
                                                                    <div class="checkBox"><label><input type="radio"
                                                                                                         name="<?= esc_attr($reportReasonInputName); ?>"
                                                                                                         value="<?= esc_attr((string) $reasonOption['code']); ?>"><?= esc_html((string) $reasonOption['label']); ?></label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                                <div class="commentReportNote js--commentReportNoteWrap" hidden>
                                                                    <textarea class="js--commentReportNote"
                                                                              rows="3"
                                                                              maxlength="1000"
                                                                              placeholder="その他を選択した場合は詳細を入力してください。"></textarea>
                                                                </div>
                                                                <p class="commentReportError js--commentReportError" aria-live="polite"></p>
                                                                <div class="button">
                                                                    <button type="submit">
                                                                        <div class="submitIcon">
                                                                            <svg aria-label="報告" role="img" viewBox="0 0 120 120">
                                                                                <path d="M89,41v-21c0-.1,0-.3,0-.4,0,0,0-.2,0-.3,0-.2,0-.4-.1-.6,0,0,0-.1,0-.2,0-.2-.2-.4-.3-.6,0,0,0,0,0-.1,0,0,0,0,0,0,0-.2-.2-.3-.3-.4,0,0-.1-.1-.2-.2,0-.1-.2-.2-.3-.3,0,0-.2-.2-.3-.2-.1,0-.2-.1-.3-.2-.1,0-.2-.1-.3-.2-.1,0-.3,0-.4-.1-.1,0-.2,0-.3-.1-.1,0-.3,0-.4,0-.1,0-.2,0-.4,0,0,0,0,0-.1,0-.1,0-.2,0-.3,0-.1,0-.2,0-.3,0-.2,0-.3,0-.5.1-.1,0-.2,0-.3,0-.1,0-.3.1-.4.2,0,0-.2,0-.3.1l-35,21.4h-20.9c-6.2,0-11,4.8-11,11v15c0,4.7,2.9,8.5,7,10.1v18.9c0,6.2,4.8,11,11,11s.3,0,.5-.1c.2,0,.3.1.5.1h7c2.2,0,4-1.8,4-4v-25h1.9l34.9,21.3s0,0,0,0h.1c.1.2.3.2.4.3,0,0,.2,0,.3.1.2,0,.3.1.5.1,0,0,.1,0,.2,0,.2,0,.5,0,.7,0s.5,0,.7,0c0,0,.1,0,.2,0,.2,0,.4-.1.6-.2,0,0,0,0,.1,0,.2,0,.4-.2.5-.3,0,0,0,0,.1,0,.2-.1.4-.3.5-.4,0,0,0,0,.1-.1.1-.1.2-.2.3-.4,0,0,.1-.1.1-.2,0,0,0,0,0,0,0,0,0-.1,0-.2,0-.2.2-.3.3-.5,0,0,0-.1,0-.2,0-.2.1-.3.1-.5,0,0,0-.2,0-.3,0-.1,0-.3,0-.4v-22c8.3,0,15-6.7,15-15s-6.7-15-15-15ZM24,49c0-1.8,1.2-3,3-3h22s0,0,0,0c0,0,0,0,0,0,.2,0,.5,0,.7,0,0,0,.1,0,.2,0,.2,0,.4-.1.6-.2,0,0,0,0,0,0,.2,0,.3-.2.5-.3l29.9-18.3v58.7l-29.9-18.3c0,0-.2,0-.3-.1-.1,0-.2-.1-.4-.2-.1,0-.3,0-.4-.1-.1,0-.2,0-.3,0-.2,0-.4,0-.5,0,0,0-.1,0-.2,0h-22c-1.8,0-3-1.2-3-3v-15ZM38,96h-3c-.2,0-.3,0-.5.1-.2,0-.3-.1-.5-.1-1.8,0-3-1.2-3-3v-18h7v21ZM89,63v-14c3.8,0,7,3.2,7,7s-3.2,7-7,7Z"/>
                                                                            </svg>
                                                                        </div>
                                                                        <p class="submit__text">報告する</p>
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="commentSpOpen">
                                        <button type="button" class="button js--commentFormToggle">コメントする</button>
                                    </div>
                                </div>
                                <div class="commentCol">
                                    <div class="commentInput js--commentInput">
                                        <form class="js--commentForm" novalidate>
                                            <textarea id="comment-textarea"
                                                      class="js--commentTextarea"
                                                      rows="12"
                                                      maxlength="5000"
                                                      placeholder="この記事を読んで感じたことや印象に残った点、参考になった部分などをコメントしてください。"
                                                <?= $commentFormDisabled ? 'disabled' : ''; ?>></textarea>
                                            <div class="commentCtrl">
                                                <a href="" class="delete" data-comment-clear></a>
                                                <button type="submit" class="js--commentSubmit"
                                                    <?= $commentFormDisabled ? 'disabled' : ''; ?>>コメントする</button>
                                            </div>
                                        </form>
                                        <?php if ($commentPostingStateMessage !== ''): ?>
                                            <p class="commentInput__state js--commentFormState"><?= esc_html($commentPostingStateMessage); ?></p>
                                        <?php endif; ?>
                                        <?php if (!$isCommentLoggedIn): ?>
                                            <p class="commentInput__loginLink"><a href="<?= esc_url($commentLoginUrl); ?>">ログインはこちら</a></p>
                                        <?php endif; ?>
                                        <?php if ($isCommentFreeViewer && !$canSubmitComment && $isCommentPostingEnabled): ?>
                                            <p class="commentInput__loginLink"><a href="<?= esc_url($commentUpgradeUrl); ?>">有料会員登録はこちら</a></p>
                                        <?php endif; ?>
                                        <div class="commentCaution">
                                            読んだ人が情景を思い浮かべられるような感想は、ほかの利用者があなたのコメントを楽しみに待つきっかけになります。<br>
                                            ※一度投稿したコメントを編集することはできません。<br>
                                            ※ほかの利用者が安心して読めるよう、誹謗中傷や不適切な表現はお控えください。<br>
                                            ※内容によっては、編集部の判断により予告なく非表示または削除する場合があります。<br>
                                            ※投稿されたコメントは、記事内や関連ページで紹介されることがあります。<br>
                                        </div>
                                        <p class="commentInput__feedback js--commentFeedback" aria-live="polite"></p>
                                    </div>
                                </div>
                            </div>
                            <div class="commentSpScreen js--commentOverlay"></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="pdFooter">
                    <!-- シェア -->
                    <?php
                    $title = urlencode(get_the_title());
                    $url = urlencode(get_permalink());
                    ?>
                    <div class="pdFooter__box shareBlock">
                        <p class="pdFooter__title shareBlock__title">シェア</p>
                        <ul class="shareList">
                            <li class="shareTarget shareTarget--x">
                                <a class="shareLink"
                                   href="https://twitter.com/intent/tweet?text=<?= $title; ?>&url=<?= $url; ?>"
                                   target="_blank"
                                   title="X">
                                    <div class="snsIcon snsIcon--x">
                                        <div class="mask mask__bgColor--btnBg snsIcon__inner"></div>
                                    </div>
                                </a>
                            </li>
                            <li class="shareTarget shareTarget--line">
                                <a class="shareLink"
                                   href="https://social-plugins.line.me/lineit/share?url=<?= $url; ?>"
                                   target="_blank"
                                   title="LINE">
                                    <div class="snsIcon snsIcon--line">
                                        <div class="mask mask__bgColor--btnBg snsIcon__inner"></div>
                                    </div>
                                </a>
                            </li>
                            <li class="shareTarget shareTarget--fb">
                                <a class="shareLink"
                                   href="https://www.facebook.com/sharer/sharer.php?u=<?= $url; ?>"
                                   target="_blank"
                                   title="Facebook">
                                    <div class="snsIcon snsIcon--fb">
                                        <div class="mask mask__bgColor--btnBg snsIcon__inner"></div>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <!-- お気に入り -->
                    <div class="js--favBtn pdFooter__box pdFooter__favBtn <?= esc_attr($favoriteActiveClass); ?>"
                         role="button"
                         data-id="<?= esc_attr($article_info['id']); ?>"
                         data-resource-type="tokk_article"
                         data-resource-id="<?= esc_attr((string) $article_info['id']); ?>"
                         aria-pressed="<?= esc_attr($favoriteAriaPressed); ?>"
                         tabindex="0">
                        <p class="pdFooter__title favTitle">お気に入り</p>
                        <div class="favBtn" aria-hidden="true">
                            <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                <title>お気に入り</title>
                                <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="js--likeBtn pdFooter__box pdFooter__likeBtn <?= esc_attr($likeActiveClass); ?>"
                         role="button"
                         data-resource-type="tokk_article"
                         data-resource-id="<?= esc_attr((string) $article_info['id']); ?>"
                         aria-pressed="<?= esc_attr($likeAriaPressed); ?>"
                         tabindex="0">
                        <p class="pdFooter__title likeTitle">いいね</p>
                        <div class="favBtn likeBtn" aria-hidden="true">
                            <svg class="favIcon" aria-label="いいね" role="img" viewBox="0 0 20 20">
                                <title>いいね</title>
                                <path d="M1.35981 9.49502V16.652H4.38981L4.54281 16.67C7.35581 17.326 9.21981 17.799 10.1488 18.092C11.3828 18.481 11.8428 18.576 12.6798 18.632C13.3058 18.675 14.0168 18.434 14.3408 18.104C14.5198 17.922 14.6538 17.548 14.7068 16.968C14.7177 16.8464 14.7611 16.7299 14.8326 16.6308C14.904 16.5317 15.0008 16.4538 15.1128 16.405C15.3618 16.297 15.5688 16.121 15.7418 15.865C15.9018 15.631 16.0058 15.195 16.0248 14.564C16.028 14.448 16.0608 14.3347 16.1201 14.2349C16.1795 14.1351 16.2633 14.0522 16.3638 13.994C16.9458 13.657 17.2338 13.277 17.2938 12.831C17.3598 12.338 17.1998 11.783 16.7808 11.151C16.6825 11.0028 16.6459 10.8221 16.6788 10.6473C16.7116 10.4725 16.8114 10.3174 16.9568 10.215C17.3578 9.93302 17.5778 9.54102 17.6328 8.98502C17.7208 8.09902 17.1558 7.44402 15.8768 7.31302C14.7377 7.1999 13.5875 7.29581 12.4828 7.59602C12.3575 7.62877 12.2254 7.62504 12.1021 7.58529C11.9789 7.54553 11.8695 7.47139 11.787 7.37159C11.7044 7.27179 11.652 7.15049 11.6361 7.02195C11.6201 6.89341 11.6412 6.76299 11.6968 6.64602C12.1968 5.58802 12.4748 4.71502 12.5398 4.03902C12.6248 3.14202 12.4178 2.49202 11.9338 1.95602C11.5668 1.55002 10.9798 1.31802 10.7598 1.36602C10.4698 1.42802 10.2808 1.59602 10.0348 2.18402C9.88981 2.53202 9.81981 2.82802 9.69981 3.51902C9.58481 4.17502 9.52181 4.47102 9.39081 4.85902C8.99581 6.03502 8.02681 7.25402 6.72581 8.09502C5.81326 8.68239 4.82525 9.14326 3.78881 9.46502C3.72394 9.48465 3.65657 9.49475 3.58881 9.49502H1.35981ZM1.31781 18.015C0.994807 18.024 0.704807 17.952 0.461807 17.782C0.151807 17.565 0.00580697 17.223 0.00280697 16.829L0.00580697 9.50602C-0.028193 9.11602 0.086807 8.75802 0.358807 8.49202C0.613807 8.24202 0.946807 8.12402 1.29881 8.13202H3.48381C4.36769 7.85045 5.21035 7.45299 5.98981 6.95002C7.03781 6.27202 7.80981 5.30002 8.10481 4.42402C8.20581 4.12202 8.25981 3.87202 8.36181 3.28402C8.49981 2.49502 8.58581 2.12802 8.78381 1.65602C9.19381 0.674018 9.73181 0.194018 10.4738 0.0330184C11.2038 -0.124982 12.2668 0.296018 12.9388 1.04002C13.6838 1.86402 14.0128 2.89502 13.8908 4.16902C13.8381 4.71569 13.6868 5.33035 13.4368 6.01302C14.2906 5.8885 15.1564 5.8697 16.0148 5.95702C18.0218 6.16202 19.1488 7.46902 18.9848 9.12102C18.9128 9.83302 18.6548 10.438 18.2158 10.913C18.5848 11.624 18.7318 12.327 18.6398 13.013C18.5338 13.803 18.0938 14.461 17.3618 14.972C17.3048 15.665 17.1458 16.218 16.8638 16.632C16.6409 16.9659 16.3511 17.2499 16.0128 17.466C15.9048 18.15 15.6778 18.685 15.3068 19.061C14.6918 19.687 13.5928 20.06 12.5888 19.992C11.6358 19.928 11.0718 19.812 9.74181 19.392C8.86481 19.115 7.04881 18.655 4.31181 18.015H1.31781ZM3.01881 9.18402C3.01854 9.09455 3.03594 9.00591 3.06999 8.92318C3.10405 8.84045 3.1541 8.76525 3.21727 8.70189C3.28044 8.63854 3.35549 8.58827 3.43812 8.55397C3.52075 8.51967 3.60934 8.50202 3.69881 8.50202C3.78811 8.50228 3.87648 8.52013 3.95888 8.55454C4.04128 8.58896 4.1161 8.63927 4.17905 8.7026C4.24201 8.76593 4.29188 8.84104 4.32581 8.92364C4.35974 9.00624 4.37707 9.09472 4.37681 9.18402V16.862C4.37694 16.9513 4.35948 17.0398 4.32543 17.1223C4.29138 17.2049 4.2414 17.2799 4.17835 17.3431C4.1153 17.4064 4.04041 17.4566 3.95796 17.4909C3.8755 17.5252 3.78711 17.5429 3.69781 17.543C3.60851 17.5429 3.52011 17.5252 3.43766 17.4909C3.35521 17.4566 3.28032 17.4064 3.21727 17.3431C3.15422 17.2799 3.10424 17.2049 3.07019 17.1223C3.03613 17.0398 3.01868 16.9513 3.01881 16.862V9.18402Z"/>
                            </svg>
                        </div>
                    </div>
                    <!-- 情報をお寄せください -->
                    <?php if (!empty($area_info)): ?>

                        <?php
                        // ----------------------------
                        // 1. メールアドレスの決定
                        // ----------------------------
                        switch ($area_info['slug']) {
                            case 'kansai':
                                $raw_mail = 'info★hhms.co.jp';
                                break;

                            case 'suita':
                                $raw_mail = 'suita_tokk★hhms.co.jp';
                                break;

                            case 'takarazuka':
                                $raw_mail = 'takarazuka_tokk★hhms.co.jp';
                                break;

                            case 'takatsuki-shimamoto':
                                $raw_mail = 'takatsuki_shimamoto_tokk★hhms.co.jp';
                                break;

                            case 'toyonaka-itami':
                                $raw_mail = 'toyonaka_itami_tokk★hhms.co.jp';
                                break;

                            case 'minoh':
                                $raw_mail = 'minoh_tokk★hhms.co.jp';
                                break;

                            case 'juso-awaji-kamishinjo':
                                $raw_mail = 'juso_awaji_kamishinjo_tokk★hhms.co.jp';
                                break;

                            case 'katsura-arashiyama':
                                $raw_mail = 'katsura_arashiyama_tokk★hhms.co.jp';
                                break;

                            case 'ibaraki-settsu':
                                $raw_mail = 'ibaraki_settsu_tokk★hhms.co.jp';
                                break;

                            case 'kawanishi-ikeda':
                                $raw_mail = 'kawanishi_ikeda_tokk★hhms.co.jp';
                                break;

                            case 'okamoto-mikage':
                                $raw_mail = 'okamoto_mikage_tokk★hhms.co.jp';
                                break;

                            case 'rokko-nada':
                                $raw_mail = 'rokko_nada_tokk★hhms.co.jp';
                                break;

                            case 'amagasaki':
                                $raw_mail = 'amagasaki_tokk★hhms.co.jp';
                                break;

                            case 'nagaokakyo-oyamazaki-muko':
                                $raw_mail = 'nagaokakyo_oyamazaki_muko_tokk★hhms.co.jp';
                                break;

                            case 'umeda':
                                $raw_mail = 'umeda_tokk★hhms.co.jp';
                                break;
                                
                            default:
                                $raw_mail = 'tokk★hhms.co.jp';
                        }

                        // mailto用リンク
                        $contact_href = 'mailto:' . $raw_mail;

                        // ----------------------------
                        // 2. 表示テキスト／title の文言
                        // ----------------------------
                        $title_text = $area_info['name'] . 'エリアの情報をお寄せください';

                        // ----------------------------
                        // 3. 固定案内文
                        // ----------------------------
                        $notice_text = '(★マークを@に変更してください) ＊メールアプリが立ち上がります。';
                        ?>
                        <div class="pdFooter__box pdFooter__linkBtn">
                            <a class="pdFooter__linkBtn--link"
                               href="<?= esc_attr($contact_href); ?>"
                               title="<?= esc_attr($title_text); ?>">

                                <!-- title と同じ内容 -->
                                <p class="pdFooter__title favTitle"><?= esc_html($title_text); ?></p>

                                <!-- 固定案内文 -->
                                <p class="pdFooter__notice"><?= $notice_text; ?></p>

                                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                            </a>
                        </div>

                    <?php endif; ?>

                </div>
            </div>
        </section>
        <!-- ------------------------------------- ↓ pdSideBar ↓　-->
        <?php
        // -------------------------------------
        //ランキング記事情報取得
        // -------------------------------------
        ?>
        <?php
        // エリアが存在する記事の場合、エリアを指定してWPPのクエリで人気記事データ（オブジェクト配列）を取得
        if ($area_info) {
            $popular = new \WordPressPopularPosts\Query([
                'post_type' => 'articles',
                'taxonomy' => 'area',// エリア横断なので未指定
                'term_id' => (string)$area_info['id'], // エリア横断なので未指定
                'limit' => 5,
                'range' => RANKING_RANGE, // functions.phpにて定義
                // 'order_by'=> 'views',     // 既定は views
            ]);
            $area_name = $area_info['name'];

            // エリアが存在しない記事の場合、エリア未指定でWPPのクエリで人気記事データ（オブジェクト配列）を取得
        } else {
            $popular = new \WordPressPopularPosts\Query([
                'post_type' => 'articles',
                // 'taxonomy'  => 'area',// エリア横断なので未指定
                // 'term_id'   => (string) $area_info['id'], // エリア横断なので未指定
                'limit' => 5,
                'range' => RANKING_RANGE, // functions.phpにて定義
                // 'order_by'=> 'views',     // 既定は views
            ]);
            $area_name = "";

        }

        //プラグイン用クエリで取得した情報からランキング投稿IDリストを作成
        $popular_posts_id = [];
        foreach ($popular->get_posts() as $item) {
            $popular_posts_id[] = (int)$item->id;
        }
        $popular_post_count = count($popular_posts_id ?? []);

        //デバッグ用
        // lutwiyo_debug('記事詳細：エリア別ランキング記事ID情報', $popular_posts_id);
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
        // lutwiyo_debug('記事詳細：エリア別ランキング記事情報', $popular_articles);
        // テンプレート呼び出し
        // get_template_part(
        //     'partials/modules/article-list',
        //     'ranking',
        //     [
        //         'articles_info_list' => $popular_articles,
        //         'display_date_label' => $display_date_label
        //     ]
        // );
        ?>

        <section class="pdSideBar" data-boxBgColor="bodySub">
            <div class="stickyTarget pdSideBar__inner">
                <!-- pdSideBar ranking -->
                <div class="pdSideBar__ranking">
                    <p class="fs--17 blockTitle"><?= $area_name ? $area_name . 'の' : '' ?>ランキング
                        <span class="fontEn rankingDate"><?= $display_date_label ?></span>
                    </p>
                    <?php $counter = 0; ?>
                    <?php foreach ($popular_articles as $article) : ?>
                        <?php
                        // lutwiyo_debug($article);
                        // 追加記事タイトル：$article['title']
                        $title = $article['title'];
                        $link = $article['permalink'];
                        $image_url = $article['image_url'];
                        // $is_pinned = $article['is_pinned'];
                        // $is_pinned_class = $article['is_pinned'] ? ' is--pinned' : '';
                        $display_date_label = $article['display_date_label'] ?? '';
                        // lutwiyo_debug($is_pinned_result . $title, $article['area_info'], $article['tag_info']);
                        $counter++;
                        ?>
                        <div class="blockBox blockBox--flex blockBox--ranking">
                            <a class="blockBox__link" href="<?= esc_url($link); ?>"
                               title="<?= esc_attr($title); ?>"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="<?= esc_url($image_url); ?>"
                                                                   alt="<?= esc_attr($title); ?>"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <div class="rankingIcon cornerCover__wrapper" data-boxBgColor="bodySub">
                                    <div class="fontEn rankingIcon__num"><?= esc_attr($counter); ?></div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <?php
                                $rankingBadgeArgs = PostViewHelper::prepare_articles($article);
                                get_template_part('partials/modules/article-access', 'badge', [
                                    'visible' => $rankingBadgeArgs['is_paid_member_limited'] ?? false,
                                    'label' => $rankingBadgeArgs['access_badge_label'] ?? '',
                                    'modifier_class' => $rankingBadgeArgs['access_badge_modifier_class'] ?? '',
                                    'context' => 'list-card',
                                ]);
                                ?>
                                <p class="textHover__target blockTitle">
                                    <?= $article['title'] ?></p>
                                <div class="fontEn date"><p
                                            class="textColor--textGray date--p"><?= $display_date_label; ?></p></div>
                                <ul class="hashList">
                                    <?php foreach ($article['tag_info'] as $tag) :
                                        $tag_name = $tag['name'];
                                        $tag_link = $tag['link'];
                                        ?>
                                        <li class="hashTarget">
                                            <a class="textHoverWrapper hashLink" href="<?= esc_url($tag_link); ?>"
                                               title="<?= esc_attr($tag_name); ?>">
                                                <p class="textHover__target hashTarget--p"><?= esc_attr($tag_name); ?></p>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

                <!-- pdSideBar connCate -->
                <div class="pdSideBar__connCate">
                    <ul class="tagsList">
                        <?php
                        $category_info_list = $global_area_category_info_list ?? $header_category_info_list;
                        $category_url_suffix = ($global_is_area_context) ? "/area/{$global_queried_object->slug}/" : "/category/";
                        ?>
                        <?php foreach ($category_info_list as $category): ?>
                            <li class="tagsTarget"><a class="flex--cc tagsLink"
                                                      href="<?= $category_url_suffix; ?><?php echo esc_attr($category['slug']); ?>/"
                                                      title="<?= esc_attr($category['name']); ?>"><p
                                            class="tagsTarget--p"><?= esc_html($category['name']); ?></p>
                                    <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                                </a></li>
                        <?php endforeach; ?>

                    </ul>
                </div>
                <!-- pdSideBar ad -->
                <?php if ($show_ad): //広告ブロックを表示がONの場合のみ表示 ?>
                    <?php
                    //エリアに紐づかない記事の場合は横断TOPの広告を表示するため$ad_listを空に設定
                    if (!empty($area_info_list)) {
                        $ad_list = $area_info_list[0]['ad_list'];
                    } else {
                        $ad_list = [];
                    }
                    // lutwiyo_debug("■広告リスト（エリア）：",$ad_list);
                    $current_jst_time = get_current_jst(); // 現在の日時をJSTで取得

                    if (!empty($ad_list)) {
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
                                //'orderby' => 'post__in', // 指定した順序で取得
                            ],
                            // 抽出したいACFフィールド名の配列
                            [],
                            // 各投稿に対して追加実行する関数セット
                            []
                        );

                    } else {
                        //エリアの広告が未設定だった場合は横断TOPの広告と同じものを取得する
                        $ad_list = get_field('ad_list', 'option'); // 広告リストを取得
                        // lutwiyo_debug("■広告リスト（横断TOP）：",$ad_list);
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
                                //'orderby' => 'post__in', // 指定した順序で取得
                            ],
                            // 抽出したいACFフィールド名の配列
                            [],
                            // 各投稿に対して追加実行する関数セット
                            []
                        );
                    }


                    if (!empty($ad_info_list)) {
                        // 1. 並び順の基準となる ID 配列を取得
                        $order_ids = wp_list_pluck($ad_list, 'ID'); // 例: [116966, 116910, ...]

                        // 2. ID => 並び順インデックス のマップを作成
                        $order_map = [];
                        foreach ($order_ids as $index => $id) {
                            // 数値or文字列ズレ防止のためキャストしておくと安心
                            $order_map[(string)$id] = $index;
                        }

                        // 3. $ad_info_list から、order_map に存在しないIDを除外
                        $ad_info_list = array_filter($ad_info_list, function ($item) use ($order_map) {
                            return isset($order_map[(string)$item['id']]);
                        });

                        // array_filter でキーが飛ぶので、インデックスを振り直し
                        $ad_info_list = array_values($ad_info_list);

                        // 4. 並び替え（order_map の順番にソート）
                        usort($ad_info_list, function ($a, $b) use ($order_map) {
                            $posA = $order_map[(string)$a['id']];
                            $posB = $order_map[(string)$b['id']];

                            return $posA <=> $posB;
                        });
                    }
                    ?>
                    <?php if (!empty($ad_info_list) || is_array($ad_info_list)) : ?>
                        <div class="pdSideBar__ad stickyTarget">
                            <?php foreach ($ad_info_list as $ad) :
                                // lutwiyo_debug($ad);
                                // =========== 広告エリア ===========
                                // 管理用タイトル：$advertisement['title']
                                // lutwiyo_debug($advertisement['title'] . $advertisement['start_date'] . '〜' . $advertisement['end_date']);
                                // 広告タイプ：$advertisement['ad_type']
                                $ad_type = $ad['ad_type'];
                                $ad_image_url = $ad['image_url'];
                                $ad_text = $ad['text'];
                                $ad_link = $ad['url'];
                                $ad_is_blank = $ad['is_blank'] ? ' target="_blank" rel="noopener noreferrer"' : '';
                                $ad_code = $ad['code'];
                                // 画像URL：$advertisement['image_url']
                                // リンク先URL：$advertisement['url']
                                // 別タブで開くかどうか：$advertisement['is_blank']
                                // 広告コード：$advertisement['code']
                                ?>
                                <?php if ($ad_type === 'tag') : ?>
                                <?= $ad_code; // TODO ここは<script>タグなどコードを直接出力しているが、セキュリティ上問題がないか心配。                  ?>
                            <?php else : ?>
                                <a href="<?= esc_url($ad_link); ?>" <?= $ad_is_blank; ?>
                                   title="<?= esc_attr($ad_text); ?>">
                                    <img src="<?= esc_url($ad_image_url); ?>" alt="<?= esc_attr($ad_text); ?>"
                                         width="630" height="526"
                                         loading="lazy">
                                </a>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </section>
    </article>
    <!-- ------------------------------------- ↓ pageRecommend 吹田のおすすめ記事 ↓　-->

    <!-- ------------------------------------- ↓ 吹田のおすすめ記事 ↓　-->
    <div id="logly-lift-4333540"></div>
    <article class="articlePT articlePB tieupArticle " data-boxBgColor="body">
        <p class="gridWide title fs--22">おすすめ記事</p>
        <section class="gridWide keenSlider__wrapper keenSlider__parts--lefttop">
            <div id="logly-lift-4333539"></div>
            <div id="logly-lift-4333540"></div>
            <script>
                if (window.innerWidth < 960) {
                    var _lgy_lw = document.createElement("script");
                    _lgy_lw.type = "text/javascript";
                    _lgy_lw.charset = "UTF-8";
                    _lgy_lw.async = true;
                    _lgy_lw.src = "https://l.logly.co.jp/lift_widget.js?adspot_id=4333540";
                    var _lgy_lw_0 = document.getElementsByTagName("script")[0];
                    _lgy_lw_0.parentNode.insertBefore(_lgy_lw, _lgy_lw_0);
                } else {
                    var _lgy_lw = document.createElement("script");
                    _lgy_lw.type = "text/javascript";
                    _lgy_lw.charset = "UTF-8";
                    _lgy_lw.async = true;
                    _lgy_lw.src = "https://l.logly.co.jp/lift_widget.js?adspot_id=4333539";
                    var _lgy_lw_0 = document.getElementsByTagName("script")[0];
                    _lgy_lw_0.parentNode.insertBefore(_lgy_lw, _lgy_lw_0);
                }
            </script>
            <style>
                #logly-lift-4333539 .logly-lift-widget-header,
                #logly-lift-4333540 .logly-lift-widget-header {
                    display: none;
                }
            </style>
        </section>
    </article>
    <?php /*
    <article class="articlePT articlePB tieupArticle " data-boxBgColor="body">
        <h2 class="gridWide title fs--22">吹田のおすすめ記事</h2>
        <section class="gridWide keenSlider__wrapper keenSlider__parts--lefttop">
            <div class="sliderWrap" data-keen="true"
                 data-loop="false"
                 data-mode="snap"
                 data-rtl="false"
                 data-origin="auto"
                 data-per-view-pc="4.1"
                 data-spacing-pc="3"
                 data-per-view-tablet="2.2"
                 data-spacing-tablet="0"
                 data-per-view-sp="1.2"
                 data-spacing-sp="3">
                <div class="keen-slider">
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup is--pinned" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--1.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--2.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--3.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--4.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--5.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--6.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- keen dots -->
                <div class="keen-dots fontEn" data-keen-dots></div>
                <!-- keen button -->
                <button type="button" data-keen-prev aria-label="prev">
                    <div class="btnCircle btnShaped" data-shaped="38-38">
                        <div class="btnArrow btnArrow--prev" data-arrow="w-8"></div>
                    </div>
                </button>
                <button type="button" data-keen-next aria-label="next">
                    <div class="btnCircle btnShaped" data-shaped="38-38">
                        <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                    </div>
                </button>
            </div>
            <div class="btn btnShaped btnBgColor btnAll" data-shaped="145-38">
                <a class="flex--cc btnLink" href="/suita/articles/" aria-label="梅田エリアの記事一覧を見る"
                   title="梅田エリアの記事一覧を見る">
                    <p class="btnTtext">すべてみる</p>
                    <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                </a>
            </div>
        </section>
    </article>
 */ ?>
    <!-- ------------------------------------- ↓ 他のエリアのおすすめ記事 ↓　-->
    <?php /*
    <article class="articlePT articlePB tieupArticle " data-boxBgColor="body">
        <h2 class="gridWide title fs--22">他のエリアのおすすめ記事</h2>
        <section class="gridWide keenSlider__wrapper keenSlider__parts--lefttop">
            <div class="sliderWrap" data-keen="true"
                 data-loop="false"
                 data-mode="snap"
                 data-rtl="false"
                 data-origin="auto"
                 data-per-view-pc="4.1"
                 data-spacing-pc="3"
                 data-per-view-tablet="2.2"
                 data-spacing-tablet="0"
                 data-per-view-sp="1.2"
                 data-spacing-sp="3">
                <div class="keen-slider">
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup is--pinned" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--7.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--8.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--9.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--10.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--11.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="keen-slider__slide">
                        <div class="blockBox blockBox--tieup" data-boxBgColor="white">
                            <a class="blockBox__link" href="/suita/articles/0000/"
                               title="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"></a>
                            <div class="blockBox__thum">
                                <div class="thumImg__wrapper"><img class="thumImg" src="/assets/img/sample/thum--12.jpg"
                                                                   alt="【神戸三宮・元町】ランチ決定版！ベスト22！迷わず決まるおすすめ店"
                                                                   loading="lazy" width="1900" height="1270"></div>
                                <!-- favBtn -->
                                <div class="favBtn js--favBtn" role="button">
                                    <svg class="favIcon" aria-label="お気に入り" role="img" viewBox="0 0 20 20">
                                        <title>お気に入り</title>
                                        <path d="M5 2h10a1 1 0 0 1 1 1v15l-6-3.8L4 18V3a1 1 0 0 1 1-1z"/>
                                    </svg>
                                </div>
                                <div class="blockBox__pinIcon cornerCover__wrapper" data-boxBgColor="white">
                                    <div class="icon iconPin">
                                        <div class="mask iconInner">固定</div>
                                    </div>
                                    <div class="cornerCover cornerCover--lb cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                    <div class="cornerCover cornerCover--rt cornerCover--outside">
                                        <div class="mask cornerCover__inner"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="blockBox__info">
                                <p class="fs--15 textHover__target blockTitle">
                                    ＜映画情報＞アメコミヒーローの原点として世界中で愛される『スーパーマン』の新作映画が公開中</p>
                                <div class="blockBox__infoSub--list">
                                    <div class="blockBox__infoSub--target">
                                        <div class="icon iconTime">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontEn fontW--r blockBox__infoSub--text textColor--footer">2min</p>
                                    </div>
                                    <a class="textHoverWrapper blockBox__infoSub--target" href="/suita/"
                                       title="三宮・新開地">
                                        <div class="icon iconMap">
                                            <div class="mask iconInner"></div>
                                        </div>
                                        <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text">
                                            三宮・新開地</p>
                                    </a>
                                </div>
                                <ul class="hashList">
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="感動ランチ"><p
                                                    class="textHover__target hashTarget--p">感動ランチ</p></a>
                                    </li>
                                    <li class="hashTarget">
                                        <a class="textHoverWrapper hashLink" href="/tags/tag-1/" title="神戸沿線"><p
                                                    class="textHover__target hashTarget--p">神戸沿線</p></a>
                                    </li>
                                </ul>
                                <div class="fontEn date"><p class="textColor--textGray date--p">25.12.00</p></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- keen dots -->
                <div class="keen-dots fontEn" data-keen-dots></div>
                <!-- keen button -->
                <button type="button" data-keen-prev aria-label="prev">
                    <div class="btnCircle btnShaped" data-shaped="38-38">
                        <div class="btnArrow btnArrow--prev" data-arrow="w-8"></div>
                    </div>
                </button>
                <button type="button" data-keen-next aria-label="next">
                    <div class="btnCircle btnShaped" data-shaped="38-38">
                        <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                    </div>
                </button>
            </div>
            <div class="btn btnShaped btnBgColor btnAll" data-shaped="145-38">
                <a class="flex--cc btnLink" href="/suita/articles/" aria-label="梅田エリアの記事一覧を見る"
                   title="梅田エリアの記事一覧を見る">
                    <p class="btnTtext">すべてみる</p>
                    <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                </a>
            </div>
        </section>
    </article>
    */ ?>
    <!-- ------------------------------------- ↓ recommendArticle おすすめエリア ↓　-->
    <?php
    // -------------------------------------
    // おすすめエリア
    // -------------------------------------
    ?>


    <?php if (!empty($area_info)) : ?>
        <article class="gridWide articlePT articlePB recommendArticle" data-boxBgColor="bodySub">
            <p class="title fs--22">おすすめエリア</p>
            <?php
            $recommended_area_and_articles_list = is_array($area_info['recommended_area_and_articles_list'] ?? null)
                ? $area_info['recommended_area_and_articles_list']
                : [];
            foreach ($recommended_area_and_articles_list as $recommended_area_and_articles) {
                $area_info_list_in_loop = TermModelHelper::get_terms_payload(
                    'area',
                    [$recommended_area_and_articles['area']], // ここはIDで渡ってくるので注意
                    [],
                    []
                );
                $area_info_in_loop = $area_info_list_in_loop[0] ?? [];
                if (!is_array($area_info_in_loop)) {
                    $area_info_in_loop = [];
                }

                if (empty($area_info_in_loop)) continue;
                $pinned_list = $recommended_area_and_articles['pinned_list'] ?? [];
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
                $raw_query = null;
                $additional_area_articles_info_list = PostModelHelper::get_posts_payload(
                    [
                        'post_type' => 'articles',
                        'posts_per_page' => (5 - $pinned_count),
                        'tax_query' => [
//                            [
//                                'taxonomy' => 'category',
//                                'field' => 'slug',
//                                'terms' => $area_info_in_loop['slug'],
//                            ],
                            [
                                'taxonomy' => 'area',
                                'field' => 'id',
                                'terms' => $recommended_area_and_articles['area'],
                            ],
                        ],
                        'post__not_in' => wp_list_pluck($pinned_articles_info_list, 'id'), // ピン留め記事を除外
                    ],
                    // 抽出したいACFフィールド名の配列
                    [],
                    // 各投稿に対して追加実行する関数セット
                    [],
                    $raw_query
                );
                // lutwiyo_debug('pinned_list', wp_list_pluck($pinned_articles_info_list, 'id'));
                // lutwiyo_debug('raw query',$raw_query->query);

                $pinned_articles_info_list = array_map(
                    fn($item) => $item + ['is_pinned' => true],
                    $pinned_articles_info_list ?? []
                );

                // ピン留め記事と追加記事を統合
                $integrated_area_articles_info_list = array_merge(
                    $pinned_articles_info_list,
                    $additional_area_articles_info_list
                );

                // テンプレート呼び出し
                get_template_part(
                    'partials/modules/article-list',
                    'area',
                    [
                        'area_info' => $area_info_in_loop,
                        'integrated_articles_info_list' => $integrated_area_articles_info_list,
                        'view_all_url' => '' // TODO: リンク先を動的に設定する
                    ]
                );
            }
            ?>
        </article>
    <?php endif; ?>
</main>


<?php get_footer(); ?>
