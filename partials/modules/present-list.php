<?php
/**
 * Module: present list
 *
 * @param array $args {
 * @type array $present_info_list プレゼント一覧
 * }
 */
$present_info_list = $args['present_info_list'] ?? [];
if (empty($present_info_list) || !is_array($present_info_list)) {
    return; // データがない場合は何も出力しない
} ?>
<?php
// TODO : すべてみるのリンク先を動的に変更する?
// TODO: テンプレートファイルでは終了したプレゼントも表示対象になっているのでどちらが正しいか整理して実装する
?>
<section class="keenSlider__wrapper flexColumnBox borderBox presentSection">
    <h2 class="title fs--19">今月のプレゼント</h2>
    <div class="presentSlider sliderWrap" data-keen="true"
         data-loop="false"
         data-mode="snap"
         data-rtl="false"
         data-origin="auto"
         data-per-view-pc="1.03"
         data-spacing-pc="0"
         data-per-view-tablet="1.03"
         data-spacing-tablet="0"
         data-per-view-sp="1"
         data-spacing-sp="20">
        <div class="keen-slider">
            <?php foreach ($present_info_list as $present): ?>
                <?php
                // lutwiyo_debug($present);
                $title = $present['title'] ?? '';
                $description = $present['text'] ?? '';
                $image_url = $present['image_url'] ?? '';
                $link = $present['url'] ?? '';
                $link_type = $present['link_type'];
                switch ($link_type) {
                    case 'present_detail':
                        // 記事詳細にリンク
                        $link_label = '記事を読んでプレゼントに応募する';
                        break;
                    case 'present_list':
                        // プレゼント一覧にリンク
                        $link_label = 'プレゼントに応募する';
                        break;
                    case 'expired':
                        // 応募期間終了
                        $link_label = '終了しました';
                        break;
                    default:
                        // デフォルトは内部リンクとして扱う
                        $link = home_url($link);
                        break;
                }
                if ($link_type === 'present_list') {
                    $presentActionGate = lutwiyo_get_member_benefit_action_gate($link, 'present');
                    $link = (string) ($presentActionGate['href'] ?? $link);
                    $presentLinkAttrs = lutwiyo_get_member_benefit_action_attrs($presentActionGate);
                } else {
                    $presentLinkAttrs = '';
                }
                ?>
                <div class="keen-slider__slide">
                    <div class="blockBox blockBox--flex">
                        <a class="blockBox__link<?php echo $presentLinkAttrs === '' ? '' : ' js--memberBenefitActionGate'; ?>"
                           href="<?= esc_url($link); ?>"
                           <?php echo $presentLinkAttrs; ?>
                           title="<?= esc_attr($title); ?>"></a>
                        <div class="blockBox__thum">
                            <div class="thumImg__wrapper"><img class="thumImg"
                                                               src="<?= esc_attr($image_url); ?>"
                                                               alt="<?= esc_attr($title); ?>"
                                                               loading="lazy" width="1900" height="1270"></div>
                        </div>
                        <div class="blockBox__info" data-boxBgColor="body">
                            <p class="fs--17 textHover__target blockTitle"><?= esc_text_with_br($title); ?></p>
                            <p class="fontW--r textColor--footer blockSubText">
                                <?= esc_text_with_br($description); ?></p>
                            <div class="btn btnShaped btnShaped__border" data-shaped="auto-38">
                                <div class="flex--cc btnLink">
                                    <p class="btnTtext"><?= esc_html($link_label); ?></p>
                                    <?php if ($link_type !== 'expired'): ?>
                                        <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- keen dots -->
        <div class="keen-dots fontEn" data-keen-dots></div>
        <!-- keen button -->
        <button type="button" data-keen-prev aria-label="prev">
            <div class="btnShaped" data-shaped="30-30">
                <div class="btnArrow btnArrow--prev" data-arrow="w-8"></div>
            </div>
        </button>
        <button type="button" data-keen-next aria-label="next">
            <div class="btnShaped" data-shaped="30-30">
                <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
            </div>
        </button>
        <?php //TODO: すべてみるボタンのリンク先を正しく設定する ?>
        <div class="btn btnShaped btnBgColor btnAll" data-shaped="120-28">
            <a class="flex--cc btnLink" href="<?php echo esc_url(lutwiyo_get_member_benefit_entry_url(home_url('/present/'))); ?>" aria-label="すべてみる" title="すべてみる">
                <p class="btnTtext">すべてみる</p>
                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
            </a>
        </div>
    </div>
</section>
