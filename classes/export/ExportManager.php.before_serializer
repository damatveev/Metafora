<?php

/**
 * @file plugins/importexport/metafora/classes/export/ExportManager.php
 *
 * Coordinates OJS publication export to transport formats used by the Metafora plugin.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\export;

use APP\facades\Repo;
use APP\plugins\importexport\metafora\classes\mapping\OjsPublicationMapper;
use APP\plugins\importexport\metafora\classes\validation\PublicationValidator;
use PKP\context\Context;
use RuntimeException;

class ExportManager
{
    public function __construct(
        private readonly OjsPublicationMapper $mapper = new OjsPublicationMapper(),
        private readonly PublicationValidator $validator = new PublicationValidator(),
    ) {
    }

    public function collect(array $submissionIds, Context $context): array
    {
        $publications = [];

        $collector = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([\STATUS_PUBLISHED]);

        foreach ($collector->getMany() as $submission) {

            if ($submissionIds !== [] &&
                !in_array($submission->getId(), $submissionIds)) {
                continue;
            }

            $publication = $this->mapper->map(
                $submission,
                $context
            );

            $errors = $this->validator->validate($publication);

            if ($errors !== []) {
                throw new RuntimeException(
                    sprintf(
                        'Submission %d failed Metafora validation: %s',
                        $submission->getId(),
                        implode(' ', $errors)
                    )
                );
            }

            $publications[] = $publication;
        }

        if ($publications === []) {
            throw new RuntimeException(
                'No published publications found.'
            );
        }

        return $publications;
    }

    public function exportJson(array $submissionIds, Context $context): string
    {
        $payload = array_map(
            static fn ($publication): array => $publication->toArray(),
            $this->collect($submissionIds, $context)
        );

        $json = json_encode(
            [
                'schema' => 'metafora-ojs-export/0.1',
                'contextId' => $context->getId(),
                'publications' => $payload,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (!is_string($json)) {
            throw new RuntimeException('Unable to create Metafora JSON export.');
        }

        return $json;
    }
}
