<?php

namespace APP\plugins\importexport\metafora;

use PKP\plugins\ImportExportPlugin;

class MetaforaExportPlugin extends ImportExportPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success) {
            $this->addLocaleData();
        }
        return $success;
    }

    public function getName()
    {
        return 'MetaforaExportPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.importexport.metafora.displayName');
    }

    public function getDescription()
    {
        return __('plugins.importexport.metafora.description');
    }

    public function executeCLI($scriptName, &$args)
    {
        $this->usage($scriptName);
    }

    public function usage($scriptName)
    {
        echo "Metafora Export Plugin 0.1.0-beta\n";
    }
}
