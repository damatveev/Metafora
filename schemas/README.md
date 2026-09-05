# Bundled Metafora XML schemas

Place only official Metafora schemas in this directory.

Expected paths:

- `journal3.xsd` — official Journal XML schema
- `science_space_articles.xsd` — official Science Space XML schema
- `jats/` — unpacked official JATS Archive 1.4 schema set

The plugin will refuse schema-validated XML export when the required schema is missing or unreadable.

Official sources are documented in `docs/api/xsd/README.md`.

Do not replace the upstream XSD files with locally reconstructed schemas.
