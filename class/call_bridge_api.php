<?php

if (!function_exists('lutwiyo_resolve_bridge_api_path')) {
    /**
     * ブリッジAPI種別からmember-service配下の実際のエンドポイントを解決する。
     *
     * @param string $action
     * @param array<string,mixed> $payload
     * @return string|null
     */
    function lutwiyo_resolve_bridge_api_path($action)
    {
        $pathMap = [
            // ログイン開始API
            'login' => '/member-service/api/v1/bridge/auth/login',
            // 新規会員登録開始API
            'regist' => '/member-service/api/v1/bridge/auth/regist',
            // ログイン状態確認API
            'login_status' => '/member-service/api/v1/bridge/auth/login-status',
            // ログアウトAPI
            'logout' => '/member-service/api/v1/bridge/auth/logout',
            // 会員情報取得API
            'member_info' => '/member-service/api/v1/bridge/auth/member-info',
            // 会員コンテキスト統合API
            'member_context' => '/member-service/api/v1/bridge/auth/member-context',
            // 記事詳細閲覧コンテキスト統合API
            'article_viewer_context' => '/member-service/api/v1/bridge/auth/article-viewer-context',
            // マイページ情報取得API（TOKK独自項目）
            'member_profile' => '/member-service/api/v1/bridge/auth/member-profile',
            // CrossIDマイページ遷移API
            'mypage' => '/member-service/api/v1/bridge/auth/mypage',
            // サービス解約API
            'service_cancel' => '/member-service/api/v1/bridge/auth/service-cancel',
            // 会員プラン変更API（STANDARD -> FREE 予約）
            'plan_change' => '/member-service/api/v1/bridge/auth/plan-change',
            // 有料会員プラン金額取得API
            'payment_plan_amount' => '/member-service/api/v1/bridge/auth/payment-plan-amount',
            // 有料会員登録API
            'payment_request' => '/member-service/api/v1/bridge/auth/payment-request',
            // 支払履歴表示API
            'payment_history' => '/member-service/api/v1/bridge/auth/payment-history',
            // 決済方法管理API
            'payment_method' => '/member-service/api/v1/bridge/auth/payment-method',
            // 継続課金カードmain設定状態取得API
            'paymethod_status' => '/member-service/api/v1/bridge/auth/paymethod-status',
        ];

        return is_string($action) && isset($pathMap[$action]) ? $pathMap[$action] : null;
    }
}


if (!function_exists('lutwiyo_resolve_bridge_authorization_header')) {
    /**
     * member-service呼び出し時に使うAuthorizationヘッダー値を解決する。
     * - `TOKK_MEMBER_SERVICE_AUTHORIZATION` 定数があれば最優先で利用
     * - なければ、現在リクエストのAuthorization/Basic認証情報を引き継ぐ
     */
    function lutwiyo_resolve_bridge_authorization_header()
    {
        // 1) wp-config.php想定の定数指定があれば最優先で使う。
        if (defined('TOKK_MEMBER_SERVICE_AUTHORIZATION')) {
            $configured = trim((string) constant('TOKK_MEMBER_SERVICE_AUTHORIZATION'));
            if ($configured !== '') {
                return $configured;
            }
        }

        // 2) 現在のリクエストヘッダーを引き継ぐ。
        $serverAuthorization = isset($_SERVER['HTTP_AUTHORIZATION'])
            ? trim((string) $_SERVER['HTTP_AUTHORIZATION'])
            : '';
        if ($serverAuthorization !== '') {
            return $serverAuthorization;
        }

        // 3) 互換対応としてPHP_AUTH_*からBasic認証を再構築する。
        $phpAuthUser = isset($_SERVER['PHP_AUTH_USER']) ? (string) $_SERVER['PHP_AUTH_USER'] : '';
        $phpAuthPw = isset($_SERVER['PHP_AUTH_PW']) ? (string) $_SERVER['PHP_AUTH_PW'] : '';
        if ($phpAuthUser !== '') {
            return 'Basic ' . base64_encode($phpAuthUser . ':' . $phpAuthPw);
        }

        // 4) どこからも取得できない場合は空を返す。
        return '';
    }
}

if (!function_exists('lutwiyo_build_bridge_cookie_header')) {
    /**
     * ブラウザCookieを member-service 向けの Cookie ヘッダー形式へ整形する。
     */
    function lutwiyo_build_bridge_cookie_header()
    {
        if (empty($_COOKIE) || !is_array($_COOKIE)) {
            return '';
        }

        $pairs = [];
        foreach ($_COOKIE as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }

            // $pairs[] = rawurlencode($name) . '=' . rawurlencode((string) $value);
            $pairs[] = $name . '=' . (string) $value;
        }

        return !empty($pairs) ? implode('; ', $pairs) : '';
    }
}

if (!function_exists('lutwiyo_forward_bridge_set_cookie_headers')) {
    /**
     * member-service の Set-Cookie 行を現在リクエストの $_COOKIE に反映する。
     * 同一リクエスト内で直後のブリッジAPI呼び出しが最新Cookieを使えるようにする。
     */
    function lutwiyo_sync_bridge_set_cookie_to_request_cookie(string $cookieLine)
    {
        $trimmed = trim($cookieLine);
        if ($trimmed === '') {
            return;
        }

        $parts = explode(';', $trimmed);
        if (empty($parts)) {
            return;
        }

        $nameValue = trim((string) $parts[0]);
        if ($nameValue === '' || !str_contains($nameValue, '=')) {
            return;
        }

        [$rawName, $rawValue] = explode('=', $nameValue, 2);
        $cookieName = rawurldecode(trim((string) $rawName));
        if ($cookieName === '') {
            return;
        }

        $cookieValue = rawurldecode((string) $rawValue);
        $_COOKIE[$cookieName] = $cookieValue;
    }

    /**
     * member-service の Set-Cookie をブラウザへそのまま返す。
     *
     * @param array<string,mixed> $response
     */
    function lutwiyo_forward_bridge_set_cookie_headers(array $response)
    {
        if (headers_sent()) {
            return;
        }

        $responseHeaders = wp_remote_retrieve_headers($response);
        $setCookie = $responseHeaders['set-cookie'] ?? null;

        if (is_string($setCookie) && $setCookie !== '') {
            lutwiyo_sync_bridge_set_cookie_to_request_cookie($setCookie);
            header('Set-Cookie: ' . $setCookie, false);
            return;
        }

        if (!is_array($setCookie)) {
            return;
        }

        foreach ($setCookie as $cookieLine) {
            if (!is_string($cookieLine) || $cookieLine === '') {
                continue;
            }

            lutwiyo_sync_bridge_set_cookie_to_request_cookie($cookieLine);
            header('Set-Cookie: ' . $cookieLine, false);
        }
    }
}

if (!function_exists('lutwiyo_decode_bridge_json_body')) {
    /**
     * @param array<string,mixed> $response
     * @return array<string,mixed>|null
     */
    function lutwiyo_decode_bridge_json_body(array $response, string $method, string $path)
    {
        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);
        $body = json_decode($rawBody, true);
        if (is_array($body)) {
            return $body;
        }

        $responseHeaders = wp_remote_retrieve_headers($response);
        $wwwAuthenticate = '';
        if (is_object($responseHeaders) && method_exists($responseHeaders, 'offsetGet')) {
            $wwwAuthenticate = (string) ($responseHeaders['www-authenticate'] ?? '');
        } elseif (is_array($responseHeaders)) {
            $wwwAuthenticate = (string) ($responseHeaders['www-authenticate'] ?? '');
        }

        error_log(sprintf(
            '[tokk_member_bridge] invalid JSON response. method=%s path=%s status=%d www_authenticate=%s body=%s',
            $method,
            $path,
            $statusCode,
            $wwwAuthenticate,
            mb_substr($rawBody, 0, 500)
        ));

        return null;
    }
}

if (!function_exists('lutwiyo_get_member_service_request_metrics_state')) {
    /**
     * member-service 呼び出しメトリクスのリクエスト内状態を返す。
     *
     * @return array<string,mixed>
     */
    function lutwiyo_get_member_service_request_metrics_state()
    {
        $metrics = $GLOBALS['lutwiyo_member_service_request_metrics'] ?? null;
        if (!is_array($metrics)) {
            $metrics = [
                'total' => 0,
                'paths' => [],
                'methods' => [],
                'callers' => [],
                'path_callers' => [],
            ];
            $GLOBALS['lutwiyo_member_service_request_metrics'] = $metrics;
        }

        return $metrics;
    }
}

if (!function_exists('lutwiyo_get_member_service_metrics_request_path')) {
    /**
     * メトリクスログ向けに現在リクエストのパスのみを返す。
     */
    function lutwiyo_get_member_service_metrics_request_path(): string
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($requestUri === '') {
            return '';
        }

        $requestPath = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($requestPath) || $requestPath === '') {
            return $requestUri;
        }

        return $requestPath;
    }
}

if (!function_exists('lutwiyo_get_member_service_metrics_request_method')) {
    /**
     * メトリクスログ向けに現在リクエストのHTTPメソッドを返す。
     */
    function lutwiyo_get_member_service_metrics_request_method(): string
    {
        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper(trim((string) $_SERVER['REQUEST_METHOD'])) : '';

        return $requestMethod !== '' ? $requestMethod : 'GET';
    }
}

if (!function_exists('lutwiyo_resolve_member_service_request_metric_caller')) {
    /**
     * member-service リクエストの呼び出し元ラベルを解決する。
     *
     * @param array<string,mixed> $requestArgs
     */
    function lutwiyo_resolve_member_service_request_metric_caller(array $requestArgs = []): string
    {
        $explicitCaller = trim((string) ($requestArgs['caller'] ?? ''));
        if ($explicitCaller !== '') {
            return $explicitCaller;
        }

        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($frames as $frame) {
            $file = isset($frame['file']) && is_string($frame['file']) ? $frame['file'] : '';
            if ($file === '' || str_ends_with($file, '/class/call_bridge_api.php')) {
                continue;
            }

            $function = isset($frame['function']) && is_string($frame['function'])
                ? $frame['function']
                : 'unknown';
            $line = isset($frame['line']) ? (int) $frame['line'] : 0;
            $normalized = str_replace('\\', '/', $file);
            $themePos = strpos($normalized, '/wp-content/themes/lutwiyo/');
            if ($themePos !== false) {
                $normalized = substr($normalized, $themePos + 1);
            } else {
                $normalized = ltrim($normalized, '/');
            }

            return $line > 0
                ? sprintf('%s:%d@%s', $normalized, $line, $function)
                : sprintf('%s@%s', $normalized, $function);
        }

        return 'unknown';
    }
}

if (!function_exists('lutwiyo_build_member_service_request_metrics_payload')) {
    /**
     * member-service 呼び出しメトリクスのログペイロードを組み立てる。
     *
     * @return array<string,mixed>
     */
    function lutwiyo_build_member_service_request_metrics_payload(): array
    {
        $metrics = lutwiyo_get_member_service_request_metrics_state();
        $total = (int) ($metrics['total'] ?? 0);
        $paths = is_array($metrics['paths'] ?? null) ? $metrics['paths'] : [];
        $methods = is_array($metrics['methods'] ?? null) ? $metrics['methods'] : [];
        $callers = is_array($metrics['callers'] ?? null) ? $metrics['callers'] : [];
        $pathCallers = is_array($metrics['path_callers'] ?? null) ? $metrics['path_callers'] : [];

        ksort($paths);
        ksort($methods);
        ksort($callers);
        foreach ($pathCallers as $path => $callerCounts) {
            if (!is_array($callerCounts)) {
                unset($pathCallers[$path]);
                continue;
            }

            ksort($callerCounts);
            $pathCallers[$path] = $callerCounts;
        }
        ksort($pathCallers);

        $memberContextDiagnostics = function_exists('lutwiyo_get_member_context_diagnostics')
            ? lutwiyo_get_member_context_diagnostics()
            : [];

        return [
            'ts' => function_exists('wp_date') ? wp_date(DATE_ATOM) : date(DATE_ATOM),
            'host' => isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : '',
            'request_method' => lutwiyo_get_member_service_metrics_request_method(),
            'request' => lutwiyo_get_member_service_metrics_request_path(),
            'total' => $total,
            'methods' => $methods,
            'paths' => $paths,
            'callers' => $callers,
            'path_callers' => $pathCallers,
            'member_context_diagnostics' => is_array($memberContextDiagnostics)
                ? array_values($memberContextDiagnostics)
                : [],
        ];
    }
}

if (!function_exists('lutwiyo_write_member_service_request_metrics_to_log_file')) {
    /**
     * member-service 呼び出しメトリクスを専用ログファイルへ追記する。
     */
    function lutwiyo_write_member_service_request_metrics_to_log_file(array $payload): bool
    {
        if (!function_exists('tokk_member_service_metrics_log_path')) {
            return false;
        }

        $logPath = tokk_member_service_metrics_log_path();
        if ($logPath === '') {
            return false;
        }

        $logDir = dirname($logPath);
        if ($logDir === '' || $logDir === '.') {
            return false;
        }

        if (!is_dir($logDir)) {
            $created = function_exists('wp_mkdir_p')
                ? wp_mkdir_p($logDir)
                : @mkdir($logDir, 0755, true);
            if (!$created && !is_dir($logDir)) {
                return false;
            }
        }

        $line = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($line) || $line === '') {
            return false;
        }

        return @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }
}

if (!function_exists('lutwiyo_log_member_service_request_metrics')) {
    /**
     * member-service 呼び出しメトリクスをリクエスト終了時に出力する。
     */
    function lutwiyo_log_member_service_request_metrics(): void
    {
        if (!function_exists('tokk_member_service_request_metrics_enabled') || !tokk_member_service_request_metrics_enabled()) {
            return;
        }

        $payload = lutwiyo_build_member_service_request_metrics_payload();

        if (lutwiyo_write_member_service_request_metrics_to_log_file($payload)) {
            return;
        }

        error_log(sprintf(
            '[tokk_member_bridge_metrics] request=%s total=%d methods=%s paths=%s',
            (string) ($payload['request'] ?? ''),
            (int) ($payload['total'] ?? 0),
            wp_json_encode($payload['methods'] ?? [], JSON_UNESCAPED_SLASHES),
            wp_json_encode($payload['paths'] ?? [], JSON_UNESCAPED_SLASHES)
        ));
    }
}

if (!function_exists('lutwiyo_register_member_service_request_metrics_shutdown_logger')) {
    /**
     * member-service 呼び出しメトリクスの shutdown ロガーを一度だけ登録する。
     */
    function lutwiyo_register_member_service_request_metrics_shutdown_logger(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;
        register_shutdown_function('lutwiyo_log_member_service_request_metrics');
    }
}

if (!function_exists('lutwiyo_record_member_service_request_metric')) {
    /**
     * 実際に発行した member-service リクエストをメトリクスへ記録する。
     */
    function lutwiyo_record_member_service_request_metric(string $method, string $path, string $caller = 'unknown'): void
    {
        if (!function_exists('tokk_member_service_request_metrics_enabled') || !tokk_member_service_request_metrics_enabled()) {
            return;
        }

        lutwiyo_register_member_service_request_metrics_shutdown_logger();

        $metrics = lutwiyo_get_member_service_request_metrics_state();
        $normalizedMethod = strtoupper(trim($method));
        $normalizedPath = '/' . ltrim(trim($path), '/');

        $normalizedCaller = trim($caller) !== '' ? trim($caller) : 'unknown';

        $metrics['total'] = (int) ($metrics['total'] ?? 0) + 1;
        $metrics['paths'][$normalizedPath] = (int) ($metrics['paths'][$normalizedPath] ?? 0) + 1;
        $metrics['methods'][$normalizedMethod] = (int) ($metrics['methods'][$normalizedMethod] ?? 0) + 1;
        $metrics['callers'][$normalizedCaller] = (int) ($metrics['callers'][$normalizedCaller] ?? 0) + 1;

        $pathCallers = is_array($metrics['path_callers'][$normalizedPath] ?? null)
            ? $metrics['path_callers'][$normalizedPath]
            : [];
        $pathCallers[$normalizedCaller] = (int) ($pathCallers[$normalizedCaller] ?? 0) + 1;
        $metrics['path_callers'][$normalizedPath] = $pathCallers;

        $GLOBALS['lutwiyo_member_service_request_metrics'] = $metrics;
    }
}

if (!function_exists('lutwiyo_call_member_service_api')) {
    /**
     * member-service APIへメソッド指定でリクエストし、JSON配列を返す。
     * WordPressとLaravelで同一ドメインを共有しているため、Cookie転送でLaravelセッションを継続させる。
     *
     * @param string $path `/member-service/...` 形式
     * @param string $method HTTP method
     * @param array<string,mixed>|null $payload
     * @param array<string,mixed> $requestArgs
     * @return array{status:int,body:array<string,mixed>}|null
     */
    function lutwiyo_call_member_service_api($path, $method = 'POST', $payload = null, array $requestArgs = [])
    {
        $normalizedPath = '/' . ltrim((string) $path, '/');
        if ($normalizedPath === '/') {
            return null;
        }

        $httpMethod = strtoupper(trim((string) $method));
        if ($httpMethod === '') {
            $httpMethod = 'POST';
        }

        // 一部環境で PATCH/DELETE が Web サーバー層で遮断されるため、
        // member-service への実リクエストは POST + Method Override を利用して互換性を確保する。
        $actualHttpMethod = $httpMethod;
        $methodOverride = '';
        if (in_array($httpMethod, ['PATCH', 'DELETE'], true)) {
            $actualHttpMethod = 'POST';
            $methodOverride = $httpMethod;
        }

        $url = home_url($normalizedPath);
        if ($httpMethod === 'GET' && is_array($payload) && !empty($payload)) {
            $url = add_query_arg($payload, $url);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $cookieHeader = lutwiyo_build_bridge_cookie_header();
        if ($cookieHeader !== '') {
            $headers['Cookie'] = $cookieHeader;
        }

        $authorizationHeader = lutwiyo_resolve_bridge_authorization_header();
        if ($authorizationHeader !== '') {
            $headers['Authorization'] = $authorizationHeader;
        }

        if ($methodOverride !== '') {
            $headers['X-HTTP-Method-Override'] = $methodOverride;
        }

        $metricsEnabled = function_exists('tokk_member_service_request_metrics_enabled')
            && tokk_member_service_request_metrics_enabled();
        $requestCaller = $metricsEnabled
            ? lutwiyo_resolve_member_service_request_metric_caller($requestArgs)
            : 'unknown';

        $customHeaders = is_array($requestArgs['headers'] ?? null) ? $requestArgs['headers'] : [];
        unset($requestArgs['headers']);
        unset($requestArgs['caller']);

        $remoteRequestArgs = array_merge([
            'method' => $actualHttpMethod,
            'headers' => array_merge($headers, $customHeaders),
            'timeout' => 20,
            'redirection' => 0,
        ], $requestArgs);

        if ($httpMethod !== 'GET' && is_array($payload)) {
            $remoteRequestArgs['body'] = wp_json_encode($payload);
        }

        lutwiyo_record_member_service_request_metric($actualHttpMethod, $normalizedPath, $requestCaller);

        $response = wp_remote_request($url, $remoteRequestArgs);

        if (is_wp_error($response)) {
            error_log(sprintf(
                '[tokk_member_bridge] wp_remote_request error. method=%s path=%s message=%s',
                $httpMethod,
                $normalizedPath,
                $response->get_error_message()
            ));
            return null;
        }

        lutwiyo_forward_bridge_set_cookie_headers($response);

        $decodedBody = lutwiyo_decode_bridge_json_body($response, $httpMethod, $normalizedPath);
        if ($decodedBody === null) {
            return null;
        }

        return [
            'status' => (int) wp_remote_retrieve_response_code($response),
            'body' => $decodedBody,
        ];
    }
}

if (!function_exists('lutwiyo_call_bridge_api')) {
    /**
     * member-service配下のブリッジAPIを呼び出してJSON配列を返す。
     *
     * @param string $action
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}|null
     */
    function lutwiyo_call_bridge_api($action, $payload = [])
    {
        static $requestCache = [];

        $path = lutwiyo_resolve_bridge_api_path($action);
        if ($path === null) {
            return null;
        }

        // 共通パラメータをここで補完し、呼び出し側は処理名のみ指定できるようにする。
        if (!isset($payload['mode'])) {
            $payload['mode'] = 'basic';
        }
        if (!isset($payload['service_invalid'])) {
            $payload['service_invalid'] = 0;
        }

        $cacheKey = wp_json_encode([
            'action' => (string) $action,
            'payload' => $payload,
        ]);
        if (is_string($cacheKey) && array_key_exists($cacheKey, $requestCache)) {
            return $requestCache[$cacheKey];
        }

        $response = lutwiyo_call_member_service_api($path, 'POST', $payload);

        if (is_string($cacheKey)) {
            $requestCache[$cacheKey] = $response;
        }

        return $response;
    }
}

if (!function_exists('lutwiyo_call_bridge_api_uncached')) {
    /**
     * member-service配下のブリッジAPIを毎回実行してJSON配列を返す（リクエスト内キャッシュを使用しない）。
     *
     * @param string $action
     * @param array<string,mixed> $payload
     * @return array{status:int,body:array<string,mixed>}|null
     */
    function lutwiyo_call_bridge_api_uncached($action, $payload = [])
    {
        $path = lutwiyo_resolve_bridge_api_path($action);
        if ($path === null) {
            return null;
        }

        if (!isset($payload['mode'])) {
            $payload['mode'] = 'basic';
        }
        if (!isset($payload['service_invalid'])) {
            $payload['service_invalid'] = 0;
        }

        return lutwiyo_call_member_service_api($path, 'POST', $payload);
    }
}
