# Installation and upgrade

## Requirements

- OJS 3.5.0.5 or newer.
- PHP 8.2 or 8.3 with DOM/XML and cURL support.
- A Metafora API v2 URL and API key.
- Database permissions normally required by OJS plugin upgrades.

## Install from a release

1. Download `metafora-ojs35.zip` from the matching GitHub release.
2. Back up the OJS database.
3. If an older plugin copy exists, back up `plugins/importexport/metafora/`.
4. Extract the ZIP into `plugins/importexport/`. The resulting path must be `plugins/importexport/metafora/`.
5. Verify that `index.php`, `MetaforaExportPlugin.php`, and `version.xml` are directly inside that directory.
6. Open **Tools → Import/Export → Metafora Export Plugin** in OJS.
7. Configure the API URL and API key for the current journal.
8. Save and run the connection test.
9. Send one test article and synchronize its remote state before a bulk export.

The plugin is an Import/Export plugin, not a Generic plugin. Remove or disable obsolete experimental copies such as `plugins/generic/metaforaExport/`.

## Upgrade

Back up the database and plugin directory, then replace the plugin files with the new release. Do not copy temporary `.bak`, `.before*`, `.broken*`, `.failed*`, or `.error` files into the new installation.

Version 0.4.0.1 uses database-backed export history and remote-state records. Existing legacy per-journal export history is imported when appropriate. Keep these tables during an ordinary code rollback so the audit history is preserved.

## Security

Never commit or package an API key. Enter it only through journal-specific OJS settings. A release archive must be built by the repository workflow or from a clean tagged checkout.
