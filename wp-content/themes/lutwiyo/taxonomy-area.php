<?php
/**
 * taxonomy-area.php
 * areaタクソノミーの共通テンプレート
 */

$post_type = get_query_var('post_type');
$areacat = get_query_var('areacat'); // ← ここ変更

$template = 'default';

if ($post_type === 'articles' && empty($areacat)) {
    $template = 'articles';
} elseif ($post_type === 'articles' && !empty($areacat)) {
    $template = 'category';
}

get_template_part('partials/taxonomy/area', $template);

global $wp_query;
// lutwiyo_debug($post_type, $category, $template,$wp_query);
