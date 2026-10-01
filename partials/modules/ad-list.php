<?php
/**
 * Module: ad list
 *
 * @param array $args {
 * @type array $ad_info_list 広告一覧
 * }
 */
$ad_info_list = $args['ad_info_list'] ?? [];
if (function_exists('lutwiyo_can_view_advertisement') && !lutwiyo_can_view_advertisement()) {
    return;
}
if (empty($ad_info_list) || !is_array($ad_info_list)) {
    return; // データがない場合は何も出力しない
} ?>
<section class="gridWide cmSection">
    <ul class="cmList">
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
            <li class="cmTarget">
                <?php if ($ad_type === 'tag') : ?>
                    <?= $ad_code; // TODO ここは<script>タグなどコードを直接出力しているが、セキュリティ上問題がないか心配。  ?>
                <?php else : ?>
                    <a class="cmLink"
                       href="<?= esc_url($ad_link); ?>"
                       title="<?= esc_attr($ad_text); ?>"
                        <?= $ad_is_blank; ?>>
                        <img class="thumImg"
                             src="<?= esc_url($ad_image_url); ?>"
                             alt="<?= esc_attr($ad_text); ?>"
                             width="630" height="524" loading="lazy">
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
