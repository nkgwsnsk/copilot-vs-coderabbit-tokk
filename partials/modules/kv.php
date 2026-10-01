<?php
/**
 * Module: article list area
 *
 * @param array $args {
 * @type array $area_info エリア情報
 * }
 */

$area_info = $args['area_info'] ?? [];
if (empty($area_info) || !is_array($area_info)) {
    return; // データがない場合は何も出力しない
} ?>
<?php
$name = $area_info['name'] ?? '';
$link = home_url('/area/') . $area_info['slug'] . '/';
$image_url = $area_info['image_url'] ?? '';

// lutwiyo_debug($area_info);
// -------------------------------------
// 天気予報情報取得
//get_weatherを呼び出して取得可能な要素サンプル
//array(5) { ["label"]=> string(15) "豊中・伊丹" ["lat"]=> float(34.785) ["lon"]=> float(135.438) ["memo"]=> NULL ["data"]=> array(4) { ["weather"]=> array(3) { ["main"]=> string(6) "Clouds" ["description"]=> string(3) "雲" ["icon"]=> string(3) "03d" } ["temp"]=> float(21.91) ["name"]=> string(6) "久代" ["source"]=> string(22) "openweathermap_current" } }
// -------------------------------------
$weather = get_weather($area_info['slug']);
$raw_temp = $weather['data']['temp'] ?? '';
$normalized_temp = preg_replace('/[°℃\s]+/u', '', (string) $raw_temp);
$formatted_temp = $normalized_temp === '' ? '' : $normalized_temp;

// lutwiyo_debug("天気予報情報");
// lutwiyo_debug("{$area_info['name']}の天気：" . $weather["data"]["weather"]["main"]);
// lutwiyo_debug("{$area_info['name']}の気温：" . $weather["data"]["temp"]);
?>
<article class="grid kv">
    <section class="kvInner">
        <div class="kvBg">
            <img class="kvBg__img"
                 src="<?= esc_url($image_url); ?>"
                 alt="TOKK <?= esc_attr($name); ?>>"
                 loading="lazy" width="2650" height="410">
        </div>
        <?php if (is_singular('articles')): ?>
            <div class="kvTitle">
                <?php // <p class="fontEn topKv__title topKv__title--en">TOKK</p> ?>
                <p class="topKv__title topKv__title--jp"><?= esc_html($name); ?> TOKK</p>
            </div>
        <?php else : ?>
            <h1 class="kvTitle">
                <?php // <p class="fontEn topKv__title topKv__title--en">TOKK</p> ?>
                <p class="topKv__title topKv__title--jp"><?= esc_html($name); ?> TOKK</p>
            </h1>
        <?php endif; ?>
        <a class="kvLink" href="<?= esc_html($link); ?>" title="<?= esc_attr($name); ?>"></a>
        <?php // TODO 天気情報を実装する ?>
        <!-- weather -->
        <?php if (!is_wp_error($weather) && !empty($weather['data'])) : ?>
            <div class="flex--cc weather cornerCover__wrapper" data-boxBgColor="body">
                <div class="cornerCover cornerCover--lb cornerCover--outside">
                    <div class="mask cornerCover__inner"></div>
                </div>
                <div class="cornerCover cornerCover--rt cornerCover--outside">
                    <div class="mask cornerCover__inner"></div>
                </div>
                <div class="fontEn fontEn--sb weather__inner">
                    <div class="weatherInfo">
                        <div class="weatherIcon  <?= esc_attr($weather["data"]["weather"]["icon"]); ?>">
                            <div class="mask mask__bgColor--text weatherIcon__inner"></div>
                        </div>
                        <p class="weatherInfo__title"><?= esc_attr($weather["data"]["weather"]["main"]); ?></p>
                    </div>
                    <p class="weatherInfo__num"><?= esc_html($formatted_temp); ?></p>
                </div>
            </div>
        <?php endif; ?>
        <!-- kvInfo -->
        <div class="flex--cc kvInfo cornerCover__wrapper" data-boxBgColor="body">
            <div class="cornerCover cornerCover--lb">
                <div class="mask cornerCover__inner"></div>
            </div>
            <div class="cornerCover cornerCover--rt">
                <div class="mask cornerCover__inner"></div>
            </div>
            <div class="kvInfo__inner">
                <nav class="breadcrumbs" aria-label="Breadcrumbs" role="navigation">
                    <ol class="breadcrumbs__list" vocab="http://schema.org/" typeof="BreadcrumbList">
                        <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                            <a class="breadcrumbs__link" href="/" property="item" typeof="WebPage">
                                <span property="name">トップ</span>
                            </a>
                            <meta property="position" content="1">
                        </li>
                        <li class="breadcrumbs__target" property="itemListElement" typeof="ListItem">
                            <span property="name"><?= esc_html($name); ?></span>
                            <meta property="position" content="2">
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
    </section>
</article>
