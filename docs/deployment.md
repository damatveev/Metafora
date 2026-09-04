# Deployment to OJS 3.5

Target: OJS 3.5.0.5+.

## Package

The GitHub Actions workflow `Build plugin package` creates the artifact `metafora-ojs35.zip`. The archive contains one top-level directory:

`metafora/`

This directory is intended for deployment to:

`plugins/importexport/metafora/`

## First installation checklist

1. Back up the OJS database and plugin directory before deployment.
2. Remove or rename any obsolete preliminary copy under `plugins/generic/metaforaExport/` so it cannot be confused with the import/export plugin.
3. Deploy the `metafora` directory to `plugins/importexport/`.
4. In OJS Administration, open Import/Export Plugins and verify that `Metafora Export Plugin` is listed.
5. Open plugin settings for the current journal and configure API URL and API token. Secrets must only be entered through OJS settings and must never be committed to Git.
6. Save settings and verify that settings are isolated by journal `context_id`.
7. Run the connection test using the configured test endpoint.
8. Open the plugin export page, select one test publication and run JSON export.
9. Verify title, authors, DOI, issue, ISSN, references and file metadata in the generated package.
10. Do not enable production API submission until the official Metafora endpoint contract and XML schema mapping have been verified.

## Rollback

If the plugin causes an OJS error, remove or rename `plugins/importexport/metafora/` and restore the previous plugin directory from backup. The current plugin does not require a custom database table.

## XML formats

Journal XML, JATS and Science Space must not be treated as production-ready until generated XML passes validation against the official Metafora XSD resources.

Author: Dmitry Matveev (Дмитрий Матвеев)
