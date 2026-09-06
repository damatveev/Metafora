<?php

/**
 * @file plugins/importexport/metafora/classes/validation/PublicationValidator.php
 *
 * Structural validation of the internal publication model before export.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\validation;

use APP\plugins\importexport\metafora\classes\model\MetaforaPublication;

class PublicationValidator
{
    /**
     * @return string[] Validation messages. Empty array means valid.
     */
    public function validate(MetaforaPublication $publication): array
    {
        $errors = [];

        if (!$this->hasLocalizedValue($publication->title)) {
            $errors[] = 'Publication title is required.';
        }

        if ($publication->authors === []) {
            $errors[] = 'At least one author is required.';
        } else {
            foreach ($publication->authors as $index => $author) {
                $givenName = $this->localizedOrScalar($author['givenName'] ?? null);
                $familyName = $this->localizedOrScalar($author['familyName'] ?? null);
                $preferredName = $this->localizedOrScalar($author['preferredPublicName'] ?? null);

                if ($givenName === '' && $familyName === '' && $preferredName === '') {
                    $errors[] = sprintf('Author #%d has no usable name.', $index + 1);
                }
            }
        }

        $printIssn = trim((string) ($publication->journal['printIssn'] ?? ''));
        $onlineIssn = trim((string) ($publication->journal['onlineIssn'] ?? ''));
        if ($printIssn === '' && $onlineIssn === '') {
            $errors[] = 'Journal ISSN or eISSN is required.';
        }

        foreach ($publication->files as $index => $file) {
            $remoteUrl = trim((string) ($file['remoteUrl'] ?? ''));
            $submissionFileId = (int) ($file['submissionFileId'] ?? 0);
            if ($remoteUrl === '' && $submissionFileId <= 0) {
                $errors[] = sprintf('File #%d has neither remote URL nor submissionFileId.', $index + 1);
            }
        }

        return $errors;
    }

    private function hasLocalizedValue(array $values): bool
    {
        foreach ($values as $value) {
            if (is_string($value) && trim(strip_tags($value)) !== '') {
                return true;
            }
        }
        return false;
    }

    private function localizedOrScalar(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && trim($item) !== '') {
                    return trim($item);
                }
            }
        }
        return '';
    }
}
