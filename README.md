# Metafora Export Plugin for OJS 3.5

Универсальный модуль экспорта публикаций из Open Journal Systems 3.5 в ИС «Метафора».

## Автор

Dmitry Matveev (Дмитрий Матвеев)

## Совместимость

- OJS 3.5.0.5+
- Категория плагина: Import/Export (`plugins.importexport`)
- Многожурнальная установка: настройки хранятся отдельно для каждого `context_id`

## Планируемые режимы экспорта

- Metafora API
- Journal XML
- JATS XML
- Science Space XML
- XSD-валидация XML перед отправкой

## Документация ИС «Метафора»

- https://metafora.rcsi.science/documentation
- https://metafora.rcsi.science/api_doc
- https://metafora.rcsi.science/files
- https://metafora.rcsi.science/publications

XML-схемы:

- https://metafora.rcsi.science/xsd_files/journal3.xsd
- https://metafora.rcsi.science/xsd_files/science_space_articles.xsd
- https://metafora.rcsi.science/xsd_files/JATS-archive-oasis-article1-4.zip

## Безопасность

API URL и API token не хранятся в исходном коде или репозитории. Они задаются администратором журнала в настройках плагина.

## Разработка

Рабочая ветка: `develop`.

Архитектурные образцы: стандартные плагины OJS 3.5 `pubmed`, `doaj`, `crossref`, `rsciexport` из ветки `stable-3_5_0` репозитория PKP OJS.
