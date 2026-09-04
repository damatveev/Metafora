# Metafora API client

Developer: Dmitry Matveev (Дмитрий Матвеев)

The plugin uses a dedicated API client isolated from OJS publication mapping and XML exporters.

Configuration is stored per OJS journal (`context_id`) and must be entered in the plugin settings. API credentials must never be committed to the repository.

## Current configuration

- `apiUrl` — base URL or exact test endpoint configured by the journal manager.
- `apiToken` — authentication token stored in OJS plugin settings.
- Authorization header — `Bearer <token>` by default.

## Endpoint policy

Concrete Metafora endpoint paths are not hard-coded until they are verified against the current official API reference at:

- https://metafora.rcsi.science/api_doc

This prevents the plugin from depending on guessed or obsolete endpoint paths.

## Connection test

`MetaforaApiClient::testConnection()` performs a GET request against the configured URL (or a supplied relative endpoint) and returns HTTP status, headers, response body and decoded JSON when available.

The settings UI will expose this method through a dedicated action once the exact test/authentication endpoint from the API specification is confirmed.
