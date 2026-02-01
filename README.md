# FreshRSS Article Translation & Summary Extension

Translate and summarize FreshRSS entry content into Chinese using an OpenAI-compatible API.

## Install

1. Copy this folder to your FreshRSS extensions directory:
   `/var/www/FreshRSS/extensions/freshrss-translate-cn` (or your preferred folder name).
2. In FreshRSS, go to **Settings -> Extensions** and enable **freshrss-translate-cn**.

## Configure

1. Go to **Settings -> Extensions**.
2. Click **Configure** on **freshrss-translate-cn**.
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
- If you still see "No content to translate", please tell me your FreshRSS theme name and a screenshot of the entry HTML structure.
- If you see a 403/CSRF error, hard refresh the page to ensure the latest JS is loaded.
