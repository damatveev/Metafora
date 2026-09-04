<?php

namespace APP\plugins\importexport\metafora;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\plugins\Plugin;

class MetaforaSettingsForm extends Form
{
    public int $contextId;
    public Plugin $plugin;

    public function __construct(Plugin $plugin, int $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData(): void
    {
        foreach ($this->getFormFields() as $fieldName => $fieldType) {
            $this->setData($fieldName, $this->plugin->getSetting($this->contextId, $fieldName));
        }
        if (!$this->getData('exportFormat')) {
            $this->setData('exportFormat', 'api');
        }
    }

    public function readInputData(): void
    {
        $this->readUserVars(array_keys($this->getFormFields()));
    }

    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);
        foreach ($this->getFormFields() as $fieldName => $fieldType) {
            $this->plugin->updateSetting($this->contextId, $fieldName, $this->getData($fieldName), $fieldType);
        }
    }

    public function fetch($request, $template = null, $display = false)
    {
        TemplateManager::getManager($request)->assign('exportFormats', [
            'api' => __('plugins.importexport.metafora.settings.format.api'),
            'journal' => __('plugins.importexport.metafora.settings.format.journal'),
            'jats' => __('plugins.importexport.metafora.settings.format.jats'),
            'scienceSpace' => __('plugins.importexport.metafora.settings.format.scienceSpace'),
        ]);
        return parent::fetch($request, $template, $display);
    }

    public function getFormFields(): array
    {
        return [
            'apiUrl' => 'string',
            'apiToken' => 'string',
            'apiTestEndpoint' => 'string',
            'exportFormat' => 'string',
            'validateXml' => 'bool',
            'includePdf' => 'bool',
            'includeReferences' => 'bool',
            'autoExport' => 'bool',
        ];
    }
}
