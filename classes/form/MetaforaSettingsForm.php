<?php

namespace APP\plugins\importexport\metafora\classes\form;

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
        if (!$this->getData('apiUrl')) {
            $this->setData('apiUrl', 'https://metafora.rcsi.science/api/v2');
        }
        if (!$this->getData('apiTestEndpoint')) {
            $this->setData('apiTestEndpoint', 'files/status/?file_uid=00000000-0000-0000-0000-000000000000');
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
        $templateMgr = TemplateManager::getManager($request);


        $formData = [];

        foreach ($this->getFormFields() as $fieldName => $fieldType) {
            $formData[$fieldName] = $this->getData($fieldName);
        }


        $templateMgr->assign([
            'formData' => $formData,
            'includePdf' => $this->getData('includePdf'),
        ]);

        return parent::fetch($request, $template, $display);
    }

    public function getFormFields(): array
    {
        return [
            'apiUrl' => 'string',
            'apiToken' => 'string',
            'apiTestEndpoint' => 'string',
            'includePdf' => 'bool',
        ];
    }
}
