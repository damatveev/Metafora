<?php

namespace APP\plugins\importexport\metafora;

use PKP\plugins\ImportExportPlugin;

class MetaforaExportPlugin extends ImportExportPlugin
{
    public function getName(): string
    {
        return 'MetaforaExportPlugin';
    }

    public function getDisplayName(): string
    {
        return __('plugins.importexport.metafora.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.importexport.metafora.description');
    }
}
