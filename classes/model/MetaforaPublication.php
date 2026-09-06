<?php

/**
 * @file plugins/importexport/metafora/classes/model/MetaforaPublication.php
 *
 * Internal transport model independent from a concrete Metafora API version.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\model;

class MetaforaPublication
{
    public function __construct(
        public readonly int $submissionId,
        public readonly array $title,
        public readonly array $abstract,
        public readonly array $authors,
        public readonly array $keywords,
        public readonly ?string $doi,
        public readonly array $issue,
        public readonly array $journal,
        public readonly array $files,
        public readonly array $references,
        public readonly array $metadata = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'submissionId' => $this->submissionId,
            'title' => $this->title,
            'abstract' => $this->abstract,
            'authors' => $this->authors,
            'keywords' => $this->keywords,
            'doi' => $this->doi,
            'issue' => $this->issue,
            'journal' => $this->journal,
            'files' => $this->files,
            'references' => $this->references,
            'metadata' => $this->metadata,
        ];
    }
}
