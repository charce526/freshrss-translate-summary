<?php

declare(strict_types=1);

final class TranslateSummaryExtension extends Minz_Extension {
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    private const DEFAULT_MODEL = 'gpt-3.5-turbo';
    private const DEFAULT_TRANSLATE_PROMPT = 'Translate the following text into Chinese, maintaining the original HTML structure where possible.';
    private const DEFAULT_SUMMARY_PROMPT = 'Summarize the following text in Chinese with key points, keeping it concise.';

    public function init(): void {
        $this->registerHook('entry_before_display', [$this, 'injectTranslateUi']);
        $this->registerHook('js_vars', [$this, 'injectJsVars']);
        $this->registerController('TranslateSummary');

        Minz_View::appendScript($this->getFileUrl('translate.js'));
        Minz_View::appendStyle($this->getFileUrl('translate.css'));
    }

    public function handleConfigureAction(): void {
        if (Minz_Request::isPost()) {
            $baseUrl = trim(Minz_Request::paramString('api_base_url', true));
            $apiKey = trim(Minz_Request::paramString('api_key', true));
            $model = trim(Minz_Request::paramString('model', true));
            $translatePrompt = trim(Minz_Request::paramString('translate_prompt', true));
            $summaryPrompt = trim(Minz_Request::paramString('summary_prompt', true));

            $config = [
                'api_base_url' => $baseUrl,
                'api_key' => $apiKey,
                'model' => $model,
                'translate_prompt' => $translatePrompt,
                'summary_prompt' => $summaryPrompt,
            ];

            $this->setConfigValues($config);

            $this->setFlashNotice('Translation settings saved.');
        }
    }

    public function injectTranslateUi(FreshRSS_Entry $entry): FreshRSS_Entry {
        return $entry;
    }

    /**
     * @param array<string,mixed> $vars
     * @return array<string,mixed>
     */
    public function injectJsVars(array $vars): array {
        $vars['translateCn'] = [
            'endpoint' => '?c=TranslateSummary&a=translate',
            'csrf' => $this->getCsrfToken(),
        ];

        return $vars;
    }

    public function getBaseUrl(): string {
        $baseUrl = $this->getConfigValue('api_base_url');
        if ($baseUrl === '') {
            $baseUrl = self::DEFAULT_BASE_URL;
        }

        return rtrim($baseUrl, '/');
    }

    public function getApiKey(): string {
        return $this->getConfigValue('api_key');
    }

    public function getModel(): string {
        $model = $this->getConfigValue('model');
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }

        return $model;
    }

    public function getTranslatePrompt(): string {
        $prompt = $this->getConfigValue('translate_prompt');
        if ($prompt === '') {
            $prompt = self::DEFAULT_TRANSLATE_PROMPT;
        }

        return $prompt;
    }

    public function getSummaryPrompt(): string {
        $prompt = $this->getConfigValue('summary_prompt');
        if ($prompt === '') {
            $prompt = self::DEFAULT_SUMMARY_PROMPT;
        }

        return $prompt;
    }

    private function setFlashNotice(string $message): void {
        Minz_Session::_param('notice', $message);
    }

    private function getCsrfToken(): string {
        if (!class_exists('FreshRSS_Context', false) || !FreshRSS_Context::hasSystemConf()) {
            return '';
        }

        $token = FreshRSS_Context::systemConf()->token;
        if (is_string($token)) {
            return $token;
        }
        if (is_int($token) || is_float($token) || is_bool($token)) {
            return (string) $token;
        }

        return '';
    }

    public function getConfigValue(string $key, string $default = ''): string {
        $value = $this->getUserConfigurationValue($key, $default);
        if (is_string($value) || is_int($value) || is_bool($value)) {
            $valueString = (string) $value;
            return $valueString !== '' ? $valueString : $default;
        }
        return $default;
    }

    /** @param array<string,mixed> $values */
    public function setConfigValues(array $values): void {
        $current = $this->getUserConfiguration();
        foreach ($values as $key => $value) {
            if (is_string($value) || is_int($value) || is_bool($value)) {
                $current[$key] = (string) $value;
            }
        }
        $this->setUserConfiguration($current);
    }
}
