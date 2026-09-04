# Архитектура Metafora Export Plugin

Автор: Dmitry Matveev (Дмитрий Матвеев)

Целевая платформа: OJS 3.5.0.5+.

Категория: Модули импорта/экспорта (`plugins.importexport`).

Плагин должен быть универсальным для разных журналов OJS. Все параметры подключения и экспорта задаются на уровне журнала через настройки плагина (`context_id`).

Основной поток данных:

OJS Publication -> Metadata Mapper -> Metafora Data Model -> Export Adapter -> Metafora API/XML.

Поддерживаемые направления: API, Journal XML, JATS XML, Science Space XML. XML перед отправкой валидируется по соответствующей XSD-схеме.

В качестве эталонов реализации используются стандартные OJS 3.5 плагины PubMed, DOAJ, Crossref и RSCI Export.
