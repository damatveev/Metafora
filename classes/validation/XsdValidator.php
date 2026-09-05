<?php

/**
 * @file plugins/importexport/metafora/classes/validation/XsdValidator.php
 *
 * Validates generated XML against a local XSD schema.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\validation;

use DOMDocument;
use RuntimeException;

class XsdValidator
{
    public function validate(string $xml, string $xsdPath): array
    {
        if (!is_readable($xsdPath)) {
            throw new RuntimeException('XSD schema is not readable: ' . $xsdPath);
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new DOMDocument();
            $document->preserveWhiteSpace = false;

            if (!$document->loadXML($xml, LIBXML_NONET)) {
                return $this->collectErrors();
            }

            $valid = $document->schemaValidate($xsdPath);
            $errors = $this->collectErrors();

            if ($valid && !$errors) {
                return [];
            }

            return $errors ?: [['message' => 'XML does not conform to the XSD schema.']];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function collectErrors(): array
    {
        return array_map(
            static fn (\LibXMLError $error): array => [
                'level' => $error->level,
                'code' => $error->code,
                'line' => $error->line,
                'column' => $error->column,
                'message' => trim($error->message),
            ],
            libxml_get_errors()
        );
    }
}
