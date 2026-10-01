<?php
/**
 * Pagination Utility (2025 Edition - Themed version)
 *
 * Generates <div class="paging"> structure with ellipsis (...) when total pages exceed threshold.
 * Compatible with WordPress ($paged, $max_num_pages).
 *
 * @version 2.2.0 (2025-10)
 */

final class Pagination
{
    /**
     * Generate pagination HTML compatible with the design template.
     *
     * @param int $current_page_num Current page number (1-indexed)
     * @param int $max_page_num Maximum number of pages
     * @param string|null $base_url Base URL (auto-detected if null)
     * @param string $page_param Query parameter name (default: 'paged')
     * @param int $display_range Number of numeric pages to display before/after current (default: 1)
     * @return string HTML
     */
    public static function render(
        int     $current_page_num,
        int     $max_page_num,
        ?string $base_url = null,
        string  $page_param = 'paged',
        int     $display_range = 1
    ): string
    {
        if ($max_page_num <= 1) {
            return '';
        }

        // ベースURLを自動検出
        if ($base_url === null) {
            $base_url = $_SERVER['REQUEST_URI'] ?? '/';
        }

        // 現在のページ番号パラメータを除去
        $base_url = self::remove_parameters($base_url, [$page_param]);

        // URL生成クロージャ
        $build_url = function (int $page) use ($base_url, $page_param): string {
            $separator = (parse_url($base_url, PHP_URL_QUERY)) ? '&' : '?';
            return htmlspecialchars($base_url . $separator . "{$page_param}={$page}", ENT_QUOTES, 'UTF-8');
        };

        return self::render_with_builder($current_page_num, $max_page_num, $build_url, $display_range);
    }

    /**
     * Generate pagination HTML for pretty URLs (e.g., /staff/slug/page/2/).
     *
     * @param int $current_page_num Current page number (1-indexed)
     * @param int $max_page_num Maximum number of pages
     * @param string $base_url Base URL (e.g., https://example.com/staff/slug/)
     * @param string $page_segment URL path segment used for paging (default: 'page')
     * @param int $display_range Number of numeric pages to display before/after current (default: 1)
     * @return string HTML
     */
    public static function render_pretty(
        int    $current_page_num,
        int    $max_page_num,
        string $base_url,
        string $page_segment = 'page',
        int    $display_range = 1
    ): string
    {
        if ($max_page_num <= 1) {
            return '';
        }

        $base = rtrim($base_url, '/');
        $page_segment = trim($page_segment, '/');

        $build_url = function (int $page) use ($base, $page_segment): string {
            if ($page <= 1) {
                return htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8');
            }
            return htmlspecialchars($base . '/' . $page_segment . '/' . $page . '/', ENT_QUOTES, 'UTF-8');
        };

        return self::render_with_builder($current_page_num, $max_page_num, $build_url, $display_range);
    }

    /**
     * 共通のページングHTMLを生成
     *
     * @param int $current_page_num
     * @param int $max_page_num
     * @param callable $build_url
     * @param int $display_range
     * @return string
     */
    private static function render_with_builder(
        int $current_page_num,
        int $max_page_num,
        callable $build_url,
        int $display_range
    ): string
    {
        $has_prev = $current_page_num > 1;
        $has_next = $current_page_num < $max_page_num;

        // 出力バッファ
        $html = [];

        $html[] = '<div class="paging">';

        // PREVボタン
        if ($has_prev) {
            $html[] = sprintf(
                '<a class="btnCircle pagingBtn" href="%s" data-circle="w-36"><div class="btnArrow btnArrow--prev" data-arrow="w-8"></div></a>',
                $build_url($current_page_num - 1)
            );
        } else {
            $html[] = '<a class="btnCircle pagingBtn disabled" href="#" data-circle="w-36"><div class="btnArrow btnArrow--prev" data-arrow="w-8"></div></a>';
        }

        // ページ番号リスト開始
        $html[] = '<ul class="pagingList">';

        /**
         * ページ表示ロジック：
         * - 総ページ数が4以下 → すべて表示
         * - 5ページ以上 → 先頭・末尾・現在前後のみ表示し、間を "..." で省略
         */
        if ($max_page_num <= 4) {
            for ($i = 1; $i <= $max_page_num; $i++) {
                $html[] = self::renderPageItem($i, $current_page_num, $build_url);
            }
        } else {
            $range_start = max(1, $current_page_num - $display_range);
            $range_end = min($max_page_num, $current_page_num + $display_range);

            // 先頭ページ
            $html[] = self::renderPageItem(1, $current_page_num, $build_url);

            // 「...」省略 (2ページ目より後で範囲が空いた場合)
            if ($range_start > 2) {
                $html[] = '<li class="pagingTarget"><p class="pagingNum">...</p></li>';
            }

            // 中央部分
            for ($i = $range_start; $i <= $range_end; $i++) {
                if ($i !== 1 && $i !== $max_page_num) {
                    $html[] = self::renderPageItem($i, $current_page_num, $build_url);
                }
            }

            // 「...」省略 (末尾2ページ前で範囲が空いた場合)
            if ($range_end < $max_page_num - 1) {
                $html[] = '<li class="pagingTarget"><p class="pagingNum">...</p></li>';
            }

            // 最終ページ
            $html[] = self::renderPageItem($max_page_num, $current_page_num, $build_url);
        }

        $html[] = '</ul>'; // pagingList 終了

        // NEXTボタン
        if ($has_next) {
            $html[] = sprintf(
                '<a class="btnCircle pagingBtn" href="%s" data-circle="w-36"><div class="btnArrow btnArrow--next" data-arrow="w-8"></div></a>',
                $build_url($current_page_num + 1)
            );
        } else {
            $html[] = '<a class="btnCircle pagingBtn disabled" href="#" data-circle="w-36"><div class="btnArrow btnArrow--next" data-arrow="w-8"></div></a>';
        }

        $html[] = '</div>'; // paging 終了

        return implode("\n", $html);
    }

    /**
     * 個々のページ項目を生成
     *
     * @param int $page_num
     * @param int $current_page
     * @param callable $build_url
     * @return string
     */
    private static function renderPageItem(int $page_num, int $current_page, callable $build_url): string
    {
        $is_active = $page_num === $current_page;
        $aria_selected = $is_active ? 'true' : 'false';
        $classes = 'btnCircle pagingTarget' . ($is_active ? ' active' : '');
        $url = $build_url($page_num);
        $title = htmlspecialchars((string)$page_num, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<li class="%s" aria-selected="%s" data-circle="w-36">' .
            '<a class="pagingLink" href="%s" title="%s"><p class="pagingNum">%d</p></a>' .
            '</li>',
            $classes,
            $aria_selected,
            $url,
            $title,
            $page_num
        );
    }

    /**
     * 指定したクエリパラメータをURLから安全に除去
     *
     * @param string $url
     * @param string[] $target_param_list
     * @return string
     */
    public static function remove_parameters(string $url, array $target_param_list): string
    {
        $parsed = parse_url($url);
        $query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }

        foreach ($target_param_list as $param) {
            unset($query[$param]);
        }

        $scheme = $parsed['scheme'] ?? '';
        $host = $parsed['host'] ?? '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '';
        $queryStr = http_build_query($query);
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        $rebuilt_url = '';
        if ($scheme && $host) {
            $rebuilt_url .= "{$scheme}://{$host}{$port}";
        }
        $rebuilt_url .= $path;
        if ($queryStr !== '') {
            $rebuilt_url .= '?' . $queryStr;
        }
        $rebuilt_url .= $fragment;

        return $rebuilt_url;
    }
}
