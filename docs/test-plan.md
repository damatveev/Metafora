# Metafora Export Plugin — test plan

Target: OJS 3.5.0.5+.

## Static checks

- PHP syntax on PHP 8.2 and 8.3.
- `version.xml` must parse as XML.
- Repository scan must not find hard-coded API bearer tokens.

## Installation smoke test

1. Install/copy the plugin to `plugins/importexport/metafora`.
2. Confirm that OJS lists it under Import/Export Plugins.
3. Open plugin settings for a journal.
4. Save API URL/token and verify settings remain isolated by `context_id`.
5. Run the connection test with a configured test endpoint.

## Export smoke test

1. Open the Metafora export page.
2. Select a publication belonging to the current journal.
3. Export JSON.
4. Confirm title, abstract, authors, DOI, issue, journal data, references and galleys are present.
5. Confirm export is rejected if required structural metadata is missing.
6. Confirm a publication from another context cannot be exported.

## XML tests

Journal/JATS/Science Space serializers must not be enabled until their generated XML validates against the corresponding official schema.

Official schema sources are listed in `docs/api/sources.md`.
