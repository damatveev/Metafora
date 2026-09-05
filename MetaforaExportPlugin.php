<?php

namespace APP\plugins\importexport\metafora;

use APP\notification\NotificationManager;
use APP\plugins\importexport\metafora\classes\api\MetaforaApiClient;
use APP\plugins\importexport\metafora\classes\export\ExportManager;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\file\FileManager;
use PKP\plugins\ImportExportPlugin;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

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

    public function display($args, $request)
    {
        parent::display($args, $request);

        $context = $request->getContext();
        if (!$context) {
            throw new NotFoundHttpException();
        }

        $operation = array_shift($args) ?: 'index';

        switch ($operation) {
            case 'index':
                $templateMgr = TemplateManager::getManager($request);
                $apiUrl = $request->getDispatcher()->url(
                    $request,
                    PKPApplication::ROUTE_API,
                    $context->getPath(),
                    'submissions'
                );

                $submissionsListPanel = new \APP\components\listPanels\SubmissionsListPanel(
                    'submissions',
                    __('common.publications'),
                    [
                        'apiUrl' => $apiUrl,
                        'count' => 100,
                        'getParams' => new \stdClass(),
                        'lazyLoad' => true,
                    ]
                );

                $submissionsConfig = $submissionsListPanel->getConfig();
                $submissionsConfig['addUrl'] = '';
                $submissionsConfig['filters'] = array_slice($submissionsConfig['filters'], 1);

                $templateMgr->setState([
                    'components' => [
                        'submissions' => $submissionsConfig,
                    ],
                ]);
                $templateMgr->assign([
                    'pageTitle' => $this->getDisplayName(),
                    'pageComponent' => 'ImportExportPage',
                ]);
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                return;

            case 'exportJson':
                $submissionIds = (array) $request->getUserVar('selectedSubmissions');
                $json = (new ExportManager())->exportJson($submissionIds, $context);

                $fileManager = new FileManager();
                $path = $this->getExportFileName($this->getExportPath(), 'metafora', $context);
                $path = preg_replace('/\.xml$/', '.json', $path) ?: ($path . '.json');
                $fileManager->writeFile($path, $json);
                $fileManager->downloadByPath($path);
                $fileManager->deleteByPath($path);
                return;

            default:
                throw new NotFoundHttpException();
        }
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

            case 'testConnection':
                $apiUrl = (string) $this->getSetting($context->getId(), 'apiUrl');
                $apiToken = (string) $this->getSetting($context->getId(), 'apiToken');
                $testEndpoint = (string) $this->getSetting($context->getId(), 'apiTestEndpoint');

                try {
                    $result = (new MetaforaApiClient($apiUrl, $apiToken))->testConnection($testEndpoint);
                    $status = (int) ($result['status'] ?? 0);
                    $ok = $status >= 200 && $status < 400;

                    return new JSONMessage($ok, [
                        'status' => $status,
                        'message' => $ok
                            ? __('plugins.importexport.metafora.connection.success', ['status' => $status])
                            : __('plugins.importexport.metafora.connection.failure', ['status' => $status]),
                    ]);
                } catch (Throwable $e) {
                    return new JSONMessage(false, [
                        'status' => 0,
                        'message' => __('plugins.importexport.metafora.connection.error'),
                    ]);
                }
        }

        return parent::manage($args, $request);
    }

    /**
     * CLI support required by ImportExportPlugin.
     *
     * CLI export is intentionally disabled until the Metafora export contract
     * and transport endpoints are finalized.
     */
    public function executeCLI($scriptName, &$args)
    {
        $command = array_shift($args);

        if ($command !== 'export') {
            $this->usage($scriptName);
            return;
        }

        $request = app()->get('request');
        $context = $request->getContext();

        if (!$context) {
            echo "No journal context found.\n";
            return;
        }

        $submissions = \APP\facades\Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        $ids = [];

        foreach ($submissions as $submission) {
            $ids[] = $submission->getId();
        }

        if ($ids === []) {
            echo "No submissions found.\n";
            return;
        }

        try {
            $json = (new \APP\plugins\importexport\metafora\classes\export\ExportManager())
                ->exportJson($ids, $context);

            $file = 'metafora-export-' . date('Ymd-His') . '.json';

            file_put_contents(
                $file,
                $json
            );

            echo "Export completed:\n";
            echo $file . "\n";

        } catch (\Throwable $e) {

            echo "Export failed:\n";
            echo $e->getMessage() . "\n";
        }
    }

    public function usage($scriptName): void
    {
        echo "Metafora Export Plugin for OJS 3.5\n";
        echo "CLI export is not enabled in this development build.\n";
    }
}
