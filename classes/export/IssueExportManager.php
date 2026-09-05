<?php

namespace APP\plugins\importexport\metafora\classes\export;

use APP\facades\Repo;
use PKP\context\Context;

class IssueExportManager
{
    public function getIssues(Context $context): array
    {
        $issues = [];

        $collector = Repo::issue()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->orderBy('datePublished', 'DESC');

        foreach ($collector->getMany() as $issue) {

            $submissions = Repo::submission()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByIssueIds([$issue->getId()])
                ->filterByStatus([STATUS_PUBLISHED])
                ->getMany();

            $issues[] = [
                'id' => $issue->getId(),
                'volume' => $issue->getData('volume'),
                'number' => $issue->getData('number'),
                'year' => $issue->getData('year'),
                'title' => $issue->getData('title'),
                'datePublished' => $issue->getData('datePublished'),
                'articlesCount' => count($submissions),
            ];
        }

        return $issues;
    }
}
