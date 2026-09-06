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

class OjsPublicationMapper
{
    public function map(Submission $submission, Context $context): MetaforaPublication
    {
        $publication = $submission->getCurrentPublication();

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

        foreach ($publication->getData('authors') as $author) {
            $authors[] = [
                'givenName' => $author->getData('givenName'),
                'familyName' => $author->getData('familyName'),
                'preferredPublicName' => $author->getData('preferredPublicName'),
                'affiliation' => $author->getData('affiliation'),
                'country' => $author->getData('country'),
                'orcid' => $author->getData('orcid'),
            ];
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

        return new MetaforaPublication(
            submissionId: $submission->getId(),
            title: $this->mapLocalizedField($publication, 'title'),
            abstract: $this->mapLocalizedField($publication, 'abstract'),
            authors: $authors,
            keywords: $this->mapLocalizedField($publication, 'keywords'),
            doi: $publication->getDoi(),
            issue: $issueData,
            journal: $journal,
            files: $this->mapFiles($publication->getData('galleys')->all()),
            references: $this->mapReferences($publication->getData('citationsRaw')),
        );
    }

    /**
     * Map publication galleys to transport-safe file descriptors.
     *
     * No local filesystem paths are exposed. The exporter keeps only OJS IDs
     * and descriptive metadata; the binary transport layer resolves the file
     * later through Repo::submissionFile().
     */
    private function mapLocalizedField($publication, string $field): array
    {
        $result = [];

        $locales = [];

        if (method_exists($publication, 'getData')) {
            $primaryLocale = $publication->getData('locale');

            if ($primaryLocale) {
                $locales[] = $primaryLocale;
            }
        }

        // OJS 3.5 publication settings locales
        $locales = array_unique(array_merge($locales, ['en', 'ru']));

        foreach ($locales as $locale) {
            $value = $publication->getData($field, $locale);

            if (is_string($value) && trim($value) !== '') {
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
        if (!is_string($citationsRaw) || trim($citationsRaw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $reference): string => trim($reference),
            preg_split('/\R/u', $citationsRaw) ?: []
        )));
    }
}
