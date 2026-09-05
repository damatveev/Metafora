# Metafora API client

Developer: Dmitry Matveev (Дмитрий Матвеев)

The plugin uses a dedicated API client isolated from OJS publication mapping and XML serializers.

Configuration is stored per OJS journal (`context_id`) and must be entered in the plugin settings. API credentials must never be committed to the repository.

## Verified Metafora API v2 contract

The official RCSI Metafora documentation (user instruction dated 28.10.2025, API section / Figure 42) confirms:

- Authentication header: `Api-Key`.
- API is used to upload XML files and publication PDF files.
- Supported XML formats are JATS and Journal.
- Verified file operations shown in the official API v2 interface:
  - `POST /files/jats/xml` — upload JATS XML.
  - `POST /files/jats/xml_pdf` — upload JATS XML with a PDF file.
  - `POST /files/journal` — upload Journal XML.
  - `POST /files/pdf` — attach/upload a PDF for an existing XML record.
  - `GET /files/status` — retrieve file processing status.

The plugin must not use `Authorization: Bearer ...`; Metafora requires `Api-Key: <secret>`.

## Base URL

`apiUrl` remains configurable per journal. The official Metafora API v2 interface is the source of truth for the production base URL. Do not hard-code credentials or environment-specific URLs into source code.

## Request bodies

Endpoint names and authentication are now verified. Multipart parameter names and response field semantics must be copied from the live OpenAPI v2 definition before implementing each upload method; they must not be guessed.

## Connection test

`MetaforaApiClient::testConnection()` performs a GET request against the configured URL (or a supplied relative endpoint) and returns HTTP status, headers, response body and decoded JSON when available. The API key is transmitted only in the `Api-Key` request header and is never returned by the plugin.

## Sources

- https://metafora.rcsi.science/api_doc
- https://metafora.rcsi.science/documentation
- Official user instruction for IS Metafora, version 28.10.2025.
- Official instruction for the Metafora export plugin, version 25.11.2025.
