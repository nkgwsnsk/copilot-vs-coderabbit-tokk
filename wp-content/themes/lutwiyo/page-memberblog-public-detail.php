<?php
/**
 * Template Name: Member Blog Public Detail
 *
 * 公開会員ブログ詳細ページ（post_id 指定）
 */

get_header();

$publicDetailErrorMessage = function_exists('tokk_memberblog_get_public_detail_error_message')
    ? tokk_memberblog_get_public_detail_error_message()
    : 'ブログ記事の表示に失敗しました。再読み込みをお試しください。';
$postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
$result = $postId > 0 ? tokk_memberblog_fetch_public_post($postId) : [
    'success' => false,
    'error_message' => $publicDetailErrorMessage,
];
$item = is_array($result['item'] ?? null) ? $result['item'] : [];
$memberUid = trim((string) ($item['member_uid'] ?? ''));
$memberSlug = trim((string) ($item['member_slug'] ?? ''));
$memberDisplayName = trim((string) ($item['member_display_name'] ?? ''));
if ($memberDisplayName === '') {
    $memberDisplayName = '会員';
}
$memberProfileImageUrl = trim((string) ($item['member_profile_image_url'] ?? ''));
$areaSlugs = isset($item['area_slugs']) && is_array($item['area_slugs']) ? $item['area_slugs'] : [];
$tagSlugs = isset($item['tag_slugs']) && is_array($item['tag_slugs']) ? $item['tag_slugs'] : [];
if (function_exists('tokk_memberblog_filter_allowed_tag_slugs')) {
    $tagSlugs = tokk_memberblog_filter_allowed_tag_slugs($tagSlugs);
}
$title = trim((string) ($item['title'] ?? ''));
if ($title === '') {
    $title = '無題';
}

$primaryAreaInfo = function_exists('tokk_memberblog_get_primary_area_info')
    ? tokk_memberblog_get_primary_area_info($item)
    : [];
$primaryAreaSlug = trim((string) ($primaryAreaInfo['slug'] ?? ($areaSlugs[0] ?? '')));
$primaryAreaName = trim((string) ($primaryAreaInfo['name'] ?? ''));
if ($primaryAreaName === '' && $primaryAreaSlug !== '' && function_exists('tokk_memberblog_get_term_name_by_slug')) {
    $primaryAreaName = tokk_memberblog_get_term_name_by_slug('area', $primaryAreaSlug);
}
$primaryAreaLink = $primaryAreaSlug !== ''
    ? home_url('/memberblog-public-area-list/?area_slug=' . rawurlencode($primaryAreaSlug))
    : '';
$memberListUrl = $memberSlug !== ''
    ? home_url('/memberblog-public-member-list/?member_slug=' . rawurlencode($memberSlug))
    : '';
$publicListUrl = home_url('/memberblog-public-list/');
$imageUrl = function_exists('tokk_memberblog_get_public_card_image_url')
    ? tokk_memberblog_get_public_card_image_url($item)
    : get_stylesheet_directory_uri() . '/assets/img/sample/thum--28.webp';
$defaultMemberProfileImageUrl = get_stylesheet_directory_uri() . '/assets/img/sample/thum--28.webp';
$updatedDateLabel = function_exists('tokk_memberblog_format_updated_date_label')
    ? tokk_memberblog_format_updated_date_label((string) ($item['updated_at'] ?? ''))
    : trim((string) ($item['updated_at'] ?? ''));

$sidebarRankingArticles = function_exists('tokk_memberblog_get_sidebar_ranking_articles')
    ? tokk_memberblog_get_sidebar_ranking_articles($primaryAreaInfo)
    : [];
$sidebarCategories = function_exists('tokk_memberblog_get_sidebar_categories')
    ? tokk_memberblog_get_sidebar_categories()
    : [];
$sidebarAdList = function_exists('tokk_memberblog_get_sidebar_ad_list')
    ? tokk_memberblog_get_sidebar_ad_list($primaryAreaInfo)
    : [];
$showSidebarAds = !empty($sidebarAdList)
    && (!function_exists('lutwiyo_can_view_advertisement') || lutwiyo_can_view_advertisement());
$showSidebar = !empty($sidebarRankingArticles) || !empty($sidebarCategories) || $showSidebarAds;
$rankingDateLabel = sprintf(
    '%s - %s',
    date('Y.n.j', strtotime('yesterday -6 days')),
    date('Y.n.j', strtotime('yesterday'))
);

$memberRelatedContext = null;
if (($memberSlug !== '' || $memberUid !== '') && function_exists('tokk_memberblog_get_public_posts_context_from_filters')) {
    $candidate = tokk_memberblog_get_public_posts_context_from_filters([
        'member_uid' => $memberSlug === '' ? $memberUid : '',
        'member_slug' => $memberSlug,
        'page' => 1,
        'per_page' => 5,
    ]);
    $candidateItems = is_array($candidate['items'] ?? null) ? $candidate['items'] : [];
    $candidateItems = array_values(array_filter($candidateItems, static function ($row) use ($postId): bool {
        return (int) ($row['id'] ?? 0) !== $postId;
    }));
    if ($candidateItems !== []) {
        $candidate['items'] = $candidateItems;
        $memberRelatedContext = $candidate;
    }
}

$areaRelatedContext = null;
if ($primaryAreaSlug !== '' && function_exists('tokk_memberblog_get_public_posts_context_from_filters')) {
    $candidate = tokk_memberblog_get_public_posts_context_from_filters([
        'area_slug' => $primaryAreaSlug,
        'page' => 1,
        'per_page' => 5,
    ]);
    $candidateItems = is_array($candidate['items'] ?? null) ? $candidate['items'] : [];
    $candidateItems = array_values(array_filter($candidateItems, static function ($row) use ($postId): bool {
        return (int) ($row['id'] ?? 0) !== $postId;
    }));
    if ($candidateItems !== []) {
        $candidate['items'] = $candidateItems;
        $areaRelatedContext = $candidate;
    }
}

$commentViewAcl = function_exists('lutwiyo_normalize_comment_view_acl')
    ? lutwiyo_normalize_comment_view_acl((string) ($item['comment_view_acl'] ?? 'public'))
    : 'public';
$commentPostAcl = function_exists('lutwiyo_normalize_comment_post_acl')
    ? lutwiyo_normalize_comment_post_acl((string) ($item['comment_post_acl'] ?? 'disabled'))
    : 'disabled';
$commentCurrentMemberUid = function_exists('lutwiyo_get_current_member_uid_from_bridge')
    ? lutwiyo_get_current_member_uid_from_bridge()
    : '';
$commentCurrentMemberProfile = function_exists('lutwiyo_get_current_member_profile_summary')
    ? lutwiyo_get_current_member_profile_summary()
    : ['nickname' => ''];
$commentCurrentMemberDisplayName = trim((string) ($commentCurrentMemberProfile['nickname'] ?? ''));
if ($commentCurrentMemberDisplayName === '') {
    $commentCurrentMemberDisplayName = '会員';
}
$commentCurrentMemberProfileImageUrl = trim((string) ($commentCurrentMemberProfile['profile_image_url'] ?? ''));
$commentMemberAccessContext = function_exists('lutwiyo_get_member_access_context')
    ? lutwiyo_get_member_access_context()
    : ['is_logged_in' => false, 'plan' => 'guest', 'uid' => ''];
$commentViewerPlan = (string) ($commentMemberAccessContext['plan'] ?? 'guest');
$isCommentLoggedIn = ($commentMemberAccessContext['is_logged_in'] ?? false) === true
    && in_array($commentViewerPlan, ['free', 'paid'], true);
$isCommentFreeViewer = $isCommentLoggedIn && $commentViewerPlan === 'free';
$likeResourceId = trim((string) ($item['id'] ?? $postId));
$isLikeLoggedIn = $commentCurrentMemberUid !== '';
$isLikedByCurrentMember = $isLikeLoggedIn && $likeResourceId !== '' && function_exists('lutwiyo_get_current_member_like_state')
    ? lutwiyo_get_current_member_like_state('member_blog_post', $likeResourceId)
    : false;
$likeActiveClass = $isLikedByCurrentMember ? 'active' : '';
$likeAriaPressed = $isLikedByCurrentMember ? 'true' : 'false';
$memberDisplayNameWithSuffix = $memberDisplayName === '会員' ? '会員' : $memberDisplayName . 'さん';
$memberListLinkLabel = $memberDisplayNameWithSuffix . 'の会員ブログ一覧を見る';
$memberRelatedHeading = $memberDisplayNameWithSuffix . 'の他の記事';
$areaListLinkLabel = ($primaryAreaName !== '' ? $primaryAreaName : 'このエリア') . 'の会員ブログを見る';
$publicListLinkLabel = '会員ブログ一覧に戻る';
$showCommentBlock = $commentViewAcl !== 'private';
$canShowCommentList = function_exists('lutwiyo_can_member_context_view_comments')
    ? lutwiyo_can_member_context_view_comments($commentMemberAccessContext, $commentViewAcl)
    : ($showCommentBlock && $isCommentLoggedIn);
$requiresCommentLoginForView = $showCommentBlock && !$canShowCommentList;
$isCommentPostingEnabled = $commentPostAcl === 'enabled';
$canSubmitComment = function_exists('lutwiyo_can_member_context_post_comments')
    ? lutwiyo_can_member_context_post_comments($commentMemberAccessContext, $commentViewAcl, $commentPostAcl)
    : ($canShowCommentList && $isCommentPostingEnabled && $commentViewerPlan === 'paid');
$commentFormDisabled = !$canSubmitComment;
$commentLoginUrl = home_url('/login/');
$commentRegisterUrl = home_url('/regist/');
$commentUpgradeUrl = home_url('/paid-exp/');
$commentPostingStateMessage = $canSubmitComment
    ? ''
    : (function_exists('lutwiyo_get_comment_post_unavailable_message')
        ? lutwiyo_get_comment_post_unavailable_message($commentMemberAccessContext, $commentPostAcl)
        : (!$isCommentPostingEnabled ? '現在コメント投稿は停止中です。' : '会員にログインするとコメント投稿できます。'));

$commentListResponse = [
    'ok' => false,
    'status' => 0,
    'code' => '',
    'message' => '',
    'total' => 0,
    'items' => [],
];
if ($canShowCommentList && $postId > 0 && function_exists('lutwiyo_get_member_comment_items')) {
    $commentListResponse = lutwiyo_get_member_comment_items('member_blog_post', (string) $postId, 1, 20, 'posted_at:desc');
}
$commentItems = is_array($commentListResponse['items'] ?? null) ? $commentListResponse['items'] : [];
$commentTotalCount = max((int) ($commentListResponse['total'] ?? 0), count($commentItems));
$commentFetchFailed = $canShowCommentList && !$commentListResponse['ok'];
$commentListNoticeMessage = '';
$commentListNoticeModifierClass = 'commentBlock__notice--info';
if (!$requiresCommentLoginForView) {
    if (!$isCommentPostingEnabled) {
        $commentListNoticeMessage = 'この記事ではコメントが停止されています。';
    } elseif ($commentFetchFailed) {
        $commentListNoticeMessage = 'コメントを読み込めませんでした。時間をおいて再度お試しください。';
    } elseif ($commentTotalCount === 0) {
        $commentListNoticeMessage = '記事にコメントを追加して盛り上がりましょう！';
        $commentListNoticeModifierClass = 'commentBlock__notice--success';
    }
}
$formatCommentPostedAtLabel = static function ($rawPostedAt): string {
    $normalized = trim((string) $rawPostedAt);
    if ($normalized === '') {
        return '';
    }

    try {
        $datetime = new DateTime($normalized);
    } catch (Exception $exception) {
        return '';
    }

    return $datetime->format('Y.m.d H:i');
};

?>
<main class="main wrapper" role="main">
<?php if ($result['success'] && !empty($primaryAreaInfo)) : ?>
    <?php
    get_template_part(
        'partials/modules/kv',
        null,
        [
            'area_info' => $primaryAreaInfo,
        ]
    );
    get_template_part(
        'partials/modules/area-nav',
        null,
        [
            'area_info' => $primaryAreaInfo,
        ]
    );
    ?>
<?php endif; ?>

<?php if (!$result['success']) : ?>
    <section class="otherSection" aria-label="会員ブログ公開詳細エラー">
        <div class="gridWide">
            <p class="memberblog-public-detail-page__error"><?php echo esc_html((string) ($result['error_message'] ?? $publicDetailErrorMessage)); ?></p>
            <p><a href="<?php echo esc_url($publicListUrl); ?>"><?php echo esc_html($publicListLinkLabel); ?></a></p>
        </div>
    </section>
<?php else : ?>
    <article class="pdArticle memberblog-public-detail-page">
        <section class="pdSection" data-boxBgColor="bodySub">
            <div class="pdSection__grid">
                <div class="pdContents__leader">
                    <h1 class="fs--32 blockTitle"><?php echo esc_html($title); ?></h1>

                    <div class="blockBox__infoSub--list">
                        <?php if ($primaryAreaName !== '' && $primaryAreaLink !== '') : ?>
                            <a class="textHoverWrapper blockBox__infoSub--target" href="<?php echo esc_url($primaryAreaLink); ?>" title="<?php echo esc_attr($primaryAreaName); ?>">
                                <div class="icon iconMap"><div class="mask iconInner"></div></div>
                                <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text"><?php echo esc_html($primaryAreaName); ?></p>
                            </a>
                        <?php endif; ?>
                        <?php if ($memberListUrl !== '') : ?>
                            <a class="textHoverWrapper blockBox__infoSub--target" href="<?php echo esc_url($memberListUrl); ?>" title="<?php echo esc_attr($memberDisplayNameWithSuffix); ?>の公開一覧">
                                <div class="memberblog-public-detail-page__personIcon" aria-hidden="true">
                                    <svg class="userIcon" role="img" viewBox="0 0 20 20">
                                        <path d="M12.4,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM6.9,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM8.8,11.1h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7H4.6v-.7c0-2.3,1.9-4.2,4.2-4.2Z"/>
                                    </svg>
                                </div>
                                <p class="fontW--r textColor--footer textHover__target blockBox__infoSub--text"><?php echo esc_html($memberDisplayNameWithSuffix); ?></p>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($tagSlugs) || $likeResourceId !== '') : ?>
                        <ul class="hashList memberblog-public-detail-page__hashList">
                            <?php foreach ($tagSlugs as $tagSlugRaw) : ?>
                                <?php
                                $tagSlug = trim((string) $tagSlugRaw);
                                if ($tagSlug === '') {
                                    continue;
                                }
                                $tagName = function_exists('tokk_memberblog_get_term_name_by_slug')
                                    ? tokk_memberblog_get_term_name_by_slug('post_tag', $tagSlug)
                                    : $tagSlug;
                                $tagUrl = home_url('/memberblog-public-list/?tag_slug=' . rawurlencode($tagSlug));
                                ?>
                                <li class="hashTarget hashUser">
                                    <a class="textHoverWrapper hashLink" href="<?php echo esc_url($tagUrl); ?>" title="<?php echo esc_attr($tagName); ?>">
                                        <p class="textHover__target hashTarget--p"><?php echo esc_html($tagName); ?></p>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($likeResourceId !== '') : ?>
                                <li class="hashTarget memberblog-public-detail-page__likeHash">
                                    <div class="js--likeBtn memberblog-public-detail-page__likeChip <?php echo esc_attr($likeActiveClass); ?>"
                                         role="button"
                                         data-resource-type="member_blog_post"
                                         data-resource-id="<?php echo esc_attr($likeResourceId); ?>"
                                         aria-pressed="<?php echo esc_attr($likeAriaPressed); ?>"
                                         tabindex="0">
                                        <div class="favBtn likeBtn" aria-hidden="true">
                                            <svg class="favIcon" aria-label="いいね" role="img" viewBox="0 0 20 20">
                                                <title>いいね</title>
                                                <path d="M1.35981 9.49502V16.652H4.38981L4.54281 16.67C7.35581 17.326 9.21981 17.799 10.1488 18.092C11.3828 18.481 11.8428 18.576 12.6798 18.632C13.3058 18.675 14.0168 18.434 14.3408 18.104C14.5198 17.922 14.6538 17.548 14.7068 16.968C14.7177 16.8464 14.7611 16.7299 14.8326 16.6308C14.904 16.5317 15.0008 16.4538 15.1128 16.405C15.3618 16.297 15.5688 16.121 15.7418 15.865C15.9018 15.631 16.0058 15.195 16.0248 14.564C16.028 14.448 16.0608 14.3347 16.1201 14.2349C16.1795 14.1351 16.2633 14.0522 16.3638 13.994C16.9458 13.657 17.2338 13.277 17.2938 12.831C17.3598 12.338 17.1998 11.783 16.7808 11.151C16.6825 11.0028 16.6459 10.8221 16.6788 10.6473C16.7116 10.4725 16.8114 10.3174 16.9568 10.215C17.3578 9.93302 17.5778 9.54102 17.6328 8.98502C17.7208 8.09902 17.1558 7.44402 15.8768 7.31302C14.7377 7.1999 13.5875 7.29581 12.4828 7.59602C12.3575 7.62877 12.2254 7.62504 12.1021 7.58529C11.9789 7.54553 11.8695 7.47139 11.787 7.37159C11.7044 7.27179 11.652 7.15049 11.6361 7.02195C11.6201 6.89341 11.6412 6.76299 11.6968 6.64602C12.1968 5.58802 12.4748 4.71502 12.5398 4.03902C12.6248 3.14202 12.4178 2.49202 11.9338 1.95602C11.5668 1.55002 10.9798 1.31802 10.7598 1.36602C10.4698 1.42802 10.2808 1.59602 10.0348 2.18402C9.88981 2.53202 9.81981 2.82802 9.69981 3.51902C9.58481 4.17502 9.52181 4.47102 9.39081 4.85902C8.99581 6.03502 8.02681 7.25402 6.72581 8.09502C5.81326 8.68239 4.82525 9.14326 3.78881 9.46502C3.72394 9.48465 3.65657 9.49475 3.58881 9.49502H1.35981ZM1.31781 18.015C0.994807 18.024 0.704807 17.952 0.461807 17.782C0.151807 17.565 0.00580697 17.223 0.00280697 16.829L0.00580697 9.50602C-0.028193 9.11602 0.086807 8.75802 0.358807 8.49202C0.613807 8.24202 0.946807 8.12402 1.29881 8.13202H3.48381C4.36769 7.85045 5.21035 7.45299 5.98981 6.95002C7.03781 6.27202 7.80981 5.30002 8.10481 4.42402C8.20581 4.12202 8.25981 3.87202 8.36181 3.28402C8.49981 2.49502 8.58581 2.12802 8.78381 1.65602C9.19381 0.674018 9.73181 0.194018 10.4738 0.0330184C11.2038 -0.124982 12.2668 0.296018 12.9388 1.04002C13.6838 1.86402 14.0128 2.89502 13.8908 4.16902C13.8381 4.71569 13.6868 5.33035 13.4368 6.01302C14.2906 5.8885 15.1564 5.8697 16.0148 5.95702C18.0218 6.16202 19.1488 7.46902 18.9848 9.12102C18.9128 9.83302 18.6548 10.438 18.2158 10.913C18.5848 11.624 18.7318 12.327 18.6398 13.013C18.5338 13.803 18.0938 14.461 17.3618 14.972C17.3048 15.665 17.1458 16.218 16.8638 16.632C16.6409 16.9659 16.3511 17.2499 16.0128 17.466C15.9048 18.15 15.6778 18.685 15.3068 19.061C14.6918 19.687 13.5928 20.06 12.5888 19.992C11.6358 19.928 11.0718 19.812 9.74181 19.392C8.86481 19.115 7.04881 18.655 4.31181 18.015H1.31781ZM3.01881 9.18402C3.01854 9.09455 3.03594 9.00591 3.06999 8.92318C3.10405 8.84045 3.1541 8.76525 3.21727 8.70189C3.28044 8.63854 3.35549 8.58827 3.43812 8.55397C3.52075 8.51967 3.60934 8.50202 3.69881 8.50202C3.78811 8.50228 3.87648 8.52013 3.95888 8.55454C4.04128 8.58896 4.1161 8.63927 4.17905 8.7026C4.24201 8.76593 4.29188 8.84104 4.32581 8.92364C4.35974 9.00624 4.37707 9.09472 4.37681 9.18402V16.862C4.37694 16.9513 4.35948 17.0398 4.32543 17.1223C4.29138 17.2049 4.2414 17.2799 4.17835 17.3431C4.1153 17.4064 4.04041 17.4566 3.95796 17.4909C3.8755 17.5252 3.78711 17.5429 3.69781 17.543C3.60851 17.5429 3.52011 17.5252 3.43766 17.4909C3.35521 17.4566 3.28032 17.4064 3.21727 17.3431C3.15422 17.2799 3.10424 17.2049 3.07019 17.1223C3.03613 17.0398 3.01868 16.9513 3.01881 16.862V9.18402Z"/>
                                            </svg>
                                        </div>
                                        <p class="memberblog-public-detail-page__likeLabel">いいね</p>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($updatedDateLabel !== '') : ?>
                        <div class="fontEn date"><p class="textColor--textGray date--p"><?php echo esc_html($updatedDateLabel); ?></p></div>
                    <?php endif; ?>

                    <div class="postPerson">
                        <div class="iconPostPerson">
                            <div class="iconPostPerson__thum">
                                <img class="thumImg" src="<?php echo esc_url($memberProfileImageUrl !== '' ? $memberProfileImageUrl : $defaultMemberProfileImageUrl); ?>" alt="<?php echo esc_attr($memberDisplayName); ?>" width="40" height="40" loading="lazy">
                            </div>
                        </div>
                        <div class="postPerson__detail">
                            <p class="fontW--r blockBox__infoSub--personTitle">会員ブログ投稿者</p>
                            <p class="fontW--r blockBox__infoSub--text"><?php echo esc_html($memberDisplayName); ?></p>
                        </div>
                    </div>
                </div>

                <div class="wordpress__block">
                    <?php
                    $blocks = isset($item['blocks']) && is_array($item['blocks']) ? $item['blocks'] : [];
                    if ($blocks !== []) {
                        tokk_memberblog_render_public_post_blocks($blocks);
                    } elseif (!empty($item['excerpt'])) {
                        echo '<p class="memberblog-public-detail-page__fallback">' . esc_html((string) $item['excerpt']) . '</p>';
                    } else {
                        echo '<p class="memberblog-public-detail-page__fallback">本文の表示データがありません。</p>';
                    }
                    ?>
                </div>

                <?php if ($showCommentBlock): ?>
                    <div class="commentBlock js--commentRoot"
                         data-resource-type="member_blog_post"
                         data-resource-id="<?php echo esc_attr((string) $postId); ?>"
                         data-comment-view-acl="<?php echo esc_attr((string) $commentViewAcl); ?>"
                         data-comment-post-acl="<?php echo esc_attr((string) $commentPostAcl); ?>"
                         data-comment-can-view="<?php echo $canShowCommentList ? '1' : '0'; ?>"
                         data-comment-can-post="<?php echo $canSubmitComment ? '1' : '0'; ?>"
                         data-comment-post-disabled-message="<?php echo esc_attr($commentPostingStateMessage !== '' ? $commentPostingStateMessage : 'コメント投稿は現在ご利用いただけません。'); ?>"
                         data-comment-current-member-display-name="<?php echo esc_attr($commentCurrentMemberDisplayName); ?>"
                         data-comment-current-member-profile-image-url="<?php echo esc_url($commentCurrentMemberProfileImageUrl); ?>"
                         data-comment-login-url="<?php echo esc_url($commentLoginUrl); ?>">
                        <h3 class="commentBlock__title">
                            <svg class="titleIcon" aria-label="コメント一覧" role="img" viewBox="0 0 22 22">
                                <path d="M1.8,19.5c-.6,0-1-.4-1-1v-9.2c0-.6.4-1,1-1s1,.4,1,1v9.2c0,.6-.4,1-1,1Z"/>
                                <path d="M14.3,19.5H1.8c-.6,0-1-.4-1-1s.4-1,1-1h12.5c.6,0,1,.4,1,1s-.4,1-1,1Z"/>
                                <path d="M20.2,13.7c-.6,0-1-.4-1-1v-3.3c0-.6.4-1,1-1s1,.4,1,1v3.3c0,.6-.4,1-1,1Z"/>
                                <path d="M14.3,4.5h-6.7c-.6,0-1-.4-1-1s.4-1,1-1h6.7c.6,0,1,.4,1,1s-.4,1-1,1Z"/>
                                <path d="M1.8,10.3c-.6,0-1-.4-1-1C.8,5.6,3.9,2.5,7.7,2.5s1,.4,1,1-.4,1-1,1c-2.7,0-4.8,2.2-4.8,4.8s-.4,1-1,1Z"/>
                                <path d="M20.2,10.3c-.6,0-1-.4-1-1,0-2.7-2.2-4.8-4.8-4.8s-1-.4-1-1,.4-1,1-1c3.8,0,6.8,3.1,6.8,6.8s-.4,1-1,1Z"/>
                                <path d="M14.3,19.5c-.6,0-1-.4-1-1s.4-1,1-1c2.7,0,4.8-2.2,4.8-4.8s.4-1,1-1,1,.4,1,1c0,3.8-3.1,6.8-6.8,6.8Z"/>
                                <path d="M15.2,12c-.3,0-.5-.1-.7-.3,0,0-.2-.2-.2-.3,0-.1,0-.2,0-.4,0-.3.1-.5.3-.7.3-.3.7-.4,1.1-.2.1,0,.2.1.3.2.2.2.3.4.3.7s-.1.5-.3.7c-.2.2-.5.3-.7.3Z"/>
                                <path d="M11,12c-.1,0-.3,0-.4,0-.1,0-.2-.1-.3-.2-.2-.2-.3-.5-.3-.7s0-.1,0-.2c0,0,0-.1,0-.2,0,0,0-.1,0-.2,0,0,0-.1.1-.2.1,0,.2-.2.3-.2.2-.1.5-.1.8,0,.1,0,.2.1.3.2,0,0,0,0,.1.2,0,0,0,.1,0,.2,0,0,0,.1,0,.2,0,0,0,.1,0,.2,0,.3-.1.5-.3.7-.1,0-.2.2-.3.2-.1,0-.2,0-.4,0Z"/>
                                <path d="M6.8,12c-.1,0-.3,0-.4,0-.1,0-.2-.1-.3-.2-.2-.2-.3-.5-.3-.7s0-.1,0-.2c0,0,0-.1,0-.2,0,0,0-.1,0-.2,0,0,0-.1.1-.2,0,0,.2-.2.3-.2.4-.2.8,0,1.1.2,0,0,0,0,.1.2,0,0,0,.1,0,.2,0,0,0,.1,0,.2,0,0,0,.1,0,.2,0,.3-.1.5-.3.7-.2.2-.5.3-.7.3Z"/>
                            </svg>
                            <p class="title__text js--commentCountText">コメント一覧(<span class="js--commentCount"><?php echo esc_html((string) $commentTotalCount); ?></span>件)</p>
                        </h3>

                        <?php if ($requiresCommentLoginForView): ?>
                            <div class="commentBlock__loginOnly js--commentLoginOnly">
                                <p class="commentBlock__loginOnlyMessage">この記事のコメントはログインすると閲覧できます。</p>
                                <div class="commentBlock__loginOnlyActions">
                                    <a class="commentBlock__loginOnlyButton commentBlock__loginOnlyButton--primary" href="<?php echo esc_url($commentLoginUrl); ?>">ログインはこちら</a>
                                    <a class="commentBlock__loginOnlyButton commentBlock__loginOnlyButton--secondary" href="<?php echo esc_url($commentRegisterUrl); ?>">新規会員登録はこちら</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="commentBlock__row">
                                <div class="commentCol">
                                    <?php if ($commentListNoticeMessage !== ''): ?>
                                        <p class="commentBlock__notice js--commentListNotice <?php echo esc_attr($commentListNoticeModifierClass); ?>"><?php echo esc_html($commentListNoticeMessage); ?></p>
                                    <?php endif; ?>
                                    <ul class="commentList js--commentList">
                                        <?php foreach ($commentItems as $commentItem): ?>
                                            <?php
                                            $commentId = max(0, (int) ($commentItem['comment_id'] ?? 0));
                                            if ($commentId <= 0) {
                                                continue;
                                            }
                                            $commentStatus = trim((string) ($commentItem['status'] ?? 'visible'));
                                            if ($commentStatus === 'deleted') {
                                                continue;
                                            }
                                            $commentBody = trim((string) ($commentItem['body'] ?? ''));
                                            $commentDisplayName = trim((string) ($commentItem['member_display_name'] ?? ''));
                                            if ($commentDisplayName === '') {
                                                $commentDisplayName = '会員';
                                            }
                                            $commentProfileImageUrl = trim((string) ($commentItem['member_profile_image_url'] ?? ''));
                                            $postedAtLabel = $formatCommentPostedAtLabel($commentItem['posted_at'] ?? '');
                                            $commentItemClassList = [];
                                            if ($commentStatus === 'pending_approval') {
                                                $commentItemClassList[] = 'is-pending';
                                            }
                                            if ($commentStatus === 'delete_requested') {
                                                $commentItemClassList[] = 'is-delete-requested';
                                            }
                                            $commentItemClass = implode(' ', $commentItemClassList);
                                            $isCommentMine = (bool) ($commentItem['is_mine'] ?? false);
                                            $reportReasonInputName = 'member_blog_comment_report_reason_' . $commentId;
                                            ?>
                                            <li class="<?php echo esc_attr($commentItemClass); ?>"
                                                data-comment-id="<?php echo esc_attr((string) $commentId); ?>"
                                                data-comment-status="<?php echo esc_attr($commentStatus); ?>"
                                                data-comment-is-mine="<?php echo $isCommentMine ? '1' : '0'; ?>">
                                                <div class="user">
                                                    <div class="icon">
                                                        <?php if ($commentProfileImageUrl !== ''): ?>
                                                            <img class="commentUserImage" src="<?php echo esc_url($commentProfileImageUrl); ?>" alt="<?php echo esc_attr($commentDisplayName); ?>">
                                                        <?php else: ?>
                                                            <svg class="userIcon" aria-label="<?php echo esc_attr($commentDisplayName); ?>" role="img" viewBox="0 0 20 20">
                                                                <path d="M12.4,10.3c1-.7,1.6-1.9,1.6-3.2,0-2.2-1.8-4-4-4s-4.1,1.8-4.1,4,.6,2.5,1.6,3.2c-2.3.6-4,2.6-4,5.1v1.2c0,.3.2.5.5.5h11.8c.3,0,.5-.2.5-.5v-1.2c0-2.4-1.7-4.5-4-5.1ZM6.9,7c0-1.7,1.4-3,3.1-3s3,1.4,3,3-1.3,3-2.9,3h-.2c-1.6,0-2.9-1.4-2.9-3ZM8.8,11.1h1.1s0,0,0,0,0,0,0,0h1.1c2.3,0,4.2,1.9,4.2,4.2v.7H4.6v-.7c0-2.3,1.9-4.2,4.2-4.2Z"/>
                                                            </svg>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="name"><?php echo esc_html($commentDisplayName); ?></div>
                                                </div>
                                                <div class="comment">
                                                    <div class="commentInner"><?php echo nl2br(esc_html($commentBody)); ?></div>
                                                    <?php if ($postedAtLabel !== ''): ?>
                                                        <p class="commentMeta"><?php echo esc_html($postedAtLabel); ?></p>
                                                    <?php endif; ?>
                                                    <?php if ($commentStatus === 'pending_approval'): ?>
                                                        <p class="commentMeta commentMeta--pending">承認待ちのコメントです。</p>
                                                    <?php endif; ?>
                                                    <?php if ($commentStatus === 'delete_requested'): ?>
                                                        <p class="commentMeta commentMeta--delete-requested">削除依頼を受け付けています。</p>
                                                    <?php endif; ?>
                                                    <?php if ($isCommentMine && $commentStatus !== 'delete_requested'): ?>
                                                        <button type="button" class="commentMenu js--commentDeleteRequestToggle" data-comment-id="<?php echo esc_attr((string) $commentId); ?>" aria-label="コメントの削除依頼メニューを開く">
                                                            <span class="dot"></span>
                                                            <span class="dot"></span>
                                                            <span class="dot"></span>
                                                        </button>
                                                        <div class="commentWidget widgetDelete js--commentDeleteRequestWidget" data-comment-id="<?php echo esc_attr((string) $commentId); ?>" hidden>
                                                            <form class="js--commentDeleteRequestForm" data-comment-id="<?php echo esc_attr((string) $commentId); ?>">
                                                                <p class="commentDeleteNote">このコメントの削除を依頼しますか？</p>
                                                                <p class="commentDeleteError js--commentDeleteError" aria-live="polite"></p>
                                                                <div class="button">
                                                                    <button type="submit"><p class="submit__text">削除依頼する</p></button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <button type="button" class="commentMenu js--commentReportToggle" data-comment-id="<?php echo esc_attr((string) $commentId); ?>" aria-label="コメントを通報する">
                                                            <span class="dot"></span>
                                                            <span class="dot"></span>
                                                            <span class="dot"></span>
                                                        </button>
                                                        <div class="commentWidget widgetReport js--commentReportWidget" data-comment-id="<?php echo esc_attr((string) $commentId); ?>" hidden>
                                                            <form class="js--commentReportForm" data-comment-id="<?php echo esc_attr((string) $commentId); ?>">
                                                                <?php foreach (lutwiyo_get_comment_report_reasons() as $reasonOption) : ?>
                                                                    <div class="checkBox"><label><input type="radio" name="<?php echo esc_attr($reportReasonInputName); ?>" value="<?php echo esc_attr((string) $reasonOption['code']); ?>"><?php echo esc_html((string) $reasonOption['label']); ?></label></div>
                                                                <?php endforeach; ?>
                                                                <div class="commentReportNote js--commentReportNoteWrap" hidden>
                                                                    <textarea class="js--commentReportNote" rows="3" maxlength="1000" placeholder="詳細（任意）"></textarea>
                                                                </div>
                                                                <p class="commentReportError js--commentReportError" aria-live="polite"></p>
                                                                <div class="button">
                                                                    <button type="submit"><p class="submit__text">通報する</p></button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="commentSpOpen">
                                        <button type="button" class="button js--commentFormToggle">コメントする</button>
                                    </div>
                                </div>
                                <div class="commentCol">
                                    <div class="commentInput js--commentInput">
                                        <form class="js--commentForm" novalidate>
                                            <textarea id="comment-textarea"
                                                      class="js--commentTextarea"
                                                      rows="12"
                                                      maxlength="5000"
                                                      placeholder="この記事を読んで感じたことや印象に残った点、参考になった部分などをコメントしてください。"
                                                      <?php echo $commentFormDisabled ? 'disabled' : ''; ?>></textarea>
                                            <div class="commentCtrl">
                                                <a href="" class="delete" data-comment-clear></a>
                                                <button type="submit"
                                                        class="js--commentSubmit"
                                                        <?php echo $commentFormDisabled ? 'disabled' : ''; ?>>コメントする</button>
                                            </div>
                                        </form>
                                        <?php if ($commentPostingStateMessage !== ''): ?>
                                            <p class="commentInput__state js--commentFormState"><?php echo esc_html($commentPostingStateMessage); ?></p>
                                        <?php endif; ?>
                                        <?php if (!$isCommentLoggedIn): ?>
                                            <p class="commentInput__loginLink"><a href="<?php echo esc_url($commentLoginUrl); ?>">ログインはこちら</a></p>
                                        <?php endif; ?>
                                        <?php if ($isCommentFreeViewer && !$canSubmitComment && $isCommentPostingEnabled): ?>
                                            <p class="commentInput__loginLink"><a href="<?php echo esc_url($commentUpgradeUrl); ?>">有料会員登録はこちら</a></p>
                                        <?php endif; ?>
                                        <div class="commentCaution">
                                            読んだ人が情景を思い浮かべられるような感想は、ほかの利用者があなたのコメントを楽しみに待つきっかけになります。<br>
                                            ※一度投稿したコメントを編集することはできません。<br>
                                            ※ほかの利用者が安心して読めるよう、誹謗中傷や不適切な表現はお控えください。<br>
                                            ※内容によっては、編集部の判断により予告なく非表示または削除する場合があります。<br>
                                            ※投稿されたコメントは、記事内や関連ページで紹介されることがあります。<br>
                                        </div>
                                        <p class="commentInput__feedback js--commentFeedback" aria-live="polite"></p>
                                    </div>
                                </div>
                            </div>
                            <div class="commentSpScreen js--commentOverlay"></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <nav class="memberblog-public-detail-page__links" aria-label="公開導線">
                    <?php if ($memberListUrl !== '') : ?>
                        <a href="<?php echo esc_url($memberListUrl); ?>"><?php echo esc_html($memberListLinkLabel); ?></a>
                    <?php endif; ?>
                    <?php if ($primaryAreaLink !== '') : ?>
                        <a href="<?php echo esc_url($primaryAreaLink); ?>"><?php echo esc_html($areaListLinkLabel); ?></a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($publicListUrl); ?>"><?php echo esc_html($publicListLinkLabel); ?></a>
                </nav>
            </div>
        </section>

        <?php if ($showSidebar): ?>
            <section class="pdSideBar" data-boxBgColor="bodySub">
                <div class="stickyTarget pdSideBar__inner">
                    <?php if (!empty($sidebarRankingArticles)): ?>
                        <div class="pdSideBar__ranking">
                            <p class="fs--17 blockTitle"><?php echo esc_html($primaryAreaName !== '' ? $primaryAreaName . 'のランキング' : 'ランキング'); ?>
                                <span class="fontEn rankingDate"><?php echo esc_html($rankingDateLabel); ?></span>
                            </p>
                            <?php $rankingCounter = 0; ?>
                            <?php foreach ($sidebarRankingArticles as $rankingArticle): ?>
                                <?php
                                if (!is_array($rankingArticle)) {
                                    continue;
                                }
                                $rankingCounter++;
                                $rankingTitle = trim((string) ($rankingArticle['title'] ?? ''));
                                $rankingLink = trim((string) ($rankingArticle['permalink'] ?? ''));
                                $rankingImageUrl = trim((string) ($rankingArticle['image_url'] ?? ''));
                                $rankingDate = trim((string) ($rankingArticle['display_date_label'] ?? ''));
                                $rankingTags = isset($rankingArticle['tag_info']) && is_array($rankingArticle['tag_info']) ? $rankingArticle['tag_info'] : [];
                                ?>
                                <div class="blockBox blockBox--flex blockBox--ranking">
                                    <?php if ($rankingLink !== ''): ?>
                                        <a class="blockBox__link" href="<?php echo esc_url($rankingLink); ?>" title="<?php echo esc_attr($rankingTitle); ?>"></a>
                                    <?php endif; ?>
                                    <div class="blockBox__thum">
                                        <div class="thumImg__wrapper"><img class="thumImg" src="<?php echo esc_url($rankingImageUrl !== '' ? $rankingImageUrl : get_stylesheet_directory_uri() . '/assets/img/sample/thum--28.webp'); ?>" alt="<?php echo esc_attr($rankingTitle); ?>" loading="lazy" width="1900" height="1270"></div>
                                        <div class="rankingIcon cornerCover__wrapper" data-boxBgColor="bodySub">
                                            <div class="fontEn rankingIcon__num"><?php echo esc_html((string) $rankingCounter); ?></div>
                                            <div class="cornerCover cornerCover--lb cornerCover--outside"><div class="mask cornerCover__inner"></div></div>
                                            <div class="cornerCover cornerCover--rt cornerCover--outside"><div class="mask cornerCover__inner"></div></div>
                                        </div>
                                    </div>
                                    <div class="blockBox__info">
                                        <p class="textHover__target blockTitle"><?php echo esc_html($rankingTitle); ?></p>
                                        <?php if ($rankingDate !== ''): ?>
                                            <div class="fontEn date"><p class="textColor--textGray date--p"><?php echo esc_html($rankingDate); ?></p></div>
                                        <?php endif; ?>
                                        <?php if (!empty($rankingTags)): ?>
                                            <ul class="hashList">
                                                <?php foreach ($rankingTags as $rankingTag): ?>
                                                    <?php
                                                    $rankingTagName = trim((string) ($rankingTag['name'] ?? ''));
                                                    if ($rankingTagName === '') {
                                                        continue;
                                                    }
                                                    $rankingTagLink = trim((string) ($rankingTag['link'] ?? ''));
                                                    ?>
                                                    <li class="hashTarget">
                                                        <a class="textHoverWrapper hashLink" href="<?php echo esc_url($rankingTagLink); ?>" title="<?php echo esc_attr($rankingTagName); ?>">
                                                            <p class="textHover__target hashTarget--p"><?php echo esc_html($rankingTagName); ?></p>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($sidebarCategories)): ?>
                        <?php $categoryUrlPrefix = $primaryAreaSlug !== '' ? '/area/' . $primaryAreaSlug . '/' : '/category/'; ?>
                        <div class="pdSideBar__connCate">
                            <ul class="tagsList">
                                <?php foreach ($sidebarCategories as $sidebarCategory): ?>
                                    <?php
                                    if (!is_array($sidebarCategory)) {
                                        continue;
                                    }
                                    $categorySlug = trim((string) ($sidebarCategory['slug'] ?? ''));
                                    $categoryName = trim((string) ($sidebarCategory['name'] ?? ''));
                                    if ($categorySlug === '' || $categoryName === '') {
                                        continue;
                                    }
                                    ?>
                                    <li class="tagsTarget">
                                        <a class="flex--cc tagsLink" href="<?php echo esc_url(home_url($categoryUrlPrefix . $categorySlug . '/')); ?>" title="<?php echo esc_attr($categoryName); ?>">
                                            <p class="tagsTarget--p"><?php echo esc_html($categoryName); ?></p>
                                            <div class="btnArrow btnArrow--next" data-arrow="w-8"></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($showSidebarAds): ?>
                        <div class="pdSideBar__ad stickyTarget">
                            <?php foreach ($sidebarAdList as $ad): ?>
                                <?php
                                if (!is_array($ad)) {
                                    continue;
                                }
                                $adType = (string) ($ad['ad_type'] ?? 'image');
                                $adImageUrl = trim((string) ($ad['image_url'] ?? ''));
                                $adText = trim((string) ($ad['text'] ?? ''));
                                $adLink = trim((string) ($ad['url'] ?? ''));
                                $adCode = (string) ($ad['code'] ?? '');
                                $adIsBlank = !empty($ad['is_blank']) ? ' target="_blank" rel="noopener noreferrer"' : '';
                                if ($adType === 'tag') {
                                    echo $adCode;
                                    continue;
                                }
                                if ($adImageUrl === '' || $adLink === '') {
                                    continue;
                                }
                                ?>
                                <a href="<?php echo esc_url($adLink); ?>"<?php echo $adIsBlank; ?> title="<?php echo esc_attr($adText !== '' ? $adText : '広告'); ?>">
                                    <img src="<?php echo esc_url($adImageUrl); ?>" alt="<?php echo esc_attr($adText !== '' ? $adText : '広告'); ?>" width="630" height="526" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>

    <?php if (is_array($memberRelatedContext) && !empty($memberRelatedContext['items'])) : ?>
        <?php tokk_memberblog_render_public_list($memberRelatedContext, [
            'show_filter_form' => false,
            'heading' => $memberRelatedHeading,
            'exclude_post_id' => $postId,
            'max_items' => 4,
            'view_all_url' => $memberListUrl,
        ]); ?>
    <?php endif; ?>

    <?php if (is_array($areaRelatedContext) && !empty($areaRelatedContext['items'])) : ?>
        <?php tokk_memberblog_render_public_list($areaRelatedContext, [
            'show_filter_form' => false,
            'heading' => ($primaryAreaName !== '' ? $primaryAreaName : '同エリア') . 'の会員ブログ',
            'exclude_post_id' => $postId,
            'max_items' => 4,
            'view_all_url' => $primaryAreaLink,
        ]); ?>
    <?php endif; ?>
<?php endif; ?>
</main>

<?php get_footer(); ?>
