# Metafora Export Plugin for OJS 3.5

An Import/Export plugin for OJS 3.5.0.5+ that exports published articles and issues to the Metafora API in JATS XML, optionally with publication PDFs.

Current release: **0.4.0.1**

## Features

- Per-journal Metafora API URL and API key settings.
- Export of selected published articles and complete issues.
- JATS XML generation and local schema validation.
- Optional PDF attachment.
- Export history and remote publication status.
- Synchronization with Metafora, signature state, and recovery after a remote publication is deleted.
- English and Russian interface.
- Command-line support required by the OJS Import/Export plugin contract.

## Requirements

- OJS 3.5.0.5 or newer.
- PHP 8.2 or 8.3 with DOM/XML and cURL support.
- Database permissions required by OJS to create the plugin history/state tables.
- A Metafora API v2 URL and API key.

## Installation

1. Download the `metafora-ojs35.zip` asset from the matching GitHub release.
2. Back up the OJS database and any existing `plugins/importexport/metafora/` directory.
3. Extract the archive so the plugin is located at `plugins/importexport/metafora/`.
4. In OJS, open **Tools → Import/Export → Metafora Export Plugin**.
5. Enter the API URL and API key for the journal, save, and test the connection.

For upgrades, rollback instructions, and a first-run checklist, see [Installation](docs/installation.md) and [Deployment](docs/deployment.md).

## Basic use

Open the plugin under OJS Import/Export tools, select articles or issues, and send them to Metafora. The table shows local export history and the latest synchronized remote state. Use **Synchronize** before signing, unsigning, or restoring a deleted remote publication.

Russian operator guide: [Руководство пользователя](docs/user-guide.ru.md).

## Security

Never commit API keys. Store them only in the journal-specific OJS plugin settings. Review [SECURITY.md](SECURITY.md) before reporting a vulnerability.

## Development

Every push and pull request runs PHP syntax, XML metadata, and secret checks. Tags matching `v*` build a clean `metafora-ojs35.zip` package and publish it as a GitHub release.

## Versioning

The plugin uses four-part OJS release numbers in `version.xml`. Git tags use the same value prefixed with `v`, for example `v0.4.0.1`.

## Author

Dmitry Matveev (Дмитрий Матвеев).
