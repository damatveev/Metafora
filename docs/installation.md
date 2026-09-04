# Installation and first test on OJS 3.5.0.5+

## Plugin location

The plugin must be deployed to:

`plugins/importexport/metafora/`

It is an Import/Export plugin (`plugins.importexport`), not a Generic plugin.

## First test scope

Version 0.2.0 is intended for installation and UI/metadata testing. The safe test scope is:

1. Plugin discovery by OJS 3.5.0.5+.
2. Per-journal settings (`context_id`).
3. API URL/token storage through plugin settings.
4. Configurable connection test.
5. Selection of publications in the Import/Export page.
6. OJS metadata mapping to the internal `MetaforaPublication` model.
7. Mapping of publication galleys/submission files.
8. Neutral JSON export for inspection.

## XML schemas

Production Journal/JATS/Science Space XML export must not be enabled until the official upstream schema files are placed under `plugins/importexport/metafora/schemas/` and the format-specific serializer is implemented against those exact schemas.

Expected files include:

- `schemas/journal3.xsd`
- `schemas/science_space_articles.xsd`
- unpacked JATS 1.4 schemas under `schemas/jats/`

The validation layer will reject XML when the required schema is missing or validation fails.

## Security

Never commit an API token to Git. Configure the token only in the journal-specific plugin settings. The connection test must not expose the token or response body.

## Existing obsolete test skeleton

If an old development skeleton exists under `plugins/generic/metaforaExport/`, do not use it as the active plugin. The maintained implementation is `plugins/importexport/metafora/`.

## Author

Dmitry Matveev (Дмитрий Матвеев)
