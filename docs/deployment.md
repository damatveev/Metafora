# Deployment and rollback

Target: OJS 3.5.0.5+.

## Release package

A tag matching `v*` starts the GitHub Actions release workflow. It checks PHP syntax, parses `version.xml`, verifies that the tag equals the declared plugin version, scans for common committed secret patterns, and builds `metafora-ojs35.zip`.

The archive contains one top-level `metafora/` directory and excludes Git metadata, workflow files, development documentation, and temporary/backup files.

## Production deployment checklist

1. Confirm that the release tag, changelog version, and `version.xml` match.
2. Back up the OJS database and the current plugin directory outside the web root.
3. Extract the release package into `plugins/importexport/`.
4. Preserve ownership and permissions used by the surrounding OJS installation.
5. Open the plugin page to initialize or verify its database tables.
6. Test the configured API connection.
7. Export and synchronize one test article.
8. Verify article, issue, signature, and recovery controls before bulk use.
9. Retain the previous release package and database backup until validation is complete.

## Rollback

Restore both the previous plugin directory and the matching database backup. Do not delete export-history or remote-state tables during a normal code rollback unless a reviewed database migration explicitly requires it.

## Server repository hygiene

A production checkout should contain no stash, backup branches, or temporary edit files. Keep operational backups outside both the plugin directory and its Git repository. After deployment, `git status --short` should be empty and the checked-out commit should match the release tag.
