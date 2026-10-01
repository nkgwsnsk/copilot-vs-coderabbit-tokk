<?php
/**
 * archive-gallery.php
 * ギャラリー投稿のゲートウェイテンプレート
 */

// 現在のタグスラッグを取得（例：/gallery/art → art）
$tag_slug = get_query_var('gallery_tag');

global $wp_query;
// lutwiyo_debug($wp_query);

$tag_info = TermModelHelper::get_terms_payload(
    'post_tag',
    [
        'slug' => $tag_slug,
    ],
    [],
    []
);
// lutwiyo_debug($tag_info);
if ($tag_slug) {
    get_template_part(
        'partials/gallery/tag',
        'detail',
        [
            'tag_slug' => $tag_slug,
            'tag_info' => $tag_info[0] ?? null,
        ]
    );
} else {
    get_template_part('partials/gallery/tag', 'all');
}
?>
