<?php
/**
 * テンプレートで共通利用するグローバル変数の初期化。
 *
 * WordPressのクエリ実行後に呼び出される `wp` フックでセットすることで、
 * テンプレート読み込み前に値が揃った状態を担保する。
 */
function lutwiyo_setup_globals()
{
    global $global_is_home;
    $global_is_home = is_front_page() && is_home();

    global $global_queried_object;
    $global_queried_object = get_queried_object();

    global $global_is_area_context;
    $global_is_area_context = ($global_queried_object instanceof WP_Term && $global_queried_object->taxonomy === 'area');

    global $global_area_info_list;
    $global_area_info_list = TermModelHelper::get_terms_payload(
        'area',
        [
            'hide_empty' => false,
        ],
        [
            'shortname',
        ],
        []
    );

    // home.phpなどで再利用する場合があるため、グローバル変数として保持
    global $global_header_area_info_list;
    $global_header_area_info_list = $global_area_info_list;

    global $global_category_info_list;
    $global_category_info_list = TermModelHelper::get_terms_payload(
        'category',
        [
            'hide_empty' => true,
        ],
        [],
        []
    );

    global $global_area_category_info_list;
    $global_area_category_info_list =
        ($global_is_area_context && !empty($global_queried_object->slug))
            ? TermModelHelper::get_terms_payload('category', [
                'object_ids' => get_posts([
                    'post_type' => 'articles',
                    'fields' => 'ids',
                    'posts_per_page' => -1,
                    'tax_query' => [[
                        'taxonomy' => 'area',
                        'field' => 'slug',
                        'terms' => $global_queried_object->slug,
                    ]],
                ]),
                'hide_empty' => true,
            ])
            : null;
    global $global_tag_info_list;
    $global_tag_info_list = TermModelHelper::get_terms_payload(
        'post_tag',
        [
            'hide_empty' => false,
        ],
        [],
        []
    );
}
add_action('wp', 'lutwiyo_setup_globals');
