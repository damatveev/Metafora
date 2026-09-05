<?php

namespace APP\plugins\importexport\metafora\classes\export;

class MetaforaApiSerializer
{
    public function serialize(array $publications): string
    {
        $items = [];

        foreach ($publications as $publication) {

            $p = $publication->toArray();

            $items[] = [

                'publicationId' => $p['submissionId'] ?? null,

                'title' => $p['title'] ?? [],

                'abstract' => $p['abstract'] ?? [],

                'language' => $p['language']
                    ?? $p['locale']
                    ?? 'en',

                'publicationDate' =>
                    $p['datePublished']
                    ?? null,


                'authors' =>
                    $this->authors(
                        $p['authors'] ?? []
                    ),


                'affiliations' =>
                    $this->affiliations(
                        $p['authors'] ?? []
                    ),


                'identifiers' => [

                    'doi' =>
                        $p['doi']
                        ?? null,

                    'url' =>
                        $p['url']
                        ?? null,

                ],


                'journal' =>
                    $p['journal']
                    ?? [],


                'issue' =>
                    $p['issue']
                    ?? [],


                'publisher' => [
                    'name' =>
                        $p['journal']['publisher']
                        ?? null
                ],


                'license' =>
                    $p['license']
                    ?? null,


                'subjects' =>
                    $p['keywords']
                    ?? [],


                'files' =>
                    $p['files']
                    ?? [],


                'references' =>
                    $p['references']
                    ?? []

            ];
        }


        return json_encode(
            [
                'schema' => 'metafora-api-v1',
                'publications' => $items
            ],
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_THROW_ON_ERROR
        );
    }



    private function authors(array $authors): array
    {
        return array_map(

            static function ($a) {

                return [

                    'givenName' =>
                        $a['givenName']
                        ?? '',

                    'familyName' =>
                        $a['familyName']
                        ?? '',

                    'orcid' =>
                        $a['orcid']
                        ?? null,

                    'affiliation' =>
                        $a['affiliation']
                        ?? null

                ];

            },

            $authors

        );
    }



    private function affiliations(array $authors): array
    {
        $result=[];

        foreach ($authors as $a) {

            if (!empty($a['affiliation'])) {

                $result[] =
                    $a['affiliation'];

            }

        }

        return array_values(
            array_unique($result)
        );
    }
}
