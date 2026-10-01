<?php
/**
 * RSS2 Feed Template (LINE NEWS)
 */

// ISO8601形式の日時文字列をLINE RSS用のRFC2822形式へ変換する。
if (!function_exists('tokk_line_news_date_to_rfc2822')) {
    function tokk_line_news_date_to_rfc2822(string $iso8601): string
    {
        try {
            $dt = new DateTime($iso8601);
            return $dt->format(DateTime::RFC2822);
        } catch (Exception $e) {
            return '';
        }
    }
}

// フィード生成日時として使う現在時刻をJSTのRFC2822形式で返す。
if (!function_exists('tokk_line_news_now_rfc2822_jst')) {
    function tokk_line_news_now_rfc2822_jst(): string
    {
        $dt = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
        return $dt->format(DateTime::RFC2822);
    }
}

// 記事ごとの配信日時を決定し、modified優先でRFC2822形式へ整える。
if (!function_exists('tokk_line_news_item_datetime')) {
    function tokk_line_news_item_datetime(array $post_payload): string
    {
        $modified = (string)($post_payload['modified'] ?? '');
        $created = (string)($post_payload['date'] ?? '');

        $value = $modified !== '' ? $modified : $created;
        if ($value === '') {
            return date_i18n(DateTime::RFC2822, current_time('timestamp'));
        }

        $formatted = tokk_line_news_date_to_rfc2822($value);
        if ($formatted === '') {
            return date_i18n(DateTime::RFC2822, current_time('timestamp'));
        }

        return $formatted;
    }
}

// aタグのhrefが、優先して残したいTOKK関西の記事URLかどうかを判定する。
if (!function_exists('tokk_line_news_is_preferred_anchor_href')) {
    function tokk_line_news_is_preferred_anchor_href(string $href): bool
    {
        $parts = wp_parse_url(trim($href));
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        return preg_match('/^(?:[a-z0-9-]+-)?tokk-kansai\.jp$/', $host) === 1;
    }
}

// channel要素に出すLINE NEWS向けの配信元説明文を返す。
if (!function_exists('tokk_line_news_channel_description')) {
    function tokk_line_news_channel_description(): string
    {
        return 'TOKK関西（トック関西）は阪急沿線の暮らし・おでかけ情報を発信します。';
    }
}

// 配信除外記事をoa:delStatus付きで出すための最低限のitem情報を組み立てる。
if (!function_exists('tokk_line_news_deleted_item_payload')) {
    function tokk_line_news_deleted_item_payload(int $post_id): array
    {
        $fallbackDatetime = date_i18n(DateTime::RFC2822, current_time('timestamp'));
        $fallbackLink = home_url('/');
        $fallbackTitle = '削除記事';
        $fallbackDescription = '<p>この記事は削除されました。</p>';

        $post = get_post($post_id);
        if (!($post instanceof WP_Post)) {
            return [
                'title' => $fallbackTitle,
                'link' => $fallbackLink,
                'pubDate' => $fallbackDatetime,
                'lastPubDate' => $fallbackDatetime,
                'description' => $fallbackDescription,
            ];
        }

        $title = get_the_title($post_id);
        $link = get_permalink($post_id);
        $postDatetime = get_post_modified_time(DateTime::RFC2822, false, $post_id);

        return [
            'title' => $title !== '' ? (string)$title : $fallbackTitle,
            'link' => !empty($link) ? (string)$link : $fallbackLink,
            'pubDate' => !empty($postDatetime) ? (string)$postDatetime : $fallbackDatetime,
            'lastPubDate' => !empty($postDatetime) ? (string)$postDatetime : $fallbackDatetime,
            'description' => $fallbackDescription,
        ];
    }
}

// 記事のメイン画像候補となるURLをACFやアイキャッチから解決する。
if (!function_exists('tokk_line_news_normalize_absolute_url')) {
    function tokk_line_news_normalize_absolute_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return home_url($url);
        }

        return home_url('/' . ltrim($url, '/'));
    }
}

// 記事のメイン画像候補となるURLをACFやアイキャッチから解決する。
if (!function_exists('tokk_line_news_article_thumbnail_url')) {
    function tokk_line_news_article_thumbnail_url(array $post_payload): string
    {
        if (!empty($post_payload['image_url']) && is_string($post_payload['image_url'])) {
            return tokk_line_news_normalize_absolute_url((string)$post_payload['image_url']);
        }

        if (!empty($post_payload['articles_img'])) {
            $img = $post_payload['articles_img'];
            if (is_array($img) && !empty($img['url'])) {
                return tokk_line_news_normalize_absolute_url((string)$img['url']);
            }
            if (is_numeric($img)) {
                $url = wp_get_attachment_image_url((int)$img, 'full');
                if (!empty($url)) {
                    return tokk_line_news_normalize_absolute_url((string)$url);
                }
            }
            if (is_string($img) && $img !== '') {
                return tokk_line_news_normalize_absolute_url($img);
            }
        }

        $postId = (int)($post_payload['id'] ?? 0);
        if ($postId > 0) {
            $thumb = get_the_post_thumbnail_url($postId, 'full');
            if (!empty($thumb)) {
                return tokk_line_news_normalize_absolute_url((string)$thumb);
            }
        }

        return '';
    }
}

// enclosure要素へ出す画像URL・MIME type・captionを組み立てる。
if (!function_exists('tokk_line_news_enclosure_payload')) {
    function tokk_line_news_enclosure_payload(array $post_payload): array
    {
        $url = tokk_line_news_article_thumbnail_url($post_payload);
        if ($url === '' || strlen($url) >= 512) {
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

        $caption = trim((string)($post_payload['title'] ?? ''));
        if (function_exists('mb_strimwidth')) {
            $caption = mb_strimwidth($caption, 0, 255, '', 'UTF-8');
        }

        return [
            'url' => $url,
            'type' => $mime,
            'caption' => $caption,
        ];
    }
}

// areaスラッグから都道府県コード・市区町村コードへ変換する対応表を返す。
if (!function_exists('tokk_line_news_area_code_map')) {
    function tokk_line_news_area_code_map(): array
    {
        return [
            'umeda' => [
                'prefecture' => ['27'],
                'city' => ['27127'],
            ],
            'sannomiya-shinkaichi' => [
                'prefecture' => ['28'],
                'city' => ['28204', '28205'],
            ],
            'nishinomiya-ashiya' => [
                'prefecture' => ['28'],
                'city' => ['28105', '28102'],
            ],
            'kawaramachi-karasuma-kyotoeki' => [
                'prefecture' => ['26'],
                'city' => ['26106', '26104'],
            ],
            'namba-shinsaibashi-horie' => [
                'prefecture' => ['27'],
                'city' => ['27128', '27111'],
            ],
            'amagasaki' => [
                'prefecture' => ['28'],
                'city' => ['28202'],
            ],
            'okamoto-mikage' => [
                'prefecture' => ['28'],
                'city' => ['28101'],
            ],
            'rokko-nada' => [
                'prefecture' => ['28'],
                'city' => ['28102'],
            ],
            'toyonaka-itami' => [
                'prefecture' => ['27', '28'],
                'city' => ['27203', '28207'],
            ],
            'minoh' => [
                'prefecture' => ['27'],
                'city' => ['27218'],
            ],
            'kawanishi-ikeda' => [
                'prefecture' => ['27', '28'],
                'city' => ['28217', '27204'],
            ],
            'takarazuka' => [
                'prefecture' => ['28'],
                'city' => ['28214'],
            ],
            'juso-awaji-kamishinjo' => [
                'prefecture' => ['27'],
                'city' => ['27123', '27124'],
            ],
            'suita' => [
                'prefecture' => ['27'],
                'city' => ['27205'],
            ],
            'ibaraki-settsu' => [
                'prefecture' => ['27'],
                'city' => ['27210', '27224'],
            ],
            'takatsuki-shimamoto' => [
                'prefecture' => ['27'],
                'city' => ['27207', '27301'],
            ],
            'nagaokakyo-oyamazaki-muko' => [
                'prefecture' => ['26'],
                'city' => ['26209', '26303', '26210'],
            ],
            'katsura-arashiyama' => [
                'prefecture' => ['26'],
                'city' => ['26111', '26108'],
            ],
            'kansai' => [
                'prefecture' => ['25', '26', '27', '28', '29', '30'],
                'city' => [],
            ],
        ];
    }
}

// 1つのareaスラッグからLINE出力用の地域コード配列を取得する。
if (!function_exists('tokk_line_news_get_area_codes_from_slug')) {
    function tokk_line_news_get_area_codes_from_slug(string $slug): array
    {
        $map = tokk_line_news_area_code_map();
        if (!isset($map[$slug])) {
            return [
                'prefecture' => [],
                'city' => [],
            ];
        }

        return [
            'prefecture' => array_values((array)($map[$slug]['prefecture'] ?? [])),
            'city' => array_values((array)($map[$slug]['city'] ?? [])),
        ];
    }
}

// 記事に紐づくarea情報をTermModelHelper経由でまとめて取得する。
if (!function_exists('tokk_line_news_collect_area_payloads')) {
    function tokk_line_news_collect_area_payloads(array $post_payload): array
    {
        $areaSource = $post_payload['area'] ?? [];
        if (empty($areaSource) || !class_exists('TermModelHelper')) {
            return [];
        }

        $areas = TermModelHelper::get_terms_payload('area', $areaSource, [], []);
        return is_array($areas) ? $areas : [];
    }
}

// area情報から都道府県コード・市区町村コード・緯度経度をLINE向けに集約する。
if (!function_exists('tokk_line_news_collect_geo_payload')) {
    function tokk_line_news_collect_geo_payload(array $post_payload): array
    {
        $prefectureCodes = [];
        $cityCodes = [];
        $locations = [];

        foreach (tokk_line_news_collect_area_payloads($post_payload) as $area) {
            $slug = (string)($area['slug'] ?? '');
            if ($slug !== '') {
                $codes = tokk_line_news_get_area_codes_from_slug($slug);
                foreach ((array)($codes['prefecture'] ?? []) as $prefCode) {
                    $prefCode = (string)$prefCode;
                    if ($prefCode !== '') {
                        $prefectureCodes[$prefCode] = true;
                    }
                }
                foreach ((array)($codes['city'] ?? []) as $cityCode) {
                    $cityCode = (string)$cityCode;
                    if ($cityCode !== '') {
                        $cityCodes[$cityCode] = true;
                    }
                }
            }

            $lat = $area['lat'] ?? null;
            $lon = $area['lon'] ?? null;
            if (is_numeric($lat) && is_numeric($lon)) {
                $key = sprintf('%.6F:%.6F', (float)$lat, (float)$lon);
                $locations[$key] = [
                    'latitude' => (float)$lat,
                    'longitude' => (float)$lon,
                ];
            }
        }

        return [
            'prefectureCodes' => array_keys($prefectureCodes),
            'cityCodes' => array_keys($cityCodes),
            'locations' => array_values($locations),
        ];
    }
}

// URLに採用される代表area slug（ACF areaの先頭要素）を関連記事抽出用に解決する。
if (!function_exists('tokk_line_news_primary_area_slug')) {
    function tokk_line_news_primary_area_slug(array $post_payload): string
    {
        $areaIds = $post_payload['area'] ?? [];
        $areaIds = is_array($areaIds) ? $areaIds : [$areaIds];
        $firstAreaId = reset($areaIds);

        if (is_numeric($firstAreaId) && class_exists('TermModelHelper')) {
            $areaInfo = TermModelHelper::get_terms_payload('area', [(int)$firstAreaId], [], []);
            if (!empty($areaInfo[0]['slug'])) {
                return (string)$areaInfo[0]['slug'];
            }
        }

        $postId = (int)($post_payload['id'] ?? 0);
        if ($postId > 0) {
            $areaTerms = get_the_terms($postId, 'area');
            if (!is_wp_error($areaTerms) && !empty($areaTerms[0]->slug)) {
                return (string)$areaTerms[0]->slug;
            }
        }

        return '';
    }
}

// categoryフォールバック用に記事カテゴリslug一覧を解決する。
if (!function_exists('tokk_line_news_category_slugs')) {
    function tokk_line_news_category_slugs(array $post_payload): array
    {
        $categorySlugs = [];
        if (!empty($post_payload['category']) && is_array($post_payload['category']) && class_exists('TermModelHelper')) {
            foreach ($post_payload['category'] as $categoryId) {
                $catInfo = TermModelHelper::get_terms_payload('category', [$categoryId], [], []);
                if (!empty($catInfo[0]['slug'])) {
                    $categorySlugs[] = (string)$catInfo[0]['slug'];
                }
            }
        }

        return array_values(array_unique(array_filter($categorySlugs)));
    }
}

// areaを優先し、未設定時のみcategoryへフォールバックしてoa:reflink候補を最大3件組み立てる。
if (!function_exists('tokk_line_news_related_links')) {
    function tokk_line_news_related_links(array $post_payload): array
    {
        $relatedArgs = [
            'post_type' => 'articles',
            // URL代表area一致の後段絞り込みで件数が減るため、候補は少し多めに取得する。
            'posts_per_page' => 12,
            'post__not_in' => [(int)($post_payload['id'] ?? 0)],
        ];

        $primaryAreaSlug = tokk_line_news_primary_area_slug($post_payload);
        if ($primaryAreaSlug !== '') {
            $relatedArgs['tax_query'] = [
                [
                    'taxonomy' => 'area',
                    'field' => 'slug',
                    'terms' => [$primaryAreaSlug],
                    'operator' => 'IN',
                ],
            ];
        } else {
            $categorySlugs = tokk_line_news_category_slugs($post_payload);
            if (empty($categorySlugs)) {
                return [];
            }

            $relatedArgs['tax_query'] = [
                [
                    'taxonomy' => 'category',
                    'field' => 'slug',
                    'terms' => $categorySlugs,
                    'operator' => 'IN',
                ],
            ];
        }

        $relatedPosts = PostModelHelper::get_posts_payload($relatedArgs);
        $links = [];
        $seenPostIds = [];
        $seenUrls = [];
        foreach ((array)$relatedPosts as $relatedPost) {
            $relatedPostId = (int)($relatedPost['id'] ?? 0);
            if ($relatedPostId > 0 && isset($seenPostIds[$relatedPostId])) {
                continue;
            }

            if ($primaryAreaSlug !== '') {
                $relatedPrimaryAreaSlug = tokk_line_news_primary_area_slug((array)$relatedPost);
                if ($relatedPrimaryAreaSlug !== $primaryAreaSlug) {
                    continue;
                }
            }

            $title = trim((string)($relatedPost['title'] ?? ''));
            $url = trim((string)($relatedPost['permalink'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }

            if (isset($seenUrls[$url])) {
                continue;
            }

            $links[] = [
                'title' => $title,
                'url' => $url,
            ];

            if ($relatedPostId > 0) {
                $seenPostIds[$relatedPostId] = true;
            }
            $seenUrls[$url] = true;

            if (count($links) >= 3) {
                break;
            }
        }

        return $links;
    }
}

// iframeのsrcがLINEで許容したいYouTube埋め込みURLかを判定する。
if (!function_exists('tokk_line_news_is_youtube_embed_src')) {
    function tokk_line_news_is_youtube_embed_src(string $src): bool
    {
        return preg_match('#^https?://(?:www\.)?(?:youtube\.com/embed/|youtube-nocookie\.com/embed/)#i', $src) === 1;
    }
}

// 優先対象以外のaタグをリンクなしのテキストへ変換する。
if (!function_exists('tokk_line_news_handle_anchor_node')) {
    function tokk_line_news_handle_anchor_node(DOMElement $anchor, DOMDocument $dom): void
    {
        $parent = $anchor->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }

        $replacementText = trim($anchor->textContent);
        $parent->replaceChild($dom->createTextNode($replacementText), $anchor);
    }
}

// 本文先頭から見て、優先対象となるTOKK関西URLのaタグを最初の1件だけ特定する。
if (!function_exists('tokk_line_news_find_preferred_anchor')) {
    function tokk_line_news_find_preferred_anchor(DOMXPath $xpath): ?DOMElement
    {
        foreach ($xpath->query('//a') ?: [] as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $href = trim((string)$anchor->getAttribute('href'));
            if (tokk_line_news_is_preferred_anchor_href($href)) {
                return $anchor;
            }
        }

        return null;
    }
}

// セル内テキストを1行化し、brや改行由来の分断は半角スペースへ寄せて返す。
if (!function_exists('tokk_line_news_flatten_dom_text')) {
    function tokk_line_news_flatten_dom_text(DOMNode $node): string
    {
        $clone = $node->cloneNode(true);
        if (!$clone instanceof DOMNode) {
            return '';
        }

        $ownerDocument = $clone instanceof DOMDocument ? $clone : $clone->ownerDocument;
        if (!$ownerDocument instanceof DOMDocument) {
            return '';
        }

        $xpath = new DOMXPath($ownerDocument);
        foreach ($xpath->query('.//br', $clone) ?: [] as $br) {
            if ($br->parentNode instanceof DOMNode) {
                $br->parentNode->replaceChild($ownerDocument->createTextNode(' '), $br);
            }
        }

        return trim((string)preg_replace('/\s+/u', ' ', $clone->textContent));
    }
}

// tableを行単位の段落へ崩し、1列目を項目、2列目以降を値として「項目：値...」へ整形する。
if (!function_exists('tokk_line_news_replace_table_with_paragraphs')) {
    function tokk_line_news_replace_table_with_paragraphs(DOMElement $table, DOMDocument $dom): void
    {
        $parent = $table->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }

        $fragment = $dom->createDocumentFragment();
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('.//tr', $table) ?: [] as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }

            $cells = [];
            foreach ($xpath->query('./th|./td', $row) ?: [] as $cell) {
                if (!$cell instanceof DOMElement) {
                    continue;
                }

                $cells[] = tokk_line_news_flatten_dom_text($cell);
            }

            $cells = array_values(array_filter($cells, static function ($value) {
                return $value !== '';
            }));

            if (empty($cells)) {
                continue;
            }

            $item = array_shift($cells);
            $line = $item;

            if (!empty($cells)) {
                $line .= '：' . implode(' ', $cells);
            }

            $paragraph = $dom->createElement('p');
            $paragraph->appendChild($dom->createTextNode($line));
            $fragment->appendChild($paragraph);
        }

        $parent->replaceChild($fragment, $table);
    }
}

// description本文をDOMで走査し、見出し変換や属性除去などLINE向けに整形する。
if (!function_exists('tokk_line_news_transform_description_dom')) {
    function tokk_line_news_transform_description_dom(string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);
        $html = '<?xml encoding="UTF-8"><div id="tokk-line-root">' . $content . '</div>';
        $loaded = $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $content;
        }

        $xpath = new DOMXPath($dom);
        $rootNodes = $xpath->query('//*[@id="tokk-line-root"]');
        $root = ($rootNodes instanceof DOMNodeList && $rootNodes->length > 0) ? $rootNodes->item(0) : null;
        if (!$root instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $content;
        }

        // style属性はLINE仕様上保持しないため、全要素から除去する。
        foreach ($xpath->query('//*[@style]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $node->removeAttribute('style');
            }
        }

        // script/styleタグは本文表示に不要かつLINE仕様外のため除去する。
        foreach ($xpath->query('//script|//style') ?: [] as $node) {
            if ($node->parentNode instanceof DOMNode) {
                $node->parentNode->removeChild($node);
            }
        }

        // LINEで許容される見出しはh1/h2のみなので、h3〜h6はh2へ寄せる。
        // DOMDocument::renameNode() は環境によって使えないため、
        // 新しいh2要素を作って子ノードと属性を移し替える互換実装にする。
        foreach ($xpath->query('//h3|//h4|//h5|//h6') ?: [] as $heading) {
            if ($heading instanceof DOMElement) {
                $replacement = $dom->createElement('h2');

                if ($heading->hasAttributes()) {
                    foreach ($heading->attributes as $attribute) {
                        $replacement->setAttribute($attribute->nodeName, $attribute->nodeValue);
                    }
                }

                while ($heading->firstChild) {
                    $replacement->appendChild($heading->firstChild);
                }

                if ($heading->parentNode instanceof DOMNode) {
                    $heading->parentNode->replaceChild($replacement, $heading);
                }
            }
        }

        // tableはLINEでそのまま扱えないため、行単位のpタグへ崩して情報を保持する。
        foreach ($xpath->query('//table') ?: [] as $table) {
            if ($table instanceof DOMElement) {
                tokk_line_news_replace_table_with_paragraphs($table, $dom);
            }
        }

        // iframeはYouTube埋め込みだけを残し、それ以外は丸ごと除去する。
        // 残す場合も必要最小限の属性だけに絞る。
        foreach ($xpath->query('//iframe') ?: [] as $iframe) {
            if (!$iframe instanceof DOMElement) {
                continue;
            }
            $src = trim((string)$iframe->getAttribute('src'));
            if (!tokk_line_news_is_youtube_embed_src($src)) {
                if ($iframe->parentNode instanceof DOMNode) {
                    $iframe->parentNode->removeChild($iframe);
                }
                continue;
            }

            $keepAttrs = ['src', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen'];
            if ($iframe->hasAttributes()) {
                $toRemove = [];
                foreach ($iframe->attributes as $attr) {
                    if (!in_array($attr->nodeName, $keepAttrs, true)) {
                        $toRemove[] = $attr->nodeName;
                    }
                }
                foreach ($toRemove as $attrName) {
                    $iframe->removeAttribute($attrName);
                }
            }
        }

        // imgはLINEで扱える拡張子だけを残し、captionも文字数上限内へ丸める。
        foreach ($xpath->query('//img') ?: [] as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }
            $src = trim((string)$img->getAttribute('src'));
            $ext = strtolower((string)pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            if ($src === '' || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                if ($img->parentNode instanceof DOMNode) {
                    $img->parentNode->removeChild($img);
                }
                continue;
            }

            if ($img->hasAttribute('data-caption')) {
                $caption = (string)$img->getAttribute('data-caption');
                if (function_exists('mb_strimwidth')) {
                    $caption = mb_strimwidth($caption, 0, 255, '', 'UTF-8');
                }
                $img->setAttribute('data-caption', $caption);
            }
        }

        // 【MAP】リンクは常に文字列だけ残す。既存の優先リンク判定より先に処理する。
        foreach ($xpath->query('//a') ?: [] as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            if (trim((string)preg_replace('/\s+/u', '', $anchor->textContent)) === '【MAP】') {
                tokk_line_news_handle_anchor_node($anchor, $dom);
            }
        }

        // TOKK関西の記事URLに当たるaタグがあれば、その最初の1件だけを残す。
        // 見つからない場合はaタグを加工せず、LINE側の標準動作に委ねる。
        $preferredAnchor = tokk_line_news_find_preferred_anchor($xpath);
        if ($preferredAnchor instanceof DOMElement) {
            foreach ($xpath->query('//a') ?: [] as $anchor) {
                if ($anchor instanceof DOMElement && !$anchor->isSameNode($preferredAnchor)) {
                    tokk_line_news_handle_anchor_node($anchor, $dom);
                }
            }
        }

        // 整形後に空になったpタグはノイズになるため除去する。
        foreach ($xpath->query('//p[not(normalize-space()) and not(*)]') ?: [] as $emptyParagraph) {
            if ($emptyParagraph->parentNode instanceof DOMNode) {
                $emptyParagraph->parentNode->removeChild($emptyParagraph);
            }
        }

        // ラッパー配下のHTML断片だけを連結して返す。
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $result;
    }
}

// 最終的なdescription本文を許可タグベースでサニタイズし、空本文も補完する。
if (!function_exists('tokk_line_news_sanitize_content')) {
    function tokk_line_news_sanitize_content(string $content): string
    {
        $content = preg_replace('/<blockquote class="instagram-media"[\s\S]*?<\/blockquote>/i', '', $content);
        $content = tokk_line_news_transform_description_dom($content);

        $allowedTags = [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'h1' => [],
            'h2' => [],
            'blockquote' => [
                'class' => true,
                'title' => true,
                'data-lang' => true,
                'lang' => true,
                'dir' => true,
                'data-instgrm-captioned' => true,
                'data-instgrm-permalink' => true,
                'data-instgrm-version' => true,
            ],
            'a' => ['href' => true],
            'img' => [
                'src' => true,
                'alt' => true,
                'data-caption' => true,
            ],
            'iframe' => [
                'src' => true,
                'width' => true,
                'height' => true,
                'frameborder' => true,
                'allow' => true,
                'allowfullscreen' => true,
            ],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'hr' => [],
        ];

        $content = wp_kses($content, $allowedTags);
        $content = wpautop(trim($content), false);
        $content = preg_replace('/<p(?:\s+[^>]*)?>\s*<\/p>/i', '', $content);

        if (trim(wp_strip_all_tags($content)) === '') {
            return '<p>本文なし</p>';
        }

        return $content;
    }
}

header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . '>';

do_action('rss_tag_pre', 'rss2');
?>
<rss version="2.0"
     xmlns:oa="http://news.line.me/rss/1.0/oa"
    <?php do_action('rss2_ns'); ?>
>
    <channel>
        <title><?php wp_title_rss(); ?></title>
        <link><?= esc_url(home_url('/')); ?></link>
        <description><![CDATA[<?= tokk_line_news_channel_description(); ?>]]></description>
        <lastBuildDate><?= esc_html(tokk_line_news_now_rfc2822_jst()); ?></lastBuildDate>
        <language><?php bloginfo_rss('language'); ?></language>
        <?php
        $excludedFields = get_field('feed_excluded_articles_list', 'option');
        if (!is_array($excludedFields)) {
            $excludedFields = [];
        }

        foreach ($excludedFields as $field) :
            if (empty($field->ID)) {
                continue;
            }
            $deletedPayload = tokk_line_news_deleted_item_payload((int)$field->ID);
            ?>
            <item>
                <guid><?= esc_html((string)$field->ID); ?></guid>
                <title><![CDATA[<?= $deletedPayload['title']; ?>]]></title>
                <link><?= esc_url($deletedPayload['link']); ?></link>
                <pubDate><?= esc_html($deletedPayload['pubDate']); ?></pubDate>
                <description><![CDATA[<?= $deletedPayload['description']; ?>]]></description>
                <oa:lastPubDate><?= esc_html($deletedPayload['lastPubDate']); ?></oa:lastPubDate>
                <oa:delStatus>1</oa:delStatus>
            </item>
        <?php endforeach; ?>
        <?php
        $postList = (array)PostModelHelper::get_posts_payload(
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

        usort($postList, function ($a, $b) {
            $aMod = $a['modified'] ?? '';
            $bMod = $b['modified'] ?? '';

            if ($aMod === '' && $bMod === '') {
                return 0;
            }
            if ($aMod === '') {
                return 1;
            }
            if ($bMod === '') {
                return -1;
            }

            return strtotime($bMod) <=> strtotime($aMod);
        });

        if (!empty($postList)) :
            foreach ($postList as $postPayload) :
                $itemDatetime = tokk_line_news_item_datetime($postPayload);
                $contentSource = (string)($postPayload['content'] ?? '');
                $postId = (int)($postPayload['id'] ?? 0);
                if ($postId > 0) {
                    $postObject = get_post($postId);
                    if ($postObject instanceof WP_Post && function_exists('lutwiyo_should_mask_paid_article_content') && lutwiyo_should_mask_paid_article_content($postObject)) {
                        $contentSource = lutwiyo_build_paid_article_masked_content($postObject);
                    } elseif (function_exists('lutwiyo_strip_paywall_gate_placeholder')) {
                        $contentSource = lutwiyo_strip_paywall_gate_placeholder($contentSource);
                    }
                }

                $content = tokk_line_news_sanitize_content($contentSource);
                $enclosure = tokk_line_news_enclosure_payload($postPayload);
                $geo = tokk_line_news_collect_geo_payload($postPayload);
                $relatedLinks = tokk_line_news_related_links($postPayload);
                ?>
                <item>
                    <guid><?= esc_html((string)($postPayload['id'] ?? '')); ?></guid>
                    <title><![CDATA[<?= (string)($postPayload['title'] ?? ''); ?>]]></title>
                    <link><?= esc_url((string)($postPayload['permalink'] ?? '')); ?></link>
                    <pubDate><?= esc_html($itemDatetime); ?></pubDate>
                    <description><![CDATA[<?= $content; ?>]]></description>
                    <?php if (!empty($enclosure)): ?>
                        <enclosure
                            url="<?= esc_url($enclosure['url']); ?>"
                            type="<?= esc_attr($enclosure['type']); ?>"
                            <?php if ($enclosure['caption'] !== ''): ?>
                                caption="<?= esc_attr($enclosure['caption']); ?>"
                            <?php endif; ?>
                        />
                        <oa:imgAuthor>
                            <oa:authorName><![CDATA[<?= get_bloginfo('name'); ?>]]></oa:authorName>
                            <oa:authorUrl><?= esc_url(home_url('/')); ?></oa:authorUrl>
                        </oa:imgAuthor>
                    <?php endif; ?>
                    <?php foreach ($relatedLinks as $relatedLink): ?>
                        <oa:reflink>
                            <oa:refTitle><![CDATA[<?= $relatedLink['title']; ?>]]></oa:refTitle>
                            <oa:refUrl><?= esc_url($relatedLink['url']); ?></oa:refUrl>
                        </oa:reflink>
                    <?php endforeach; ?>
                    <oa:lastPubDate><?= esc_html($itemDatetime); ?></oa:lastPubDate>
                    <?php foreach ((array)$geo['prefectureCodes'] as $prefectureCode): ?>
                        <oa:prefectureCode><?= esc_html($prefectureCode); ?></oa:prefectureCode>
                    <?php endforeach; ?>
                    <?php foreach ((array)$geo['cityCodes'] as $cityCode): ?>
                        <oa:cityCode><?= esc_html($cityCode); ?></oa:cityCode>
                    <?php endforeach; ?>
                    <?php foreach ((array)$geo['locations'] as $location): ?>
                        <oa:location>
                            <oa:latitude><?= esc_html((string)$location['latitude']); ?></oa:latitude>
                            <oa:longitude><?= esc_html((string)$location['longitude']); ?></oa:longitude>
                        </oa:location>
                    <?php endforeach; ?>
                </item>
            <?php
            endforeach;
        else :
            echo 'NODATA';
        endif;
        ?>
    </channel>
</rss>
