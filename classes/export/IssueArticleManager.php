<?php

namespace APP\plugins\importexport\metafora\classes\export;

use APP\facades\Repo;
use PKP\context\Context;

class IssueArticleManager
{
    public function getArticles(
        int $issueId,
        Context $context
    ): array {

        $result = [];

        $submissions = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByIssueIds([$issueId])
            ->filterByStatus([STATUS_PUBLISHED])
            ->getMany();

        foreach ($submissions as $submission) {

            $publication = $submission->getCurrentPublication();

            $result[] = [
                'submissionId' => $submission->getId(),

                'title' => $publication
                    ? $publication->getData('title')
                    : [],

                'doi' => $publication
                    ? $publication->getDoi()
                    : null,
            ];
        }

        return $result;
    }
}
