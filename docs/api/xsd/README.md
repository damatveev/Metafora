# Metafora XML schemas

This directory is reserved for the official XML schemas used by the Metafora Export Plugin.

Authoritative upstream sources:

- Journal: https://metafora.rcsi.science/xsd_files/journal3.xsd
- Science Space: https://metafora.rcsi.science/xsd_files/science_space_articles.xsd
- JATS Archive 1.4: https://metafora.rcsi.science/xsd_files/JATS-archive-oasis-article1-4.zip

The plugin must not guess or recreate these schemas. Production XML export must be validated against an official local schema copy before submission to Metafora.

Do not store credentials or API tokens in this directory.
