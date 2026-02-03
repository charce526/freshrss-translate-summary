<?php

declare(strict_types=1);

final class FreshExtension_TranslateSummary_Controller extends FreshRSS_ActionController {
    public function translateAction(): void {
        $this->view = null;

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
        $content = isset($payload['content_html']) ? trim((string) $payload['content_html']) : '';
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
        $this->view = null;

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
        $content = isset($payload['content_html']) ? trim((string) $payload['content_html']) : '';
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

    private function getRequestPayload(): array {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            return $decoded;
        }

        return [
            'content_html' => Minz_Request::param('content_html', ''),
        ];
    }

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
            CURLOPT_POSTFIELDS => json_encode($body),
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

        if (isset($decoded['error']['message'])) {
            $message = (string) $decoded['error']['message'];
            return ['ok' => false, 'error' => $message, 'status' => $statusCode > 0 ? $statusCode : 502];
        }

        $translated = $decoded['choices'][0]['message']['content'] ?? '';
        if (!is_string($translated) || trim($translated) === '') {
            return ['ok' => false, 'error' => 'Empty translation response.', 'status' => 502];
        }

        return ['ok' => true, 'translated_html' => $translated];
    }

    private function sendJson(array $payload, int $status = 200): void {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload);
    }
}
