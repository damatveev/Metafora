<?php

namespace APP\plugins\importexport\metafora;

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\plugins\importexport\metafora\classes\api\MetaforaApiClient;
use APP\plugins\importexport\metafora\classes\export\ExportManager;
use APP\plugins\importexport\metafora\classes\export\IssueArticleManager;
use APP\plugins\importexport\metafora\classes\form\MetaforaSettingsForm;
use APP\plugins\importexport\metafora\classes\history\ExportHistoryRepository;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\context\Context;
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



    public function getSettingsFormClassName(): string
    {
        return MetaforaSettingsForm::class;
    }




    public function getPluginSettingsPrefix(): string
    {
        return 'metafora';
    }


    public function display($args, $request): void
    {
        parent::display($args, $request);
        $context = $request->getContext();
        if (!$context) {
            throw new NotFoundHttpException();
        }
        $history = $this->history();
        $history->ensureTable();
        $legacyHistory = json_decode((string) $this->getSetting($context->getId(), 'exportHistory'), true);
        if (is_array($legacyHistory) && $legacyHistory !== []) {
            $history->importLegacy($context->getId(), $legacyHistory);
        }

        switch (array_shift($args) ?: 'index') {
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
                        'getParams' => [
                            'status' => STATUS_PUBLISHED,
                            'orderBy' => 'datePublished',
                            'orderDirection' => 'DESC',
                        ],
                        'lazyLoad' => true,
                    ]
                );
                $submissionsListPanel->includeIssuesFilter = true;

                $submissionsConfig = $submissionsListPanel->getConfig();
                $submissionsConfig['addUrl'] = '';
                $submissionsConfig['filters'] = array_slice($submissionsConfig['filters'], 1);
                $submissionsConfig['metaforaStatuses'] = $this->getSubmissionStatuses($context);
                $submissionsConfig['metaforaMetadata'] = $this->getSubmissionMetadata($context);
                $submissionsConfig['metaforaLabels'] = [
                    'notSent' => __('plugins.importexport.metafora.table.notSent'),
                    'sent' => __('plugins.importexport.metafora.table.sent'),
                    'sending' => __('plugins.importexport.metafora.table.sending'),
                    'failed' => __('plugins.importexport.metafora.table.failed'),
                ];

                $templateMgr->setState([
                    'components' => [
                        'submissions' => $submissionsConfig,
                    ],
                ]);

                $settingsForm = new MetaforaSettingsForm($this, $context->getId());
                $settingsForm->initData();

                $templateMgr->assign([
                    'pageTitle' => $this->getDisplayName(),
                    'pageComponent' => 'ImportExportPage',
                    'metaforaSettingsTemplate' => $this->getTemplateResource('settingsForm.tpl'),
                    'apiUrl' => $settingsForm->getData('apiUrl'),
                    'apiToken' => $settingsForm->getData('apiToken'),
                    'validateXml' => (bool) $settingsForm->getData('validateXml'),
                    'includePdf' => (bool) $settingsForm->getData('includePdf'),
                    'includeReferences' => (bool) $settingsForm->getData('includeReferences'),
                    'metaforaIssues' => $this->getIssuesTableData($context, $request),
                ]);
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                return;

            case 'sendSubmissions':
                $submissionIds = $this->normalizeIds((array) $request->getUserVar('selectedSubmissions'));
                $documents = [];
                $validationErrors = [];
                $error = $submissionIds === []
                    ? __('plugins.importexport.metafora.error.noSubmissionsSelected')
                    : null;
                if ($error === null) {
                    [$documents, $validationErrors] = $this->buildDocuments($submissionIds, $context);
                }
                $this->sendAndDownloadReport(
                    $documents,
                    $context,
                    $error,
                    $validationErrors
                );
                return;

            case 'sendIssues':
                $issueIds = $this->normalizeIds((array) $request->getUserVar('selectedIssues'));
                $documents = [];
                $validationErrors = [];
                $error = $issueIds === []
                    ? __('plugins.importexport.metafora.error.noIssuesSelected')
                    : null;
                if ($error === null) {
                    try {
                        $articleIds = [];
                        foreach ($issueIds as $issueId) {
                            foreach ((new IssueArticleManager())->getArticles($issueId, $context) as $article) {
                                $articleIds[] = (int) $article['submissionId'];
                            }
                        }
                        [$documents, $validationErrors] = $this->buildDocuments(
                            array_values(array_unique($articleIds)),
                            $context
                        );
                    } catch (Throwable $exception) {
                        $error = $exception->getMessage();
                    }
                }
                $this->sendAndDownloadReport(
                    $documents,
                    $context,
                    $error,
                    $validationErrors
                );
                return;

            default:
                throw new NotFoundHttpException();
        }
    }

    /**
     * Upload generated JATS documents and return a downloadable processing report.
     */
    private function sendAndDownloadReport(
        array $documents,
        Context $context,
        ?string $initialError = null,
        array $initialItems = []
    ): void
    {
        @set_time_limit(0);
        $items = $initialItems;
        $history = $this->history();
        $includePdf = (bool) $this->getSetting($context->getId(), 'includePdf');
        $exportType = $includePdf ? 'xml_pdf' : 'xml';

        foreach ($initialItems as $item) {
            $submissionId = (int) ($item['submissionId'] ?? 0);
            if ($submissionId > 0) {
                $history->recordFailure(
                    $submissionId,
                    $context->getId(),
                    $exportType,
                    (string) ($item['message'] ?? 'Export validation failed')
                );
            }
        }

        if ($initialError !== null) {
            $items[] = ['success' => false, 'message' => $initialError];
        } elseif ($documents !== []) {
            try {
                $client = $this->getApiClient($context);
            } catch (Throwable $exception) {
                foreach ($documents as $submissionId => $xml) {
                    $item = [
                        'submissionId' => (int) $submissionId,
                        'success' => false,
                        'httpStatus' => 0,
                        'message' => $exception->getMessage(),
                    ];
                    $items[] = $item;
                    $history->recordFailure(
                        (int) $submissionId,
                        $context->getId(),
                        $exportType,
                        $exception->getMessage(),
                        0,
                        null,
                        $xml
                    );
                }
                $client = null;
            }

            foreach ($client ? $documents : [] as $submissionId => $xml) {
                $historyId = $history->start(
                    (int) $submissionId,
                    $context->getId(),
                    $exportType,
                    $xml
                );
                $xmlPath = tempnam($this->getExportPath(), 'metafora-jats-');
                if ($xmlPath === false) {
                    $message = 'Unable to create a temporary JATS file.';
                    $history->finish($historyId, false, 0, null, $message);
                    $items[] = [
                        'submissionId' => (int) $submissionId,
                        'success' => false,
                        'httpStatus' => 0,
                        'message' => $message,
                    ];
                    continue;
                }

                try {
                    if (file_put_contents($xmlPath, $xml) === false) {
                        throw new \RuntimeException('Unable to write a temporary JATS file.');
                    }

                    $pdfPath = $includePdf ? $this->getPdfPath((int) $submissionId, $context) : null;
                    if ($includePdf && $pdfPath === null) {
                        throw new \RuntimeException(__('plugins.importexport.metafora.error.pdfRequired'));
                    }
                    $response = $pdfPath
                        ? $client->sendJatsXmlPdf($xmlPath, $pdfPath)
                        : $client->sendJatsXml($xmlPath);
                    $status = (int) ($response['status'] ?? 0);
                    $responsePayload = $response['json'] ?? $response['body'] ?? null;
                    $success = $status >= 200 && $status < 300;

                    $history->finish(
                        $historyId,
                        $success,
                        $status,
                        $responsePayload
                    );

                    $items[] = [
                        'submissionId' => (int) $submissionId,
                        'success' => $success,
                        'httpStatus' => $status,
                        'pdfIncluded' => $pdfPath !== null,
                        'response' => $responsePayload,
                    ];
                } catch (Throwable $exception) {
                    $history->finish($historyId, false, 0, null, $exception->getMessage());
                    $items[] = [
                        'submissionId' => (int) $submissionId,
                        'success' => false,
                        'httpStatus' => 0,
                        'pdfIncluded' => false,
                        'message' => $exception->getMessage(),
                    ];
                } finally {
                    if (is_file($xmlPath)) {
                        unlink($xmlPath);
                    }
                }
            }
        }

        if ($items === []) {
            $items[] = [
                'success' => false,
                'message' => __('plugins.importexport.metafora.error.noPublishedArticles'),
            ];
        }

        $successful = count(array_filter($items, static fn (array $item): bool => !empty($item['success'])));
        $report = json_encode([
            'schema' => 'metafora-ojs-send-report/1.0',
            'generatedAt' => gmdate(DATE_ATOM),
            'journalId' => $context->getId(),
            'total' => count($items),
            'successful' => $successful,
            'failed' => count($items) - $successful,
            'items' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $fileManager = new FileManager();
        $path = $this->getExportFileName($this->getExportPath(), 'metafora-send-report', $context);
        $path = preg_replace('/\.xml$/', '.json', $path) ?: ($path . '.json');
        $fileManager->writeFile($path, $report);
        $fileManager->downloadByPath($path);
        $fileManager->deleteByPath($path);
    }

    private function buildDocuments(array $submissionIds, Context $context): array
    {
        $documents = [];
        $errors = [];
        $manager = $this->getExportManager($context);

        foreach ($submissionIds as $submissionId) {
            try {
                foreach ($manager->exportJats([(int) $submissionId], $context) as $id => $xml) {
                    $documents[$id] = $xml;
                }
            } catch (Throwable $exception) {
                $errors[] = [
                    'submissionId' => (int) $submissionId,
                    'success' => false,
                    'httpStatus' => 0,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [$documents, $errors];
    }

    private function getApiClient(Context $context): MetaforaApiClient
    {
        $apiUrl = trim((string) $this->getSetting($context->getId(), 'apiUrl'));
        $apiToken = trim((string) $this->getSetting($context->getId(), 'apiToken'));

        if ($apiUrl === '' || $apiToken === '') {
            throw new \RuntimeException(__('plugins.importexport.metafora.error.settingsRequired'));
        }

        return new MetaforaApiClient($apiUrl, $apiToken);
    }

    private function getExportManager(Context $context): ExportManager
    {
        $validateXml = $this->getSetting($context->getId(), 'validateXml');
        $includeReferences = $this->getSetting($context->getId(), 'includeReferences');

        return new ExportManager(
            validateXml: $validateXml === null ? true : (bool) $validateXml,
            includeReferences: $includeReferences === null ? true : (bool) $includeReferences,
        );
    }

    private function getPdfPath(int $submissionId, Context $context): ?string
    {
        $submission = Repo::submission()->get($submissionId);
        if (!$submission || (int) $submission->getData('contextId') !== $context->getId()) {
            return null;
        }

        $publication = $submission->getCurrentPublication();
        $galleys = $publication?->getData('galleys') ?? [];
        if (is_object($galleys) && method_exists($galleys, 'all')) {
            $galleys = $galleys->all();
        }

        foreach ((array) $galleys as $galley) {
            $submissionFileId = (int) $galley->getData('submissionFileId');
            $submissionFile = $submissionFileId ? Repo::submissionFile()->get($submissionFileId) : null;
            if (!$submissionFile) {
                continue;
            }

            $file = app()->get('file')->get($submissionFile->getData('fileId'));
            if (!$file) {
                continue;
            }
            $mimeType = strtolower((string) ($file->mimetype ?? $submissionFile->getData('mimetype')));
            if ($mimeType !== 'application/pdf') {
                continue;
            }

            $path = rtrim((string) Config::getVar('files', 'files_dir'), '/\\')
                . DIRECTORY_SEPARATOR . ltrim((string) $file->path, '/\\');
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function getSubmissionStatuses(Context $context): array
    {
        return $this->history()->getLatestForJournal($context->getId());
    }

    private function history(): ExportHistoryRepository
    {
        return new ExportHistoryRepository();
    }

    private function getSubmissionMetadata(Context $context): array
    {
        $result = [];
        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([STATUS_PUBLISHED])
            ->getMany();
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }
            $issue = null;
            $issueId = (int) $publication->getData('issueId');
            if ($issueId) {
                $issueObject = Repo::issue()->get($issueId);
                if ($issueObject) {
                    $issue = trim(implode(' ', array_filter([
                        $issueObject->getData('year'),
                        $issueObject->getData('volume') ? 'Т. ' . $issueObject->getData('volume') : null,
                        $issueObject->getData('number') ? '№ ' . $issueObject->getData('number') : null,
                    ])));
                }
            }
            $authors = [];
            $authorCollection = $publication->getData('authors');
            foreach ($authorCollection ?: [] as $author) {
                $name = $this->localizedValue($author->getData('preferredPublicName'));
                if ($name === '') {
                    $name = trim($this->localizedValue($author->getData('givenName')) . ' ' . $this->localizedValue($author->getData('familyName')));
                }
                if ($name !== '') {
                    $authors[] = $name;
                }
            }
            $result[$submission->getId()] = ['issue' => $issue ?: '—', 'authors' => $authors ? implode(', ', $authors) : '—'];
        }
        return $result;
    }

    private function getIssuesTableData(Context $context, $request): array
    {
        $result = [];
        $statuses = $this->getSubmissionStatuses($context);
        foreach (Repo::issue()->getCollector()->filterByContextIds([$context->getId()])->getMany() as $issue) {
            $articles = (new IssueArticleManager())->getArticles($issue->getId(), $context);
            $articleStatuses = array_values(array_filter(array_map(
                static fn (array $article) => $statuses[$article['submissionId']] ?? null,
                $articles
            )));
            $status = 'not_sent';
            if (array_filter($articleStatuses, static fn (array $item) => $item['status'] === 'failed')) {
                $status = 'failed';
            } elseif (array_filter($articleStatuses, static fn (array $item) => $item['status'] === 'sending')) {
                $status = 'sending';
            } elseif ($articles && count($articleStatuses) === count($articles)) {
                $status = 'success';
            }
            $error = '—';
            foreach ($articleStatuses as $item) {
                if ($item['status'] === 'failed' && !empty($item['message'])) {
                    $error = $item['message'];
                    break;
                }
            }
            $label = trim(implode(' ', array_filter([
                $issue->getData('year'),
                $issue->getData('volume') ? 'Т. ' . $issue->getData('volume') : null,
                $issue->getData('number') ? '№ ' . $issue->getData('number') : null,
            ])));
            $result[] = [
                'id' => $issue->getId(), 'label' => $label ?: (string) $issue->getId(),
                'articleCount' => count($articles), 'status' => $status, 'error' => $error,
                'statusLabel' => __('plugins.importexport.metafora.table.' . ($status === 'success' ? 'sent' : ($status === 'not_sent' ? 'notSent' : $status))),
                'url' => $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'issue', 'view', [$issue->getId()]),
            ];
        }
        return $result;
    }

    private function localizedValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_array($value)) {
            foreach ($value as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    return trim($candidate);
                }
            }
        }
        return '';
    }


    public function executeCLI($scriptName, &$args): void
    {
        $command = $args[0] ?? null;
        $journalPath = $args[1] ?? null;


        if (!$command || !$journalPath) {
            $this->usage($scriptName);
            return;
        }


        $context = \Application::get()
            ->getContextDAO()
            ->getByPath($journalPath);


        if (!$context) {
            echo "Journal not found\n";
            return;
        }


        try {

            switch ($command) {


                case 'issues':

                    $issues = Repo::issue()
                        ->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->getMany();


                    echo "ID | Volume | Number | Year | Articles\n";
                    echo "--------------------------------\n";


                    foreach ($issues as $issue) {

                        $articles = Repo::submission()
                            ->getCollector()
                            ->filterByContextIds([$context->getId()])
                            ->filterByIssueIds([$issue->getId()])
                            ->filterByStatus([STATUS_PUBLISHED])
                            ->getMany();


                        echo $issue->getId()
                            . " | "
                            . $issue->getData('volume')
                            . " | "
                            . $issue->getData('number')
                            . " | "
                            . $issue->getData('year')
                            . " | "
                            . count($articles)
                            . "\n";
                    }

                    return;


                case 'issue-info':

                    $issueId = (int)($args[2] ?? 0);

                    $manager = new IssueArticleManager();

                    $articles = $manager->getArticles(
                        $issueId,
                        $context
                    );


                    echo "Submission ID | DOI | Title\n";
                    echo "--------------------------------\n";


                    foreach ($articles as $article) {

                        echo ($article['submissionId'] ?? '')
                            . " | "
                            . ($article['doi'] ?? '')
                            . " | "
                            . ($article['title']['en']
                                ?? $article['title']['ru']
                                ?? '')
                            . "\n";
                    }

                    return;


                case 'export-jats-issue':

                    $issueId = (int)($args[2] ?? 0);

                    $manager = new ExportManager();

                    $documents = $manager->exportJatsByIssue(
                        $issueId,
                        $context
                    );


                    $dir = "metafora-export/issue-" . $issueId;


                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }


                    foreach ($documents as $id => $xml) {

                        $file = $dir . "/" . $id . ".xml";

                        file_put_contents(
                            $file,
                            $xml
                        );

                        echo $file . "\n";
                    }

                    return;


                case 'test-connection':

                    $apiUrl = (string)$this->getSetting(
                        $context->getId(),
                        'apiUrl'
                    );

                    $apiToken = (string)$this->getSetting(
                        $context->getId(),
                        'apiToken'
                    );


                    if ($apiUrl === '' || $apiToken === '') {
                        echo "Metafora API settings are empty\n";
                        return;
                    }


                    $result = (new MetaforaApiClient(
                        $apiUrl,
                        $apiToken
                    ))->testConnection();


                    echo "HTTP: "
                        . ($result['status'] ?? '')
                        . "\n";

                    return;


                default:

                    echo "Unknown command: {$command}\n";
                    $this->usage($scriptName);
            }


        } catch (\Throwable $e) {

            echo "ERROR:\n";
            echo $e->getMessage() . "\n";
            echo $e->getFile()
                . ":"
                . $e->getLine()
                . "\n";
        }
    }


    public function usage($scriptName): void
    {
        echo "Metafora Export Plugin for OJS 3.5\n";
        echo "Usage:\n";
        echo "  php tools/importExport.php MetaforaExportPlugin issues <journal>\n";
        echo "  php tools/importExport.php MetaforaExportPlugin issue-info <journal> <issueId>\n";
        echo "  php tools/importExport.php MetaforaExportPlugin export-jats-issue <journal> <issueId>\n";
        echo "  php tools/importExport.php MetaforaExportPlugin test-connection <journal>\n";
    }


    public function manage($args, $request): JSONMessage
    {
        $context = $request->getContext();
        if (!$context) {
            return parent::manage($args, $request);
        }
        $this->addLocaleData();
        $formClass = $this->getSettingsFormClassName();
        $form = new $formClass($this, $context->getId());

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
                try {
                    $result = $this->getApiClient($context)->testConnection();
                    $status = (int) ($result['status'] ?? 0);
                    $ok = $status > 0 && !in_array($status, [401, 403], true);
                    return new JSONMessage($ok, [
                        'status' => $status,
                        'message' => $ok
                            ? __('plugins.importexport.metafora.connection.success', ['status' => $status])
                            : __('plugins.importexport.metafora.connection.failure', ['status' => $status]),
                    ]);
                } catch (Throwable) {
                    return new JSONMessage(false, [
                        'status' => 0,
                        'message' => __('plugins.importexport.metafora.connection.error'),
                    ]);
                }
        }

        return parent::manage($args, $request);
    }


}
