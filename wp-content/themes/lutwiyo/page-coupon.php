<?php get_header(); ?>
<?php
// ▼ JSONの配置場所（指示どおり）
$json_path = WP_CONTENT_DIR . '/json/coupon.json';

// ▼ サイトのタイムゾーン（WordPress設定に追従）
$tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Asia/Tokyo');
$now = new DateTimeImmutable('now', $tz);

// ▼ 日本語曜日
$w_jp = ['日','月','火','水','木','金','土'];

// ▼ 日付フォーマット: 2025年8月24日（日）
$fmt_jp_date = function (?DateTimeInterface $dt) use ($w_jp) {
  if (!$dt) return '';
  return $dt->format('Y年n月j日') . '（' . $w_jp[(int)$dt->format('w')] . '）';
};

// ▼ JSON読み込み＆パース
$list = [];
if (is_readable($json_path)) {
  $raw = file_get_contents($json_path);
  $json = json_decode($raw, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
    $list = isset($json['data']) && is_array($json['data']) ? $json['data'] : [];
  }
}

// ▼ 開始・終了の間だけに絞る
$filtered = [];
foreach ($list as $row) {
  $start_s = $row['start_date'] ?? '';
  $end_s   = $row['end_date'] ?? '';
  if ($start_s === '' || $end_s === '') continue;

  // "YYYY-mm-dd HH:ii:ss" をサイトTZで解釈
  $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start_s, $tz) ?: null;
  $end   = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $end_s, $tz) ?: null;
  if (!$start || !$end) continue;

  if ($now >= $start && $now <= $end) {
    // 表示用に整形した値も持たせておく
    $row['_end_view'] = $fmt_jp_date($end);
    $filtered[] = $row;
  }
}

// ▼ 例として期限が近い順にソート（任意）
usort($filtered, function($a, $b) {
  return strcmp($a['end_date'] ?? '', $b['end_date'] ?? '');
});

// echo "<br><br><br><br><br><br><br>";
// lutwiyo_debug("JSON",$json_path);
// lutwiyo_debug("クーポン一覧",$filtered);
?>

    <!-- ==================================================================== ↓ wrapper ↓ -->
    <main class="main wrapper" role="main">
        <!-- ------------------------------------- ↓ kv ↓　-->
        <article class="grid kv">
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
                            <span property="name">クーポンを探す</span>
                            <meta property="position" content="2">
                        </li>
                    </ol>
                </nav>
            </div>
        </article>
        <!-- ------------------------------------- ↓ areaNav ↓　-->
    		<?php
            get_template_part(
                'partials/modules/area',
                'nav'
            );
    		?>
        <!-- ------------------------------------- ↓ tag 休日スポット ↓　-->
        <article class="articlePT articlePB latestArticle" data-boxBgColor="body">
            <section class="gridWide latestSection">
                <h1 class="title fs--22">クーポンを探す</h1>


            <section class="couponSection">
                <div class="flexColumn flexColumn--3">
                  <?php if (empty($filtered)) : ?>
                      <p>現在、利用可能なクーポンはありません。</p>
                    <?php else : ?>
                      <?php foreach ($filtered as $c) :
                        $url       = (string)($c['url'] ?? '');
                        $couponActionGate = lutwiyo_get_member_benefit_action_gate($url, 'coupon');
                        $couponLinkAttrs = lutwiyo_get_member_benefit_action_attrs($couponActionGate);
                        $img       = (string)($c['image_url'] ?? '');
                        $name      = (string)($c['name'] ?? '');
                        $end_view  = (string)($c['_end_view'] ?? '');
                        $facilities  = $c['facilities'] ?? '';
                        // altはnameを利用
                        // is--new 判定（start_date が NEW_ARRIVAL_DAYS 日以内）
                        // すでに $tz と $now は上部で定義済み
                        $start_s = (string)($c['start_date'] ?? '');
                        $start   = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $start_s, $tz) ?: null;

                        // NEW_ARRIVAL_DAYS が未定義ならフォールバック（3日）
                        if (!defined('NEW_ARRIVAL_DAYS')) {
                          define('NEW_ARRIVAL_DAYS', 3);
                        }
                        $is_new = false;
                        if ($start) {
                          $is_new = ( $now->getTimestamp() - $start->getTimestamp() ) < ( NEW_ARRIVAL_DAYS * DAY_IN_SECONDS );
                        }
                        // クラス組み立て
                        $classes = 'blockBox couponBlock';
                        if ($is_new) {
                          $classes .= ' is--new';
                        }
                        ?>
                        <div class="<?= esc_attr($classes); ?>" data-boxBgColor="white">
                          <a class="blockBox__link<?php echo ($couponActionGate['mode'] ?? 'direct') === 'direct' ? '' : ' js--memberBenefitActionGate'; ?>"
                             href="<?= esc_url((string) ($couponActionGate['href'] ?? $url)); ?>"
                             <?php echo $couponLinkAttrs; ?>
                             <?php if (($couponActionGate['mode'] ?? 'direct') === 'direct') : ?>target="_blank"<?php endif; ?>
                             aria-label="クーポン獲得"
                             title="クーポン獲得"></a>

                          <div class="blockBox__thum">
                            <div class="thumImg__wrapper">
                              <img class="thumImg"
                                   src="<?= esc_url($img); ?>"
                                   alt="<?= esc_attr($name); ?>"
                                   loading="lazy" width="1900" height="1270">
                            </div>
                          </div>

                          <div class="blockBox__info">
                            <p class="fs--15 textHover__target blockTitle">
                              <?= esc_html($name); ?>
                            </p>
                            <?php foreach ($facilities as $f) : ?>
                              <div class="blockBox__infoSub--list">
                                  <a class="textHoverWrapper blockBox__infoSub--target" href="<?= esc_url($f['google_map_url']); ?>" title="<?= esc_attr($f['name']); ?>" target="_blank">
                                      <div class="icon iconMap"><div class="mask iconInner"></div></div>
                                      <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text"><?= esc_attr($f['name']); ?></p>
                                  </a>
                              </div>
                            <?php endforeach ?>
                            <dl class="blockBoxInfo__detail">
                              <dt class="fontW--r textColor--textGray blockBoxInfo__detail--title">有効期限</dt>
                              <dd class="blockBoxInfo__detail--text">
                                <p><?= esc_html($end_view); ?>まで</p>
                              </dd>
                            </dl>

                            <div class="btn btnShaped btnBgColor btnAll" data-shaped="auto-38">
                              <div class="flex--cc btnLink">
                                <p class="btnTtext">クーポン獲得</p>
                                <div class="btnArrow btnArrow--next" data-arrow="w-12"></div>
                              </div>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            </section>
        </article>
    </main>


<?php get_footer(); ?>
