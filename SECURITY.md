# Security policy

## Supported version

Security updates are applied to the latest published release.

## Reporting a vulnerability

Do not publish API keys, credentials, private article files, or exploitable details in a public issue. Contact the repository owner privately through GitHub and include the affected version, impact, and reproducible steps with secrets removed.

## Deployment guidance

- Store the Metafora API key only in OJS plugin settings.
- Restrict access to OJS administration and server configuration.
- Back up the OJS database and plugin directory before upgrades.
- Install only ZIP assets from releases published by this repository.
- Verify the release tag and the version in `version.xml`.
