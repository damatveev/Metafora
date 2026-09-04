<?php

namespace APP\plugins\importexport\metafora;

use APP\notification\NotificationManager;
use PKP\core\JSONMessage;
use PKP\plugins\ImportExportPlugin;

class MetaforaExportPlugin extends ImportExportPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success) {
            $this->addLocaleData();
        }
        return $success;
    }

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

    public function getPluginSettingsPrefix(): string
    {
        return 'metafora';
    }

    public function manage($args, $request): JSONMessage
    {
        $context = $request->getContext();
        if (!$context) {
            return parent::manage($args, $request);
        }

        $this->addLocaleData();
        $form = new MetaforaSettingsForm($this, $context->getId());

        switch ($request->getUserVar('verb')) {
            case 'index':
                $form->initData();
                return new JSONMessage(true, $form->fetch($request));

            case 'save':
                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    $user = $request->getUser();
                    if ($user) {
                        (new NotificationManager())->createTrivialNotification($user->getId());
                    }
                    return new JSONMessage(true);
                }
                return new JSONMessage(true, $form->fetch($request));
        }

        return parent::manage($args, $request);
    }
}
