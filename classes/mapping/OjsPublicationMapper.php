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
use APP\plugins\importexport\metafora\classes\export\PdfReferenceExtractor;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\PKPApplication;
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
                    'givenName' => $this->normalizeLocalizedValue($author->getData('givenName'), $publication->getData('locale')),
                    'familyName' => $this->normalizeLocalizedValue($author->getData('familyName'), $publication->getData('locale')),
                    'preferredPublicName' => $this->normalizeLocalizedValue($author->getData('preferredPublicName'), $publication->getData('locale')),
                    'email' => $author->getData('email'),
                    'affiliation' => $affiliations[0]['organization'] ?? $author->getData('affiliation'),
                    'affiliations' => $affiliations,
                    'country' => $author->getData('country'),
                    'orcid' => $author->getData('orcid'),
                ];
            }
        }

        $journal = [
            'id' => $context->getId(),
            'name' => $this->normalizeLocalizedValue($context->getData('name'), $context->getData('primaryLocale')),
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
            keywords: $this->mapKeywords($publication),
            doi: $publication->getDoi(),
            issue: $issueData,
            journal: $journal,
            files: $this->mapFiles($galleys),
            references: $this->mapReferences(
                $publication->getData('citationsRaw'),
                $this->normalizeLocale((string) $publication->getData('locale')),
                $this->pdfPath($galleys)
            ),
            metadata: $this->mapMetadata($submission, $publication, $context, $authors),
        );
    }

    private function mapLocalizedField($publication, string $field): array
    {
        return $this->normalizeLocalizedValue($publication->getData($field), $publication->getData('locale'));
    }

    private function mapKeywords($publication): array
    {
        $result = [];
        foreach ((array) ($publication->getData('keywords') ?? []) as $locale => $items) {
            $names = [];
            foreach ((array) $items as $item) {
                $name = is_array($item) ? ($item['name'] ?? '') : $item;
                if (is_string($name) && trim($name) !== '') {
                    $names[] = trim($name);
                }
            }
            if ($names !== []) {
                $normalizedLocale = $this->contentLocale($names) ?: $this->normalizeLocale((string) $locale);
                $result[$normalizedLocale] = $names;
            }
        }
        return $result;
    }

    private function normalizeLocalizedValue(mixed $value, mixed $fallbackLocale): array
    {
        if (is_string($value)) {
            return trim($value) === '' ? [] : [($this->contentLocale($value) ?: $this->normalizeLocale((string) $fallbackLocale)) => $value];
        }
        if (!is_array($value)) return [];
        $result = [];
        foreach ($value as $locale => $localizedValue) {
            if ((is_string($localizedValue) && trim($localizedValue) !== '') || (is_array($localizedValue) && $localizedValue !== [])) {
                $normalizedLocale = $this->normalizeLocale((string) $locale);
                $contentLocale = $this->contentLocale($localizedValue);
                $result[$contentLocale ?: $normalizedLocale] = $localizedValue;
            }
        }
        return $result;
    }

    private function contentLocale(mixed $value): ?string
    {
        $text = is_array($value)
            ? implode(' ', array_filter($value, 'is_string'))
            : (is_string($value) ? $value : '');
        $text = strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $cyrillic = preg_match_all('/[А-ЯЁа-яё]/u', $text);
        $latin = preg_match_all('/[A-Za-z]/u', $text);
        if ($cyrillic >= 3 && $cyrillic > $latin) {
            return 'ru';
        }
        if ($latin >= 3 && $latin > $cyrillic) {
            return 'en';
        }
        return null;
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(str_replace('-', '_', trim($locale)));
        return str_starts_with($locale, 'ru') ? 'ru' : (str_starts_with($locale, 'en') ? 'en' : ($locale ?: 'en'));
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

    private function mapReferences(mixed $citationsRaw, string $locale, ?string $pdfPath): array
    {
        if ($citationsRaw instanceof Stringable) {
            $citationsRaw = (string) $citationsRaw;
        }

        if (!is_string($citationsRaw) || trim($citationsRaw) === '') {
            $references = [];
        } else {
            $references = array_values(array_filter(array_map(
                static fn (string $reference): string => trim($reference),
                preg_split('/\R/u', $citationsRaw) ?: []
            )));
        }
        $groups = $references === [] ? [] : [$locale => $references];
        if ($pdfPath !== null) {
            foreach ((new PdfReferenceExtractor())->extract($pdfPath) as $pdfLocale => $pdfReferences) {
                if (empty($groups[$pdfLocale])) {
                    $groups[$pdfLocale] = $pdfReferences;
                }
            }
        }
        if (count($groups) === 1) {
            $sourceLocale = (string) array_key_first($groups);
            $fallbackLocale = $sourceLocale === 'ru' ? 'en' : 'ru';
            $groups[$fallbackLocale] = reset($groups);
        }
        return $groups;
    }

    private function pdfPath(array $galleys): ?string
    {
        foreach ($galleys as $galley) {
            $submissionFileId = (int) $galley->getData('submissionFileId');
            $submissionFile = $submissionFileId ? Repo::submissionFile()->get($submissionFileId) : null;
            if (!$submissionFile) continue;
            $file = app()->get('file')->get($submissionFile->getData('fileId'));
            if (!$file || strtolower((string) ($file->mimetype ?? $submissionFile->getData('mimetype'))) !== 'application/pdf') continue;
            $path = rtrim((string) \PKP\config\Config::getVar('files', 'files_dir'), '/\\') . DIRECTORY_SEPARATOR . ltrim((string) $file->path, '/\\');
            if (is_file($path) && is_readable($path)) return $path;
        }
        return null;
    }

    private function mapMetadata(
        Submission $submission,
        $publication,
        Context $context,
        array $authors
    ): array {
        // Metafora expects the article authors in the rightsholder field.
        // Use OJS' copyright holder only when no author name is available.
        $copyrightHolders = $this->authorNamesByLocale($authors);
        if ($copyrightHolders === []) {
            $copyrightHolders = $this->normalizeLocalizedValue(
                $publication->getData('copyrightHolder'),
                $publication->getData('locale')
            );
        }

        return [
            'articleId' => $submission->getId(),
            'publicationId' => $publication->getId(),
            'language' => $this->normalizeLocale((string) $publication->getData('locale')),
            'datePublished' => $publication->getData('datePublished'),
            'lastModified' => $publication->getData('lastModified'),
            'pages' => $this->mapPages($publication),
            'licenseUrl' => $publication->getData('licenseUrl'),
            'copyrightHolder' => $copyrightHolders,
            'copyrightYear' => $publication->getData('copyrightYear'),
            'url' => $this->publicationUrl($submission, $context),
            'identifiers' => [
                'doi' => $publication->getDoi(),
            ],
            'type' => 'journalArticle',
            'status' => 'published',
            'journalId' => $context->getId(),
        ];
    }

    private function publicationUrl(Submission $submission, Context $context): string
    {
        $request = \Application::get()->getRequest();
        return $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $context->getPath(),
            'article',
            'view',
            [$submission->getId()],
            urlLocaleForPage: ''
        );
    }

    private function authorNamesByLocale(array $authors): array
    {
        $names = [];
        foreach ($authors as $author) {
            $locales = array_unique(array_merge(
                array_keys((array) ($author['givenName'] ?? [])),
                array_keys((array) ($author['familyName'] ?? [])),
                array_keys((array) ($author['preferredPublicName'] ?? []))
            ));
            foreach ($locales as $locale) {
                $name = trim((string) (($author['preferredPublicName'][$locale] ?? '')));
                if ($name === '') {
                    $name = trim(implode(' ', array_filter([
                        $author['givenName'][$locale] ?? '',
                        $author['familyName'][$locale] ?? '',
                    ])));
                }
                if ($name !== '') {
                    $names[$locale][] = $name;
                }
            }
        }
        return array_map(static fn (array $items): string => implode(', ', $items), $names);
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
                            $values[] = ['locale' => $this->contentLocale($localizedName) ?: $this->normalizeLocale((string) $locale), 'name' => trim($localizedName)];
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
                        $values[] = ['locale' => $this->contentLocale($localizedName) ?: $this->normalizeLocale((string) $locale), 'name' => trim($localizedName)];
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
        $state = '';
        $country = $countryName ?: $countryCode;
        $postalCode = '';

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
                $previous = (string) end($parts);
                if (count($parts) >= 2 && preg_match('/\d{5,6}$/u', $previous)) {
                    $state = $city;
                    $city = (string) array_pop($parts);
                }
                $organization = implode(', ', $parts);
            } elseif (count($parts) === 2 && $countryCode !== '') {
                $city = (string) array_pop($parts);
                $organization = implode(', ', $parts);
            }
        }

        $city = preg_replace('/^г\.?\s*/ui', '', trim($city)) ?? trim($city);
        if (preg_match('/^(.*?)\s+(\d{5,6})$/u', $city, $matches)) {
            $city = trim($matches[1]);
            $postalCode = $matches[2];
        }

        return [
            'organization' => $organization,
            'city' => $city,
            'state' => $state,
            'postalCode' => $postalCode,
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
