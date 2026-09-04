<?php

/**
 * @file plugins/importexport/metafora/classes/export/SchemaValidatedXmlExporter.php
 *
 * Wraps a format-specific serializer and validates its output against the
 * official Metafora XSD before the XML can leave the plugin.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\export;

use APP\plugins\importexport\metafora\classes\schema\MetaforaSchemaRegistry;
use APP\plugins\importexport\metafora\classes\validation\XsdValidator;
use RuntimeException;

class SchemaValidatedXmlExporter
{
    public function __construct(
        private readonly MetaforaSchemaRegistry $schemaRegistry,
        private readonly XsdValidator $validator = new XsdValidator(),
    ) {
    }

    /**
     * @param array $publications MetaforaPublication[]
     */
    public function export(XmlSerializerInterface $serializer, array $publications): string
    {
        $xml = $serializer->serialize($publications);
        $schemaPath = $this->schemaRegistry->getSchemaPath($serializer->getFormat());
        $errors = $this->validator->validate($xml, $schemaPath);

        if ($errors !== []) {
            throw new RuntimeException($this->formatErrors($errors));
        }

        return $xml;
    }

    private function formatErrors(array $errors): string
    {
        $messages = array_map(
            static function (array $error): string {
                $line = isset($error['line']) ? ' line ' . $error['line'] : '';
                $message = trim((string) ($error['message'] ?? 'Unknown XML validation error'));
                return $message . $line;
            },
            $errors
        );

        return 'Metafora XML validation failed: ' . implode('; ', $messages);
    }
}
