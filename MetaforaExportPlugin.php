<?php

namespace APP\plugins\importexport\metafora;

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\plugins\importexport\metafora\classes\api\MetaforaApiClient;
use APP\plugins\importexport\metafora\classes\export\ExportManager;
use APP\plugins\importexport\metafora\classes\export\IssueArticleManager;
use APP\plugins\importexport\metafora\classes\form\MetaforaSettingsForm;
use APP\plugins\importexport\metafora\classes\history\ExportHistoryRepository;
use APP\plugins\importexport\metafora\classes\history\RemoteStateRepository;
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

        $operation = array_shift($args) ?: 'index';
        if (in_array($operation, [
            'syncSubmission',
            'syncSubmissions',
            'syncIssue',
            'syncIssues',
            'replaceSubmission',
            'signSubmission',
            'unsignSubmission',
        ], true)) {
            $this->handleRemoteAction($operation, $request, $context);
            return;
        }

        $history = $this->history();
        $history->ensureTable();
        $this->remoteState()->ensureTable();
        $legacyHistory = json_decode((string) $this->getSetting($context->getId(), 'exportHistory'), true);
        if (is_array($legacyHistory) && $legacyHistory !== []) {
            $history->importLegacy($context->getId(), $legacyHistory);
        }

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
                        'count' => 25,
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
                $submissionsConfig['metaforaRemote'] = $this->getSubmissionRemoteStates($context);
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
                    'pageWidth' => 'full',
                    'metaforaSettingsTemplate' => $this->getTemplateResource('settingsForm.tpl'),
                    'apiUrl' => $settingsForm->getData('apiUrl'),
                    'apiToken' => $settingsForm->getData('apiToken'),
                    'deliveryMode' => $settingsForm->getData('deliveryMode') === 'download' ? 'download' : 'api',
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
                $this->deliverDocuments($documents, $context, $error, $validationErrors);
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
                $this->deliverDocuments($documents, $context, $error, $validationErrors);
                return;
            default:
                throw new NotFoundHttpException();
        }
    }

    /** Return JSON for all remote actions, including routing and PHP errors. */
    private function handleRemoteAction(string $operation, $request, Context $context): void
    {
        try {
            if (!$request->isPost()) {
                $this->outputJson([
                    'success' => false,
                    'httpStatus' => 405,
                    'message' => __('plugins.importexport.metafora.ajax.method'),
                ], 405);
                return;
            }
            if (!$request->checkCSRF()) {
                $this->outputJson([
                    'success' => false,
                    'httpStatus' => 403,
                    'message' => __('plugins.importexport.metafora.ajax.csrf'),
                ], 403);
                return;
            }

            $this->history()->ensureTable();
            $this->remoteState()->ensureTable();

            if ($operation === 'syncSubmission') {
                $this->outputJson($this->syncSubmissionRemoteState(
                    (int)$request->getUserVar('submissionId'),
                    $context
                ));
                return;
            }

            if ($operation === 'syncSubmissions') {
                $submissionIds = $this->normalizeIds((array)$request->getUserVar('selectedSubmissions'));
                if ($submissionIds === []) {
                    $this->outputJson([
                        'success' => false,
                        'message' => __('plugins.importexport.metafora.error.noSubmissionsSelected'),
                    ]);
                    return;
                }

                $items = [];
                foreach ($submissionIds as $submissionId) {
                    $items[] = $this->syncSubmissionRemoteState($submissionId, $context);
                }
                $this->outputJson([
                    'success' => count(array_filter(
                        $items,
                        static fn(array $item): bool => !empty($item['success'])
                    )) > 0,
                    'items' => $items,
                ]);
                return;
            }

            if ($operation === 'syncIssue') {
                $this->outputJson($this->syncIssueRemoteState(
                    (int)$request->getUserVar('issueId'),
                    $context
                ));
                return;
            }

            if ($operation === 'syncIssues') {
                $issueIds = $this->normalizeIds((array)$request->getUserVar('selectedIssues'));
                if ($issueIds === []) {
                    $this->outputJson([
                        'success' => false,
                        'message' => __('plugins.importexport.metafora.error.noIssuesSelected'),
                    ]);
                    return;
                }

                @set_time_limit(0);
                $issues = [];
                foreach ($issueIds as $issueId) {
                    $issues[] = $this->syncIssueRemoteState($issueId, $context);
                }
                $this->outputJson([
                    'success' => count(array_filter(
                        $issues,
                        static fn(array $issue): bool => !empty($issue['success'])
                    )) > 0,
                    'issues' => $issues,
                ]);
                return;
            }

            if ($operation === 'replaceSubmission') {
                $this->outputJson($this->replaceDeletedSubmission(
                    (int)$request->getUserVar('submissionId'),
                    $context
                ));
                return;
            }

            $this->outputJson($this->changePublicationSignature(
                (int)$request->getUserVar('submissionId'),
                $context,
                $operation === 'signSubmission'
            ));
        } catch (Throwable $exception) {
            error_log('[Metafora AJAX] ' . $operation . ': ' . $exception->getMessage());
            $this->outputJson([
                'success' => false,
                'httpStatus' => 500,
                'message' => $exception->getMessage(),
            ], 500);
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
                    $duplicateFileUid = $this->duplicateFileUid($status, $responsePayload);
                    $duplicateState = null;

                    if (!$success && $duplicateFileUid !== '') {
                        $remoteRepository = $this->remoteState();
                        $existingRemote = $remoteRepository->get(
                            (int)$submissionId,
                            $context->getId()
                        ) ?? [];
                        $remoteRepository->save(
                            (int)$submissionId,
                            $context->getId(),
                            $duplicateFileUid,
                            $existingRemote['articleUid'] ?? null,
                            $existingRemote['remoteStatus'] ?? 'duplicate',
                            $existingRemote['signatureStatus'] ?? null,
                            $existingRemote['remoteExists'] ?? null,
                            $status,
                            $responsePayload,
                            null,
                            false
                        );
                        $duplicateState = $this->syncSubmissionRemoteState(
                            (int)$submissionId,
                            $context
                        );
                        $success = ($duplicateState['publicationExists'] ?? null) === true;
                    }

                    $historyError = !$success && $duplicateFileUid !== ''
                        ? __('plugins.importexport.metafora.restore.required')
                        : null;

                    $history->finish(
                        $historyId,
                        $success,
                        $status,
                        $responsePayload,
                        $historyError
                    );

                    if ($success && $duplicateFileUid === '') {
                        $fileUid = $this->stringValue(
                            $this->findRecursiveValue($responsePayload, ['file_uid'])
                        );
                        $this->remoteState()->rememberUpload(
                            (int) $submissionId,
                            $context->getId(),
                            $fileUid !== '' ? $fileUid : null,
                            $status,
                            $responsePayload
                        );
                    }

                    $items[] = [
                        'submissionId' => (int) $submissionId,
                        'success' => $success,
                        'httpStatus' => $status,
                        'pdfIncluded' => $pdfPath !== null,
                        'response' => $responsePayload,
                        'message' => $historyError,
                        'canRestore' => !$success
                            && $duplicateFileUid !== ''
                            && ($duplicateState['remoteExists'] ?? null) === false,
                        'fileUid' => $duplicateFileUid !== '' ? $duplicateFileUid : null,
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
        $statuses = $this->history()->getLatestForJournal($context->getId());
        foreach ($this->remoteState()->getForJournal($context->getId()) as $submissionId => $remote) {
            if (!isset($statuses[$submissionId])) {
                $statuses[$submissionId] = [
                    'submissionId' => (int) $submissionId,
                    'exportType' => null,
                    'status' => 'not_sent',
                    'success' => false,
                    'httpStatus' => null,
                    'message' => null,
                    'response' => null,
                    'createdAt' => null,
                    'updatedAt' => $remote['updatedAt'] ?? null,
                ];
            }

            $statuses[$submissionId]['remote'] = $remote;
            $statuses[$submissionId]['effectiveStatus'] = match ($remote['remoteExists'] ?? null) {
                true => 'success',
                false => 'not_sent',
                default => $statuses[$submissionId]['status'],
            };
            $statuses[$submissionId]['updatedAt'] = $remote['updatedAt']
                ?? $statuses[$submissionId]['updatedAt']
                ?? null;
        }

        return $statuses;
    }

    private function getSubmissionRemoteStates(Context $context): array
    {
        return $this->remoteState()->getForJournal($context->getId());
    }

    private function deliverDocuments(
        array $documents,
        Context $context,
        ?string $initialError,
        array $validationErrors
    ): void {
        if ($this->getSetting($context->getId(), 'deliveryMode') === 'download') {
            if ($documents !== []) {
                $this->downloadDocuments($documents, $context, $validationErrors);
                return;
            }
        }
        $this->sendAndDownloadReport($documents, $context, $initialError, $validationErrors);
    }

    /** Download one JATS file directly or several files as a ZIP archive. */
    private function downloadDocuments(array $documents, Context $context, array $errors = []): void
    {
        $fileManager = new FileManager();
        if (count($documents) === 1 && $errors === []) {
            $submissionId = (int) array_key_first($documents);
            $path = $this->getExportFileName($this->getExportPath(), 'metafora-' . $submissionId, $context);
            $fileManager->writeFile($path, (string) reset($documents));
            $fileManager->downloadByPath($path);
            $fileManager->deleteByPath($path);
            return;
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP ZIP extension is required to download several JATS files.');
        }
        $xmlPath = $this->getExportFileName($this->getExportPath(), 'metafora-export', $context);
        $zipPath = preg_replace('/\.xml$/', '.zip', $xmlPath) ?: ($xmlPath . '.zip');
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the Metafora export archive.');
        }
        foreach ($documents as $submissionId => $xml) {
            $zip->addFromString('article-' . (int) $submissionId . '.xml', (string) $xml);
        }
        if ($errors !== []) {
            $zip->addFromString('errors.json', json_encode(
                $errors,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: '[]');
        }
        $zip->close();
        $fileManager->downloadByPath($zipPath);
        $fileManager->deleteByPath($zipPath);
    }

    private function history(): ExportHistoryRepository
    {
        return new ExportHistoryRepository();
    }

    private function remoteState(): RemoteStateRepository
    {
        return new RemoteStateRepository();
    }

    private function syncSubmissionRemoteState(int $submissionId, Context $context): array
    {
        $submission = Repo::submission()->get($submissionId);
        if (
            !$submission
            || (int) $submission->getData('contextId') !== $context->getId()
            || (int) $submission->getData('status') !== STATUS_PUBLISHED
        ) {
            return [
                'submissionId' => $submissionId,
                'success' => false,
                'message' => __('plugins.importexport.metafora.sync.submissionNotFound'),
            ];
        }

        $publication = $submission->getCurrentPublication();
        $doi = trim((string) ($publication?->getDoi() ?? ''));
        $repository = $this->remoteState();
        $existing = $repository->get($submissionId, $context->getId()) ?? [];
        $fileUid = trim((string) ($existing['fileUid'] ?? ''));
        $articleUid = trim((string) ($existing['articleUid'] ?? ''));
        $signatureStatus = $existing['signatureStatus'] ?? null;

        try {
            $client = $this->getApiClient($context);
            $fileFound = null;
            $publicationFound = null;
            $remoteStatus = null;
            $lastStatus = 0;
            $responses = [];
            $errors = [];

            if ($doi !== '') {
                $doiResponse = $client->getPublicationByDoi($doi);
                $doiStatus = (int) ($doiResponse['status'] ?? 0);
                $doiPayload = $doiResponse['json'] ?? $doiResponse['body'] ?? null;
                $responses['publicationByDoi'] = $doiPayload;
                $lastStatus = $doiStatus;

                if ($doiStatus >= 200 && $doiStatus < 300) {
                    if (!is_array($doiResponse['json'] ?? null)) {
                        $errors[] = __('plugins.importexport.metafora.ajax.invalidJson');
                    } else {
                        $publicationFound = true;
                        $doiData = $this->metaforaData($doiPayload);
                        $articleUidFromDoi = $this->stringValue($doiData['article_uid'] ?? null);
                        $fileUidFromDoi = $this->stringValue($doiData['file_uid'] ?? null);
                        if ($articleUidFromDoi !== '') {
                            $articleUid = $articleUidFromDoi;
                        }
                        if ($fileUidFromDoi !== '') {
                            $fileUid = $fileUidFromDoi;
                        }
                        $signatureStatus = $this->signatureStatusFromPayload($doiPayload);
                        $remoteStatus = 'published';
                    }
                } elseif ($doiStatus === 404) {
                    $publicationFound = false;
                    $articleUid = '';
                    $signatureStatus = null;
                    $remoteStatus = 'missing';
                } else {
                    $errors[] = $this->apiError($doiStatus, $doiPayload);
                }
            }

            // DOI lookup may recover file_uid for legacy rows. Querying file
            // status afterwards keeps processing state and article UID current.
            if ($fileUid !== '') {
                $fileResponse = $client->checkStatus($fileUid);
                $fileStatus = (int) ($fileResponse['status'] ?? 0);
                $filePayload = $fileResponse['json'] ?? $fileResponse['body'] ?? null;
                $responses['fileStatus'] = $filePayload;
                $lastStatus = $fileStatus;

                if ($fileStatus >= 200 && $fileStatus < 300) {
                    if (!is_array($fileResponse['json'] ?? null)) {
                        $errors[] = __('plugins.importexport.metafora.ajax.invalidJson');
                    } else {
                        $fileFound = true;
                        $fileData = $this->metaforaData($filePayload);
                        $articles = $fileData['articles'] ?? [];
                        if ($articleUid === '' && is_array($articles)) {
                            $articleUid = $this->stringValue($articles[0] ?? null);
                        }
                        $statusText = $this->stringValue(
                            $fileData['xml']['status']['status_text'] ?? null
                        );
                        if ($publicationFound !== false && $statusText !== '') {
                            $remoteStatus = $statusText;
                        }
                    }
                } elseif ($fileStatus === 404) {
                    $fileFound = false;
                } else {
                    $errors[] = $this->apiError($fileStatus, $filePayload);
                }
            }

            if ($doi === '' && $fileUid === '') {
                $errors[] = __('plugins.importexport.metafora.sync.noIdentifier');
            }

            // A DOI 404 is authoritative for deleted publications. File state
            // is used only when a publication cannot be looked up by DOI.
            $remoteExists = $doi !== ''
                ? ($publicationFound ?? ($existing['remoteExists'] ?? null))
                : $fileFound;
            if ($remoteStatus === null) {
                $remoteStatus = match ($remoteExists) {
                    true => 'published',
                    false => 'missing',
                    default => 'unknown',
                };
            }
            $error = $errors === [] ? null : implode('; ', array_unique($errors));

            $repository->save(
                $submissionId,
                $context->getId(),
                $fileUid !== '' ? $fileUid : null,
                $articleUid !== '' ? $articleUid : null,
                $remoteStatus,
                $signatureStatus,
                $remoteExists,
                $lastStatus,
                $responses,
                $error,
                true
            );

            return array_merge(
                [
                    'submissionId' => $submissionId,
                    'success' => $error === null,
                    'publicationExists' => $publicationFound,
                    'fileExists' => $fileFound,
                ],
                $repository->get($submissionId, $context->getId()) ?? []
            );
        } catch (Throwable $exception) {
            $repository->save(
                $submissionId,
                $context->getId(),
                $fileUid !== '' ? $fileUid : null,
                $articleUid !== '' ? $articleUid : null,
                $existing['remoteStatus'] ?? 'unknown',
                $signatureStatus,
                $existing['remoteExists'] ?? null,
                0,
                null,
                $exception->getMessage(),
                true
            );

            return array_merge(
                ['submissionId' => $submissionId, 'success' => false],
                $repository->get($submissionId, $context->getId()) ?? [],
                ['message' => $exception->getMessage()]
            );
        }
    }

    private function syncIssueRemoteState(int $issueId, Context $context): array
    {
        $issue = Repo::issue()->get($issueId);
        if (!$issue || (int)$issue->getJournalId() !== $context->getId()) {
            return [
                'issueId' => $issueId,
                'success' => false,
                'message' => __('plugins.importexport.metafora.sync.issueNotFound'),
                'items' => [],
            ];
        }

        @set_time_limit(0);
        $items = [];
        foreach ((new IssueArticleManager())->getArticles($issueId, $context) as $article) {
            $items[] = $this->syncSubmissionRemoteState((int)$article['submissionId'], $context);
        }

        $successful = count(array_filter(
            $items,
            static fn(array $item): bool => !empty($item['success'])
        ));

        return [
            'issueId' => $issueId,
            'success' => $items !== [] && $successful === count($items),
            'successful' => $successful,
            'failed' => count($items) - $successful,
            'items' => $items,
            'message' => $items === []
                ? __('plugins.importexport.metafora.error.noPublishedArticles')
                : null,
        ];
    }

    /**
     * Explicitly replace a stale processed JATS file after its publication was
     * deleted in Metafora. The UI requires confirmation before this operation.
     */
    private function replaceDeletedSubmission(int $submissionId, Context $context): array
    {
        $state = $this->syncSubmissionRemoteState($submissionId, $context);
        $fileUid = trim((string)($state['fileUid'] ?? ''));
        if (empty($state['success'])) {
            return [
                'submissionId' => $submissionId,
                'success' => false,
                'message' => (string)($state['message']
                    ?? __('plugins.importexport.metafora.sync.error')),
            ];
        }
        if (($state['remoteExists'] ?? null) !== false || $fileUid === '') {
            return [
                'submissionId' => $submissionId,
                'success' => false,
                'message' => __('plugins.importexport.metafora.restore.notRequired'),
            ];
        }

        [$documents, $validationErrors] = $this->buildDocuments([$submissionId], $context);
        if ($validationErrors !== [] || !isset($documents[$submissionId])) {
            return array_merge([
                'submissionId' => $submissionId,
                'success' => false,
                'message' => __('plugins.importexport.metafora.restore.buildFailed'),
            ], $validationErrors[0] ?? []);
        }

        $includePdf = (bool)$this->getSetting($context->getId(), 'includePdf');
        $pdfPath = $includePdf ? $this->getPdfPath($submissionId, $context) : null;
        if ($includePdf && $pdfPath === null) {
            return [
                'submissionId' => $submissionId,
                'success' => false,
                'message' => __('plugins.importexport.metafora.error.pdfRequired'),
            ];
        }

        $xml = (string)$documents[$submissionId];
        $xmlPath = tempnam($this->getExportPath(), 'metafora-restore-');
        if ($xmlPath === false) {
            throw new \RuntimeException('Unable to create a temporary JATS file.');
        }

        $history = $this->history();
        $historyId = $history->start(
            $submissionId,
            $context->getId(),
            $includePdf ? 'xml_pdf' : 'xml',
            $xml
        );

        try {
            if (file_put_contents($xmlPath, $xml) === false) {
                throw new \RuntimeException('Unable to write a temporary JATS file.');
            }

            $client = $this->getApiClient($context);
            $deleteResponse = $client->deleteFile($fileUid);
            $deleteStatus = (int)($deleteResponse['status'] ?? 0);
            $deletePayload = $deleteResponse['json'] ?? $deleteResponse['body'] ?? null;
            if ($deleteStatus !== 204 && $deleteStatus !== 404) {
                $message = $this->apiError($deleteStatus, $deletePayload);
                $history->finish($historyId, false, $deleteStatus, $deletePayload, $message);
                return [
                    'submissionId' => $submissionId,
                    'success' => false,
                    'httpStatus' => $deleteStatus,
                    'message' => $message,
                ];
            }

            $this->remoteState()->save(
                $submissionId,
                $context->getId(),
                null,
                null,
                'missing',
                null,
                false,
                $deleteStatus,
                $deletePayload,
                null,
                true
            );

            $uploadResponse = $pdfPath
                ? $client->sendJatsXmlPdf($xmlPath, $pdfPath)
                : $client->sendJatsXml($xmlPath);
            $uploadStatus = (int)($uploadResponse['status'] ?? 0);
            $uploadPayload = $uploadResponse['json'] ?? $uploadResponse['body'] ?? null;
            $success = $uploadStatus >= 200 && $uploadStatus < 300;
            $history->finish($historyId, $success, $uploadStatus, $uploadPayload);

            if ($success) {
                $newFileUid = $this->stringValue(
                    $this->findRecursiveValue($uploadPayload, ['file_uid'])
                );
                $this->remoteState()->rememberUpload(
                    $submissionId,
                    $context->getId(),
                    $newFileUid !== '' ? $newFileUid : null,
                    $uploadStatus,
                    $uploadPayload
                );
            }

            return [
                'submissionId' => $submissionId,
                'success' => $success,
                'httpStatus' => $uploadStatus,
                'response' => $uploadPayload,
                'message' => $success
                    ? __('plugins.importexport.metafora.restore.started')
                    : $this->apiError($uploadStatus, $uploadPayload),
            ];
        } catch (Throwable $exception) {
            $history->finish($historyId, false, 0, null, $exception->getMessage());
            return [
                'submissionId' => $submissionId,
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        } finally {
            if (is_file($xmlPath)) {
                unlink($xmlPath);
            }
        }
    }

    private function changePublicationSignature(
        int $submissionId,
        Context $context,
        bool $sign
    ): array {
        $state = $this->syncSubmissionRemoteState($submissionId, $context);
        $articleUid = trim((string) ($state['articleUid'] ?? ''));
        $signatureStatus = $state['signatureStatus'] ?? null;

        if ($articleUid === '' || ($state['remoteExists'] ?? null) !== true) {
            return array_merge($state, [
                'success' => false,
                'message' => __('plugins.importexport.metafora.signature.noArticleUid'),
            ]);
        }
        if (($sign && $signatureStatus !== 'unsigned') || (!$sign && $signatureStatus !== 'signed')) {
            return array_merge($state, [
                'success' => false,
                'message' => __('plugins.importexport.metafora.signature.invalidState'),
            ]);
        }

        try {
            $client = $this->getApiClient($context);
            $response = $sign
                ? $client->signPublication($articleUid)
                : $client->unsignPublication($articleUid);
            $status = (int) ($response['status'] ?? 0);
            $payload = $response['json'] ?? $response['body'] ?? null;
            $success = $status >= 200 && $status < 300;
            $operationError = $success
                ? null
                : ($this->responseMessage($payload)
                    ?: __('plugins.importexport.metafora.signature.error'));

            // Never trust an optimistic local signature value. Always repeat
            // the read-only synchronization after sign/unsign.
            $synced = $this->syncSubmissionRemoteState($submissionId, $context);
            return array_merge($synced, [
                'success' => $success && !empty($synced['success']),
                'operationHttpStatus' => $status,
                'operationResponse' => $payload,
                'message' => $operationError ?? ($synced['message'] ?? null),
            ]);
        } catch (Throwable $exception) {
            $synced = $this->syncSubmissionRemoteState($submissionId, $context);
            return array_merge($synced, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function findRecursiveValue(mixed $data, array $keys): mixed
    {
        if (!is_array($data)) {
            return null;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->findRecursiveValue($value, $keys);
                if ($found !== null && $found !== '') {
                    return $found;
                }
            }
        }

        return null;
    }

    private function signatureStatusFromPayload(mixed $payload): ?string
    {
        $data = $this->metaforaData($payload);
        if (!array_key_exists('signed_at', $data)) {
            return null;
        }

        return $data['signed_at'] === null || trim((string)$data['signed_at']) === ''
            ? 'unsigned'
            : 'signed';
    }

    private function metaforaData(mixed $payload): array
    {
        return is_array($payload) && is_array($payload['data'] ?? null)
            ? $payload['data']
            : [];
    }

    private function apiError(int $status, mixed $payload): string
    {
        $message = $this->responseMessage($payload)
            ?: __('plugins.importexport.metafora.sync.error');
        return 'HTTP ' . $status . ': ' . $message;
    }

    private function duplicateFileUid(int $status, mixed $payload): string
    {
        if ($status !== 409) {
            return '';
        }
        $error = strtoupper($this->stringValue(
            $this->findRecursiveValue($payload, ['error'])
        ));
        if ($error !== 'XML_ALREADY_EXISTS') {
            return '';
        }

        return $this->stringValue(
            $this->findRecursiveValue($payload, ['exists_file_uid'])
        );
    }

    private function responseMessage(mixed $payload): ?string
    {
        $value = $this->findRecursiveValue($payload, ['message', 'detail', 'error']);
        $message = $this->stringValue($value);
        return $message !== '' ? $message : null;
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }
        return '';
    }

    private function outputJson(array $data, int $httpStatus = 200): void
    {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }


    private function getSubmissionMetadata(Context $context): array
    {
        $result = [];
        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([STATUS_PUBLISHED])
            ->orderBy('datePublished', 'DESC')
            ->limit(100)
            ->getMany();
        $issueLabels = [];
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            if (!$publication) {
                continue;
            }
            $issue = null;
            $issueId = (int) $publication->getData('issueId');
            if ($issueId) {
                if (!array_key_exists($issueId, $issueLabels)) {
                    $issueObject = Repo::issue()->get($issueId);
                    $issueLabels[$issueId] = $issueObject
                        ? trim(implode(' ', array_filter([
                            $issueObject->getData('year'),
                            $issueObject->getData('volume') ? 'Т. ' . $issueObject->getData('volume') : null,
                            $issueObject->getData('number') ? '№ ' . $issueObject->getData('number') : null,
                        ])))
                        : null;
                }
                $issue = $issueLabels[$issueId];
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
        $articlesByIssue = [];
        $submissions = Repo::submission()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([STATUS_PUBLISHED])
            ->getMany();
        foreach ($submissions as $submission) {
            $publication = $submission->getCurrentPublication();
            $issueId = (int)($publication?->getData('issueId') ?? 0);
            if ($issueId <= 0) {
                continue;
            }
            $articlesByIssue[$issueId][] = [
                'submissionId' => $submission->getId(),
                'title' => $publication?->getData('title') ?? [],
            ];
        }

        foreach (Repo::issue()->getCollector()->filterByContextIds([$context->getId()])->getMany() as $issue) {
            $articles = $articlesByIssue[$issue->getId()] ?? [];
            $articleStatuses = [];
            $problems = [];
            foreach ($articles as $article) {
                $submissionId = (int)$article['submissionId'];
                $item = $statuses[$submissionId] ?? null;
                $effectiveStatus = $item['effectiveStatus'] ?? $item['status'] ?? 'not_sent';
                $articleStatuses[] = $effectiveStatus;
                if ($effectiveStatus !== 'success') {
                    $problems[] = [
                        'submissionId' => $submissionId,
                        'title' => $this->localizedValue($article['title'] ?? [])
                            ?: ('#' . $submissionId),
                        'status' => $effectiveStatus,
                        'statusLabel' => __('plugins.importexport.metafora.table.' . (
                            $effectiveStatus === 'not_sent' ? 'notSent' : $effectiveStatus
                        )),
                        'error' => $effectiveStatus === 'failed'
                            ? (string)($item['message'] ?? '')
                            : '',
                    ];
                }
            }
            $status = 'not_sent';
            if (in_array('failed', $articleStatuses, true)) {
                $status = 'failed';
            } elseif (in_array('sending', $articleStatuses, true)) {
                $status = 'sending';
            } elseif ($articles && $problems === []) {
                $status = 'success';
            }
            $label = trim(implode(' ', array_filter([
                $issue->getData('year'),
                $issue->getData('volume') ? 'Т. ' . $issue->getData('volume') : null,
                $issue->getData('number') ? '№ ' . $issue->getData('number') : null,
            ])));
            $result[] = [
                'id' => $issue->getId(), 'label' => $label ?: (string) $issue->getId(),
                'articleCount' => count($articles), 'status' => $status, 'problems' => $problems,
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


                case 'inspect-article':

                    $submissionId = (int)($args[2] ?? 0);
                    if ($submissionId <= 0) {
                        throw new \InvalidArgumentException('Submission ID is required.');
                    }

                    $manager = new ExportManager();
                    $documents = $manager->exportJats([$submissionId], $context);
                    if (!isset($documents[$submissionId])) {
                        throw new \RuntimeException("Published submission {$submissionId} was not found in this journal.");
                    }

                    $dir = 'metafora-export/diagnostics';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }

                    $xmlFile = $dir . '/' . $submissionId . '.xml';
                    $jsonFile = $dir . '/' . $submissionId . '.json';
                    $xml = $documents[$submissionId];
                    file_put_contents($xmlFile, $xml);
                    file_put_contents($jsonFile, $manager->exportJson([$submissionId], $context));

                    echo "XML: {$xmlFile}\n";
                    echo 'SHA256: ' . hash('sha256', $xml) . "\n";
                    echo "MAPPED DATA: {$jsonFile}\n";
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


                case 'diagnose-remote':

                    $submissionId = (int)($args[2] ?? 0);
                    if ($submissionId <= 0) {
                        throw new \InvalidArgumentException('Submission ID is required.');
                    }

                    $submission = Repo::submission()->get($submissionId);
                    if (
                        !$submission
                        || (int)$submission->getData('contextId') !== $context->getId()
                    ) {
                        throw new \RuntimeException("Submission {$submissionId} was not found in this journal.");
                    }

                    $publication = $submission->getCurrentPublication();
                    $doi = trim((string)($publication?->getDoi() ?? ''));
                    $remoteRepository = new RemoteStateRepository();
                    $remoteRepository->ensureTable();
                    $remote = $remoteRepository->get($submissionId, $context->getId()) ?? [];
                    $fileUid = trim((string)($args[3] ?? ($remote['fileUid'] ?? '')));

                    if ($fileUid === '') {
                        $historyItem = $this->history()->getLatestForJournal($context->getId())[$submissionId] ?? [];
                        $fileUid = $this->stringValue(
                            $this->findRecursiveValue($historyItem['response'] ?? null, ['file_uid'])
                        );
                    }

                    $client = $this->getApiClient($context);
                    if ($doi !== '') {
                        $result = $client->getPublicationByDoi($doi);
                        echo json_encode([
                            'endpoint' => 'GET /publications/doi/{doi}',
                            'doi' => $doi,
                            'httpStatus' => $result['status'] ?? 0,
                            'response' => $result['json'] ?? $result['body'] ?? null,
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    } else {
                        echo "GET /publications/doi/{doi}: skipped; submission has no DOI\n";
                    }

                    if ($fileUid !== '') {
                        $result = $client->checkStatus($fileUid);
                        echo json_encode([
                            'endpoint' => 'GET /files/status/?file_uid=...',
                            'file_uid' => $fileUid,
                            'httpStatus' => $result['status'] ?? 0,
                            'response' => $result['json'] ?? $result['body'] ?? null,
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    } else {
                        echo "GET /files/status/?file_uid=...: skipped; file_uid is not known locally\n";
                    }

                    return;


                case 'sync-remote':

                    $submissionId = (int)($args[2] ?? 0);
                    if ($submissionId <= 0) {
                        throw new \InvalidArgumentException('Submission ID is required.');
                    }

                    $this->history()->ensureTable();
                    $this->remoteState()->ensureTable();
                    echo json_encode(
                        $this->syncSubmissionRemoteState($submissionId, $context),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ) . "\n";
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
        echo "  php tools/importExport.php MetaforaExportPlugin inspect-article <journal> <submissionId>\n";
        echo "  php tools/importExport.php MetaforaExportPlugin test-connection <journal>\n";
        echo "  php tools/importExport.php MetaforaExportPlugin diagnose-remote <journal> <submissionId> [fileUid]\n";
        echo "  php tools/importExport.php MetaforaExportPlugin sync-remote <journal> <submissionId>\n";
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
