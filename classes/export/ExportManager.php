<?php

namespace APP\plugins\importexport\metafora\classes\export;

use APP\facades\Repo;
use APP\plugins\importexport\metafora\classes\mapping\OjsPublicationMapper;
use APP\plugins\importexport\metafora\classes\validation\PublicationValidator;
use APP\plugins\importexport\metafora\classes\validation\JatsValidator;
use APP\plugins\importexport\metafora\classes\model\MetaforaPublication;
use PKP\context\Context;
use RuntimeException;

class ExportManager
{
    public function __construct(
        private readonly OjsPublicationMapper $mapper = new OjsPublicationMapper(),
        private readonly PublicationValidator $validator = new PublicationValidator(),
    ) {
    }


    public function collect(array $submissionIds, Context $context): array
    {
        $publications = [];

        $collector = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([STATUS_PUBLISHED]);

        foreach ($collector->getMany() as $submission) {

            if ($submissionIds !== [] &&
                !in_array($submission->getId(), $submissionIds)) {
                continue;
            }

            $publication = $this->mapper->map(
                $submission,
                $context
            );

            $this->validate($publication, $submission->getId());

            $publications[] = $publication;
        }

        return $publications;
    }


    public function collectByIssue(int $issueId, Context $context): array
    {
        $publications = [];

        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByIssueIds([$issueId])
            ->filterByStatus([STATUS_PUBLISHED])
            ->getMany();


        foreach ($submissions as $submission) {

            $publication = $this->mapper->map(
                $submission,
                $context
            );

            $this->validate($publication, $submission->getId());

            $publications[] = $publication;
        }

        return $publications;
    }


    public function exportJson(array $submissionIds, Context $context): string
    {
        $payload = array_map(
            static fn($publication) => $publication->toArray(),
            $this->collect($submissionIds, $context)
        );


        return json_encode(
            [
                'schema' => 'metafora-ojs-export/0.1',
                'contextId' => $context->getId(),
                'publications' => $payload,
            ],
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );
    }


    public function exportJats(array $submissionIds, Context $context): array
    {
        $publications = $this->collect($submissionIds, $context);

        return $this->buildJats($publications);
    }


    public function exportJatsByIssue(int $issueId, Context $context): array
    {
        $publications = $this->collectByIssue(
            $issueId,
            $context
        );

        return $this->buildJats($publications);
    }


    private function buildJats(array $publications): array
    {
        $builder = new JatsArticleBuilder();

        $schemaPath = dirname(__DIR__, 2)
            . '/schemas/jats/JATS-archive-oasis-article1-4.xsd';


        $validator = new JatsValidator(
            $schemaPath
        );


        $result = [];


        foreach ($publications as $publication) {

            $xml = $builder->build($publication);

            $validator->validate($xml);

            $result[$publication->submissionId] = $xml;
        }


        return $result;
    }


    private function validate(
        MetaforaPublication $publication,
        int $submissionId
    ): void {

        $errors = $this->validator->validate($publication);

        if ($errors !== []) {

            throw new RuntimeException(
                sprintf(
                    'Submission %d failed Metafora validation: %s',
                    $submissionId,
                    implode(' ', $errors)
                )
            );
        }
    }
}
