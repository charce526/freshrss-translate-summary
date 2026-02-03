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
            $baseUrl = trim((string) Minz_Request::param('api_base_url', ''));
            $apiKey = trim((string) Minz_Request::param('api_key', ''));
            $model = trim((string) Minz_Request::param('model', ''));
            $translatePrompt = trim((string) Minz_Request::param('translate_prompt', ''));
            $summaryPrompt = trim((string) Minz_Request::param('summary_prompt', ''));

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

    private function getEntryContent(FreshRSS_Entry $entry): ?string {
        if (method_exists($entry, 'content')) {
            return (string) $entry->content();
        }

        if (method_exists($entry, '_content')) {
            return (string) $entry->_content();
        }

        return null;
    }

    private function setEntryContent(FreshRSS_Entry $entry, string $content): void {
        if (method_exists($entry, '_content')) {
            $entry->_content($content);
            return;
        }

        if (method_exists($entry, 'setContent')) {
            $entry->setContent($content);
            return;
        }

        if (property_exists($entry, 'content')) {
            $entry->content = $content;
        }
    }

    private function setFlashNotice(string $message): void {
        if (class_exists('Minz_Session') && method_exists('Minz_Session', 'setFlashNotice')) {
            Minz_Session::setFlashNotice($message);
            return;
        }

        if (class_exists('Minz_Session') && method_exists('Minz_Session', 'set')) {
            Minz_Session::set('notice', $message);
            return;
        }

        if (function_exists('Minz_Request::param')) {
            // No-op fallback for older versions without flash support.
        }
    }

    private function getCsrfToken(): string {
        if (class_exists('FreshRSS_Context') && method_exists('FreshRSS_Context', 'csrf')) {
            return (string) FreshRSS_Context::csrf();
        }

        if (class_exists('Minz_Session') && method_exists('Minz_Session', 'param')) {
            return (string) Minz_Session::param('csrf');
        }

        if (class_exists('Minz_Session') && method_exists('Minz_Session', 'get')) {
            return (string) Minz_Session::get('csrf');
        }

        return '';
    }

    public function getConfigValue(string $key, string $default = ''): string {
        if (method_exists($this, 'getUserConfigurationValue')) {
            $value = (string) $this->getUserConfigurationValue($key);
            return $value !== '' ? $value : $default;
        }

        if (method_exists($this, 'getUserConfiguration')) {
            $params = $this->getMethodParamCount('getUserConfiguration');
            if ($params === 0) {
                $all = $this->getUserConfiguration();
                if (is_array($all) && array_key_exists($key, $all)) {
                    $value = (string) $all[$key];
                    return $value !== '' ? $value : $default;
                }
            } else {
                $value = (string) $this->getUserConfiguration($key);
                return $value !== '' ? $value : $default;
            }
        }

        if (method_exists($this, 'getConfigurationValue')) {
            $value = (string) $this->getConfigurationValue($key);
            return $value !== '' ? $value : $default;
        }

        return $default;
    }

    public function setConfigValues(array $values): void {
        if (method_exists($this, 'setUserConfigurationValue')) {
            foreach ($values as $key => $value) {
                $this->setUserConfigurationValue($key, (string) $value);
            }
            return;
        }

        if (method_exists($this, 'setUserConfigurationValues')) {
            $this->setUserConfigurationValues($values);
            return;
        }

        if (method_exists($this, 'setUserConfiguration')) {
            $params = $this->getMethodParamCount('setUserConfiguration');
            if ($params <= 1) {
                $this->setUserConfiguration($values);
            } else {
                foreach ($values as $key => $value) {
                    $this->setUserConfiguration($key, (string) $value);
                }
            }
            return;
        }

        if (method_exists($this, 'setConfigurationValue')) {
            foreach ($values as $key => $value) {
                $this->setConfigurationValue($key, (string) $value);
            }
        }
    }

    private function getMethodParamCount(string $method): int {
        try {
            $reflection = new ReflectionMethod($this, $method);
            return $reflection->getNumberOfParameters();
        } catch (ReflectionException $exception) {
            return 0;
        }
    }
}
