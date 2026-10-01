<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('lutwiyo_get_comment_view_acl_default')) {
    /**
     * TOKK記事コメント閲覧ACLの既定値。
     */
    function lutwiyo_get_comment_view_acl_default(): string
    {
        return 'public';
    }
}

if (!function_exists('lutwiyo_get_comment_post_acl_default')) {
    /**
     * TOKK記事コメント投稿ACLの既定値。
     */
    function lutwiyo_get_comment_post_acl_default(): string
    {
        return 'enabled';
    }
}

/**
 * TOKK記事（post_type=articles）向けコメントポリシーACF定義。
 *
 * 今回のコード管理対象は以下2項目のみ:
 * - comment_view_acl
 * - comment_post_acl
 */
add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key' => 'group_comment_policy_articles_v1',
        'title' => 'コメントポリシー設定',
        'fields' => [
            [
                'key' => 'field_comment_view_acl_articles_v1',
                'label' => 'コメント閲覧範囲',
                'name' => 'comment_view_acl',
                'type' => 'radio',
                'layout' => 'horizontal',
                'choices' => [
                    'public' => '誰でも閲覧可能',
                    'members' => '会員のみ閲覧可能',
                    'private' => '非公開',
                ],
                'default_value' => lutwiyo_get_comment_view_acl_default(),
                'return_format' => 'value',
                'required' => 1,
            ],
            [
                'key' => 'field_comment_post_acl_articles_v1',
                'label' => 'コメント投稿可否',
                'name' => 'comment_post_acl',
                'type' => 'radio',
                'layout' => 'horizontal',
                'choices' => [
                    'enabled' => '投稿可能',
                    'disabled' => '投稿不可',
                ],
                'default_value' => lutwiyo_get_comment_post_acl_default(),
                'return_format' => 'value',
                'required' => 1,
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'articles',
                ],
            ],
        ],
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'active' => true,
    ]);
});
