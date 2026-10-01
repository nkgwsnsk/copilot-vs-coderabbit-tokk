<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 記事ごとの会員公開プラン設定をACFでコード管理する。
 *
 * - public: 通常公開
 * - paid_member: 有料会員限定
 */
add_action('acf/init', function (): void {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    acf_add_local_field_group([
        'key' => 'group_tokk_article_member_access_policy',
        'title' => '会員公開設定',
        'fields' => [
            [
                'key' => 'field_tokk_article_member_access_plan',
                'label' => '会員公開プラン',
                'name' => 'member_access_plan',
                'type' => 'select',
                'instructions' => 'public: 通常公開 / paid_member: 有料会員限定',
                'required' => 1,
                'choices' => [
                    'public' => '通常公開（public）',
                    'paid_member' => '有料会員限定（paid_member）',
                ],
                'default_value' => 'public',
                'allow_null' => 0,
                'multiple' => 0,
                'ui' => 1,
                'ajax' => 0,
                'return_format' => 'value',
                'placeholder' => '',
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
        'hide_on_screen' => '',
        'active' => true,
    ]);
});
