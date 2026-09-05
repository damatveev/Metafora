<?php

namespace APP\plugins\importexport\metafora\classes\export;

use APP\plugins\importexport\metafora\classes\api\MetaforaApiClient;
use RuntimeException;

class MetaforaSendManager
{
    public function __construct(
        private readonly MetaforaApiClient $client
    ) {
    }


    public function sendIssue(
        int $issueId
    ): array {

        $directory = 'metafora-export/issue-' . $issueId;

        if (!is_dir($directory)) {
            throw new RuntimeException(
                'Export directory not found: ' . $directory
            );
        }


        $files = glob(
            $directory . '/*.xml'
        );


        if (!$files) {
            throw new RuntimeException(
                'No JATS files found for issue ' . $issueId
            );
        }


        $result = [];


        foreach ($files as $file) {

            $response = $this->client->sendJatsXml(
                $file
            );


            $result[] = [
                'file' => $file,
                'status' => $response['status'],
                'response' => $response['json'] ?? $response['body'],
            ];
        }


        return $result;
    }
}
