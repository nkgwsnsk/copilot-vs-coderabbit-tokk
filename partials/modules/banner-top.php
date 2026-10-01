<?php
/**
 * Module: banner top
 *
 * @param array $args {
 * @type array $banner_list バナーリスト
 * }
 */
$banner_list = $args['banner_list'] ?? [];
if (empty($banner_list) || !is_array($banner_list)) {
    return; // データがない場合は何も出力しない
} ?>
<section class="gridWide bannerSection">
    <ul class="bannerList">
        <?php foreach ($banner_list as $banner): ?>
            <?php
            // =========== バナーエリア ===========
            $image_url = get_image_url($banner['image']);
            $link = $banner['url'] ?? '';
            $title = $banner['text'] ?? '';
            $is_blank_attr = $banner['is_blank'] ? ' target="_blank" rel="noopener noreferrer"' : '';
            ?>
            <li class="bannerTarget">
                <a class="bannerLink"
                   href="<?php echo esc_url($link); ?>"
                   title="<?= esc_attr($title); ?>"
                    <?= $is_blank_attr; ?>
                >
                    <img class="thumImg"
                         src="<?= esc_url($image_url); ?>"
                         alt="<?= esc_attr($title); ?>"
                         width="1268" height="550">
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>