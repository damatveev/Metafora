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
use PKP\context\Context;
use RuntimeException;

class ExportManager
{
    public function __construct(private readonly OjsPublicationMapper $mapper = new OjsPublicationMapper())
    {
    }

    public function collect(array $submissionIds, Context $context): array
    {
        $publications = [];

        foreach (array_unique(array_map('intval', $submissionIds)) as $submissionId) {
            if ($submissionId <= 0) {
                continue;
            }

            $submission = Repo::submission()->get($submissionId);
            if (!$submission || (int) $submission->getData('contextId') !== $context->getId()) {
                continue;
            }

            $publications[] = $this->mapper->map($submission, $context);
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
