<?php

namespace APP\plugins\importexport\metafora\classes\form;

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
        if (!$this->getData('apiUrl')) {
            $this->setData('apiUrl', 'https://metafora.rcsi.science/api/v2');
        }
        if ($this->getData('validateXml') === null) {
            $this->setData('validateXml', true);
        }
        if ($this->getData('includePdf') === null) {
            $this->setData('includePdf', true);
        }
        if ($this->getData('includeReferences') === null) {
            $this->setData('includeReferences', true);
        }
        if (!$this->getData('deliveryMode')) {
            $this->setData('deliveryMode', 'api');
        }
    }

    public function readInputData(): void
    {
        $this->readUserVars(array_keys($this->getFormFields()));
    }

    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);
        $this->setData('deliveryMode', $this->getData('deliveryMode') === 'download' ? 'download' : 'api');
        foreach ($this->getFormFields() as $fieldName => $fieldType) {
            $this->plugin->updateSetting($this->contextId, $fieldName, $this->getData($fieldName), $fieldType);
        }
    }

    public function getFormFields(): array
    {
        return [
            'apiUrl' => 'string',
            'apiToken' => 'string',
            'deliveryMode' => 'string',
            'validateXml' => 'bool',
            'includePdf' => 'bool',
            'includeReferences' => 'bool',
        ];
    }
}
