<?php

/**
 * @file plugins/importexport/metafora/classes/schema/MetaforaSchemaRegistry.php
 *
 * Resolves official Metafora XML schemas bundled with the plugin.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\schema;

use InvalidArgumentException;
use RuntimeException;

class MetaforaSchemaRegistry
{
    public const FORMAT_JOURNAL = 'journal';
    public const FORMAT_SCIENCE_SPACE = 'scienceSpace';
    public const FORMAT_JATS = 'jats';

    public function __construct(private readonly string $pluginPath)
    {
    }

    public function getSchemaPath(string $format): string
    {
        $relativePath = match ($format) {
            self::FORMAT_JOURNAL => 'schemas/journal3.xsd',
            self::FORMAT_SCIENCE_SPACE => 'schemas/science_space_articles.xsd',
            self::FORMAT_JATS => 'schemas/jats/JATS-archivearticle1-4.xsd',
            default => throw new InvalidArgumentException('Unsupported Metafora XML format: ' . $format),
        };

        $path = rtrim($this->pluginPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                sprintf('Official Metafora XSD is not installed for format "%s": %s', $format, $path)
            );
        }

        return $path;
    }

    public function hasSchema(string $format): bool
    {
        try {
            $this->getSchemaPath($format);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
