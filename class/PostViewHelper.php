<?php
/**
 * Class PostViewHelper
 *
 * 全てのViewテンプレート共通の出力補助クラス。
 * 現時点では articles 向けの共通初期化処理のみ実装。
 *
 * 責務：
 * - PostModelHelperが返す $article 配列をテンプレートで扱いやすい形に整形
 * - エスケープ処理は行わず、テンプレート側で文脈に応じて実施
 * - テンプレート構文の簡潔化 ($article['title'] → $title)
 *
 * 使用例：
 * <?php foreach ($article_info_list as $article): ?>
 *     <?php PostViewHelper::prepare_articles($article); ?>
 *     <img src="<?= esc_url($image_url); ?>" alt="<?= esc_attr($title); ?>">
 * <?php endforeach; ?>
 */


if (!class_exists('PostViewHelper')) {
    class PostViewHelper
    {
        /**
         * articles用の共通データ初期化処理
         *
         * @param array $article PostModelHelperから渡される記事データ配列
         * @return array 整形済みデータ（テンプレートでextract()可能）
         */
        public static function prepare_articles(array $article): array
        {
            $id = $article['id'] ?? 0;
            $is_pr = $article['is_pr'] ?? false;
            $title = $article['title'] ?? '';
            // タイトルに(PR)は表示しないとする仕様変更に伴いコメントアウト
            // $title = $article['is_pr'] ? '(PR) ' . $title : $title;
            $link = $article['permalink'] ?? '#';
            $image_url = $article['image_url'] ?? '/assets/img/common/placeholder--600x400.png';
            $reading_time_label = $article['reading_time_label'] ?? '';
            $display_date_label = $article['display_date_label'] ?? '';
            $is_pinned = !empty($article['is_pinned']);
            $is_new = !empty($article['is_new']);
            $is_pinned_class = $is_pinned ? 'is--pinned' : '';
            $is_new_class = $is_new ? 'is--new' : '';
            $is_pinned_result = $is_pinned ? '📍' : '';
            $is_favorite = $article['is_favorited'] ?? false;
            $is_favorite_class = $is_favorite ? 'active' : '';
            $has_present = $article['has_present'] ?? false;
            $has_present_class = $has_present ? 'is--special active' : '';
            $member_access_plan = $article['member_access_plan'] ?? 'public';
            $is_paid_member_limited = $member_access_plan === 'paid_member';
            $access_badge_label = $is_paid_member_limited ? '有料会員限定' : '';
            $access_badge_modifier_class = $is_paid_member_limited ? 'articleAccessBadge--paidMember' : '';
            return [
                'id' => $id,
                'is_pr' => $is_pr,
                'title' => $title,
                'link' => $link,
                'image_url' => $image_url,
                'reading_time_label' => $reading_time_label,
                'display_date_label' => $display_date_label,
                'is_pinned' => $is_pinned,
                'is_new' => $is_new,
                'is_pinned_class' => $is_pinned_class,
                'is_new_class' => $is_new_class,
                'is_pinned_result' => $is_pinned_result,
                'is_favorite' => $is_favorite,
                'is_favorite_class' => $is_favorite_class,
                'has_present' => $has_present,
                'has_present_class' => $has_present_class,
                'member_access_plan' => $member_access_plan,
                'is_paid_member_limited' => $is_paid_member_limited,
                'access_badge_label' => $access_badge_label,
                'access_badge_modifier_class' => $access_badge_modifier_class,
            ];
        }
    }
}
