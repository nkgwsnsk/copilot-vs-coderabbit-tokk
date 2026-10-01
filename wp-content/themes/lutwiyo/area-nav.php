<?php
/**
 * エリアナビゲーション共通パーツ
 *
 * @param array $header_area_info_list  エリア情報配列
 * @param string $current_slug          現在のスラッグ（オプション）
 */

// 引数が渡されていない場合に備えてデフォルト値
$header_area_info_list = get_query_var('header_area_info_list', []);
$current_slug = get_query_var('current_slug', '');

?>

<article class="grid keenSlider__wrapper areaNav">
    <div class="sliderWrap" data-keen="true"
        data-loop="false"
        data-mode="snap"
        data-rtl="false"
        data-origin="auto"
        data-per-view-pc="auto"
        data-spacing-pc="12"
        data-per-view-tablet="auto"
        data-spacing-tablet="10"
        data-per-view-sp="auto"
        data-spacing-sp="8">
        <div class="keen-slider">
            <div class="keen-slider__slide">
                <div class="areaNav__target" role="tab" aria-selected="<?php echo $current_slug === '' ? 'true' : 'false'; ?>" data-area="all">
                    <a class="areaNav__link" href="/" title="すべて">
                        <p class="areaNav__link--p">すべて</p>
                    </a>
                </div>
            </div>
            <?php foreach ($header_area_info_list as $area): ?>
                <div class="keen-slider__slide">
                    <div class="areaNav__target"
                        role="tab"
                        aria-selected="<?php echo ($current_slug === $area['slug']) ? 'true' : 'false'; ?>"
                        data-area="<?php echo esc_attr($area['slug']); ?>">
                        <a class="areaNav__link"
                            href="/area/<?php echo esc_html($area['slug']); ?>"
                            title="<?php echo esc_html($area['name']); ?>">
                            <p class="areaNav__link--p"><?php echo esc_html($area['shortname']); ?></p>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</article>
