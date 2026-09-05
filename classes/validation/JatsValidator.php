<?php

/**
 * @file plugins/importexport/metafora/classes/validation/JatsValidator.php
 *
 * Validates generated JATS XML against the official JATS 1.4 XSD
 * used by the Metafora export workflow.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\validation;

use DOMDocument;
use RuntimeException;

class JatsValidator
{
    public function __construct(
        private readonly string $schemaPath
    ) {
    }

    public function validate(string $xml): void
    {
        if (!is_file($this->schemaPath)) {
            throw new RuntimeException(
                'JATS XSD not found: ' . $this->schemaPath
            );
        }

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument();

        if (!$document->loadXML($xml)) {
            throw new RuntimeException(
                'Generated JATS XML is not well-formed: ' . $this->errors()
            );
        }

        if (!$document->schemaValidate($this->schemaPath)) {
            throw new RuntimeException(
                'Generated JATS XML failed XSD validation: ' . $this->errors()
            );
        }

        libxml_clear_errors();
    }

    private function errors(): string
    {
        $messages = [];

        foreach (libxml_get_errors() as $error) {
            $messages[] = sprintf(
                'line %d: %s',
                $error->line,
                trim($error->message)
            );
        }

        libxml_clear_errors();

        return implode(' | ', $messages);
    }
}
