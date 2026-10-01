<?php
/**
 * RSS2 Feed Template (Gunosy)
 */

if (!function_exists('tokk_feed_date_to_rfc2822')) {
    // ISO8601形式の日時を、RSS用のRFC2822形式へ変換する
    function tokk_feed_date_to_rfc2822(string $iso8601): string
    {
        try {
            $dt = new DateTime($iso8601);
            return $dt->format(DateTime::RFC2822);
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('tokk_gunosy_now_rfc2822_jst')) {
    // 現在日時をRFC822形式（JST固定）で返す
    function tokk_gunosy_now_rfc2822_jst(): string
    {
        $dt = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
        return $dt->format(DateTime::RFC2822);
    }
}

if (!function_exists('tokk_gunosy_item_datetime')) {
    // 記事の配信日時を決定する（modified優先、なければdate、最終的に現在時刻）
    function tokk_gunosy_item_datetime(array $post_payload): string
    {
        $modified = (string)($post_payload['modified'] ?? '');
        $created = (string)($post_payload['date'] ?? '');

        $value = $modified !== '' ? $modified : $created;
        if ($value === '') {
            return date_i18n(DateTime::RFC2822, current_time('timestamp'));
        }

        $formatted = tokk_feed_date_to_rfc2822($value);
        if ($formatted === '') {
            return date_i18n(DateTime::RFC2822, current_time('timestamp'));
        }

        return $formatted;
    }
}

if (!function_exists('tokk_gunosy_sanitize_content')) {
    // Gunosy用に本文HTMLを整形する
    function tokk_gunosy_sanitize_content(string $content): string
    {
        // Gunosy向け: 危険な要素を排除しつつ本文HTMLを残す
        $content = preg_replace('/<script[\s\S]*?>[\s\S]*?<\/script>/i', '', $content);
        $content = preg_replace('/<style[\s\S]*?>[\s\S]*?<\/style>/i', '', $content);
        $content = preg_replace('/<iframe[\s\S]*?>[\s\S]*?<\/iframe>/i', '', $content);
        $content = preg_replace('/\sstyle=("|\')(.*?)\1/i', '', $content);
        $content = preg_replace('/<blockquote class="instagram-media"[\s\S]*?>[\s\S]*?<\/blockquote>/', '', $content);
        // Gunosyバリデータ対策: 箇条書きは段落へ正規化
        $content = preg_replace('#</?(ul|ol)[^>]*>#i', '', $content);
        $content = preg_replace('#<li[^>]*>#i', '<p>', $content);
        $content = preg_replace('#</li>#i', '</p>', $content);

        // 許可するHTMLタグを明示し、不要タグは除去する
        $allowed_tags = [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'blockquote' => [],
            'a' => ['href' => true],
            'img' => [
                'src' => true,
                'alt' => true,
            ],
        ];

        $content = wp_kses($content, $allowed_tags);
        // プレーンテキストの段落を補完して、可能な限り<p>単位に揃える
        $content = wpautop(trim($content), false);
        $content = preg_replace('/<p(?:\s+[^>]*)?>\s*<\/p>/i', '', $content);

        if (trim(wp_strip_all_tags($content)) === '') {
            return '<p>本文なし</p>';
        }

        $content = tokk_gunosy_wrap_text_nodes_with_p($content);
        return tokk_gunosy_wrap_orphan_text_lines($content);
    }
}

if (!function_exists('tokk_gunosy_wrap_text_nodes_with_p')) {
    // トップレベルに残った生テキストを<p>で包む
    function tokk_gunosy_wrap_text_nodes_with_p(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $internal_errors = libxml_use_internal_errors(true);

        // 断片HTMLとして読み込み、不要なhtml/bodyタグの付与を抑止
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="tokk-wrap">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $xpath = new DOMXPath($dom);
        $wrap_nodes = $xpath->query('//*[@id="tokk-wrap"]');
        $wrap = ($wrap_nodes instanceof DOMNodeList && $wrap_nodes->length > 0)
            ? $wrap_nodes->item(0)
            : null;

        if (!$wrap instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($internal_errors);
            return $html;
        }

        $children = [];
        foreach ($wrap->childNodes as $child) {
            $children[] = $child;
        }

        while ($wrap->firstChild) {
            $wrap->removeChild($wrap->firstChild);
        }

        $block_tags = [
            'p',
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6',
            'ul',
            'ol',
            'li',
            'blockquote',
            'img',
        ];

        $current_p = null;
        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = preg_replace('/\s+/u', ' ', (string)$child->nodeValue);
                $text = trim((string)$text);
                if ($text === '') {
                    continue;
                }

                if (!$current_p instanceof DOMElement) {
                    $current_p = $dom->createElement('p');
                    $wrap->appendChild($current_p);
                }
                $current_p->appendChild($dom->createTextNode($text));
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);
            if (in_array($tag, $block_tags, true)) {
                $current_p = null;
                $wrap->appendChild($child);
                continue;
            }

            // インライン要素は段落へ寄せる
            if (!$current_p instanceof DOMElement) {
                $current_p = $dom->createElement('p');
                $wrap->appendChild($current_p);
            }
            $current_p->appendChild($child);
        }

        $result = '';
        foreach ($wrap->childNodes as $node) {
            $result .= $dom->saveHTML($node);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internal_errors);

        return $result !== '' ? $result : $html;
    }
}

if (!function_exists('tokk_gunosy_wrap_orphan_text_lines')) {
    // 行頭がテキストで始まる行（途中にinlineタグを含む行も含む）を<p>で包む
    function tokk_gunosy_wrap_orphan_text_lines(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $lines = preg_split('/\R/u', $html);
        if (!is_array($lines)) {
            return $html;
        }

        $result_lines = [];
        $text_buffer = [];

        $flush_buffer = static function () use (&$result_lines, &$text_buffer): void {
            if (empty($text_buffer)) {
                return;
            }
            $text = trim(implode(' ', $text_buffer));
            if ($text !== '') {
                $result_lines[] = '<p>' . $text . '</p>';
            }
            $text_buffer = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim((string)$line);

            if ($trimmed === '') {
                $flush_buffer();
                $result_lines[] = '';
                continue;
            }

            // タグで始まる行はそのまま扱う（前段のテキストバッファは確定）
            if (preg_match('/^</u', $trimmed) === 1) {
                $flush_buffer();
                $result_lines[] = $trimmed;
                continue;
            }

            // テキスト行（途中に<a>や<br>が含まれていても対象）
            $text_buffer[] = $trimmed;
        }

        $flush_buffer();

        $result = implode("\n", $result_lines);

        // 最後の保険: まだ残っている生テキスト行を強制的に<p>で包む
        $final_lines = preg_split('/\R/u', $result);
        if (!is_array($final_lines)) {
            return $result;
        }

        foreach ($final_lines as $idx => $line) {
            $trimmed = trim((string)$line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^\s*[^<\s]/u', $line) === 1) {
                $final_lines[$idx] = '<p>' . $trimmed . '</p>';
            }
        }

        return implode("\n", $final_lines);
    }
}

if (!function_exists('tokk_gunosy_deleted_item_payload')) {
    // deleted itemでもバリデータ上の必須要素を満たすための補完データを返す
    function tokk_gunosy_deleted_item_payload(int $post_id): array
    {
        $fallback_datetime = date_i18n(DateTime::RFC2822, current_time('timestamp'));
        $fallback_link = home_url('/');
        $fallback_title = '削除記事';
        $fallback_content = '<p>この記事は削除されました。</p>';

        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return [
                'title' => $fallback_title,
                'link' => $fallback_link,
                'pubDate' => $fallback_datetime,
                'modified' => $fallback_datetime,
                'content' => $fallback_content,
            ];
        }

        $title = get_the_title($post_id);
        $link = get_permalink($post_id);
        $post_datetime = get_post_modified_time(DateTime::RFC2822, false, $post_id);

        return [
            'title' => $title !== '' ? (string)$title : $fallback_title,
            'link' => !empty($link) ? (string)$link : $fallback_link,
            'pubDate' => !empty($post_datetime) ? (string)$post_datetime : $fallback_datetime,
            'modified' => !empty($post_datetime) ? (string)$post_datetime : $fallback_datetime,
            'content' => $fallback_content,
        ];
    }
}

if (!function_exists('tokk_gunosy_related_thumbnail_url')) {
    // relatedLink用のサムネイルURLを解決する
    function tokk_gunosy_related_thumbnail_url(array $post_payload): string
    {
        if (!empty($post_payload['image_url']) && is_string($post_payload['image_url'])) {
            return (string)$post_payload['image_url'];
        }

        if (!empty($post_payload['articles_img'])) {
            $img = $post_payload['articles_img'];
            if (is_array($img) && !empty($img['url'])) {
                return (string)$img['url'];
            }
            if (is_numeric($img)) {
                $url = wp_get_attachment_image_url((int)$img, 'full');
                if (!empty($url)) {
                    return (string)$url;
                }
            }
            if (is_string($img) && $img !== '') {
                return $img;
            }
        }

        $post_id = (int)($post_payload['id'] ?? 0);
        if ($post_id > 0) {
            $thumb = get_the_post_thumbnail_url($post_id, 'full');
            if (!empty($thumb)) {
                return (string)$thumb;
            }
        }

        return '';
    }
}

if (!function_exists('tokk_gunosy_article_thumbnail_url')) {
    // 本文先頭に出す記事サムネイルURLを解決する
    function tokk_gunosy_article_thumbnail_url(array $post_payload): string
    {
        if (!empty($post_payload['image_url']) && is_string($post_payload['image_url'])) {
            return (string)$post_payload['image_url'];
        }

        if (!empty($post_payload['articles_img'])) {
            $img = $post_payload['articles_img'];
            if (is_array($img) && !empty($img['url'])) {
                return (string)$img['url'];
            }
            if (is_numeric($img)) {
                $url = wp_get_attachment_image_url((int)$img, 'full');
                if (!empty($url)) {
                    return (string)$url;
                }
            }
            if (is_string($img) && $img !== '') {
                return $img;
            }
        }

        $post_id = (int)($post_payload['id'] ?? 0);
        if ($post_id > 0) {
            $thumb = get_the_post_thumbnail_url($post_id, 'full');
            if (!empty($thumb)) {
                return (string)$thumb;
            }
        }

        return '';
    }
}

if (!function_exists('tokk_gunosy_enclosure_payload')) {
    // enclosure要素に必要な情報を組み立てる
    function tokk_gunosy_enclosure_payload(array $post_payload): array
    {
        $url = tokk_gunosy_article_thumbnail_url($post_payload);
        if ($url === '') {
            return [];
        }

        // 仕様上、URLが長すぎる画像は取り込まれないため除外
        if (strlen($url) >= 256) {
            return [];
        }

        $ext = strtolower((string)pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };

        return [
            'url' => $url,
            'type' => $mime,
            // 仕様書サンプル準拠（サイズ計測不可時の許容値）
            'length' => '0',
            'caption' => (string)($post_payload['title'] ?? ''),
        ];
    }
}

if (!function_exists('tokk_gunosy_channel_description')) {
    // チャンネル説明文（Gunosy仕様の推奨文字数に合わせる）
    function tokk_gunosy_channel_description(): string
    {
        return '阪急阪神沿線の地元の話題と関西おでかけ情報';
    }
}

if (!function_exists('tokk_gunosy_channel_square_logo_url')) {
    // 正方形ロゴURLを固定で返す
    function tokk_gunosy_channel_square_logo_url(): string
    {
        return home_url('/assets/img/TOKKkansai_logo_120.png');
    }
}

if (!function_exists('tokk_gunosy_channel_wide_logo_url')) {
    // 横長ロゴURLを固定で返す
    function tokk_gunosy_channel_wide_logo_url(): string
    {
        return home_url('/assets/img/TOKKkansai_logo_yoko.png');
    }
}

if (!function_exists('tokk_gunosy_common_ga_tag')) {
    // 共通のGTMタグ本体（IDは差し替え前提）
    function tokk_gunosy_common_ga_tag(): string
    {
        return <<<HTML
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-M2ZWVP85');</script>
HTML;
    }
}

if (!function_exists('tokk_gunosy_analytics_tag')) {
    // <gnf:analytics> 用のタグを返す（newspass）
    function tokk_gunosy_analytics_tag(string $page_location): string
    {
        $payload = wp_json_encode(
            [
                'event' => 'newspass_view',
                'page_referrer' => 'https://newspass.jp/',
                'page_location' => $page_location,
                'app_name' => 'newspass',
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return (<<<HTML
<script>
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({$payload});
</script>

HTML
        ) . "\n" . tokk_gunosy_common_ga_tag();
    }
}

if (!function_exists('tokk_gunosy_analytics_gn_tag')) {
    // <gnf:analytics_gn> 用のタグを返す（gunosy）
    function tokk_gunosy_analytics_gn_tag(string $page_location): string
    {
        $payload = wp_json_encode(
            [
                'event' => 'gunosy_view',
                'page_referrer' => 'https://gunosy.com',
                'page_location' => $page_location,
                'app_name' => 'gunosy',
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return (<<<HTML
<script>
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({$payload});
</script>

HTML
        ) . "\n" . tokk_gunosy_common_ga_tag();
    }
}

if (!function_exists('tokk_gunosy_analytics_st_tag')) {
    // <gnf:analytics_st> 用のタグを返す（au-service-today）
    function tokk_gunosy_analytics_st_tag(string $page_location): string
    {
        $payload = wp_json_encode(
            [
                'event' => 'au-service-today_view',
                'page_referrer' => 'https://service-top.jp',
                'page_location' => $page_location,
                'app_name' => 'au-service-today',
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return (<<<HTML
<script>
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({$payload});
</script>

HTML
        ) . "\n" . tokk_gunosy_common_ga_tag();
    }
}

// RSSレスポンスヘッダーとXML宣言を出力する
header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . '>';

do_action('rss_tag_pre', 'rss2');
?>
<rss version="2.0"
     xmlns:gnf="http://assets.gunosy.com/media/gnf"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/"
    <?php do_action('rss2_ns'); ?>
>
    <channel>
        <title>TOKK（トック）関西</title>
        <link><?php bloginfo_rss('url'); ?></link>
        <description><![CDATA[<?= tokk_gunosy_channel_description(); ?>]]></description>
        <image>
            <url><?= esc_url(tokk_gunosy_channel_square_logo_url()); ?></url>
            <title>TOKK（トック）関西</title>
            <link><?php bloginfo_rss('url'); ?></link>
        </image>
        <gnf:wide_image_link><?= esc_url(tokk_gunosy_channel_wide_logo_url()); ?></gnf:wide_image_link>
        <ttl>15</ttl>
        <lastBuildDate><?= esc_html(tokk_gunosy_now_rfc2822_jst()); ?></lastBuildDate>
        <language><?php bloginfo_rss('language'); ?></language>
        <copyright><?= esc_html(get_bloginfo('name')) ?> All rights reserved.</copyright>
        <?php
        // 配信除外記事は deleted として出力する
        $fields = get_field('feed_excluded_articles_list', 'option');
        if (!is_array($fields)) {
            $fields = [];
        }
        foreach ($fields as $field) :
            if (empty($field->ID)) {
                continue;
            }
            $deleted_payload = tokk_gunosy_deleted_item_payload((int)$field->ID);
            ?>
            <item>
                <guid isPermaLink="false"><?= esc_html((string)$field->ID); ?></guid>
                <title><?= esc_html($deleted_payload['title']); ?></title>
                <link><?= esc_url($deleted_payload['link']); ?></link>
                <pubDate><?= esc_html($deleted_payload['pubDate']); ?></pubDate>
                <gnf:modified><?= esc_html($deleted_payload['modified']); ?></gnf:modified>
                <content:encoded><![CDATA[<?= $deleted_payload['content']; ?>]]></content:encoded>
                <media:status state="deleted" />
            </item>
        <?php endforeach; ?>
        <?php
        // フィード対象記事を取得する（dmenu実装と同様に goo-hide は除外）
        $post_list = (array)PostModelHelper::get_posts_payload(
            [
                'post_type' => 'articles',
                'posts_per_page' => 10,
                'tax_query' => [
                    [
                        'taxonomy' => 'articles_hide',
                        'field' => 'slug',
                        'terms' => ['goo-hide'],
                        'operator' => 'NOT IN',
                    ],
                ],
            ],
            [],
            []
        );

        // 更新日時の降順で並べる（新しい順）
        usort($post_list, function ($a, $b) {
            $a_mod = $a['modified'] ?? '';
            $b_mod = $b['modified'] ?? '';

            if ($a_mod === '' && $b_mod === '') {
                return 0;
            } elseif ($a_mod === '') {
                return 1;
            } elseif ($b_mod === '') {
                return -1;
            }

            return strtotime($b_mod) <=> strtotime($a_mod);
        });

        if (!empty($post_list)) :
            foreach ($post_list as $p) :
                // 記事ごとの日時と本文を整形
                $modified_rfc = tokk_gunosy_item_datetime($p);
                $contentSource = (string) ($p['content'] ?? '');
                $postId = (int) ($p['id'] ?? 0);
                if ($postId > 0) {
                    $postObject = get_post($postId);
                    if ($postObject instanceof WP_Post && function_exists('lutwiyo_should_mask_paid_article_content') && lutwiyo_should_mask_paid_article_content($postObject)) {
                        $contentSource = lutwiyo_build_paid_article_masked_content($postObject);
                    } elseif (function_exists('lutwiyo_strip_paywall_gate_placeholder')) {
                        $contentSource = lutwiyo_strip_paywall_gate_placeholder($contentSource);
                    }
                }

                $content = tokk_gunosy_sanitize_content($contentSource);
                $article_thumb = tokk_gunosy_article_thumbnail_url($p);
                $enclosure = tokk_gunosy_enclosure_payload($p);
                if ($article_thumb !== '') {
                    $content = sprintf(
                        '<p><img src="%s" alt="%s"></p>',
                        esc_url($article_thumb),
                        esc_attr((string)($p['title'] ?? ''))
                    ) . $content;
                }
                // 最終保険: 行頭がタグで始まらない行は必ず<p>で囲む
                $content = preg_replace(
                    '/(^|[\r\n])([ \t]*[^<\s][^\r\n]*)/m',
                    '$1<p>$2</p>',
                    (string)$content
                );
                $page_location = (string)($p['permalink'] ?? '');
                $category_slugs = [];
                // 関連記事抽出用にカテゴリslugを作る
                if (!empty($p['category']) && is_array($p['category'])) {
                    foreach ($p['category'] as $category_id) {
                        $cat_info = TermModelHelper::get_terms_payload(
                            'category',
                            [$category_id],
                            [],
                            []
                        );
                        if (!empty($cat_info[0]['slug'])) {
                            $category_slugs[] = (string)$cat_info[0]['slug'];
                        }
                    }
                    $category_slugs = array_values(array_unique($category_slugs));
                }
                ?>
                <item>
                    <title><?= esc_html((string)($p['title'] ?? '')); ?></title>
                    <link><?= esc_url((string)($p['permalink'] ?? '')); ?></link>
                    <guid isPermaLink="false"><?= esc_html((string)($p['id'] ?? '')); ?></guid>
                    <pubDate><?= esc_html($modified_rfc); ?></pubDate>
                    <gnf:modified><?= esc_html($modified_rfc); ?></gnf:modified>
                    <dc:creator>TOKK（トック）関西</dc:creator>
                    <content:encoded><![CDATA[<?= $content; ?>]]></content:encoded>
                    <gnf:analytics_gn><![CDATA[<?= tokk_gunosy_analytics_gn_tag($page_location); ?>]]></gnf:analytics_gn>
                    <gnf:analytics><![CDATA[<?= tokk_gunosy_analytics_tag($page_location); ?>]]></gnf:analytics>
                    <gnf:analytics_st><![CDATA[<?= tokk_gunosy_analytics_st_tag($page_location); ?>]]></gnf:analytics_st>
                    <media:status state="active" />
                    <?php if (!empty($enclosure)): ?>
                        <enclosure
                            url="<?= esc_url($enclosure['url']); ?>"
                            type="<?= esc_attr($enclosure['type']); ?>"
                            length="<?= esc_attr($enclosure['length']); ?>"
                            caption="<?= esc_attr($enclosure['caption']); ?>" />
                    <?php endif; ?>
                    <?php
                    // 関連記事を最大3件出力する
                    $related_args = [
                        'post_type' => 'articles',
                        'posts_per_page' => 3,
                        'post__not_in' => [(int)($p['id'] ?? 0)],
                    ];
                    if (!empty($category_slugs)) {
                        $related_args['tax_query'] = [
                            [
                                'taxonomy' => 'category',
                                'field' => 'slug',
                                'terms' => $category_slugs,
                                'operator' => 'IN',
                            ],
                        ];
                    }
                    $related_post_list = PostModelHelper::get_posts_payload($related_args);
                    if (!empty($related_post_list)) :
                        foreach ($related_post_list as $r) :
                            if (empty($r['title']) || empty($r['permalink'])) {
                                continue;
                            }
                            ?>
                            <?php $related_thumb = tokk_gunosy_related_thumbnail_url($r); ?>
                            <gnf:relatedLink
                                title="<?= esc_attr((string)$r['title']); ?>"
                                link="<?= esc_url((string)$r['permalink']); ?>"
                                <?php if ($related_thumb !== ''): ?>
                                    thumbnail="<?= esc_url($related_thumb); ?>"
                                <?php endif; ?>
                            />
                        <?php endforeach; ?>
                    <?php endif; ?>
                </item>
            <?php
            endforeach;
        else :
            echo 'NODATA';
        endif;
        ?>
    </channel>
</rss>
