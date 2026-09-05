<?php

namespace APP\plugins\importexport\metafora;

use PKP\plugins\ImportExportPlugin;

class MetaforaExportPlugin extends ImportExportPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
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

    public function display($args, $request)
    {
        parent::display($args, $request);
    }

    public function manage($args, $request)
    {
        return parent::manage($args, $request);
    }

    public function executeCLI($scriptName, &$args)
    {
        echo "Metafora Export Plugin\n";
    }
}
