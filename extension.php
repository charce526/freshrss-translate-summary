<?php

declare(strict_types=1);

final class TranslateSummaryExtension extends Minz_Extension {
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    private const DEFAULT_MODEL = 'gpt-3.5-turbo';
    private const DEFAULT_REQUEST_TIMEOUT = 180;
    private const DEFAULT_CONNECT_TIMEOUT = 30;
    private const DEFAULT_TRANSLATE_PROMPT = '请将以下内容翻译为中文，并尽可能保留原有 HTML 结构。';
    private const DEFAULT_SUMMARY_PROMPT = '请使用中文简明总结以下内容，提炼关键功能、新增内容、修复问题和重要变更。仅返回可直接插入网页的 HTML 片段，不要使用 Markdown，不要输出代码围栏、星号粗体、井号标题或 Markdown 列表标记；不要输出 html、head、body 标签。使用 p、h3、ul、li、strong、code 等 HTML 标签组织内容，只输出最终摘要，不要解释输出格式。';

    public function init(): void {
        $this->registerHook('entry_before_display', [$this, 'injectTranslateUi']);
        $this->registerHook('js_vars', [$this, 'injectJsVars']);
        $this->registerController('TranslateSummary');

        Minz_View::appendScript($this->getFileUrl('translate.js'));
        Minz_View::appendStyle($this->getFileUrl('translate.css'));
    }

    public function handleConfigureAction(): void {
        if (!Minz_Request::isPost()) {
            return;
        }

        $requestTimeout = $this->normalizeTimeout(
            trim(Minz_Request::paramString('request_timeout', true)),
            self::DEFAULT_REQUEST_TIMEOUT,
            10,
            600
        );
        $connectTimeout = $this->normalizeTimeout(
            trim(Minz_Request::paramString('connect_timeout', true)),
            self::DEFAULT_CONNECT_TIMEOUT,
            1,
            120
        );

        $config = [
            'api_base_url' => trim(Minz_Request::paramString('api_base_url', true)),
            'api_key' => trim(Minz_Request::paramString('api_key', true)),
            'model' => trim(Minz_Request::paramString('model', true)),
            'request_timeout' => (string)$requestTimeout,
            'connect_timeout' => (string)$connectTimeout,
            'translate_prompt' => trim(Minz_Request::paramString('translate_prompt', true)),
            'summary_prompt' => trim(Minz_Request::paramString('summary_prompt', true)),
        ];

        $this->setConfigValues($config);
        Minz_Session::_param('notice', '翻译与摘要设置已保存。');
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
            'csrf' => FreshRSS_Auth::csrfToken(),
        ];

        return $vars;
    }

    public function getBaseUrl(): string {
        return rtrim($this->getConfigValue('api_base_url', self::DEFAULT_BASE_URL), '/');
    }

    public function getApiKey(): string {
        return $this->getConfigValue('api_key');
    }

    public function getModel(): string {
        return $this->getConfigValue('model', self::DEFAULT_MODEL);
    }

    public function getRequestTimeout(): int {
        return $this->normalizeTimeout(
            $this->getConfigValue('request_timeout', (string)self::DEFAULT_REQUEST_TIMEOUT),
            self::DEFAULT_REQUEST_TIMEOUT,
            10,
            600
        );
    }

    public function getConnectTimeout(): int {
        return $this->normalizeTimeout(
            $this->getConfigValue('connect_timeout', (string)self::DEFAULT_CONNECT_TIMEOUT),
            self::DEFAULT_CONNECT_TIMEOUT,
            1,
            120
        );
    }

    public function getTranslatePrompt(): string {
        return $this->getConfigValue('translate_prompt', self::DEFAULT_TRANSLATE_PROMPT);
    }

    public function getSummaryPrompt(): string {
        return $this->getConfigValue('summary_prompt', self::DEFAULT_SUMMARY_PROMPT);
    }

    public function getConfigValue(string $key, string $default = ''): string {
        $value = $this->getUserConfigurationValue($key, $default);
        if (is_string($value) || is_int($value) || is_bool($value)) {
            $valueString = (string)$value;
            return $valueString !== '' ? $valueString : $default;
        }

        return $default;
    }

    /** @param array<string,mixed> $values */
    public function setConfigValues(array $values): void {
        $current = $this->getUserConfiguration();
        foreach ($values as $key => $value) {
            if (is_string($value) || is_int($value) || is_bool($value)) {
                $current[$key] = (string)$value;
            }
        }

        $this->setUserConfiguration($current);
    }

    private function normalizeTimeout(string $value, int $default, int $min, int $max): int {
        if ($value === '' || !ctype_digit($value)) {
            return $default;
        }

        return max($min, min($max, (int)$value));
    }
}
