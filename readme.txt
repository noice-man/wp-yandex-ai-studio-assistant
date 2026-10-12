=== Yandex AI Studio Assistant ===
Contributors: valery-molodtsov
Tags: yandex, ai, gpt, assistant, content, seo
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Интеграция моделей Yandex AI Studio с админкой WordPress: анализ статей и генерация черновиков по подтверждению администратора.

== Description ==

Модель не может самостоятельно публиковать или удалять записи. Чат, анализ материалов, создание и корректировка черновиков, Yoast SEO метаданные и русская инструкция на ворд-пресс.рф.

== Installation ==

Загрузите ZIP через Плагины -> Добавить новый -> Загрузить. Откройте Yandex AI, введите Folder ID и API-ключ.

== Features ==

CPT и ACF-схемы (простые поля для черновиков), термины таксономий, предпросмотр контракта, выбор моделей с пояснениями и официальной ссылкой на тарифы. Сложные поля ACF только для чтения.

== Additional Resources ==

Plugin page: https://ворд-пресс.рф/plugins/yandex-ai-studio-assistant/
Setup guide: https://ворд-пресс.рф/solutions/yandex-ai-studio-wordpress-setup/
CPT and ACF guide: https://ворд-пресс.рф/solutions/yandex-ai-studio-wordpress-acf-cpt/
Source code: https://github.com/noice-man/wp-yandex-ai-studio-assistant

== Changelog ==
= 1.1.3 =
* WPR Updates Client v1.1.1: native auto-update toggle compatibility.

= 1.1.2 =
* Fix stable WordPress slug and WPR Update Hub package directory.

= 1.1.1 =
* Migrate plugin updates to WPR Update Hub v1.1.
* Add optional, consent-based anonymous installation statistics.


= 1.1.0 =
* Контракты CPT/ACF/таксономий/темы/зарегистрированных метаполей и расширяемые подсказки.
* Безопасная подготовка ACF и существующих терминов для черновиков.
* Список моделей с описаниями, тарифами и проверкой каталога через Models API.
* Защита от сохранения черновика, изменённого после предпросмотра.


= 1.0.0 =
* Первая версия: Chat Completions, защита API-ключа, чат, анализ, черновики и SEO-метаданные.
