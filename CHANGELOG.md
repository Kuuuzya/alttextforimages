# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.5.0] - 2026-03-30
### Changed
- Плагин получил крутое название — **AltTextGPT**!
- Версия обновлена до 1.5.0 в преддверии новых релизов.

## [1.4.1] - 2026-03-30
### Added
- Значительное улучшение админ-интерфейса: добавлена автозагрузка и автообновление статистики базы медиафайлов.
- Улучшены описания настроек и подсказки, добавлена прямая ссылка на получение ключа OpenAI.

### Changed
- Отключена генерация альтов для черновиков (экономия токенов).
- Добавлена мгновенная асинхронная генерация альтов сразу после публикации статьи с использованием её финального заголовка.

## [1.4.0] - 2026-03-30
### Added
- Оформлен репозиторий для GitHub (добавлены файлы README, LICENSE, .gitignore)
- Перенос статических файлов `css` и `js` в отдельную директорию `assets/`

## [1.3.0] - 2024-XX-XX
### Initial
- Базовый функционал генерации Alt-текстов, Title и Description для изображений на лету
- Использование API OpenAI Vision
- Поддержка настройки промпта и выбор модели GPT (gpt-4o, gpt-4o-mini, gpt-4-turbo)
- Пакетная и одиночная генерация в Media Library
