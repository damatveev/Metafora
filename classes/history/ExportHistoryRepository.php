<?php

namespace APP\plugins\importexport\metafora\classes\history;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

class ExportHistoryRepository
{
    private const TABLE = 'metafora_export_history';

    public function ensureTable(): void
    {
        $schema = Capsule::schema();
        if ($schema->hasTable(self::TABLE)) {
            return;
        }

        $schema->create(self::TABLE, function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->bigInteger('article_id');
            $table->bigInteger('journal_id');
            $table->string('export_type', 16);
            $table->string('status', 16);
            $table->text('error_message')->nullable();
            $table->integer('response_code')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['journal_id', 'article_id', 'created_at'], 'metafora_history_latest');
            $table->index(['journal_id', 'status'], 'metafora_history_status');
        });
    }

    public function start(int $articleId, int $journalId, string $exportType, string $requestPayload): int
    {
        $now = gmdate('Y-m-d H:i:s');

        return (int) Capsule::table(self::TABLE)->insertGetId([
            'article_id' => $articleId,
            'journal_id' => $journalId,
            'export_type' => $exportType,
            'status' => 'sending',
            'error_message' => null,
            'response_code' => null,
            'request_payload' => $requestPayload,
            'response_payload' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function finish(int $historyId, bool $success, int $responseCode, mixed $response, ?string $error = null): void
    {
        Capsule::table(self::TABLE)->where('id', $historyId)->update([
            'status' => $success ? 'success' : 'failed',
            'error_message' => $success ? null : ($error ?: $this->responseMessage($response)),
            'response_code' => $responseCode ?: null,
            'response_payload' => $this->encodePayload($response),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function recordFailure(
        int $articleId,
        int $journalId,
        string $exportType,
        string $error,
        int $responseCode = 0,
        mixed $response = null,
        ?string $requestPayload = null
    ): void {
        $historyId = $this->start($articleId, $journalId, $exportType, $requestPayload ?? '');
        $this->finish($historyId, false, $responseCode, $response, $error);
    }

    public function getLatestForJournal(int $journalId): array
    {
        $rows = Capsule::table(self::TABLE)
            ->whereIn('id', function ($query) use ($journalId): void {
                $query->from(self::TABLE)
                    ->selectRaw('MAX(id)')
                    ->where('journal_id', $journalId)
                    ->groupBy('article_id');
            })
            ->get();
        $latest = [];

        foreach ($rows as $row) {
            $articleId = (int) $row->article_id;
            if (isset($latest[$articleId])) {
                continue;
            }
            $latest[$articleId] = [
                'submissionId' => $articleId,
                'exportType' => $row->export_type,
                'status' => $row->status,
                'success' => $row->status === 'success',
                'httpStatus' => $row->response_code,
                'message' => $row->error_message,
                'response' => $this->decodePayload($row->response_payload),
                'createdAt' => $row->created_at,
                'updatedAt' => $row->updated_at,
            ];
        }

        return $latest;
    }

    public function importLegacy(int $journalId, array $items): void
    {
        if (Capsule::table(self::TABLE)->where('journal_id', $journalId)->exists()) {
            return;
        }

        foreach ($items as $item) {
            $articleId = (int) ($item['submissionId'] ?? 0);
            if ($articleId <= 0) {
                continue;
            }
            $exportType = !empty($item['pdfIncluded']) ? 'xml_pdf' : 'xml';
            $historyId = $this->start($articleId, $journalId, $exportType, '');
            $success = !empty($item['success']);
            $response = $item['response'] ?? null;
            $this->finish(
                $historyId,
                $success,
                (int) ($item['httpStatus'] ?? 0),
                $response,
                $success ? null : (string) ($item['message'] ?? '')
            );
        }
    }

    private function encodePayload(mixed $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function decodePayload(?string $payload): mixed
    {
        if ($payload === null || $payload === '') {
            return null;
        }
        $decoded = json_decode($payload, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $payload;
    }

    private function responseMessage(mixed $response): ?string
    {
        if (is_array($response) && isset($response['message'])) {
            return (string) $response['message'];
        }
        return is_string($response) ? $response : null;
    }
}
