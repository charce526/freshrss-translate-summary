<?php

declare(strict_types=1);

final class FreshExtension_TranslateSummary_Controller extends FreshRSS_ActionController {
    public function translateAction(): void {
        $extension = Minz_ExtensionManager::findExtension('freshrss-translate-summary');
        if (!$extension instanceof TranslateSummaryExtension) {
            $this->sendJson(['ok' => false, 'error' => 'Extension not available.'], 500);
            return;
        }

        $apiKey = $extension->getApiKey();
        if ($apiKey === '') {
            $this->sendJson(['ok' => false, 'error' => 'API key is not configured.'], 400);
            return;
        }

        $payload = $this->getRequestPayload();
        $content = $this->payloadString($payload, 'content_html');
        if ($content === '') {
            $this->sendJson(['ok' => false, 'error' => 'Content is empty.'], 400);
            return;
        }

        $result = $this->requestCompletion(
            $extension->getBaseUrl(),
            $apiKey,
            $extension->getModel(),
            $extension->getTranslatePrompt(),
            $content
        );

        if (!$result['ok']) {
            $this->sendJson(['ok' => false, 'error' => $result['error']], $result['status']);
            return;
        }

        $this->sendJson(['ok' => true, 'translated_html' => $result['translated_html']]);
    }

    public function summaryAction(): void {
        $extension = Minz_ExtensionManager::findExtension('freshrss-translate-summary');
        if (!$extension instanceof TranslateSummaryExtension) {
            $this->sendJson(['ok' => false, 'error' => 'Extension not available.'], 500);
            return;
        }

        $apiKey = $extension->getApiKey();
        if ($apiKey === '') {
            $this->sendJson(['ok' => false, 'error' => 'API key is not configured.'], 400);
            return;
        }

        $payload = $this->getRequestPayload();
        $content = $this->payloadString($payload, 'content_html');
        if ($content === '') {
            $this->sendJson(['ok' => false, 'error' => 'Content is empty.'], 400);
            return;
        }

        $result = $this->requestCompletion(
            $extension->getBaseUrl(),
            $apiKey,
            $extension->getModel(),
            $extension->getSummaryPrompt(),
            $content
        );

        if (!$result['ok']) {
            $this->sendJson(['ok' => false, 'error' => $result['error']], $result['status']);
            return;
        }

        $this->sendJson(['ok' => true, 'translated_html' => $result['translated_html']]);
    }

    /** @return array<string,mixed> */
    private function getRequestPayload(): array {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $payload = [];
            foreach ($decoded as $key => $value) {
                if (is_string($key)) {
                    $payload[$key] = $value;
                }
            }
            return $payload;
        }

        return [
            'content_html' => Minz_Request::paramString('content_html', true),
        ];
    }

    /**
     * @return array{ok:true,translated_html:string}|array{ok:false,error:string,status:int}
     */
    private function requestCompletion(string $baseUrl, string $apiKey, string $model, string $prompt, string $content): array {
        $endpoint = rtrim($baseUrl, '/') . '/chat/completions';
        $body = [
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $prompt,
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ];
        $bodyJson = json_encode($body);
        if (!is_string($bodyJson)) {
            return ['ok' => false, 'error' => 'Unable to encode request body.', 'status' => 500];
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Unable to initialize request.', 'status' => 500];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $bodyJson,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            $error = $curlError !== '' ? $curlError : 'Request failed.';
            return ['ok' => false, 'error' => $error, 'status' => 502];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Invalid API response.', 'status' => 502];
        }

        if (
            isset($decoded['error']) &&
            is_array($decoded['error']) &&
            is_string($decoded['error']['message'] ?? null)
        ) {
            $message = $decoded['error']['message'];
            return ['ok' => false, 'error' => $message, 'status' => $statusCode > 0 ? $statusCode : 502];
        }

        $translated = '';
        if (
            isset($decoded['choices']) &&
            is_array($decoded['choices']) &&
            isset($decoded['choices'][0]) &&
            is_array($decoded['choices'][0]) &&
            isset($decoded['choices'][0]['message']) &&
            is_array($decoded['choices'][0]['message']) &&
            is_string($decoded['choices'][0]['message']['content'] ?? null)
        ) {
            $translated = $decoded['choices'][0]['message']['content'];
        }
        if (trim($translated) === '') {
            return ['ok' => false, 'error' => 'Empty translation response.', 'status' => 502];
        }

        return ['ok' => true, 'translated_html' => $translated];
    }

    /** @param array<string,mixed> $payload */
    private function sendJson(array $payload, int $status = 200): void {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        $this->view->_layout(null);
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        $json = json_encode($payload);
        echo is_string($json) ? $json : '{"ok":false,"error":"Encoding failed."}';
        exit;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function payloadString(array $payload, string $key): string {
        $value = $payload[$key] ?? '';
        if (is_string($value) || is_int($value) || is_bool($value)) {
            return trim((string) $value);
        }
        return '';
    }
}
