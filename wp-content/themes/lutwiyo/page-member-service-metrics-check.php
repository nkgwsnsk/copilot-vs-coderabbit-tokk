<?php
/**
 * Template Name: Member Service Metrics Check
 *
 * member-service 計測シナリオを確認するための専用固定ページテンプレート。
 * このページ自体のノイズを減らすため、共通ヘッダ・フッタは読み込まない。
 */

nocache_headers();

if (have_posts()) {
    the_post();
}

$readConfigValue = static function (string $configName) {
    if (function_exists('tokk_member_service_config_value')) {
        return tokk_member_service_config_value($configName);
    }

    if (defined($configName)) {
        return constant($configName);
    }

    $envValue = getenv($configName);
    if ($envValue !== false) {
        return $envValue;
    }

    return null;
};

$isFlagEnabled = static function (string $configName) use ($readConfigValue): bool {
    $value = $readConfigValue($configName);
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }

    if (!is_string($value)) {
        return false;
    }

    return in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
};

$sliceText = static function (string $text, int $length = 8): string {
    $trimmed = trim($text);
    if ($trimmed === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($trimmed, 0, $length);
    }

    return substr($trimmed, 0, $length);
};

$currentPage = get_post();
$pageTitle = $currentPage instanceof WP_Post ? get_the_title($currentPage) : 'member-service 計測チェック';
$pageSlug = $currentPage instanceof WP_Post ? (string) $currentPage->post_name : '';
$pageUrl = $currentPage instanceof WP_Post ? (string) get_permalink($currentPage) : home_url('/member-service-metrics-check/');
$recommendedSlug = 'member-service-metrics-check';
$siteName = (string) get_bloginfo('name');
$homeUrl = home_url('/');
$loginUrl = home_url('/login/');
$mypageUrl = home_url('/mypage/');
$mypageEditUrl = home_url('/mypage-edit/');
$memberblogPublicListUrl = home_url('/memberblog-public-list/');

$sampleArticlePost = null;
$sampleArticlePosts = get_posts([
    'post_type' => 'articles',
    'post_status' => 'publish',
    'posts_per_page' => 1,
    'orderby' => 'date',
    'order' => 'DESC',
]);
if (is_array($sampleArticlePosts) && isset($sampleArticlePosts[0]) && $sampleArticlePosts[0] instanceof WP_Post) {
    $sampleArticlePost = $sampleArticlePosts[0];
}

$sampleArticleUrl = $sampleArticlePost instanceof WP_Post
    ? (string) get_permalink($sampleArticlePost)
    : $homeUrl;
$sampleArticleTitle = $sampleArticlePost instanceof WP_Post
    ? (string) get_the_title($sampleArticlePost)
    : '最新記事';
$sampleSearchKeyword = $sliceText($sampleArticleTitle, 6);
if ($sampleSearchKeyword === '') {
    $sampleSearchKeyword = '阪急';
}
$sampleSearchUrl = home_url('/?s=' . rawurlencode($sampleSearchKeyword));

$categoryLink = '';
$categoryLabel = 'カテゴリ一覧';
$categoryTerms = get_terms([
    'taxonomy' => 'category',
    'hide_empty' => true,
    'number' => 1,
]);
if (is_array($categoryTerms) && isset($categoryTerms[0]) && $categoryTerms[0] instanceof WP_Term) {
    $categoryTermLink = get_term_link($categoryTerms[0]);
    if (!is_wp_error($categoryTermLink)) {
        $categoryLink = (string) $categoryTermLink;
        $categoryLabel = $categoryTerms[0]->name . ' のカテゴリ一覧';
    }
}
if ($categoryLink === '') {
    $categoryLink = $homeUrl;
}

$tagLink = '';
$tagLabel = 'タグ一覧';
$tagTerms = get_terms([
    'taxonomy' => 'post_tag',
    'hide_empty' => true,
    'number' => 1,
]);
if (is_array($tagTerms) && isset($tagTerms[0]) && $tagTerms[0] instanceof WP_Term) {
    $tagTermLink = get_term_link($tagTerms[0]);
    if (!is_wp_error($tagTermLink)) {
        $tagLink = (string) $tagTermLink;
        $tagLabel = $tagTerms[0]->name . ' のタグ一覧';
    }
}
if ($tagLink === '') {
    $tagLink = $homeUrl;
}

$metricsLogPath = function_exists('tokk_member_service_metrics_log_path')
    ? tokk_member_service_metrics_log_path()
    : '';

$flagDefinitions = [
    [
        'key' => 'TOKK_MEMBER_SERVICE_REQUEST_METRICS_ENABLED',
        'label' => 'metrics 出力',
        'required' => true,
    ],
    [
        'key' => 'TOKK_MEMBER_SERVICE_DISABLE_FAVORITE_COUNT',
        'label' => 'favorite 件数停止',
        'required' => true,
    ],
    [
        'key' => 'TOKK_MEMBER_SERVICE_DISABLE_LIST_FAVORITE_STATE',
        'label' => '記事一覧 favorite 停止',
        'required' => true,
    ],
    [
        'key' => 'TOKK_MEMBER_SERVICE_DISABLE_MEMBERBLOG_LIST_FAVORITE_STATE',
        'label' => '会員ブログ一覧 favorite 停止',
        'required' => true,
    ],
    [
        'key' => 'TOKK_MEMBER_SERVICE_SHORT_CIRCUIT_GUEST_FAVORITES',
        'label' => 'ゲスト favorite short-circuit',
        'required' => true,
    ],
];

$flagRows = array_map(static function (array $definition) use ($readConfigValue, $isFlagEnabled): array {
    $rawValue = $readConfigValue($definition['key']);
    if (is_bool($rawValue)) {
        $rawLabel = $rawValue ? 'true' : 'false';
    } elseif ($rawValue === null || $rawValue === false || $rawValue === '') {
        $rawLabel = '未設定';
    } elseif (is_scalar($rawValue)) {
        $rawLabel = (string) $rawValue;
    } else {
        $rawLabel = '複合値';
    }

    return [
        'key' => $definition['key'],
        'label' => $definition['label'],
        'enabled' => $isFlagEnabled($definition['key']),
        'raw_label' => $rawLabel,
        'required' => !empty($definition['required']),
    ];
}, $flagDefinitions);

$tailCommand = $metricsLogPath !== ''
    ? 'tail -f ' . escapeshellarg($metricsLogPath)
    : '';
$grepBulkCommand = $metricsLogPath !== ''
    ? 'grep \'favorites/status-bulk\' ' . escapeshellarg($metricsLogPath) . ' | tail -20'
    : '';
$grepFavoritesCommand = $metricsLogPath !== ''
    ? 'grep \'member/favorites"\' ' . escapeshellarg($metricsLogPath) . ' | tail -20'
    : '';
$grepHomeCommand = $metricsLogPath !== ''
    ? 'grep \'"request":"\/"\' ' . escapeshellarg($metricsLogPath) . ' | tail -20'
    : '';

$scenarioCards = [
    [
        'id' => 'guest-home',
        'title' => 'ゲストでトップを確認',
        'summary' => 'favorite 件数停止、公開一覧 favorite 停止、guest short-circuit の効き方をまとめて確認する。',
        'links' => [
            ['label' => 'トップ /', 'url' => $homeUrl],
            ['label' => 'ログインページ', 'url' => $loginUrl],
        ],
        'checks' => [
            'トップを開いて計測ログが 1 行以上追記されることを確認した',
            'favorite 件数バッジが表示されないことを確認した',
            '公開一覧カードに favorite ボタンが表示されないことを確認した',
            '`/member-service/api/v1/member/favorites` が出ていないことを確認した',
            '`/member-service/api/v1/member/favorites/status-bulk` が出ていないことを確認した',
            '`/member-service/api/v1/reactions/favorite/tokk_article/...` が出ていないことを確認した',
            '`login-status` が残っていても許容であることを確認した',
        ],
    ],
    [
        'id' => 'guest-list-pages',
        'title' => 'ゲストでカテゴリ・タグ・検索結果を確認',
        'summary' => '一覧導線ごとに article list favorite 抑止が効いているかを確認する。',
        'links' => [
            ['label' => $categoryLabel, 'url' => $categoryLink],
            ['label' => $tagLabel, 'url' => $tagLink],
            ['label' => '検索結果: ' . $sampleSearchKeyword, 'url' => $sampleSearchUrl],
        ],
        'checks' => [
            'カテゴリ一覧で favorite ボタンが表示されないことを確認した',
            'タグ一覧で favorite ボタンが表示されないことを確認した',
            '検索結果で favorite ボタンが表示されないことを確認した',
            '各一覧で `/member-service/api/v1/member/favorites/status-bulk` が出ていないことを確認した',
            '各一覧で `/member-service/api/v1/reactions/favorite/tokk_article/...` が出ていないことを確認した',
        ],
    ],
    [
        'id' => 'guest-article-detail',
        'title' => 'ゲストで記事詳細を確認',
        'summary' => 'guest short-circuit により article detail の favorite SSR が安全側になるかを確認する。',
        'links' => [
            ['label' => '記事詳細: ' . $sampleArticleTitle, 'url' => $sampleArticleUrl],
        ],
        'checks' => [
            '記事詳細を開いて表示崩れがないことを確認した',
            'favorite 表示が inactive 側で壊れていないことを確認した',
            '`/member-service/api/v1/member/favorites` または `/reactions/favorite/tokk_article/...` が不要に増えていないことを確認した',
            '`login-status` が残っていても許容であることを確認した',
        ],
    ],
    [
        'id' => 'guest-memberblog',
        'title' => 'ゲストで会員ブログ公開一覧・公開詳細を確認',
        'summary' => '会員ブログ一覧の favorite 抑止と、公開詳細の like 維持を切り分けて確認する。',
        'links' => [
            ['label' => '会員ブログ公開一覧', 'url' => $memberblogPublicListUrl],
        ],
        'notes' => [
            '公開詳細は一覧から任意の 1 件を開いて確認する。',
        ],
        'checks' => [
            '会員ブログ公開一覧で favorite ボタンが表示されないことを確認した',
            '会員ブログ公開一覧で `reactions/favorite/member_blog_post/...` が出ていないことを確認した',
            '公開詳細では like UI が残っていてもよいことを確認した',
            '公開詳細の表示や遷移が壊れていないことを確認した',
        ],
    ],
    [
        'id' => 'logged-in-home',
        'title' => 'ログイン済みでトップを確認',
        'summary' => 'ログイン済みでも共通件数停止と一覧 favorite 抑止が維持されるかを確認する。',
        'links' => [
            ['label' => 'トップ /', 'url' => $homeUrl],
            ['label' => 'マイページ', 'url' => $mypageUrl],
        ],
        'checks' => [
            'ログイン済みでも favorite 件数バッジが表示されないことを確認した',
            'ログイン済みでも公開一覧カードに favorite ボタンが表示されないことを確認した',
            'ログイン済みトップで `/member-service/api/v1/member/favorites` が出ていないことを確認した',
            'ログイン済みトップで `/member-service/api/v1/member/favorites/status-bulk` が出ていないことを確認した',
            'ログイン済み導線が壊れていないことを確認した',
        ],
    ],
    [
        'id' => 'logged-in-article-detail',
        'title' => 'ログイン済みで記事詳細を確認',
        'summary' => 'main favorite を残す設計が維持されているかを確認する。',
        'links' => [
            ['label' => '記事詳細: ' . $sampleArticleTitle, 'url' => $sampleArticleUrl],
        ],
        'checks' => [
            '記事詳細の main favorite が表示されることを確認した',
            'main favorite の active / inactive 表示が壊れていないことを確認した',
            '記事詳細で main favorite 用の endpoint が残っていても許容であることを確認した',
            '記事詳細のその他 UI が壊れていないことを確認した',
        ],
    ],
    [
        'id' => 'logged-in-mypage',
        'title' => 'ログイン済みで /mypage/ を確認',
        'summary' => 'MS-IR-06 で整理した read 経路により、重複参照が増えていないかを確認する。',
        'links' => [
            ['label' => 'マイページ /mypage/', 'url' => $mypageUrl],
        ],
        'checks' => [
            'マイページの初期表示が正常であることを確認した',
            '`member-info` と `member-profile` の重複呼び出しが増えていないことを確認した',
            '有料会員判定や決済導線が壊れていないことを確認した',
            'お気に入り一覧や会員ブログ管理導線が壊れていないことを確認した',
        ],
    ],
    [
        'id' => 'logged-in-mypage-edit',
        'title' => 'ログイン済みで /mypage-edit/ を確認',
        'summary' => 'MS-IR-06 の helper 化で、初期表示と編集導線が維持されているかを確認する。',
        'links' => [
            ['label' => 'マイページ編集 /mypage-edit/', 'url' => $mypageEditUrl],
        ],
        'checks' => [
            'マイページ編集の初期値が正常に表示されることを確認した',
            '`member-info` と `member-profile` の重複呼び出しが増えていないことを確認した',
            '`profile_required` や未登録導線が壊れていないことを確認した',
            '保存前の表示や入力補助が壊れていないことを確認した',
        ],
    ],
];

$storageKey = 'tokk-member-service-metrics-check:' . ($pageSlug !== '' ? $pageSlug : 'default');
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($pageTitle); ?> | <?php echo esc_html($siteName); ?></title>
    <?php wp_head(); ?>
    <style>
        :root {
            color-scheme: light;
            --tokk-metrics-bg: #f5f1ea;
            --tokk-metrics-surface: #fffdfa;
            --tokk-metrics-line: #d8cbb8;
            --tokk-metrics-text: #30261d;
            --tokk-metrics-muted: #67594a;
            --tokk-metrics-accent: #8f4a2d;
            --tokk-metrics-accent-soft: #f3dfd2;
            --tokk-metrics-ok: #235f46;
            --tokk-metrics-ok-soft: #e3f2ea;
            --tokk-metrics-warn: #8f5e18;
            --tokk-metrics-warn-soft: #fbefcf;
            --tokk-metrics-ng: #8b2f2f;
            --tokk-metrics-ng-soft: #f8dede;
        }
        * {
            box-sizing: border-box;
        }
        body.tokk-metrics-check-page {
            margin: 0;
            background: linear-gradient(180deg, #efe4d5 0%, var(--tokk-metrics-bg) 220px);
            color: var(--tokk-metrics-text);
            font-family: "Hiragino Sans", "Yu Gothic", sans-serif;
            line-height: 1.7;
        }
        .tokkMetricsCheck {
            margin: 0 auto;
            max-width: 1160px;
            padding: 32px 20px 96px;
        }
        .tokkMetricsCheckHero,
        .tokkMetricsCheckSection,
        .tokkMetricsScenario {
            background: var(--tokk-metrics-surface);
            border: 1px solid var(--tokk-metrics-line);
            border-radius: 24px;
            box-shadow: 0 18px 50px rgba(48, 38, 29, 0.08);
        }
        .tokkMetricsCheckHero {
            padding: 28px;
        }
        .tokkMetricsCheckEyebrow {
            margin: 0 0 8px;
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--tokk-metrics-accent);
        }
        .tokkMetricsCheckTitle {
            margin: 0;
            font-size: clamp(30px, 5vw, 44px);
            line-height: 1.15;
        }
        .tokkMetricsCheckLead {
            margin: 16px 0 0;
            max-width: 780px;
            color: var(--tokk-metrics-muted);
            font-size: 16px;
        }
        .tokkMetricsCheckMeta {
            margin-top: 18px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .tokkMetricsCheckChip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            border: 1px solid var(--tokk-metrics-line);
            background: #fff;
            padding: 8px 14px;
            font-size: 13px;
            color: var(--tokk-metrics-muted);
        }
        .tokkMetricsCheckLayout {
            margin-top: 22px;
            display: grid;
            gap: 20px;
        }
        .tokkMetricsCheckSection {
            padding: 24px;
        }
        .tokkMetricsCheckSectionTitle {
            margin: 0 0 14px;
            font-size: 24px;
            line-height: 1.3;
        }
        .tokkMetricsCheckSectionLead {
            margin: 0 0 18px;
            color: var(--tokk-metrics-muted);
        }
        .tokkMetricsSummaryGrid,
        .tokkMetricsConfigGrid,
        .tokkMetricsScenarioGrid {
            display: grid;
            gap: 16px;
        }
        .tokkMetricsSummaryGrid {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .tokkMetricsSummaryCard {
            border: 1px solid var(--tokk-metrics-line);
            border-radius: 18px;
            background: #fff;
            padding: 18px;
        }
        .tokkMetricsSummaryLabel {
            margin: 0;
            color: var(--tokk-metrics-muted);
            font-size: 13px;
        }
        .tokkMetricsSummaryValue {
            margin: 10px 0 0;
            font-size: 34px;
            line-height: 1;
            font-weight: 700;
        }
        .tokkMetricsSummaryNote {
            margin: 10px 0 0;
            color: var(--tokk-metrics-muted);
            font-size: 13px;
        }
        .tokkMetricsConfigGrid {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }
        .tokkMetricsConfigCard {
            border: 1px solid var(--tokk-metrics-line);
            border-radius: 18px;
            background: #fff;
            padding: 18px;
        }
        .tokkMetricsConfigCard h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }
        .tokkMetricsConfigCard p {
            margin: 8px 0 0;
            font-size: 14px;
            color: var(--tokk-metrics-muted);
        }
        .tokkMetricsStatus {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .tokkMetricsStatus--ok {
            background: var(--tokk-metrics-ok-soft);
            color: var(--tokk-metrics-ok);
        }
        .tokkMetricsStatus--warn {
            background: var(--tokk-metrics-warn-soft);
            color: var(--tokk-metrics-warn);
        }
        .tokkMetricsStatus--ng {
            background: var(--tokk-metrics-ng-soft);
            color: var(--tokk-metrics-ng);
        }
        .tokkMetricsChecklist {
            display: grid;
            gap: 12px;
        }
        .tokkMetricsChecklistItem {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid var(--tokk-metrics-line);
            border-radius: 16px;
            background: #fff;
            padding: 14px 16px;
        }
        .tokkMetricsChecklistItem input {
            margin-top: 4px;
            width: 18px;
            height: 18px;
            accent-color: var(--tokk-metrics-accent);
        }
        .tokkMetricsChecklistLabel {
            display: block;
            font-weight: 600;
        }
        .tokkMetricsChecklistHint {
            display: block;
            margin-top: 4px;
            color: var(--tokk-metrics-muted);
            font-size: 13px;
        }
        .tokkMetricsCode {
            margin: 0;
            overflow-x: auto;
            border-radius: 16px;
            background: #241d17;
            color: #fff6ec;
            padding: 16px;
            font-size: 13px;
            line-height: 1.6;
        }
        .tokkMetricsScenarioGrid {
            grid-template-columns: 1fr;
        }
        .tokkMetricsScenario {
            padding: 22px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }
        .tokkMetricsScenario.is-complete {
            border-color: rgba(35, 95, 70, 0.45);
            box-shadow: 0 18px 50px rgba(35, 95, 70, 0.14);
        }
        .tokkMetricsScenarioHeader {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
        }
        .tokkMetricsScenarioTitleWrap {
            min-width: 0;
            flex: 1 1 480px;
        }
        .tokkMetricsScenarioIndex {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--tokk-metrics-accent-soft);
            color: var(--tokk-metrics-accent);
            font-weight: 700;
            font-size: 14px;
        }
        .tokkMetricsScenarioTitle {
            margin: 10px 0 0;
            font-size: 22px;
            line-height: 1.35;
        }
        .tokkMetricsScenarioSummary {
            margin: 10px 0 0;
            color: var(--tokk-metrics-muted);
        }
        .tokkMetricsScenarioProgress {
            min-width: 170px;
            border: 1px solid var(--tokk-metrics-line);
            border-radius: 16px;
            background: #fff;
            padding: 14px 16px;
        }
        .tokkMetricsScenarioProgressLabel {
            margin: 0;
            font-size: 12px;
            color: var(--tokk-metrics-muted);
        }
        .tokkMetricsScenarioProgressValue {
            margin: 8px 0 0;
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
        }
        .tokkMetricsScenarioLinks,
        .tokkMetricsScenarioNotes {
            margin-top: 16px;
            display: grid;
            gap: 10px;
        }
        .tokkMetricsScenarioLinks a {
            color: var(--tokk-metrics-accent);
            text-decoration: none;
            font-weight: 700;
        }
        .tokkMetricsScenarioLinks a:hover {
            text-decoration: underline;
        }
        .tokkMetricsScenarioLinksItem,
        .tokkMetricsScenarioNoteItem {
            border-radius: 14px;
            background: rgba(243, 223, 210, 0.35);
            padding: 12px 14px;
        }
        .tokkMetricsActionRow {
            margin-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .tokkMetricsButton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border-radius: 999px;
            border: 1px solid var(--tokk-metrics-accent);
            background: var(--tokk-metrics-accent);
            color: #fff;
            padding: 0 20px;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }
        .tokkMetricsButton--secondary {
            background: #fff;
            color: var(--tokk-metrics-accent);
        }
        .tokkMetricsNotice {
            margin-top: 16px;
            border-radius: 16px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--tokk-metrics-line);
            color: var(--tokk-metrics-muted);
        }
        .tokkMetricsNotice strong {
            color: var(--tokk-metrics-text);
        }
        .tokkMetricsSummaryText {
            white-space: pre-wrap;
        }
        @media only screen and (max-width: 767px) {
            .tokkMetricsCheck {
                padding: 20px 14px 72px;
            }
            .tokkMetricsCheckHero,
            .tokkMetricsCheckSection,
            .tokkMetricsScenario {
                border-radius: 20px;
                padding: 18px;
            }
            .tokkMetricsScenarioHeader {
                flex-direction: column;
            }
            .tokkMetricsScenarioProgress {
                width: 100%;
            }
        }
    </style>
</head>
<body <?php body_class('tokk-metrics-check-page'); ?>>
<?php if (function_exists('wp_body_open')) { wp_body_open(); } ?>
<main class="tokkMetricsCheck">
    <section class="tokkMetricsCheckHero">
        <p class="tokkMetricsCheckEyebrow">member-service metrics scenario check</p>
        <h1 class="tokkMetricsCheckTitle"><?php echo esc_html($pageTitle); ?></h1>
        <p class="tokkMetricsCheckLead">
            計測シナリオを 1 つずつ確認し、前提条件、画面確認、ログ確認をこのページだけで追えるようにした専用ページです。
            このページ自身の API ノイズを減らすため、共通ヘッダ・フッタは読み込んでいません。
        </p>
        <div class="tokkMetricsCheckMeta">
            <span class="tokkMetricsCheckChip">ページ URL: <?php echo esc_html($pageUrl); ?></span>
            <span class="tokkMetricsCheckChip">推奨 slug: <?php echo esc_html($recommendedSlug); ?></span>
            <span class="tokkMetricsCheckChip">localStorage key: <?php echo esc_html($storageKey); ?></span>
        </div>
        <?php if ($pageSlug !== $recommendedSlug) : ?>
            <p class="tokkMetricsNotice">
                <strong>推奨:</strong> 固定ページの slug を <code><?php echo esc_html($recommendedSlug); ?></code> にすると、
                テンプレート名と URL が揃って運用しやすくなります。
            </p>
        <?php endif; ?>
        <p class="tokkMetricsNotice">
            <strong>注意:</strong> この確認ページ自体のアクセスも metrics ログに 1 行残ります。
            シナリオ確認時は <code><?php echo esc_html(parse_url($pageUrl, PHP_URL_PATH) ?: '/member-service-metrics-check/'); ?></code> の行を比較対象から外してください。
        </p>
    </section>

    <div class="tokkMetricsCheckLayout">
        <section class="tokkMetricsCheckSection" aria-labelledby="tokk-metrics-summary-title">
            <h2 id="tokk-metrics-summary-title" class="tokkMetricsCheckSectionTitle">進捗サマリー</h2>
            <div class="tokkMetricsSummaryGrid">
                <div class="tokkMetricsSummaryCard">
                    <p class="tokkMetricsSummaryLabel">前提条件</p>
                    <p class="tokkMetricsSummaryValue"><span data-summary-prereq-done>0</span>/<span data-summary-prereq-total>0</span></p>
                    <p class="tokkMetricsSummaryNote">フラグとキャッシュクリアの確認状況</p>
                </div>
                <div class="tokkMetricsSummaryCard">
                    <p class="tokkMetricsSummaryLabel">シナリオ完了</p>
                    <p class="tokkMetricsSummaryValue"><span data-summary-scenario-done>0</span>/<span data-summary-scenario-total>0</span></p>
                    <p class="tokkMetricsSummaryNote">すべてのチェックが完了したシナリオ数</p>
                </div>
                <div class="tokkMetricsSummaryCard">
                    <p class="tokkMetricsSummaryLabel">全チェック項目</p>
                    <p class="tokkMetricsSummaryValue"><span data-summary-all-done>0</span>/<span data-summary-all-total>0</span></p>
                    <p class="tokkMetricsSummaryNote">ブラウザ内に保存されるチェック進捗</p>
                </div>
            </div>
            <div class="tokkMetricsActionRow">
                <button type="button" class="tokkMetricsButton tokkMetricsButton--secondary" data-reset-all>このブラウザの進捗をリセット</button>
            </div>
            <div class="tokkMetricsNotice tokkMetricsSummaryText" data-summary-text></div>
        </section>

        <section class="tokkMetricsCheckSection" aria-labelledby="tokk-metrics-config-title">
            <h2 id="tokk-metrics-config-title" class="tokkMetricsCheckSectionTitle">現在の設定</h2>
            <p class="tokkMetricsCheckSectionLead">計測前に必要なフラグが有効になっているかをここで確認できます。</p>
            <div class="tokkMetricsConfigGrid">
                <?php foreach ($flagRows as $flagRow) : ?>
                    <section class="tokkMetricsConfigCard">
                        <h3><?php echo esc_html($flagRow['label']); ?></h3>
                        <p><code><?php echo esc_html($flagRow['key']); ?></code></p>
                        <p>
                            <span class="tokkMetricsStatus <?php echo $flagRow['enabled'] ? 'tokkMetricsStatus--ok' : 'tokkMetricsStatus--ng'; ?>">
                                <?php echo $flagRow['enabled'] ? 'ENABLED' : 'DISABLED'; ?>
                            </span>
                        </p>
                        <p>現在値: <?php echo esc_html($flagRow['raw_label']); ?></p>
                    </section>
                <?php endforeach; ?>
                <section class="tokkMetricsConfigCard">
                    <h3>metrics ログ出力先</h3>
                    <p><code>TOKK_MEMBER_SERVICE_METRICS_LOG_PATH</code></p>
                    <p>
                        <span class="tokkMetricsStatus <?php echo $metricsLogPath !== '' ? 'tokkMetricsStatus--ok' : 'tokkMetricsStatus--warn'; ?>">
                            <?php echo $metricsLogPath !== '' ? 'FILE LOG' : 'ERROR LOG FALLBACK'; ?>
                        </span>
                    </p>
                    <p><?php echo $metricsLogPath !== '' ? esc_html($metricsLogPath) : '未設定のため error_log へフォールバック'; ?></p>
                </section>
            </div>
        </section>

        <section class="tokkMetricsCheckSection" aria-labelledby="tokk-metrics-prereq-title">
            <h2 id="tokk-metrics-prereq-title" class="tokkMetricsCheckSectionTitle">事前チェック</h2>
            <div class="tokkMetricsChecklist" data-prereq-group>
                <?php foreach ($flagRows as $flagRow) : ?>
                    <?php
                    $checkKey = 'prereq:' . $flagRow['key'];
                    $hint = $flagRow['enabled']
                        ? 'このフラグは有効になっています。'
                        : '未有効です。シナリオ計測前に true を設定してください。';
                    ?>
                    <label class="tokkMetricsChecklistItem">
                        <input type="checkbox" data-check-key="<?php echo esc_attr($checkKey); ?>" data-prereq-check>
                        <span>
                            <span class="tokkMetricsChecklistLabel"><?php echo esc_html($flagRow['key']); ?></span>
                            <span class="tokkMetricsChecklistHint"><?php echo esc_html($hint); ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
                <label class="tokkMetricsChecklistItem">
                    <input type="checkbox" data-check-key="prereq:cache-cleared" data-prereq-check>
                    <span>
                        <span class="tokkMetricsChecklistLabel">キャッシュ類をクリアした</span>
                        <span class="tokkMetricsChecklistHint">OPcache、ページキャッシュ、ブラウザキャッシュをクリアしたらチェックしてください。</span>
                    </span>
                </label>
                <label class="tokkMetricsChecklistItem">
                    <input type="checkbox" data-check-key="prereq:tail-ready" data-prereq-check>
                    <span>
                        <span class="tokkMetricsChecklistLabel">ログ監視コマンドを準備した</span>
                        <span class="tokkMetricsChecklistHint">tail や grep を開いて計測ログをすぐ確認できる状態にしたらチェックしてください。</span>
                    </span>
                </label>
            </div>
        </section>

        <section class="tokkMetricsCheckSection" aria-labelledby="tokk-metrics-command-title">
            <h2 id="tokk-metrics-command-title" class="tokkMetricsCheckSectionTitle">監視コマンド</h2>
            <?php if ($metricsLogPath !== '') : ?>
                <div class="tokkMetricsChecklist">
                    <div>
                        <p class="tokkMetricsCheckSectionLead">リアルタイム監視</p>
                        <pre class="tokkMetricsCode"><code><?php echo esc_html($tailCommand); ?></code></pre>
                    </div>
                    <div>
                        <p class="tokkMetricsCheckSectionLead">簡易 grep</p>
                        <pre class="tokkMetricsCode"><code><?php echo esc_html($grepHomeCommand . PHP_EOL . $grepBulkCommand . PHP_EOL . $grepFavoritesCommand); ?></code></pre>
                    </div>
                </div>
            <?php else : ?>
                <p class="tokkMetricsNotice">
                    <strong>補足:</strong> 専用ログファイルが未設定のため、このページでは error_log へのフォールバック前提になります。
                    専用ファイルで計測する場合は <code>TOKK_MEMBER_SERVICE_METRICS_LOG_PATH</code> を設定してください。
                </p>
            <?php endif; ?>
        </section>

        <section class="tokkMetricsCheckSection" aria-labelledby="tokk-metrics-scenarios-title">
            <h2 id="tokk-metrics-scenarios-title" class="tokkMetricsCheckSectionTitle">シナリオ別チェック</h2>
            <p class="tokkMetricsCheckSectionLead">各シナリオの全チェックが完了すると、そのカードが完了状態になります。</p>
            <div class="tokkMetricsScenarioGrid">
                <?php foreach ($scenarioCards as $index => $scenarioCard) : ?>
                    <section class="tokkMetricsScenario" data-scenario-card data-scenario-id="<?php echo esc_attr($scenarioCard['id']); ?>">
                        <div class="tokkMetricsScenarioHeader">
                            <div class="tokkMetricsScenarioTitleWrap">
                                <span class="tokkMetricsScenarioIndex"><?php echo esc_html((string) ($index + 1)); ?></span>
                                <h3 class="tokkMetricsScenarioTitle"><?php echo esc_html($scenarioCard['title']); ?></h3>
                                <p class="tokkMetricsScenarioSummary"><?php echo esc_html($scenarioCard['summary']); ?></p>
                            </div>
                            <div class="tokkMetricsScenarioProgress">
                                <p class="tokkMetricsScenarioProgressLabel">このシナリオの進捗</p>
                                <p class="tokkMetricsScenarioProgressValue">
                                    <span data-scenario-done>0</span>/<span data-scenario-total>0</span>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($scenarioCard['links'])) : ?>
                            <div class="tokkMetricsScenarioLinks">
                                <?php foreach ($scenarioCard['links'] as $linkRow) : ?>
                                    <div class="tokkMetricsScenarioLinksItem">
                                        <a href="<?php echo esc_url($linkRow['url']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($linkRow['label']); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($scenarioCard['notes'])) : ?>
                            <div class="tokkMetricsScenarioNotes">
                                <?php foreach ($scenarioCard['notes'] as $noteText) : ?>
                                    <div class="tokkMetricsScenarioNoteItem"><?php echo esc_html($noteText); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="tokkMetricsChecklist">
                            <?php foreach ($scenarioCard['checks'] as $checkIndex => $checkLabel) : ?>
                                <?php $checkKey = 'scenario:' . $scenarioCard['id'] . ':' . ($checkIndex + 1); ?>
                                <label class="tokkMetricsChecklistItem">
                                    <input type="checkbox" data-check-key="<?php echo esc_attr($checkKey); ?>" data-scenario-check>
                                    <span>
                                        <span class="tokkMetricsChecklistLabel"><?php echo esc_html($checkLabel); ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var storageKey = <?php echo wp_json_encode($storageKey); ?>;
    var checkboxNodes = Array.prototype.slice.call(document.querySelectorAll('[data-check-key]'));
    var scenarioCards = Array.prototype.slice.call(document.querySelectorAll('[data-scenario-card]'));
    var prereqNodes = Array.prototype.slice.call(document.querySelectorAll('[data-prereq-check]'));
    var summaryTextNode = document.querySelector('[data-summary-text]');
    var summaryPrereqDoneNode = document.querySelector('[data-summary-prereq-done]');
    var summaryPrereqTotalNode = document.querySelector('[data-summary-prereq-total]');
    var summaryScenarioDoneNode = document.querySelector('[data-summary-scenario-done]');
    var summaryScenarioTotalNode = document.querySelector('[data-summary-scenario-total]');
    var summaryAllDoneNode = document.querySelector('[data-summary-all-done]');
    var summaryAllTotalNode = document.querySelector('[data-summary-all-total]');
    var resetButton = document.querySelector('[data-reset-all]');

    var loadState = function () {
        try {
            var raw = window.localStorage.getItem(storageKey);
            if (!raw) {
                return {};
            }
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    };

    var saveState = function (state) {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (error) {
        }
    };

    var state = loadState();

    checkboxNodes.forEach(function (node) {
        var key = node.getAttribute('data-check-key') || '';
        if (key && state[key] === true) {
            node.checked = true;
        }
    });

    var updateSummary = function () {
        var allDone = 0;
        checkboxNodes.forEach(function (node) {
            if (node.checked) {
                allDone += 1;
            }
        });

        var prereqDone = 0;
        prereqNodes.forEach(function (node) {
            if (node.checked) {
                prereqDone += 1;
            }
        });

        var scenarioDone = 0;
        scenarioCards.forEach(function (card) {
            var scenarioChecks = Array.prototype.slice.call(card.querySelectorAll('[data-scenario-check]'));
            var doneCount = 0;
            scenarioChecks.forEach(function (node) {
                if (node.checked) {
                    doneCount += 1;
                }
            });
            var totalCount = scenarioChecks.length;
            var doneNode = card.querySelector('[data-scenario-done]');
            var totalNode = card.querySelector('[data-scenario-total]');
            if (doneNode) {
                doneNode.textContent = String(doneCount);
            }
            if (totalNode) {
                totalNode.textContent = String(totalCount);
            }
            var completed = totalCount > 0 && doneCount === totalCount;
            card.classList.toggle('is-complete', completed);
            if (completed) {
                scenarioDone += 1;
            }
        });

        if (summaryPrereqDoneNode) {
            summaryPrereqDoneNode.textContent = String(prereqDone);
        }
        if (summaryPrereqTotalNode) {
            summaryPrereqTotalNode.textContent = String(prereqNodes.length);
        }
        if (summaryScenarioDoneNode) {
            summaryScenarioDoneNode.textContent = String(scenarioDone);
        }
        if (summaryScenarioTotalNode) {
            summaryScenarioTotalNode.textContent = String(scenarioCards.length);
        }
        if (summaryAllDoneNode) {
            summaryAllDoneNode.textContent = String(allDone);
        }
        if (summaryAllTotalNode) {
            summaryAllTotalNode.textContent = String(checkboxNodes.length);
        }

        if (summaryTextNode) {
            summaryTextNode.textContent = [
                '前提条件: ' + prereqDone + '/' + prereqNodes.length,
                'シナリオ完了: ' + scenarioDone + '/' + scenarioCards.length,
                '全チェック項目: ' + allDone + '/' + checkboxNodes.length,
                'この進捗はブラウザの localStorage に保存されています。'
            ].join('\n');
        }
    };

    checkboxNodes.forEach(function (node) {
        node.addEventListener('change', function () {
            var key = node.getAttribute('data-check-key') || '';
            if (!key) {
                return;
            }
            state[key] = node.checked;
            saveState(state);
            updateSummary();
        });
    });

    if (resetButton) {
        resetButton.addEventListener('click', function () {
            checkboxNodes.forEach(function (node) {
                node.checked = false;
            });
            state = {};
            saveState(state);
            updateSummary();
        });
    }

    updateSummary();
});
</script>

<?php wp_footer(); ?>
</body>
</html>
