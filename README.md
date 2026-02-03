# FreshRSS Article Translation & Summary Extension

Translate and summarize FreshRSS entry content into Chinese using an OpenAI-compatible API.

## Install

1. Copy this folder to your FreshRSS extensions directory:
   `/var/www/FreshRSS/extensions/freshrss-translate-summary`
2. In FreshRSS, go to **Settings -> Extensions** and enable **freshrss-translate-summary**.

## Configure

1. Go to **Settings -> Extensions**.
2. Click **Configure** on **freshrss-translate-summary**.
3. Fill in:
   - **API Base URL** (example: `https://api.openai.com/v1`)
   - **API Key**
   - **Model Name** (example: `gpt-4o`)
   - **Translate Prompt** (system prompt)
   - **Summary Prompt** (system prompt)
4. Save.

## Usage

Open an article and click **Translate** or **Summary** below the title. The translated HTML or summary is inserted into the article view.

## Notes

- Translation and summary happen only when you click the button.

## Credits

Inspired by [xExtension-ArticleSummary](https://github.com/LiangWei88/xExtension-ArticleSummary)
