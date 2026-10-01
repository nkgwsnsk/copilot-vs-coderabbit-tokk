<?php get_header(); ?>
<?php //スタッフ情報  ?>
<?php
// TODO:ざっくりコードレビューする。
//スタッフ情報＆スタッフグループの情報を取得します。
// -------------------------------------
// スタッフ（ピン留め優先 → 非ピン留めを更新順で）
// -------------------------------------

$staffs_info_list = TermModelHelper::get_terms_payload(
  'staff_group',
  ['hide_empty' => false],
  // ← ここで ACF の pinned_staff_list を一緒に取得
  ['pinned_staff_list']
);
// lutwiyo_debug("スタッフグループ（ACF付き）", $staffs_info_list);

$staff_by_group = []; // [slug => ['term'=>..., 'posts'=>payload[]]]

// ACF pinned_staff_list の要素 → ID配列に正規化
$normalize_pinned_ids = function($raw) {
  $ids = [];
  if (empty($raw) || !is_array($raw)) return $ids;

  foreach ($raw as $item) {
    if ($item instanceof WP_Post) {
      $ids[] = (int)$item->ID;
    } elseif (is_numeric($item)) {
      $ids[] = (int)$item;
    } elseif (is_array($item) && isset($item['ID'])) { // 念のため
      $ids[] = (int)$item['ID'];
    }
  }
  // 順序は保持しつつ重複除去
  $seen = [];
  $out  = [];
  foreach ($ids as $id) {
    if (isset($seen[$id])) continue;
    $seen[$id] = true;
    $out[] = $id;
  }
  return $out;
};

foreach ($staffs_info_list as $g) {
  $term_id = (int) ($g['id'] ?? 0);
  $slug    = $g['slug'] ?? '';
  if (!$term_id) continue;

  // 1) ピン留め ID 群を抽出（ACF戻り値の形式差を吸収）
  $pinned_ids = $normalize_pinned_ids($g['pinned_staff_list'] ?? []);

  // 1-1) 安全策：このグループに属していない ID を除外
  if (!empty($pinned_ids)) {
    $pinned_ids = array_values(array_filter($pinned_ids, function($pid) use ($term_id) {
      return has_term($term_id, 'staff_group', $pid);
    }));
  }

  // 2) ピン留め payload（ID配列 → 指定順のまま取得）
  $pinned_payload = !empty($pinned_ids)
    ? PostModelHelper::get_posts_payload(
        $pinned_ids,
        ['image','text_short','role'] // 必要な ACF
      )
    : [];

  // 3) 非ピン留めを更新順（modified DESC）で取得し、ピン留めを除外
  $non_pinned_payload = PostModelHelper::get_posts_payload(
    [
      'post_type'      => 'staff',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'post__not_in'   => array_column($pinned_payload, 'id'),
      'tax_query'      => [
        [
          'taxonomy'         => 'staff_group',
          'field'            => 'term_id',
          'terms'            => [$term_id],
          'include_children' => false,
        ],
      ],
      'orderby'        => 'modified',
      'order'          => 'DESC',
    ],
    ['image','text_short','role']
  );

  // 4) 結合（ピン留め → 非ピン留め）。念のための重複排除。
  $final = [];
  $seen  = [];
  foreach (array_merge($pinned_payload, $non_pinned_payload) as $p) {
    $pid = (int)($p['id'] ?? 0);
    if (!$pid || isset($seen[$pid])) continue;
    $seen[$pid] = true;
    $final[] = $p;
  }

  $staff_by_group[$slug] = [
    'term'  => $g,
    'posts' => $final,
  ];

  // デバッグ（任意）
  // lutwiyo_debug("{$slug} のピン留めID", $pinned_ids);
  // lutwiyo_debug("スタッフ情報（並び順整形済み）", $final);
}
?>

    <!-- ==================================================================== ↓ wrapper ↓ -->
    <main class="main wrapper" role="main">
        <!-- ------------------------------------- ↓ kv ↓　-->



        <?php // 表示確認用ロジックです。こちらをベースにテンプレートに埋め込んでください。
        // if (!empty($staff_by_group) && is_array($staff_by_group)) {
        //   foreach ($staff_by_group as $group_slug => $group) {
        //     // グループ名
        //     $group_name = $group['term']['name'] ?? $group_slug;
        //     lutwiyo_debug('【グループ名】:'. $group_name);
		//
        //     $posts = $group['posts'] ?? [];
        //     if (empty($posts)) {
        //       lutwiyo_debug('（このグループにスタッフは居ません）', ['group_slug' => $group_slug]);
        //       continue;
        //     }
		//
        //     //$iはデバッグ表示用です。なくてもOKです。
        //     $i = 1;
        //     foreach ($posts as $p) {
        //       // image は image_url 優先。なければ image（ACF配列 or 文字列）からURLを抽出
        //       $image_url = $p['image_url'] ?? '';
        //       if (!$image_url) {
        //         $img = $p['image'] ?? '';
        //         if (is_array($img)) {
        //           $image_url = $img['url'] ?? '';
        //         } elseif (is_string($img)) {
        //           $image_url = $img; // 文字列URLが入っている場合
        //         }
        //       }
		//
        //       // role は配列/文字列両対応（配列ならカンマ区切りで見やすく）
        //       $role_raw = $p['role'] ?? '';
        //       $role_display = is_array($role_raw)
        //         ? implode(', ', array_map(fn($v) => is_scalar($v) ? (string)$v : wp_json_encode($v), $role_raw))
        //         : (string)$role_raw;
		//
        //       $payload = [
        //         'image'      => $image_url,
        //         'title'      => $p['title'] ?? '',
        //         'role'       => $role_display,
        //         'text_short' => $p['text_short'] ?? '',
        //         'permalink'  => $p['permalink'] ?? '',
        //       ];
		//
        //       lutwiyo_debug("スタッフ{$i}", $payload);
        //       $i++; //デバッグ表示用です。なくてもOKです。
        //     }
        //   }
        // } else {
        //   lutwiyo_debug('スタッフグループ配列が空です', $staff_by_group ?? null);
        // }

        ?>

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
                            <span property="name">ライター紹介</span>
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
                <h1 class="title fs--22">ライター紹介</h1>
                <div class="pageInner__nav">
                    <ul class="pageInner__navList">
                        <?php foreach ($staff_by_group as $group_slug => $group): ?>
                        <?php $group_name = $group['term']['name'] ?? $group_slug; ?>
                            <li class="pageInner__navTarget">
                                <a href="/staff/#<?php echo esc_html($group_name); ?>" title="<?php echo esc_html($group_name); ?>">
                                    <p class="pageInner__navTarget--p"><?php echo esc_html($group_name); ?></p>
                                    <div class="btnArrow btnArrow--down" data-arrow="w-8"></div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php foreach ($staff_by_group as $group_slug => $group): ?>
                <?php $group_name = $group['term']['name'] ?? $group_slug; ?>
                    <section class="editSection editSection--edit" id="<?php echo esc_html($group_name); ?>">
                        <h2 class="editSection__title"><?php echo esc_html($group_name); ?></h2>
                        <ul class="latestSection__list writerList">
                            <?php $posts = $group['posts'] ?? []; ?>
                            <?php foreach ($posts as $p): ?>
                                <?php
                                // image は image_url 優先。なければ image（ACF配列 or 文字列）からURLを抽出
                                $image_url = $p['image_url'] ?? '';
                                if (!$image_url) {
                                  $img = $p['image'] ?? '';
                                  if (is_array($img)) {
                                    $image_url = $img['url'] ?? '';
                                  } elseif (is_string($img)) {
                                    $image_url = $img; // 文字列URLが入っている場合
                                  }
                                }

                                // role は配列/文字列両対応（配列ならカンマ区切りで見やすく）
                                $role_raw = $p['role'] ?? '';
                                $role_display = is_array($role_raw)
                                  ? implode(', ', array_map(fn($v) => is_scalar($v) ? (string)$v : wp_json_encode($v), $role_raw))
                                  : (string)$role_raw;
                                ?>
                                <li class="blockBox writerBlock">
                                    <a class="writerBlock__link" href="<?php echo esc_html($p['permalink'] ?? ''); ?>" title="<?php echo esc_html($p['title'] ?? ''); ?>"></a>
                                    <dl class="writerBlock__inner">
                                        <dt class="writerBlock__thum">
                                            <div class="writerBlock__thumInner"><img src="<?php echo esc_html($image_url); ?>" alt="<?php echo esc_html($p['title'] ?? ''); ?>"></div>
                                            <div class="btnCircle btnShaped" data-shaped="38-38">
                                                <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                                            </div>
                                        </dt>
                                        <dd class="fontW--r writerBlock__info">
                                            <p class="writerBlock__info--title"><?php echo esc_html($role_display); ?></p>
                                            <p class="writerBlock__info--name"><?php echo esc_html($p['title'] ?? ''); ?></p>
                                            <p class="textColor--textGray writerBlock__info--desc"><?php echo esc_html($p['text_short'] ?? ''); ?></p>
                                        </dd>
                                    </dl>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </section>
        </article>
    </main>

<?php get_footer(); ?>
