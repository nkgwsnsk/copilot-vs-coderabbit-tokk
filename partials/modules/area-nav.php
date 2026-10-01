<?php
/**
 * エリアナビゲーション共通パーツ
 *
 * @param array $header_area_info_list エリア情報配列
 * @param string $current_slug 現在のスラッグ（オプション）
 */
// TODO: 固定ページ系のときにすべてが反応するように処理を追加する
// TODO: エリアが複数備わっているページでのエリア判定ができていないので対応する
// TODO: デザインが修正されたら最新のデザインを適用する
global $global_is_home;
$is_home_value = $global_is_home ? 'true' : 'false';
global $global_header_area_info_list;
$header_area_info_list = $global_header_area_info_list;
// lutwiyo_debug($header_area_info_list);
// -------------------------------------
// エリアを強制指定する場合のパラメータ
// TODO この処理ここに書いていいのか？要検討 テンプレートなのでなるべくここにロジック入れたくない
// -------------------------------------
$force_area_info = $args['area_info'] ?? [];
if (!empty($force_area_info) || !is_array($force_area_info)) {
    $force_slug = $force_area_info['slug'] ?? '';
    $header_area_info_list = array_map(function ($area) use ($force_slug) {
        if (($area['slug'] ?? '') === $force_slug) {
            $area['is_current_area'] = true;
        }
        return $area;
    }, $header_area_info_list ?? []);
}
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
                <div class="areaNav__target" role="tab" aria-selected="<?= $is_home_value ?>" data-area="all">
                    <a class="areaNav__link" href="<?= home_url('/'); ?>" title="すべて">
                        <p class="areaNav__link--p">すべて</p>
                    </a>
                </div>
            </div>
            <?php foreach ($header_area_info_list as $area_info): ?>
                <?php $is_current_area_value = $area_info['is_current_area'] ? 'true' : 'false'; ?>
                <div class="keen-slider__slide">
                    <div class="areaNav__target"
                         role="tab"
                         aria-selected="<?= $is_current_area_value; ?>"
                         data-area="<?= esc_attr($area_info['slug']); ?>">
                        <a class="areaNav__link"
                           href="<?= home_urL('/area/') . esc_html($area_info['slug']) . '/' ; ?>"
                           title="<?= esc_html($area_info['name']); ?>">
                            <p class="areaNav__link--p"><?php echo esc_html($area_info['shortname']); ?></p>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</article>
