<?php
/**
 * Module: more tags
 *
 * @param array $args {
 * @type array $tag_list 現在のエリア情報の配列
 * @type string $type タグの種類 'category' or 'tag'
 * }
 */
$tag_list = $args['tag_list'] ?? [];
if (empty($tag_list) || !is_array($tag_list)) {
    return; // データがない場合は何も出力しない
}
$type = (string) ($args['type'] ?? 'tag');
$more_section_class = ($type === 'category') ? 'moreSection__cate' : '';
$hash_link_class = ($type !== 'category') ? 'hashLink' : '';
?>
<div class="gridWide">
    <section class="moreSection <?= esc_attr($more_section_class) ?>" data-boxBgColor="bodySub">
        <div class="moreSection__toggle">
            <ul class="tagsList">
                <?php foreach ($tag_list as $tag_term) :
                    $term_name = $tag_term->name;
                    if ($type === 'category') {
                        $term_link = home_url('category/') . $tag_term->slug . '/';
                    } else {
                        $term_link = home_url('tag/') . $tag_term->slug . '/';
                    }
                    ?>
                    <li class="tagsTarget"><a
                                class="flex--cc tagsLink"
                                href="<?= esc_url($term_link); ?>"
                                title="#<?= esc_attr($term_name); ?>"><p
                                    class="<?= esc_attr($hash_link_class); ?> tagsTarget--p"><?= esc_html($term_name); ?></p></a></li>

                <?php endforeach; ?>
            </ul>
        </div>
        <div class="btn btnShaped btnBgColor btnAll js--btnMore" data-shaped="145-38" role="button">
            <div class="flex--cc btnLink" aria-label="すべてみる"
                 title="すべてみる">
                <p class="btnTtext">すべてみる</p>
                <div class="btnArrow btnArrow--down" data-arrow="w-8"></div>
            </div>
        </div>
    </section>
</div>
