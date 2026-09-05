<?php

/**
 * @file plugins/importexport/metafora/classes/export/XmlSerializerInterface.php
 *
 * Contract for Metafora XML serializers.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\export;

use APP\plugins\importexport\metafora\classes\model\MetaforaPublication;

interface XmlSerializerInterface
{
    public function getFormat(): string;

    /**
     * @param MetaforaPublication[] $publications
     */
    public function serialize(array $publications): string;
}
