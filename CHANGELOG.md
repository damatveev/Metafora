# Changelog

All notable changes to the Metafora OJS plugin are documented here.

## [0.4.0.2] - 2026-09-14

### Added

- GNU GPL v3.0-or-later license text.
- Plugin author name and contact address.
- Boosty donation link and QR code in the OJS administration interface and README.

## [0.4.0.1] - 2026-09-14

### Added

- Synchronization of article and issue state with Metafora.
- Remote signature state and sign/unsign actions.
- Recovery workflow for re-uploading publications deleted in Metafora.
- Persistent remote-state storage.
- Batch synchronization for selected articles and issues.
- Full-width administration interface with remote/local status details.
- Release packaging and publication workflow.
- README, operator guide, security policy, and release documentation.

### Changed

- Reduced the initial article page size to 25.
- Updated Russian and English interface messages.
- Refined API status handling and recoverable duplicate uploads.

### Maintenance

- Removed tracked backup, failed-edit, operating-system metadata, and archive-extraction leftovers.
- Added ignore and archive rules so generated and temporary files cannot enter future releases.

## [0.3.2.0] - 2026-09-08

- Added persistent export status display and administrative article columns.

## [0.3.1] - 2026-09-07

- Added database-backed export history with legacy settings migration.

## [0.2.0] - 2026-09-05

- Added OJS 3.5 plugin UI, per-journal settings, metadata mapping, connection testing, and initial export support.
