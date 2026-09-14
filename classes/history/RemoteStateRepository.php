<?php

/**
 * @file plugins/importexport/metafora/classes/history/RemoteStateRepository.php
 *
 * Metafora Export Plugin for OJS 3.5
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\history;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemoteStateRepository
{
    private const TABLE = 'metafora_remote_state';

    public function ensureTable(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->bigInteger('article_id');
                $table->bigInteger('journal_id');
                $table->string('file_uid', 64)->nullable();
                $table->string('article_uid', 64)->nullable();
                $table->string('remote_status', 64)->nullable();
                $table->string('signature_status', 16)->nullable();
                $table->boolean('remote_exists')->nullable();
                $table->integer('response_code')->nullable();
                $table->text('error_message')->nullable();
                $table->longText('response_payload')->nullable();
                $table->dateTime('synced_at')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->unique(['journal_id', 'article_id'], 'metafora_remote_unique');
                $table->index(['journal_id', 'remote_status'], 'metafora_remote_status');
            });
        }
    }

    public function get(int $articleId, int $journalId): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('article_id', $articleId)
            ->where('journal_id', $journalId)
            ->first();

        return $row ? $this->mapRow($row) : null;
    }

    public function getForJournal(int $journalId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('journal_id', $journalId)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $state = $this->mapRow($row);
            $result[(int) $row->article_id] = $state;
        }

        return $result;
    }

    public function save(
        int $articleId,
        int $journalId,
        ?string $fileUid,
        ?string $articleUid,
        ?string $remoteStatus,
        ?string $signatureStatus,
        ?bool $remoteExists,
        int $responseCode = 0,
        mixed $response = null,
        ?string $error = null,
        bool $markSynced = true
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $payload = [
            'file_uid' => $fileUid ?: null,
            'article_uid' => $articleUid ?: null,
            'remote_status' => $remoteStatus ?: null,
            'signature_status' => $signatureStatus ?: null,
            'remote_exists' => $remoteExists,
            'response_code' => $responseCode ?: null,
            'error_message' => $error ?: null,
            'response_payload' => $this->encodePayload($response),
            'synced_at' => $markSynced ? $now : null,
            'updated_at' => $now,
        ];

        $exists = DB::table(self::TABLE)
            ->where('article_id', $articleId)
            ->where('journal_id', $journalId)
            ->exists();

        if ($exists) {
            DB::table(self::TABLE)
                ->where('article_id', $articleId)
                ->where('journal_id', $journalId)
                ->update($payload);
            return;
        }

        DB::table(self::TABLE)->insert(array_merge($payload, [
            'article_id' => $articleId,
            'journal_id' => $journalId,
            'created_at' => $now,
        ]));
    }

    public function rememberUpload(
        int $articleId,
        int $journalId,
        ?string $fileUid,
        int $responseCode,
        mixed $response
    ): void {
        $existing = $this->get($articleId, $journalId);
        $this->save(
            $articleId,
            $journalId,
            $fileUid ?: ($existing['fileUid'] ?? null),
            $existing['articleUid'] ?? null,
            'uploaded',
            $existing['signatureStatus'] ?? null,
            true,
            $responseCode,
            $response,
            null,
            false
        );
    }

    private function mapRow(object $row): array
    {
        return [
            'submissionId' => (int) $row->article_id,
            'fileUid' => $row->file_uid,
            'articleUid' => $row->article_uid,
            'remoteStatus' => $row->remote_status,
            'signatureStatus' => $row->signature_status,
            'remoteExists' => $row->remote_exists === null ? null : (bool) $row->remote_exists,
            'httpStatus' => $row->response_code,
            'message' => $row->error_message,
            'response' => $this->decodePayload($row->response_payload),
            'syncedAt' => $row->synced_at,
            'updatedAt' => $row->updated_at,
        ];
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
}
