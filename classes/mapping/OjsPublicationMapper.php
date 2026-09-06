<?php

/**
 * @file plugins/importexport/metafora/classes/mapping/OjsPublicationMapper.php
 *
 * Maps OJS 3.5 publication data to an internal Metafora publication model.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\mapping;

use APP\facades\Repo;
use APP\plugins\importexport\metafora\classes\model\MetaforaPublication;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\facades\Locale;
use Stringable;

class OjsPublicationMapper
{
    public function map(Submission $submission, Context $context): MetaforaPublication
    {
        $publication = $submission->getCurrentPublication();

        if (!$publication) {
            throw new \RuntimeException(
                sprintf('Submission %d has no current publication.', $submission->getId())
            );
        }

        $issueData = [];
        $issueId = $publication->getData('issueId');

        if ($issueId) {
            $issue = Repo::issue()->get((int) $issueId);

            if ($issue) {
                $issueData = [
                    'id' => $issue->getId(),
                    'volume' => $issue->getData('volume'),
                    'number' => $issue->getData('number'),
                    'year' => $issue->getData('year'),
                    'title' => $issue->getData('title'),
                    'datePublished' => $issue->getData('datePublished'),
                ];
            }
        }

        $authors = [];

        $authorCollection = $publication->getData('authors');

        if ($authorCollection) {
            foreach ($authorCollection as $author) {
                $affiliations = $this->mapAffiliations($author);
                $authors[] = [
                    'givenName' => $author->getData('givenName'),
                    'familyName' => $author->getData('familyName'),
                    'preferredPublicName' => $author->getData('preferredPublicName'),
                    'affiliation' => $affiliations[0]['organization'] ?? $author->getData('affiliation'),
                    'affiliations' => $affiliations,
                    'country' => $author->getData('country'),
                    'orcid' => $author->getData('orcid'),
                ];
            }
        }

        $journal = [
            'id' => $context->getId(),
            'name' => $context->getData('name'),
            'acronym' => $context->getData('acronym'),
            'printIssn' => $context->getData('printIssn'),
            'onlineIssn' => $context->getData('onlineIssn'),
            'urlPath' => $context->getData('urlPath'),
            'primaryLocale' => $context->getData('primaryLocale'),
        ];

        $galleys = $publication->getData('galleys');

        if (is_object($galleys) && method_exists($galleys, 'all')) {
            $galleys = $galleys->all();
        }

        if (!is_array($galleys)) {
            $galleys = [];
        }

        return new MetaforaPublication(
            submissionId: $submission->getId(),
            title: $this->mapLocalizedField($publication, 'title'),
            abstract: $this->mapLocalizedField($publication, 'abstract'),
            authors: $authors,
            keywords: $this->mapLocalizedField($publication, 'keywords'),
            doi: $publication->getDoi(),
            issue: $issueData,
            journal: $journal,
            files: $this->mapFiles($galleys),
            references: $this->mapReferences($publication->getData('citationsRaw')),
            metadata: $this->mapMetadata($submission, $publication, $context),
        );
    }

    private function mapLocalizedField($publication, string $field): array
    {
        $result = [];
        $locales = [];

        $primaryLocale = $publication->getData('locale');

        if (is_string($primaryLocale) && $primaryLocale !== '') {
            $locales[] = $primaryLocale;
        }

        $locales = array_unique(array_merge($locales, ['en', 'ru']));

        foreach ($locales as $locale) {
            $value = $publication->getData($field, $locale);

            if (is_string($value) && trim($value) !== '') {
                $result[$locale] = $value;
            } elseif (is_array($value) && $value !== []) {
                $result[$locale] = $value;
            }
        }

        return $result;
    }

    private function mapFiles(array $galleys): array
    {
        $files = [];

        foreach ($galleys as $galley) {
            $submissionFileId = (int) $galley->getData('submissionFileId');

            $submissionFile = $submissionFileId
                ? Repo::submissionFile()->get($submissionFileId)
                : null;

            $remoteUrl = $galley->getData('urlRemote');

            if (!$submissionFileId && !$remoteUrl) {
                continue;
            }

            $files[] = [
                'galleyId' => $galley->getId(),
                'label' => $galley->getLabel(),
                'locale' => $galley->getData('locale'),
                'remoteUrl' => $remoteUrl,
                'doi' => $galley->getDoi(),
                'submissionFileId' => $submissionFileId ?: null,
                'fileId' => $submissionFile?->getData('fileId'),
                'name' => $submissionFile?->getData('name'),
                'mimetype' => $submissionFile?->getData('mimetype'),
                'fileStage' => $submissionFile?->getData('fileStage'),
            ];
        }

        return $files;
    }

    private function mapReferences(mixed $citationsRaw): array
    {
        if ($citationsRaw instanceof Stringable) {
            $citationsRaw = (string) $citationsRaw;
        }

        if (!is_string($citationsRaw) || trim($citationsRaw) === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (string $reference): string => trim($reference),
                    preg_split('/\R/u', $citationsRaw) ?: []
                )
            )
        );
    }

    private function mapMetadata(
        Submission $submission,
        $publication,
        Context $context
    ): array {
        return [
            'articleId' => $submission->getId(),
            'publicationId' => $publication->getId(),
            'language' => $publication->getData('locale'),
            'datePublished' => $publication->getData('datePublished'),
            'lastModified' => $publication->getData('lastModified'),
            'pages' => $this->mapPages($publication),
            'licenseUrl' => $publication->getData('licenseUrl'),
            'copyrightHolder' => $publication->getData('copyrightHolder'),
            'copyrightYear' => $publication->getData('copyrightYear'),
            'urlPath' => $publication->getData('urlPath'),
            'identifiers' => [
                'doi' => $publication->getDoi(),
            ],
            'type' => 'journalArticle',
            'status' => 'published',
            'journalId' => $context->getId(),
        ];
    }

    /**
     * OJS 3.5 stores affiliation names separately and keeps the author's
     * country as an ISO code, but has no dedicated city field. Preserve the
     * organization and extract location only from unambiguous suffixes.
     */
    private function mapAffiliations($author): array
    {
        $values = [];
        if (method_exists($author, 'getAffiliations')) {
            foreach ($author->getAffiliations() as $affiliation) {
                $name = method_exists($affiliation, 'getName')
                    ? $affiliation->getName()
                    : $affiliation->getData('name');
                if (is_array($name)) {
                    foreach ($name as $locale => $localizedName) {
                        if (is_string($localizedName) && trim($localizedName) !== '') {
                            $values[] = ['locale' => (string) $locale, 'name' => trim($localizedName)];
                        }
                    }
                } elseif (is_string($name) && trim($name) !== '') {
                    $values[] = ['locale' => null, 'name' => trim($name)];
                }
            }
        }

        if ($values === []) {
            $legacy = $author->getData('affiliation');
            if (is_array($legacy)) {
                foreach ($legacy as $locale => $localizedName) {
                    if (is_string($localizedName) && trim($localizedName) !== '') {
                        $values[] = ['locale' => (string) $locale, 'name' => trim($localizedName)];
                    }
                }
            } elseif (is_string($legacy) && trim($legacy) !== '') {
                $values[] = ['locale' => null, 'name' => trim($legacy)];
            }
        }

        $countryCode = strtoupper(trim((string) $author->getData('country')));
        $countryName = $this->countryName($countryCode);

        $result = [];
        foreach ($values as $value) {
            foreach (preg_split('/\s*;\s*/u', $value['name']) ?: [] as $part) {
                if (trim($part) === '') {
                    continue;
                }
                $mapped = $this->splitAffiliation(trim($part), $countryCode, $countryName);
                $mapped['locale'] = $value['locale'];
                $result[] = $mapped;
            }
        }
        return $result;
    }

    private function splitAffiliation(string $value, string $countryCode, string $countryName): array
    {
        $organization = trim($value);
        $city = '';
        $country = $countryName ?: $countryCode;

        if (preg_match('/^(.*?)\s*\(([^()]*)\)\s*$/u', $organization, $matches)) {
            $organization = trim($matches[1]);
            $location = array_values(array_filter(array_map('trim', explode(',', $matches[2]))));
            $city = $location[0] ?? '';
            $country = $location[1] ?? $country;
        } else {
            $parts = array_values(array_filter(array_map('trim', explode(',', $organization))));
            if (count($parts) >= 3) {
                $country = (string) array_pop($parts);
                $city = (string) array_pop($parts);
                $organization = implode(', ', $parts);
            } elseif (count($parts) === 2 && $countryCode !== '') {
                $city = (string) array_pop($parts);
                $organization = implode(', ', $parts);
            }
        }

        return [
            'organization' => $organization,
            'city' => $city,
            'country' => $country,
            'formatted' => $organization . ($city !== '' || $country !== ''
                ? ' (' . implode(', ', array_filter([$city, $country])) . ')'
                : ''),
        ];
    }

    private function countryName(string $countryCode): string
    {
        if ($countryCode === '') {
            return '';
        }
        foreach (Locale::getCountries() as $country) {
            if (strtoupper($country->getAlpha2()) === $countryCode) {
                return $country->getLocalName();
            }
        }
        return $countryCode;
    }

    private function mapPages($publication): ?string
    {
        $pages = trim((string) $publication->getData('pages'));
        if ($pages !== '') {
            return $pages;
        }

        $urlPath = trim((string) $publication->getData('urlPath'));
        if (preg_match('/^\d+\s*[-–—]\s*\d+$/u', $urlPath)) {
            return $urlPath;
        }

        $doi = trim((string) $publication->getDoi());
        if (preg_match('/(\d+[-–—]\d+)$/u', $doi, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
