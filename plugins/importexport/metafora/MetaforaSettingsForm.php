<?php

namespace APP\plugins\importexport\metafora;

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

    public function getFormFields(): array
    {
        return [
            'apiUrl' => 'string',
            'apiToken' => 'string',
            'exportFormat' => 'string',
            'validateXml' => 'bool',
            'includePdf' => 'bool',
            'includeReferences' => 'bool',
            'autoExport' => 'bool',
        ];
    }
}
