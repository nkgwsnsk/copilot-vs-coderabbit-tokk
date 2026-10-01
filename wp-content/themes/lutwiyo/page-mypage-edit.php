<?php
// --------------------------------------
// 会員マイページ表示前ガード
// --------------------------------------
// 未ログインはログイン導線へリダイレクトする。
$currentRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$currentUrlForReturn = home_url($currentRequestUri);

// --- ログイン状態確認API呼び出し開始 ---
// マイページ編集画面の表示可否を判定するため、現在のログイン状態を取得する。
$loginStatusResponse = function_exists('lutwiyo_get_or_fetch_login_status_response')
    ? lutwiyo_get_or_fetch_login_status_response()
    : lutwiyo_call_bridge_api('login_status');
// --- ログイン状態確認API呼び出し終了 ---
$isLoggedIn = is_array($loginStatusResponse)
    && ($loginStatusResponse['body']['result'] ?? '') === 'logged_in';
$isLoginStartRequested = isset($_GET['tokk_member_login'])
    && (string) $_GET['tokk_member_login'] === '1';

if (!$isLoggedIn) {
    if (!$isLoginStartRequested) {
        lutwiyo_render_inline_login_guide_and_exit($currentUrlForReturn);
    }
}

$editPageFlashMessage = '';
$editPageErrors = [];
$editPageDebugErrors = [];
$editPageFormValues = [
    'household_income' => '',
    'nickname' => '',
    'member_slug' => '',
    'favorite_area' => '',
    'interests' => [],
    'is_comment_name_visible' => '1',
    'is_mailmagazine_opt_in' => '',
    'profile_image_type' => 'preset',
    'profile_image_id' => '',
    'profile_image_url' => '',
    'use_uploaded_profile_image' => '0',
];

$mypagePlanChangeUrl = home_url('/paid-exp/');
$mypagePlanLabel = 'フリー';
$mypageBaseUrl = remove_query_arg(['updated', 'tokk_member_login', 'mypage_nav_action', '_wpnonce'], $currentUrlForReturn);
$paymentHistoryUrl = wp_nonce_url(add_query_arg('mypage_nav_action', 'payment_history', $mypageBaseUrl), 'tokk_mypage_payment_action');
$paymentMethodUrl = wp_nonce_url(add_query_arg('mypage_nav_action', 'payment_method', $mypageBaseUrl), 'tokk_mypage_payment_action');

$householdIncomeOptions = [
    'under_100' => '〜100万円',
    '101_200' => '101〜200万円',
    '201_300' => '201〜300万円',
    '301_400' => '301〜400万円',
    '401_500' => '401〜500万円',
    '501_600' => '501〜600万円',
    '601_700' => '601〜700万円',
    '701_800' => '701〜800万円',
    '801_900' => '801〜900万円',
    '901_1000' => '901〜1000万円',
    'over_1000' => '1000万円〜',
    'unanswered' => '未回答',
];

$externalMemberDisplay = [
    'name' => '-',
    'name_kana' => '-',
    'gender' => '-',
    'birthday' => '-',
];

$tokkMemberDisplay = [
    'membership_expired_at' => '-',
    'next_renewal_at' => '-',
    'service_cancel_scheduled_at' => '-',
    'last_login_at' => '-',
];

$favoriteAreaOptions = [];
$interestOptions = [
    'department_store' => '百貨店',
    'quality' => '上質・こだわり',
    'parenting_family' => '育児・ファミリー向け',
    'asset_building_investment' => '資産形成・投資',
    'trend_news' => 'トレンド・ニュース',
    'gourmet' => 'グルメ',
    'events' => 'イベント',
    'shopping' => 'ショッピング',
    'entertainment' => 'エンタメ',
    'art' => '芸術・アート',
    'outing_travel_leisure' => 'おでかけ（旅行・レジャー・観光）',
    'takarazuka_revue' => '宝塚歌劇',
    'fortune_telling' => '占い',
    'beauty_health' => '美容・健康',
    'sports' => 'スポーツ',
    'pets' => 'ペット',
];
$profileImagePresets = function_exists('lutwiyo_get_member_profile_image_presets')
    ? lutwiyo_get_member_profile_image_presets()
    : [];
if ($editPageFormValues['profile_image_id'] === '' && $profileImagePresets !== []) {
    $firstPreset = reset($profileImagePresets);
    if (is_array($firstPreset)) {
        $editPageFormValues['profile_image_id'] = (string) ($firstPreset['id'] ?? '');
        $editPageFormValues['profile_image_url'] = (string) ($firstPreset['url'] ?? '');
    }
}

$memberUid = '';
$memberProfile = [];
$hasMemberProfile = false;
$showProfileRequiredNotice = false;
$memberSlugIsEditable = true;

// --- 会員情報取得API呼び出し開始 ---
// 表示専用の会員基本情報と、後続APIで利用するuidを取得する。
$memberInfoResponse = function_exists('lutwiyo_get_or_fetch_member_info_response')
    ? lutwiyo_get_or_fetch_member_info_response()
    : lutwiyo_call_bridge_api('member_info', [
        'content' => 'sub,data',
    ]);
// --- 会員情報取得API呼び出し終了 ---

if (is_array($memberInfoResponse) && ($memberInfoResponse['body']['result'] ?? '') === 'ok') {
    $memberUid = trim((string) ($memberInfoResponse['body']['uid'] ?? ''));

    $localMember = $memberInfoResponse['body']['local_member'] ?? [];
    $isStandardMember = is_array($localMember) && (int) ($localMember['member_rank_id'] ?? 0) === 2;
    if ($isStandardMember) {
        $mypagePlanLabel = 'スタンダード';
        $mypagePlanChangeUrl = home_url('/plan-change-input/');
    }

    $externalMember = $memberInfoResponse['body']['external_member'] ?? [];
    $externalMemberData = is_array($externalMember['data'] ?? null) ? $externalMember['data'] : [];

    $lastName = trim((string) ($externalMemberData['last_name'] ?? $externalMemberData['lastname'] ?? ''));
    $firstName = trim((string) ($externalMemberData['first_name'] ?? $externalMemberData['firstname'] ?? ''));
    $lastNameKana = trim((string) ($externalMemberData['last_name_kana'] ?? $externalMemberData['lastname_kana'] ?? ''));
    $firstNameKana = trim((string) ($externalMemberData['first_name_kana'] ?? $externalMemberData['firstname_kana'] ?? ''));

    if ($lastName !== '' || $firstName !== '') {
        $externalMemberDisplay['name'] = trim($lastName . ' ' . $firstName);
    }
    if ($lastNameKana !== '' || $firstNameKana !== '') {
        $externalMemberDisplay['name_kana'] = trim($lastNameKana . ' ' . $firstNameKana);
    }

    $genderRaw = trim((string) ($externalMemberData['gender'] ?? ''));
    $genderMap = [
        '1' => '男性',
        '2' => '女性',
        '3' => 'その他',
        '4' => '未回答',
    ];
    $externalMemberDisplay['gender'] = $genderMap[$genderRaw] ?? '-';

    $birthdayRaw = trim((string) ($externalMemberData['birthdate'] ?? $externalMemberData['birthday'] ?? ''));
    if ($birthdayRaw !== '') {
        $timestamp = strtotime($birthdayRaw);
        $externalMemberDisplay['birthday'] = $timestamp !== false
            ? wp_date('Y.m.d', $timestamp)
            : str_replace('-', '.', $birthdayRaw);
    }

    $formatDateTime = static function (string $dateTimeRaw): string {
        if ($dateTimeRaw === '') {
            return '-';
        }

        $timestamp = strtotime($dateTimeRaw);
        if ($timestamp === false) {
            return $dateTimeRaw;
        }

        return wp_date('Y年m月d日 H時i分s秒', $timestamp);
    };

    $formatDateOnly = static function (string $dateTimeRaw): string {
        if ($dateTimeRaw === '') {
            return '-';
        }

        $timestamp = strtotime($dateTimeRaw);
        if ($timestamp === false) {
            return $dateTimeRaw;
        }

        return wp_date('Y年m月d日', $timestamp);
    };

    if ($isStandardMember) {
        $tokkMemberDisplay['membership_expired_at'] = $formatDateOnly(trim((string) ($localMember['membership_expired_at'] ?? '')));
        $tokkMemberDisplay['next_renewal_at'] = $formatDateOnly(trim((string) ($localMember['next_renewal_at'] ?? '')));
        $tokkMemberDisplay['service_cancel_scheduled_at'] = $formatDateOnly(trim((string) ($localMember['service_cancel_scheduled_at'] ?? '')));
    }

    $tokkMemberDisplay['last_login_at'] = $formatDateTime(trim((string) ($localMember['last_login_at'] ?? '')));
}

$favoriteAreaTerms = get_terms([
    'taxonomy' => 'area',
    'hide_empty' => false,
]);

if (!is_wp_error($favoriteAreaTerms) && is_array($favoriteAreaTerms)) {
    foreach ($favoriteAreaTerms as $areaTerm) {
        if ($areaTerm instanceof WP_Term) {
            $favoriteAreaOptions[$areaTerm->slug] = $areaTerm->name;
        }
    }
}

if ($memberUid !== '') {
    // --- 会員プロフィール取得API呼び出し開始 ---
    // TOKK独自の会員プロフィールを取得し、編集フォーム初期値に反映する。
    $memberProfileResponse = function_exists('lutwiyo_get_or_fetch_current_member_profile_response')
        ? lutwiyo_get_or_fetch_current_member_profile_response()
        : lutwiyo_call_bridge_api('member_profile', [
            'uid' => $memberUid,
            'operation' => 'get',
        ]);
    // --- 会員プロフィール取得API呼び出し終了 ---

    $memberProfile = function_exists('lutwiyo_get_current_member_profile_member')
        ? lutwiyo_get_current_member_profile_member()
        : (is_array($memberProfileResponse)
            && ($memberProfileResponse['body']['result'] ?? '') === 'ok'
            && is_array($memberProfileResponse['body']['member'] ?? null)
            ? $memberProfileResponse['body']['member']
            : []);
    $hasMemberProfile = $memberProfile !== [];

    if ($hasMemberProfile) {
        $editPageFormValues['household_income'] = (string) ($memberProfile['household_income'] ?? '');
        $editPageFormValues['nickname'] = (string) ($memberProfile['nickname'] ?? '');
        $editPageFormValues['member_slug'] = (string) ($memberProfile['member_slug'] ?? '');
        $memberSlugIsEditable = array_key_exists('member_slug_is_editable', $memberProfile)
            ? (bool) $memberProfile['member_slug_is_editable']
            : true;
        $editPageFormValues['favorite_area'] = (string) ($memberProfile['favorite_area'] ?? '');
        $savedInterests = json_decode((string) ($memberProfile['interests'] ?? ''), true);
        if (is_array($savedInterests)) {
            $normalizedInterests = [];
            foreach ($savedInterests as $savedInterest) {
                $interestSlug = sanitize_key((string) $savedInterest);
                if ($interestSlug === '') {
                    continue;
                }

                if (isset($interestOptions[$interestSlug])) {
                    $normalizedInterests[] = $interestSlug;
                }
            }
            $editPageFormValues['interests'] = array_values(array_unique($normalizedInterests));
        }
        $editPageFormValues['profile_image_type'] = trim((string) ($memberProfile['profile_image_type'] ?? 'preset'));
        $editPageFormValues['profile_image_id'] = trim((string) ($memberProfile['profile_image_id'] ?? $editPageFormValues['profile_image_id']));
        $editPageFormValues['profile_image_url'] = trim((string) ($memberProfile['profile_image_url'] ?? $editPageFormValues['profile_image_url']));
        $editPageFormValues['use_uploaded_profile_image'] = $editPageFormValues['profile_image_type'] === 'uploaded' ? '1' : '0';

        if ($editPageFormValues['favorite_area'] !== '' && !isset($favoriteAreaOptions[$editPageFormValues['favorite_area']])) {
            $favoriteAreaOptions[$editPageFormValues['favorite_area']] = sprintf('現在の設定値: %s', $editPageFormValues['favorite_area']);
        }

        if ($editPageFormValues['profile_image_type'] === 'preset' && $editPageFormValues['profile_image_id'] !== '' && isset($profileImagePresets[$editPageFormValues['profile_image_id']])) {
            $editPageFormValues['profile_image_url'] = (string) ($profileImagePresets[$editPageFormValues['profile_image_id']]['url'] ?? $editPageFormValues['profile_image_url']);
        }

        if (isset($memberProfile['is_comment_name_visible'])) {
            $editPageFormValues['is_comment_name_visible'] = (string) ((int) $memberProfile['is_comment_name_visible']);
        }
        if (array_key_exists('is_mailmagazine_opt_in', $memberProfile) && $memberProfile['is_mailmagazine_opt_in'] !== null) {
            $mailmagazineOptIn = (string) ((int) $memberProfile['is_mailmagazine_opt_in']);
            if (in_array($mailmagazineOptIn, ['0', '1'], true)) {
                $editPageFormValues['is_mailmagazine_opt_in'] = $mailmagazineOptIn;
            }
        }
    }
}

$postedAction = isset($_POST['mypage_edit_action']) ? sanitize_text_field(wp_unslash($_POST['mypage_edit_action'])) : '';
$paymentNavigationAction = isset($_GET['mypage_nav_action']) ? sanitize_text_field(wp_unslash($_GET['mypage_nav_action'])) : '';
$isPostAction = $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($postedAction, [
    'update_profile',
    'open_crossid_edit',
    'payment_history',
    'payment_method',
], true);
$isGetPaymentNavigationAction = $_SERVER['REQUEST_METHOD'] === 'GET' && in_array($paymentNavigationAction, [
    'payment_history',
    'payment_method',
], true);

if ($isPostAction || $isGetPaymentNavigationAction) {
    // --- 再ログイン状態確認API呼び出し開始 ---
    // フォーム送信時のセッション失効を検知するため、再度ログイン状態を確認する。
    $postLoginStatusResponse = lutwiyo_call_bridge_api('login_status');
    // --- 再ログイン状態確認API呼び出し終了 ---
    $isPostLoggedIn = is_array($postLoginStatusResponse)
        && ($postLoginStatusResponse['body']['result'] ?? '') === 'logged_in';
    if (!$isPostLoggedIn) {
        lutwiyo_render_inline_login_guide_and_exit($currentUrlForReturn);
    }

    if ($isGetPaymentNavigationAction) {
        $paymentNonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($paymentNonce, 'tokk_mypage_payment_action')) {
            $editPageErrors[] = 'セッションの有効期限が切れました。再度お試しください。';
            $editPageDebugErrors[] = 'セッションの有効期限が切れました。再度お試しください。';
        }
    } else {
        if (!isset($_POST['mypage_edit_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mypage_edit_nonce'])), 'tokk_mypage_edit_action')) {
            $editPageErrors[] = 'セッションの有効期限が切れました。再度お試しください。';
            $editPageDebugErrors[] = 'セッションの有効期限が切れました。再度お試しください。';
        }
    }

    if (empty($editPageErrors)) {
        $requestedAction = $isGetPaymentNavigationAction ? $paymentNavigationAction : $postedAction;
        if ($requestedAction === 'open_crossid_edit') {
            // --- 会員情報編集導線API呼び出し開始 ---
            // 外部認証基盤の会員情報編集画面へのリダイレクトURLを取得する。
            $mypageBridgeResponse = lutwiyo_call_bridge_api('mypage', [
                'current_url' => $mypageBaseUrl,
                'function' => 'edit',
            ]);
            // --- 会員情報編集導線API呼び出し終了 ---

            if (is_array($mypageBridgeResponse) && ($mypageBridgeResponse['body']['result'] ?? '') === 'redirect') {
                $redirectTo = trim((string) ($mypageBridgeResponse['body']['redirect_to'] ?? ''));
                if ($redirectTo !== '') {
                    wp_redirect($redirectTo);
                    exit;
                }
            }

            $editPageErrors[] = '外部会員情報編集ページへの遷移に失敗しました。';
            $editPageDebugErrors[] = [
                'message' => '外部会員情報編集ページへの遷移に失敗しました。',
                'bridge_response' => $mypageBridgeResponse,
            ];
        }

        if ($requestedAction === 'update_profile') {
            $income = isset($_POST['household_income']) ? sanitize_text_field(wp_unslash($_POST['household_income'])) : '';
            $nickname = isset($_POST['nickname']) ? sanitize_text_field(wp_unslash($_POST['nickname'])) : '';
            $memberSlug = isset($_POST['member_slug']) ? strtolower(sanitize_text_field(wp_unslash($_POST['member_slug']))) : '';
            $favoriteArea = isset($_POST['favorite_area']) ? sanitize_text_field(wp_unslash($_POST['favorite_area'])) : '';
            $interestsInput = isset($_POST['interests']) && is_array($_POST['interests']) ? wp_unslash($_POST['interests']) : [];
            $commentVisible = isset($_POST['is_comment_name_visible']) ? sanitize_text_field(wp_unslash($_POST['is_comment_name_visible'])) : '';
            $mailmagazineOptIn = isset($_POST['is_mailmagazine_opt_in']) ? sanitize_text_field(wp_unslash($_POST['is_mailmagazine_opt_in'])) : '';
            $profileImageId = isset($_POST['profile_image_id']) ? sanitize_text_field(wp_unslash($_POST['profile_image_id'])) : '';
            $useUploadedProfileImage = isset($_POST['use_uploaded_profile_image']) ? '1' : '0';

            $editPageFormValues['household_income'] = $income;
            $editPageFormValues['nickname'] = $nickname;
            $editPageFormValues['member_slug'] = $memberSlug;
            $editPageFormValues['favorite_area'] = $favoriteArea;
            $selectedInterests = [];
            foreach ($interestsInput as $interestValue) {
                $interestSlug = sanitize_key((string) $interestValue);
                if ($interestSlug !== '') {
                    $selectedInterests[] = $interestSlug;
                }
            }
            $editPageFormValues['interests'] = array_values(array_unique($selectedInterests));
            $editPageFormValues['is_comment_name_visible'] = $commentVisible;
            $editPageFormValues['is_mailmagazine_opt_in'] = $mailmagazineOptIn;
            $editPageFormValues['profile_image_id'] = $profileImageId;
            $editPageFormValues['use_uploaded_profile_image'] = $useUploadedProfileImage;
            $editPageFormValues['profile_image_type'] = $useUploadedProfileImage === '1' ? 'uploaded' : 'preset';
            if ($useUploadedProfileImage !== '1' && $profileImageId !== '' && isset($profileImagePresets[$profileImageId])) {
                $editPageFormValues['profile_image_url'] = (string) ($profileImagePresets[$profileImageId]['url'] ?? '');
            }

            if ($memberUid === '') {
                $editPageErrors[] = '会員情報の識別に失敗しました。再度ログインしてください。';
                $editPageDebugErrors[] = '会員情報の識別に失敗しました。再度ログインしてください。';
            }
            if (!lutwiyo_has_non_whitespace_text($nickname)) {
                $editPageErrors[] = 'ニックネームは必須です。';
                $editPageDebugErrors[] = [
                    'message' => 'ニックネームが未入力です。',
                ];
            }
            if ($income !== '' && !isset($householdIncomeOptions[$income])) {
                $editPageErrors[] = '年収の入力値が不正です。';
                $editPageDebugErrors[] = [
                    'message' => '年収の入力値が不正です。',
                    'value' => $income,
                ];
            }
            if (!in_array($commentVisible, ['0', '1'], true)) {
                $editPageErrors[] = 'ニックネーム表示の入力値が不正です。';
                $editPageDebugErrors[] = [
                    'message' => 'ニックネーム表示の入力値が不正です。',
                    'value' => $commentVisible,
                ];
            }
            if (!in_array($mailmagazineOptIn, ['0', '1'], true)) {
                $editPageErrors[] = 'メルマガ同意の入力値が不正です。';
                $editPageDebugErrors[] = [
                    'message' => 'メルマガ同意の入力値が不正です。',
                    'value' => $mailmagazineOptIn,
                ];
            }
            if ($memberSlug === '' || !preg_match('/^[a-z0-9_-]{3,32}$/', $memberSlug)) {
                $editPageErrors[] = '公開会員IDは英小文字・数字・ハイフン・アンダースコアのみ、3〜32文字で入力してください。';
                $editPageDebugErrors[] = [
                    'message' => '公開会員IDの入力値が不正です。',
                    'value' => $memberSlug,
                ];
            }
            if (!$memberSlugIsEditable && $memberSlug !== (string) ($memberProfile['member_slug'] ?? '')) {
                $editPageErrors[] = '公開会員IDは既に確定しているため変更できません。';
                $editPageDebugErrors[] = [
                    'message' => '公開会員IDは既に確定しているため変更できません。',
                    'value' => $memberSlug,
                ];
            }
            if ($favoriteArea !== '' && !isset($favoriteAreaOptions[$favoriteArea])) {
                $editPageErrors[] = 'お気に入りエリアの入力値が不正です。';
                $editPageDebugErrors[] = [
                    'message' => 'お気に入りエリアの入力値が不正です。',
                    'value' => $favoriteArea,
                ];
            }
            if ($editPageFormValues['interests'] === []) {
                $editPageErrors[] = '興味関心は1つ以上選択してください。';
                $editPageDebugErrors[] = [
                    'message' => '興味関心が未選択です。',
                ];
            } else {
                foreach ($editPageFormValues['interests'] as $interestSlug) {
                    if (!isset($interestOptions[$interestSlug])) {
                        $editPageErrors[] = '興味関心の入力値が不正です。';
                        $editPageDebugErrors[] = [
                            'message' => '興味関心の入力値が不正です。',
                            'value' => $interestSlug,
                        ];
                        break;
                    }
                }
            }
            if ($useUploadedProfileImage !== '1' && ($profileImageId === '' || !isset($profileImagePresets[$profileImageId]))) {
                $editPageErrors[] = 'プロフィール画像の選択値が不正です。';
                $editPageDebugErrors[] = [
                    'message' => 'プロフィール画像の選択値が不正です。',
                    'value' => $profileImageId,
                ];
            }

            $profileImagePayload = [
                'profile_image_type' => 'preset',
                'profile_image_id' => $profileImageId,
                'profile_image_url' => isset($profileImagePresets[$profileImageId]['url']) ? (string) $profileImagePresets[$profileImageId]['url'] : '',
            ];

            if (empty($editPageErrors) && $useUploadedProfileImage === '1') {
                $uploadedFile = isset($_FILES['profile_image_file']) && is_array($_FILES['profile_image_file'])
                    ? $_FILES['profile_image_file']
                    : null;
                $hasNewUpload = is_array($uploadedFile) && (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

                if ($hasNewUpload && function_exists('lutwiyo_process_member_profile_uploaded_image')) {
                    $uploadResult = lutwiyo_process_member_profile_uploaded_image($uploadedFile);
                    if (!($uploadResult['ok'] ?? false)) {
                        $editPageErrors[] = (string) ($uploadResult['error'] ?? 'プロフィール画像のアップロードに失敗しました。');
                    } else {
                        $profileImagePayload = [
                            'profile_image_type' => 'uploaded',
                            'profile_image_id' => (string) ($uploadResult['id'] ?? ''),
                            'profile_image_url' => (string) ($uploadResult['url'] ?? ''),
                        ];
                        $editPageFormValues['profile_image_url'] = $profileImagePayload['profile_image_url'];
                    }
                } elseif (($editPageFormValues['profile_image_url'] ?? '') !== '' && ($memberProfile['profile_image_type'] ?? '') === 'uploaded') {
                    $profileImagePayload = [
                        'profile_image_type' => 'uploaded',
                        'profile_image_id' => trim((string) ($memberProfile['profile_image_id'] ?? '')),
                        'profile_image_url' => trim((string) ($memberProfile['profile_image_url'] ?? '')),
                    ];
                    $editPageFormValues['profile_image_url'] = $profileImagePayload['profile_image_url'];
                } else {
                    $editPageErrors[] = '独自画像を利用する場合は画像ファイルを選択してください。';
                }
            }

            if (empty($editPageErrors)) {
                // --- 更新前プロフィール取得API呼び出し開始 ---
                // 差分確認やデバッグに備え、更新前のプロフィールを取得する。
                $currentMemberProfileResponse = lutwiyo_call_bridge_api('member_profile', [
                    'uid' => $memberUid,
                    'operation' => 'get',
                ]);
                // --- 更新前プロフィール取得API呼び出し終了 ---

                $currentMemberProfile = [];
                if (is_array($currentMemberProfileResponse)
                    && ($currentMemberProfileResponse['body']['result'] ?? '') === 'ok'
                    && is_array($currentMemberProfileResponse['body']['member'] ?? null)
                ) {
                    $currentMemberProfile = $currentMemberProfileResponse['body']['member'];
                }

                $updatePayload = [
                    'uid' => $memberUid,
                    'operation' => 'update',
                    'household_income' => $income,
                    'nickname' => $nickname,
                    'member_slug' => $memberSlug,
                    'favorite_area' => $favoriteArea,
                    'interests' => wp_json_encode($editPageFormValues['interests'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'is_comment_name_visible' => (int) $commentVisible,
                    'is_mailmagazine_opt_in' => (int) $mailmagazineOptIn,
                    'profile_image_type' => $profileImagePayload['profile_image_type'],
                    'profile_image_id' => $profileImagePayload['profile_image_id'],
                    'profile_image_url' => $profileImagePayload['profile_image_url'],
                ];

                // --- 会員プロフィール更新API呼び出し開始 ---
                // 入力値をTOKK独自プロフィールへ保存する。
                $updateResponse = lutwiyo_call_bridge_api('member_profile', $updatePayload);
                // --- 会員プロフィール更新API呼び出し終了 ---
                if (is_array($updateResponse) && ($updateResponse['body']['result'] ?? '') === 'ok') {
                    $updatedRedirectArgs = ['updated' => '1'];
                    if (isset($_GET['profile_required']) && (string) $_GET['profile_required'] === '1') {
                        $updatedRedirectArgs['registered'] = '1';
                    }
                    $redirectBaseUrl = remove_query_arg('registered', $mypageBaseUrl);
                    wp_safe_redirect(add_query_arg($updatedRedirectArgs, $redirectBaseUrl));
                    exit;
                }

                $updateErrorMessage = trim((string) ($updateResponse['body']['message'] ?? ''));
                $editPageErrors[] = $updateErrorMessage !== ''
                    ? $updateErrorMessage
                    : '会員情報の更新に失敗しました。時間をおいて再度お試しください。';
                $editPageDebugErrors[] = [
                    'message' => $updateErrorMessage !== ''
                        ? $updateErrorMessage
                        : '会員情報の更新に失敗しました。時間をおいて再度お試しください。',
                    'bridge_response' => $updateResponse,
                ];
            }
        }

        if (in_array($requestedAction, ['payment_history', 'payment_method'], true)) {
            if ($memberUid === '') {
                $editPageErrors[] = '会員情報の取得に失敗しました。時間をおいて再度お試しください。';
            } else {
                $action = $requestedAction === 'payment_history' ? 'payment_history' : 'payment_method';
                $paymentDestinationLabel = $action === 'payment_history' ? 'お支払い履歴' : '決済方法';
                $paymentResponse = lutwiyo_call_bridge_api($action, [
                    'uid' => $memberUid,
                    'current_url' => $mypageBaseUrl,
                ]);

                if (is_array($paymentResponse)
                    && ($paymentResponse['body']['result'] ?? '') === 'redirect'
                    && trim((string) ($paymentResponse['body']['redirect_to'] ?? '')) !== '') {
                    wp_redirect((string) $paymentResponse['body']['redirect_to']);
                    exit;
                }

                $editPageErrors[] = sprintf(
                    '%sへの遷移に失敗しました。時間をおいて再度お試しください。',
                    $paymentDestinationLabel
                );
            }
        }
    }
}

if (!empty($editPageDebugErrors)) {
    // lutwiyo_debug('エラー内容（デバッグ）', $editPageDebugErrors);
}

$isProfileRequiredRequested = isset($_GET['profile_required'])
    && (string) $_GET['profile_required'] === '1';
$hasNickname = lutwiyo_has_non_whitespace_text($editPageFormValues['nickname'] ?? '');
$hasMailmagazineOptIn = in_array($editPageFormValues['is_mailmagazine_opt_in'], ['0', '1'], true);
$showProfileRequiredNotice = $isProfileRequiredRequested && (!$hasNickname || !$hasMailmagazineOptIn);

if (isset($_GET['updated']) && (string) $_GET['updated'] === '1') {
    $editPageFlashMessage = '会員情報を更新しました';
    if (isset($_GET['registered']) && (string) $_GET['registered'] === '1') {
        $editPageFlashMessage = '会員情報のご登録が完了しました';
    }
}

get_header();
?>
<main class="main wrapper" role="main">
    <article class="articlePT articlePB" data-boxBgColor="bodyMypage">
        <div class="gridWide">
            <h1 class="title fs--22 mypageEditPageTitle">会員情報</h1>

            <style>
                .mypageEditContainer {
                    margin: 0 auto;
                    max-width: 560px;
                    font-size: 16px;
                }
                .mypageEditSection {
                    margin-top: 32px;
                }
                .mypageEditPageTitle {
                    text-align: center;
                }
                .mypageEditSectionTitle {
                    font-size: 20px;
                    font-weight: 700;
                    margin-bottom: 16px;
                }
                #tokk-member-info {
                    scroll-margin-top: 20px;
                }
                .mypageEditRow {
                    display: flex;
                    align-items: center;
                    gap: 16px;
                    margin-bottom: 10px;
                }
                .mypageEditRow dt,
                .mypageEditLabel {
                    width: 180px;
                    min-width: 180px;
                    font-weight: 700;
                    font-size: 15px;
                }
                .mypageEditInputHelp {
                    display: block;
                    margin-top: 6px;
                    color: #666;
                    font-size: 12px;
                    line-height: 1.5;
                }
                .mypageEditInputHelp[data-status="ok"] {
                    color: #0f766e;
                }
                .mypageEditInputHelp[data-status="error"] {
                    color: #c33;
                    font-weight: 700;
                }
                .mypageEditInputHelp[data-status="locked"] {
                    color: #555;
                    font-weight: 700;
                }
                .mypageEditRow dd,
                .mypageEditValue {
                    margin: 0;
                    flex: 1;
                }

                .mypageEditRow--note {
                    align-items: flex-start;
                }
                .mypageEditRow--note dt {
                    display: none;
                }
                .mypageEditRow--note dd {
                    margin-left: 0;
                }
                .mypageEditInput {
                    width: 100%;
                    min-height: 38px;
                    border: 1px solid #888;
                    border-radius: 8px;
                    padding: 6px 10px;
                    font-size: 16px;
                    background-color: #fff;
                }
                .mypageEditSelectWrap {
                    position: relative;
                }
                .mypageEditSelect {
                    appearance: none;
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    padding-right: 36px;
                }
                .mypageEditSelectWrap::after {
                    content: "▽";
                    position: absolute;
                    top: 50%;
                    right: 12px;
                    transform: translateY(-50%);
                    pointer-events: none;
                    color: #444;
                    font-size: 14px;
                }
                .mypageEditRequired {
                    color: #d00;
                    font-size: 12px;
                    margin-left: 6px;
                    font-weight: 700;
                }
                .mypageEditActionButton {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 50px;
                    border: 1px solid rgba(58, 58, 58, 0.75);
                    border-radius: 50px;
                    padding: 0 50px;
                    font-size: 17px;
                    background: #fff;
                    cursor: pointer;
                }
                .mypageEditActionButton .mypageBtnLabel {
                    display: inline-block;
                    color: #3a3a3a;
                    line-height: 1.4;
                }
                .mypageEditRequiredNotice {
                    margin-top: 8px;
                    color: #d00;
                    font-weight: 700;
                }
                .mypageEditFieldHint {
                    margin: 0 0 10px;
                    color: #444;
                    font-size: 13px;
                    line-height: 1.5;
                }
                .mypageEditCheckboxGroup {
                    margin: 0;
                    padding: 0;
                    border: none;
                }
                .mypageEditInterestGrid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 10px;
                }
                .mypageEditInterestOption {
                    position: relative;
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                    border: 1px solid #d1d1d1;
                    border-radius: 12px;
                    padding: 10px 12px;
                    background: #fff;
                    cursor: pointer;
                    line-height: 1.5;
                    transition: background-color 0.2s ease, border-color 0.2s ease;
                }
                .mypageEditInterestOption.is-selected {
                    background: #f1f1f1;
                    border-color: #bdbdbd;
                }
                .mypageEditInterestOption:has(.mypageEditInterestControl input:checked) {
                    background: #BFBFBF;
                    border-color: #bdbdbd;
                }
                .mypageEditInterestControl {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 18px;
                    min-height: 18px;
                    margin-top: 2px;
                    flex-shrink: 0;
                }
                .mypageEditInterestControl input {
                    margin-top: 2px;
                    flex-shrink: 0;
                }
                .mypageEditInterestText {
                    display: block;
                    writing-mode: horizontal-tb;
                    text-orientation: mixed;
                    white-space: normal;
                    word-break: normal;
                    overflow-wrap: normal;
                    position: static;
                    letter-spacing: normal;
                    font-feature-settings: normal;
                    font-kerning: normal;
                }
                .mypageEditFlashMessage {
                    margin-top: 16px;
                    color: #d00;
                    font-weight: 700;
                }
                .mypageEditLinks {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    flex-wrap: wrap;
                }
                .mypageProfileImageGrid {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(72px, 1fr));
                    gap: 12px;
                    width: 100%;
                    max-width: 420px;
                }
                .mypageProfileImageOption {
                    display: block;
                    position: relative;
                    cursor: pointer;
                    min-width: 0;
                }
                .mypageProfileImageOption input {
                    position: absolute;
                    opacity: 0;
                    pointer-events: none;
                }
                .mypageProfileImageOptionFrame {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-start;
                    border: 2px solid #d7d7d7;
                    border-radius: 16px;
                    padding: 8px;
                    background: #fff;
                    transition: border-color .2s ease, box-shadow .2s ease;
                    min-height: 112px;
                    box-sizing: border-box;
                }
                .mypageProfileImageOption input:checked + .mypageProfileImageOptionFrame {
                    border-color: #0f766e;
                    box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12);
                }
                .mypageProfileImageOptionFrame img,
                .mypageProfileImagePreview img {
                    display: block;
                    width: 100%;
                    max-width: 72px;
                    height: auto;
                    aspect-ratio: 1 / 1;
                    object-fit: cover;
                    border-radius: 12px;
                    background: #f5f5f5;
                    margin: 0 auto;
                }
                .mypageProfileImageOptionLabel {
                    margin-top: 6px;
                    font-size: 12px;
                    font-weight: 700;
                    line-height: 1.2;
                    text-align: center;
                }
                .mypageProfileImageUploadToggle {
                    margin-top: 14px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .mypageProfileImageUploadToggle input {
                    width: auto;
                }
                .mypageProfileImageUploadPanel {
                    margin-top: 12px;
                    padding: 14px;
                    border: 1px solid #d7d7d7;
                    border-radius: 16px;
                    background: #fff;
                }
                .mypageProfileImagePreview {
                    margin-top: 12px;
                    max-width: 180px;
                }
                @media only screen and (max-width: 767px) {
                    .mypageProfileImageGrid {
                        grid-template-columns: repeat(4, minmax(0, 1fr));
                        gap: 8px;
                        max-width: 100%;
                    }
                    .mypageProfileImageOptionFrame {
                        padding: 6px;
                        border-radius: 12px;
                        min-height: 84px;
                    }
                    .mypageProfileImageOptionFrame img {
                        max-width: 46px;
                        border-radius: 10px;
                    }
                    .mypageProfileImageOptionLabel {
                        margin-top: 4px;
                        font-size: 10px;
                    }
                    .mypageProfileImagePreview {
                        max-width: 120px;
                    }
                }
                .mypageEditLinkButton {
                    border: none;
                    background: none;
                    color: #222;
                    font-size: 13px;
                    text-decoration: underline !important;
                    text-decoration-thickness: 1px;
                    text-underline-offset: 2px;
                    cursor: pointer;
                    padding: 0;
                }
                .mypageEditTextLink {
                    color: #222;
                    font-size: 13px;
                    text-decoration: underline !important;
                    text-decoration-thickness: 1px;
                    text-underline-offset: 2px;
                }
                .mypageCancellation .cancellation {
                    color: #222;
                    font-size: 13px;
                    text-decoration: underline !important;
                    text-decoration-thickness: 1px;
                    text-underline-offset: 2px;
                }
                .mypageEditPlanValue {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 6px;
                }
                @media (min-width: 768px) {
                    .mypageEditPlanValue {
                        flex-direction: row;
                        align-items: center;
                        gap: 14px;
                    }
                }
                @media only screen and (max-width: 767px) {
                    .mypageEditRow {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 6px;
                    }
                    .mypageEditRow dt,
                    .mypageEditLabel {
                        width: 100%;
                        min-width: 100%;
                    }
                    .mypageEditValue {
                        width: 100%;
                    }
                    .mypageEditInterestGrid {
                        grid-template-columns: 1fr;
                        gap: 8px;
                    }
                    .mypageEditActionButton {
                        width: 100%;
                        padding: 0 20px;
                    }
                    .mypageEditActionButton .mypageBtnLabel {
                        font-size: 16px;
                        white-space: normal;
                        text-align: center;
                    }
                    .mypageProfileImageGrid {
                        grid-template-columns: repeat(2, minmax(0, 1fr));
                    }
                }
            </style>

            <div class="mypageEditContainer">

            <?php if ($editPageFlashMessage !== ''): ?>
                <p class="mypageEditFlashMessage"><?php echo esc_html($editPageFlashMessage); ?></p>
            <?php endif; ?>

            <?php if (!empty($editPageErrors)): ?>
                <ul style="margin-top:16px;color:#c33;">
                    <li><?php echo esc_html('エラーが発生しました'); ?></li>
                </ul>
            <?php endif; ?>

            <section class="mypageEditSection">
                <h2 class="mypageEditSectionTitle">基本情報</h2>
                <dl>
                    <div class="mypageEditRow"><dt>お名前</dt><dd><?php echo esc_html($externalMemberDisplay['name']); ?></dd></div>
                    <div class="mypageEditRow"><dt>お名前カナ</dt><dd><?php echo esc_html($externalMemberDisplay['name_kana']); ?></dd></div>
                    <div class="mypageEditRow"><dt>性別</dt><dd><?php echo esc_html($externalMemberDisplay['gender']); ?></dd></div>
                    <div class="mypageEditRow"><dt>生年月日</dt><dd><?php echo esc_html($externalMemberDisplay['birthday']); ?></dd></div>
                    <div class="mypageEditRow mypageEditRow--note"><dt></dt><dd>その他の基本情報はHH cross IDのサイトでご確認ください</dd></div>
                </dl>

                <form method="post" action="<?php echo esc_url($mypageBaseUrl); ?>" style="margin-top:24px;">
                    <?php wp_nonce_field('tokk_mypage_edit_action', 'mypage_edit_nonce'); ?>
                    <input type="hidden" name="mypage_edit_action" value="open_crossid_edit">
                    <button type="submit" class="mypageBtn mypageEditActionButton"><span class="mypageBtnLabel">HH cross IDのサイトへ</span></button>
                </form>
            </section>

            <section class="mypageEditSection">
                <h2 id="tokk-member-info" class="mypageEditSectionTitle">TOKK会員情報</h2>
                <?php if ($showProfileRequiredNotice): ?>
                    <p class="mypageEditRequiredNotice">TOKK会員情報について、必須項目をご入力ください。</p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url($mypageBaseUrl); ?>" style="margin-top:12px;" enctype="multipart/form-data">
                    <?php wp_nonce_field('tokk_mypage_edit_action', 'mypage_edit_nonce'); ?>
                    <input type="hidden" name="mypage_edit_action" value="update_profile">

                    <div class="mypageEditRow">
                        <p class="mypageEditLabel">会員プラン</p>
                        <div class="mypageEditValue mypageEditPlanValue">
                            <span><?php echo esc_html($mypagePlanLabel); ?></span>
                            <a href="<?php echo esc_url($mypagePlanChangeUrl); ?>" class="mypageEditLinkButton">会員プランを変更する</a>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <p class="mypageEditLabel">お支払い方法</p>
                        <div class="mypageEditValue mypageEditLinks">
                            <?php if ($mypagePlanLabel === 'フリー'): ?>
                                <span>-</span>
                            <?php else: ?>
                                <span>クレジットカード</span>
                                <a href="<?php echo esc_url($paymentHistoryUrl); ?>" class="mypageEditTextLink">お支払い履歴</a>
                                <a href="<?php echo esc_url($paymentMethodUrl); ?>" class="mypageEditTextLink">決済方法</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <label for="household_income" class="mypageEditLabel">年収<span style="font-size:12px;font-weight:400;">（任意）</span></label>
                        <div class="mypageEditValue mypageEditSelectWrap">
                        <select id="household_income" class="mypageEditInput mypageEditSelect" name="household_income">
                                <option value="">選択してください</option>
                                <?php foreach ($householdIncomeOptions as $incomeValue => $incomeLabel): ?>
                                    <option value="<?php echo esc_attr($incomeValue); ?>" <?php selected($editPageFormValues['household_income'], $incomeValue); ?>>
                                        <?php echo esc_html($incomeLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <label for="nickname" class="mypageEditLabel">ニックネーム<span class="mypageEditRequired">必須</span></label>
                        <div class="mypageEditValue">
                            <input id="nickname" class="mypageEditInput" name="nickname" type="text" maxlength="64" placeholder="XX文字以外で入力してください" value="<?php echo esc_attr($editPageFormValues['nickname']); ?>" required>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <label for="member_slug" class="mypageEditLabel">公開会員ID<span class="mypageEditRequired">必須</span></label>
                        <div class="mypageEditValue">
                            <input
                                id="member_slug"
                                class="mypageEditInput"
                                name="member_slug"
                                type="text"
                                maxlength="32"
                                pattern="[a-z0-9_-]{3,32}"
                                inputmode="latin"
                                autocapitalize="none"
                                autocomplete="off"
                                placeholder="例: mb_tokku01"
                                value="<?php echo esc_attr($editPageFormValues['member_slug']); ?>"
                                data-check-endpoint="<?php echo esc_url(home_url('/member-service/api/v1/bridge/auth/member-profile-slug-availability')); ?>"
                                data-member-uid="<?php echo esc_attr($memberUid); ?>"
                                data-current-slug="<?php echo esc_attr($editPageFormValues['member_slug']); ?>"
                                data-editable="<?php echo $memberSlugIsEditable ? '1' : '0'; ?>"
                                <?php echo $memberSlugIsEditable ? '' : 'readonly'; ?>
                                required>
                            <span
                                id="member_slug_help"
                                class="mypageEditInputHelp"
                                data-status="<?php echo $memberSlugIsEditable ? 'default' : 'locked'; ?>"
                            ><?php echo esc_html($memberSlugIsEditable
                                ? '会員別ブログ公開一覧のURLで使う公開用IDです。英小文字・数字・ハイフン・アンダースコアのみ利用できます。保存後に利用可否が非同期で表示され、変更すると以後は再変更できません。'
                                : '公開会員IDは既に確定しているため変更できません。'); ?></span>
                        </div>
                    </div>

                    <div class="mypageEditRow mypageEditRow--note">
                        <label class="mypageEditLabel">プロフィール画像</label>
                        <div class="mypageEditValue">
                            <div class="mypageProfileImageGrid">
                                <?php foreach ($profileImagePresets as $presetId => $preset): ?>
                                    <label class="mypageProfileImageOption">
                                        <input type="radio"
                                               name="profile_image_id"
                                               value="<?php echo esc_attr($presetId); ?>"
                                               aria-label="<?php echo esc_attr((string) ($preset['accessible_label'] ?? 'プロフィール画像')); ?>"
                                               <?php checked($editPageFormValues['profile_image_id'], $presetId); ?>
                                               <?php echo $editPageFormValues['use_uploaded_profile_image'] === '1' ? 'disabled' : ''; ?>>
                                        <span class="mypageProfileImageOptionFrame">
                                            <img src="<?php echo esc_url((string) ($preset['url'] ?? '')); ?>" alt="<?php echo esc_attr((string) ($preset['accessible_label'] ?? 'プロフィール画像')); ?>">
                                            <span class="mypageProfileImageOptionLabel"><?php echo esc_html((string) ($preset['label'] ?? $presetId)); ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <label class="mypageProfileImageUploadToggle">
                                <input type="checkbox" name="use_uploaded_profile_image" value="1" <?php checked($editPageFormValues['use_uploaded_profile_image'], '1'); ?>>
                                <span>独自の画像をアップロードする</span>
                            </label>
                            <div class="mypageProfileImageUploadPanel" <?php echo $editPageFormValues['use_uploaded_profile_image'] === '1' ? '' : 'hidden'; ?>>
                                <input type="file" name="profile_image_file" accept=".jpg,.jpeg,.png,.webp" class="mypageEditInput">
                                <p style="margin-top:8px;font-size:13px;color:#666;">アップロードした画像は正方形にトリミングされ、会員画像として使用されます。</p>
                                <div class="mypageProfileImagePreview">
                                    <img src="<?php echo esc_url((string) ($editPageFormValues['profile_image_url'] ?: ($profileImagePresets[$editPageFormValues['profile_image_id']]['url'] ?? ''))); ?>" alt="プロフィール画像プレビュー">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <label for="is_comment_name_visible" class="mypageEditLabel">ニックネームの表示</label>
                        <div class="mypageEditValue mypageEditSelectWrap">
                        <select id="is_comment_name_visible" class="mypageEditInput mypageEditSelect" name="is_comment_name_visible" required>
                                <option value="1" <?php selected($editPageFormValues['is_comment_name_visible'], '1'); ?>>する</option>
                                <option value="0" <?php selected($editPageFormValues['is_comment_name_visible'], '0'); ?>>しない</option>
                            </select>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <label for="favorite_area" class="mypageEditLabel">お気に入りエリア</label>
                        <div class="mypageEditValue mypageEditSelectWrap">
                        <select id="favorite_area" class="mypageEditInput mypageEditSelect" name="favorite_area">
                                <option value="">選択してください</option>
                                <?php foreach ($favoriteAreaOptions as $areaValue => $areaLabel): ?>
                                    <option value="<?php echo esc_attr($areaValue); ?>" <?php selected($editPageFormValues['favorite_area'], $areaValue); ?>>
                                        <?php echo esc_html($areaLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <p class="mypageEditLabel">興味関心<span class="mypageEditRequired">必須</span></p>
                        <div class="mypageEditValue">
                            <p class="mypageEditFieldHint">複数選択可（1つ以上選択してください）</p>
                            <fieldset class="mypageEditCheckboxGroup">
                                <div class="mypageEditInterestGrid">
                                    <?php foreach ($interestOptions as $interestValue => $interestLabel): ?>
                                        <?php $isInterestChecked = in_array($interestValue, $editPageFormValues['interests'], true); ?>
                                        <label class="mypageEditInterestOption<?php echo $isInterestChecked ? ' is-selected' : ''; ?>">
                                            <span class="mypageEditInterestControl">
                                                <input
                                                    type="checkbox"
                                                    name="interests[]"
                                                    value="<?php echo esc_attr($interestValue); ?>"
                                                    <?php checked($isInterestChecked); ?>>
                                            </span>
                                            <span class="mypageEditInterestText"><?php echo esc_html($interestLabel); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <label for="is_mailmagazine_opt_in" class="mypageEditLabel">メルマガ同意<span class="mypageEditRequired">必須</span></label>
                        <div class="mypageEditValue mypageEditSelectWrap">
                        <select id="is_mailmagazine_opt_in" class="mypageEditInput mypageEditSelect" name="is_mailmagazine_opt_in" required>
                                <option value="" <?php selected($editPageFormValues['is_mailmagazine_opt_in'], ''); ?>>選択してください</option>
                                <option value="1" <?php selected($editPageFormValues['is_mailmagazine_opt_in'], '1'); ?>>同意する</option>
                                <option value="0" <?php selected($editPageFormValues['is_mailmagazine_opt_in'], '0'); ?>>同意しない</option>
                            </select>
                        </div>
                    </div>

                    <div class="mypageEditRow">
                        <p class="mypageEditLabel">有料会員期限</p>
                        <p class="mypageEditValue"><?php echo esc_html($tokkMemberDisplay['membership_expired_at']); ?></p>
                    </div>

                    <div class="mypageEditRow">
                        <p class="mypageEditLabel">次回会員更新予定日</p>
                        <p class="mypageEditValue"><?php echo esc_html($tokkMemberDisplay['next_renewal_at']); ?></p>
                    </div>

                    <div class="mypageEditRow">
                        <p class="mypageEditLabel">有料会員サービス解約予定日</p>
                        <p class="mypageEditValue"><?php echo esc_html($tokkMemberDisplay['service_cancel_scheduled_at']); ?></p>
                    </div>

                    <div class="mypageEditRow">
                        <p class="mypageEditLabel">最終ログイン日</p>
                        <p class="mypageEditValue"><?php echo esc_html($tokkMemberDisplay['last_login_at']); ?></p>
                    </div>

                    <p style="margin-top:28px;">
                        <button type="submit" class="mypageBtn mypageEditActionButton"><span class="mypageBtnLabel">会員情報を変更する</span></button>
                    </p>
                    <p style="margin-top:42px; text-align:center;">
                        <a href="<?php echo esc_url(home_url('/cancel-input/')); ?>" class="cancellation"  style="text-decoration: underline; text-underline-offset: 2px;">会員退会</a>
                    </p>
                </form>
            </section>
            </div>
        </div>
    </article>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var uploadToggle = document.querySelector('input[name="use_uploaded_profile_image"]');
        var uploadPanel = document.querySelector('.mypageProfileImageUploadPanel');
        var uploadField = document.querySelector('input[name="profile_image_file"]');
        var previewImage = document.querySelector('.mypageProfileImagePreview img');
        var presetInputs = document.querySelectorAll('input[name="profile_image_id"]');
        var memberSlugInput = document.getElementById('member_slug');
        var memberSlugHelp = document.getElementById('member_slug_help');
        var memberSlugTimer = null;
        var lastMemberSlugRequestValue = '';
        var interestCheckboxes = document.querySelectorAll('.mypageEditInterestControl input[type="checkbox"]');
        var updateProfileActionInput = document.querySelector('input[name="mypage_edit_action"][value="update_profile"]');
        var updateProfileForm = updateProfileActionInput ? updateProfileActionInput.closest('form') : null;

        var syncUploadMode = function () {
            if (!uploadToggle || !uploadPanel) {
                return;
            }

            var isUploaded = uploadToggle.checked;
            uploadPanel.hidden = !isUploaded;
            presetInputs.forEach(function (input) {
                input.disabled = isUploaded;
            });
        };

        if (uploadToggle) {
            uploadToggle.addEventListener('change', syncUploadMode);
            syncUploadMode();
        }

        var syncInterestRequiredState = function () {
            if (interestCheckboxes.length === 0) {
                return;
            }

            var hasCheckedInterest = Array.prototype.some.call(interestCheckboxes, function (checkbox) {
                return checkbox.checked;
            });

            var firstCheckbox = interestCheckboxes[0];
            if (!firstCheckbox) {
                return;
            }

            firstCheckbox.required = !hasCheckedInterest;
            firstCheckbox.setCustomValidity(hasCheckedInterest ? '' : '興味関心は1つ以上選択してください。');
        };

        if (interestCheckboxes.length > 0) {
            interestCheckboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    var option = checkbox.closest('.mypageEditInterestOption');
                    if (!option) {
                        return;
                    }

                    option.classList.toggle('is-selected', checkbox.checked);
                    syncInterestRequiredState();
                });
            });

            syncInterestRequiredState();
        }

        if (updateProfileForm) {
            updateProfileForm.addEventListener('submit', function () {
                syncInterestRequiredState();
            });
        }

        presetInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                if (!previewImage || !input.checked) {
                    return;
                }

                var optionImage = input.parentElement ? input.parentElement.querySelector('img') : null;
                if (optionImage instanceof HTMLImageElement) {
                    previewImage.src = optionImage.src;
                }
            });
        });

        if (uploadField && previewImage) {
            uploadField.addEventListener('change', function () {
                var file = uploadField.files && uploadField.files[0] ? uploadField.files[0] : null;
                if (!file) {
                    return;
                }

                var objectUrl = window.URL.createObjectURL(file);
                previewImage.src = objectUrl;
            });
        }

        var setMemberSlugHelp = function (message, status) {
            if (!memberSlugHelp) {
                return;
            }

            memberSlugHelp.textContent = message;
            memberSlugHelp.dataset.status = status || 'default';
        };

        var validateMemberSlugFormat = function (value) {
            return /^[a-z0-9_-]{3,32}$/.test(value);
        };

        var scheduleMemberSlugAvailabilityCheck = function () {
            if (!memberSlugInput || !memberSlugHelp) {
                return;
            }

            var editable = memberSlugInput.dataset.editable === '1';
            var normalizedValue = (memberSlugInput.value || '').toLowerCase();
            memberSlugInput.value = normalizedValue;

            if (!editable) {
                memberSlugInput.setCustomValidity('');
                setMemberSlugHelp('公開会員IDは既に確定しているため変更できません。', 'locked');
                return;
            }

            if (!validateMemberSlugFormat(normalizedValue)) {
                memberSlugInput.setCustomValidity('公開会員IDは英小文字・数字・ハイフン・アンダースコアのみ、3〜32文字で入力してください。');
                setMemberSlugHelp('公開会員IDは英小文字・数字・ハイフン・アンダースコアのみ、3〜32文字で入力してください。', 'error');
                return;
            }

            memberSlugInput.setCustomValidity('');

            if (memberSlugTimer) {
                window.clearTimeout(memberSlugTimer);
            }

            memberSlugTimer = window.setTimeout(function () {
                var endpoint = memberSlugInput.dataset.checkEndpoint || '';
                var memberUid = memberSlugInput.dataset.memberUid || '';
                if (!endpoint || !memberUid) {
                    return;
                }

                lastMemberSlugRequestValue = normalizedValue;
                setMemberSlugHelp('公開会員IDを確認しています...', 'default');

                var payload = new window.URLSearchParams();
                payload.set('uid', memberUid);
                payload.set('member_slug', normalizedValue);

                window.fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: payload.toString()
                }).then(function (response) {
                    return response.json().catch(function () {
                        return null;
                    }).then(function (json) {
                        return {
                            ok: response.ok,
                            status: response.status,
                            body: json
                        };
                    });
                }).then(function (result) {
                    if (!memberSlugInput || memberSlugInput.value !== lastMemberSlugRequestValue) {
                        return;
                    }

                    if (!result || !result.body || result.body.result !== 'ok') {
                        var fallbackMessage = result && result.body && typeof result.body.message === 'string' && result.body.message !== ''
                            ? result.body.message
                            : '公開会員IDの確認に失敗しました。時間をおいて再度お試しください。';
                        memberSlugInput.setCustomValidity('');
                        setMemberSlugHelp(fallbackMessage, 'error');
                        return;
                    }

                    var available = !!result.body.available;
                    var editableNow = !!result.body.member_slug_is_editable;
                    var message = typeof result.body.message === 'string' && result.body.message !== ''
                        ? result.body.message
                        : '';

                    if (!editableNow) {
                        memberSlugInput.dataset.editable = '0';
                        memberSlugInput.readOnly = true;
                        memberSlugInput.setCustomValidity('');
                        setMemberSlugHelp(message || '公開会員IDは既に確定しているため変更できません。', 'locked');
                        return;
                    }

                    if (!available) {
                        memberSlugInput.setCustomValidity(message || 'この公開会員IDは利用できません。');
                        setMemberSlugHelp(message || 'この公開会員IDは利用できません。', 'error');
                        return;
                    }

                    memberSlugInput.setCustomValidity('');
                    setMemberSlugHelp(message || 'この公開会員IDは利用できます。', 'ok');
                }).catch(function () {
                    if (!memberSlugInput || memberSlugInput.value !== lastMemberSlugRequestValue) {
                        return;
                    }

                    memberSlugInput.setCustomValidity('');
                    setMemberSlugHelp('公開会員IDの確認に失敗しました。時間をおいて再度お試しください。', 'error');
                });
            }, 300);
        };

        if (memberSlugInput) {
            memberSlugInput.addEventListener('input', scheduleMemberSlugAvailabilityCheck);
            memberSlugInput.addEventListener('change', scheduleMemberSlugAvailabilityCheck);
            scheduleMemberSlugAvailabilityCheck();
        }

        var shouldScroll = <?php echo $showProfileRequiredNotice ? 'true' : 'false'; ?>;
        if (!shouldScroll) {
            return;
        }

        var getHeaderOffset = function () {
            var offset = 0;
            var selectors = [
                '.header',
                '.globalHeader',
                '.headerMain',
                '.headerSub',
                '.headerSp',
                '.headerPc',
                '.siteHeader',
                'header'
            ];
            var forceIncludeClassNames = ['headerMain', 'headerSub', 'globalHeader'];

            selectors.forEach(function (selector) {
                document.querySelectorAll(selector).forEach(function (element) {
                    if (!(element instanceof Element)) {
                        return;
                    }

                    var style = window.getComputedStyle(element);
                    var isFixedLike = style.position === 'fixed' || style.position === 'sticky';
                    var isForceIncluded = forceIncludeClassNames.some(function (className) {
                        return element.classList.contains(className);
                    });

                    if (!isFixedLike && !isForceIncluded) {
                        return;
                    }

                    var rect = element.getBoundingClientRect();
                    if (rect.bottom > 0 && rect.top < window.innerHeight * 0.5) {
                        offset = Math.max(offset, rect.bottom);
                    }
                });
            });

            return offset;
        };

        var target = document.getElementById('tokk-member-info');
        if (target) {
            var headerOffset = getHeaderOffset();
            var targetTop = window.pageYOffset + target.getBoundingClientRect().top;
            var scrollTop = Math.max(0, targetTop - headerOffset - 12);

            window.scrollTo({ top: scrollTop, behavior: 'smooth' });
        }
    });
</script>
<?php get_footer(); ?>
