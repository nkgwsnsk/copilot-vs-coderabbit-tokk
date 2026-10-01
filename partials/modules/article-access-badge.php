<?php
/**
 * Module: article access badge
 *
 * @param array $args {
 * @type bool $visible バッジを表示するか
 * @type string $label 表示文言
 * @type string $modifier_class 見た目差し替え用の modifier class
 * @type string $context 描画コンテキスト
 * }
 */

$visible = (bool) ($args['visible'] ?? false);
if (!$visible) {
    return;
}

$label = (string) ($args['label'] ?? '');
if ($label === '') {
    return;
}

$modifierClass = trim((string) ($args['modifier_class'] ?? ''));
$context = trim((string) ($args['context'] ?? 'list-card'));
?>
<div class="articleAccessBadgeWrap articleAccessBadgeWrap--<?php echo esc_attr($context); ?>">
    <span class="articleAccessBadge <?php echo esc_attr($modifierClass); ?>">
        <?php echo esc_html($label); ?>
    </span>
</div>
