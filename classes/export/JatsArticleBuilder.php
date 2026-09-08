<?php

/**
 * @file plugins/importexport/metafora/classes/export/JatsArticleBuilder.php
 *
 * Builds JATS 1.4 article XML from the internal Metafora publication model.
 *
 * @author Dmitry Matveev (Дмитрий Матвеев)
 * @license GPL-3.0-or-later
 */

namespace APP\plugins\importexport\metafora\classes\export;

use APP\plugins\importexport\metafora\classes\model\MetaforaPublication;
use DOMDocument;
use DOMElement;
use RuntimeException;

class JatsArticleBuilder
{
    public function build(MetaforaPublication $publication, bool $includeReferences = true): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $language = (string) ($publication->metadata['language'] ?? 'en');

        $article = $doc->createElement('article');
        $article->setAttribute('article-type', 'research-article');
        $article->setAttribute('dtd-version', '1.4');
        $article->setAttribute('xml:lang', $language ?: 'en');
        $article->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xlink',
            'http://www.w3.org/1999/xlink'
        );

        $doc->appendChild($article);

        $front = $doc->createElement('front');
        $article->appendChild($front);

        $this->appendJournalMeta($doc, $front, $publication);
        $this->appendArticleMeta($doc, $front, $publication);

        $body = $doc->createElement('body');
        $article->appendChild($body);

        if ($includeReferences && $publication->references !== []) {
            $back = $doc->createElement('back');
            $article->appendChild($back);
            $this->appendReferences($doc, $back, $publication->references, $language);
        }

        $xml = $doc->saveXML();

        if (is_string($xml)) {
            $xml = preg_replace(
                '/&(?!amp;|lt;|gt;|quot;|apos;|#\\d+;|#x[0-9A-Fa-f]+;)/',
                '&amp;',
                $xml
            ) ?? $xml;
        }

        if (!is_string($xml) || $xml === '') {
            throw new RuntimeException('Unable to generate JATS XML.');
        }

        return $xml;
    }

    private function appendJournalMeta(
        DOMDocument $doc,
        DOMElement $front,
        MetaforaPublication $publication
    ): void {
        $journalMeta = $doc->createElement('journal-meta');
        $front->appendChild($journalMeta);

        $journalId = $doc->createElement(
            'journal-id',
            $this->text($publication->journal['acronym'] ?? '')
        );
        $journalId->setAttribute('journal-id-type', 'publisher-id');
        $journalMeta->appendChild($journalId);

        $journalTitleGroup = $doc->createElement('journal-title-group');
        $journalMeta->appendChild($journalTitleGroup);

        $journalTitleGroup->appendChild(
            $doc->createElement(
                'journal-title',
                $this->text($this->localized($publication->journal['name'] ?? null))
            )
        );

        $printIssn = trim((string) ($publication->journal['printIssn'] ?? ''));
        if ($printIssn !== '') {
            $issn = $doc->createElement('issn', $this->text($printIssn));
            $issn->setAttribute('pub-type', 'ppub');
            $journalMeta->appendChild($issn);
        }

        $onlineIssn = trim((string) ($publication->journal['onlineIssn'] ?? ''));
        if ($onlineIssn !== '') {
            $issn = $doc->createElement('issn', $this->text($onlineIssn));
            $issn->setAttribute('pub-type', 'epub');
            $journalMeta->appendChild($issn);
        }
    }

    private function appendArticleMeta(
        DOMDocument $doc,
        DOMElement $front,
        MetaforaPublication $publication
    ): void {
        $articleMeta = $doc->createElement('article-meta');
        $front->appendChild($articleMeta);

        $articleId = $doc->createElement(
            'article-id',
            (string) $publication->submissionId
        );
        $articleId->setAttribute('pub-id-type', 'publisher-id');
        $articleMeta->appendChild($articleId);

        if ($publication->doi) {
            $doi = $doc->createElement(
                'article-id',
                $this->text($publication->doi)
            );
            $doi->setAttribute('pub-id-type', 'doi');
            $articleMeta->appendChild($doi);
        }

        $this->appendTitles($doc, $articleMeta, $publication->title);
        $this->appendAuthors($doc, $articleMeta, $publication->authors);
        $this->appendPublicationDate($doc, $articleMeta, $publication);

        if (!empty($publication->issue['volume'])) {
            $articleMeta->appendChild(
                $doc->createElement(
                    'volume',
                    $this->text($publication->issue['volume'])
                )
            );
        }

        if (!empty($publication->issue['number'])) {
            $articleMeta->appendChild(
                $doc->createElement(
                    'issue',
                    $this->text($publication->issue['number'])
                )
            );
        }

        $this->appendPages($doc, $articleMeta, (string) ($publication->metadata['pages'] ?? ''));
        $url = trim((string) ($publication->metadata['url'] ?? ''));
        if ($url !== '') {
            $selfUri = $doc->createElement('self-uri');
            $selfUri->setAttribute('content-type', 'web');
            $selfUri->setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', $url);
            $articleMeta->appendChild($selfUri);
        }
        $this->appendPermissions($doc, $articleMeta, $publication);

        $this->appendAbstracts($doc, $articleMeta, $publication->abstract);
        $this->appendKeywords($doc, $articleMeta, $publication->keywords);
    }

    private function appendTitles(
        DOMDocument $doc,
        DOMElement $articleMeta,
        array $titles
    ): void {
        if ($titles === []) {
            return;
        }

        $primaryLocale = array_key_first($titles);
        $primaryTitle = $titles[$primaryLocale] ?? '';

        $titleGroup = $doc->createElement('title-group');
        $articleMeta->appendChild($titleGroup);

        $articleTitle = $doc->createElement(
            'article-title',
            $this->text($primaryTitle)
        );
        $titleGroup->appendChild($articleTitle);

        foreach ($titles as $locale => $title) {
            if ($locale === $primaryLocale || !is_string($title) || trim($title) === '') {
                continue;
            }

            $translated = $doc->createElement('trans-title-group');
            $translated->setAttribute('xml:lang', (string) $locale);

            $translated->appendChild(
                $doc->createElement('trans-title', $this->text($title))
            );

            $titleGroup->appendChild($translated);
        }
    }

    private function appendAuthors(
        DOMDocument $doc,
        DOMElement $articleMeta,
        array $authors
    ): void {
        if ($authors === []) {
            return;
        }

        $contribGroup = $doc->createElement('contrib-group');
        $articleMeta->appendChild($contribGroup);

        foreach ($authors as $author) {
            $contrib = $doc->createElement('contrib');
            $contrib->setAttribute('contrib-type', 'author');
            $contribGroup->appendChild($contrib);

            $name = $doc->createElement('name');
            $name->setAttribute('name-style', 'western');
            $contrib->appendChild($name);

            $familyName = $this->localized($author['familyName'] ?? null);
            $givenName = $this->localized($author['givenName'] ?? null);

            if ($familyName !== '') {
                $name->appendChild(
                    $doc->createElement('surname', $this->text($familyName))
                );
            }

            if ($givenName !== '') {
                $name->appendChild(
                    $doc->createElement('given-names', $this->text($givenName))
                );
            }

            $email = trim((string) ($author['email'] ?? ''));
            if ($email !== '') {
                $contrib->appendChild($doc->createElement('email', $this->text($email)));
            }

            $orcid = trim((string) ($author['orcid'] ?? ''));

            if ($orcid !== '') {
                $contribId = $doc->createElement(
                    'contrib-id',
                    $this->text($orcid)
                );
                $contribId->setAttribute('contrib-id-type', 'orcid');
                $contrib->appendChild($contribId);
            }

            $affiliations = [];
            foreach ((array) ($author['affiliations'] ?? []) as $candidate) {
                if (is_array($candidate) && trim((string) ($candidate['formatted'] ?? '')) !== '') {
                    $affiliations[] = $candidate;
                }
            }
            if ($affiliations === []) {
                $fallback = $this->localized($author['affiliation'] ?? null);
                if ($fallback !== '') {
                    $affiliations[] = ['organization' => $fallback, 'locale' => null];
                }
            }

            foreach ($affiliations as $affiliation) {
                $aff = $doc->createElement('aff');
                if (!empty($affiliation['locale'])) {
                    $aff->setAttribute('xml:lang', (string) $affiliation['locale']);
                }

                $organization = trim((string) ($affiliation['organization'] ?? ''));
                if ($organization !== '') {
                    $aff->appendChild(
                        $doc->createElement('institution', $this->text($organization))
                    );
                }

                $city = trim((string) ($affiliation['city'] ?? ''));
                $state = trim((string) ($affiliation['state'] ?? ''));
                $postalCode = trim((string) ($affiliation['postalCode'] ?? ''));
                if ($city !== '' || $state !== '' || $postalCode !== '') {
                    $addrLine = $doc->createElement('addr-line');
                    if ($city !== '') {
                        $cityNode = $doc->createElement('named-content', $this->text($city));
                        $cityNode->setAttribute('content-type', 'city');
                        $addrLine->appendChild($cityNode);
                    }
                    if ($state !== '') {
                        $stateNode = $doc->createElement('named-content', $this->text($state));
                        $stateNode->setAttribute('content-type', 'state');
                        $addrLine->appendChild($stateNode);
                    }
                    if ($postalCode !== '') {
                        $addrLine->appendChild(
                            $doc->createElement('postal-code', $this->text($postalCode))
                        );
                    }
                    $aff->appendChild($addrLine);
                }

                $country = trim((string) ($affiliation['country'] ?? ''));
                if ($country !== '') {
                    $aff->appendChild($doc->createElement('country', $this->text($country)));
                }

                $contrib->appendChild($aff);
            }
        }
    }

    private function appendPages(DOMDocument $doc, DOMElement $articleMeta, string $pages): void
    {
        $pages = trim($pages);
        if ($pages === '') {
            return;
        }

        $parts = preg_split('/\s*[-–—]\s*/u', $pages, 2) ?: [];
        if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
            $articleMeta->appendChild($doc->createElement('fpage', $this->text($parts[0])));
            $articleMeta->appendChild($doc->createElement('lpage', $this->text($parts[1])));
            return;
        }

        $articleMeta->appendChild($doc->createElement('elocation-id', $this->text($pages)));
    }

    private function appendPermissions(
        DOMDocument $doc,
        DOMElement $articleMeta,
        MetaforaPublication $publication
    ): void {
        $licenseUrl = trim((string) ($publication->metadata['licenseUrl'] ?? ''));
        $copyrightYear = trim((string) ($publication->metadata['copyrightYear'] ?? ''));
        $copyrightHolders = $publication->metadata['copyrightHolder'] ?? [];
        if ($licenseUrl === '' && $copyrightYear === '' && $this->localized($copyrightHolders) === '') {
            return;
        }

        $permissions = $doc->createElement('permissions');
        foreach ((array) $copyrightHolders as $locale => $holder) {
            if (!is_string($holder) || trim($holder) === '') {
                continue;
            }
            $statement = $doc->createElement(
                'copyright-statement',
                $this->text('© ' . ($copyrightYear !== '' ? $copyrightYear . ' ' : '') . $holder)
            );
            if (is_string($locale)) {
                $statement->setAttribute('xml:lang', $locale);
            }
            $permissions->appendChild($statement);
        }
        if ($copyrightYear !== '') {
            $permissions->appendChild($doc->createElement('copyright-year', $this->text($copyrightYear)));
        }

        if ($licenseUrl !== '') {
            foreach (['ru' => 'Материал распространяется на условиях лицензии ', 'en' => 'This article is distributed under the terms of the license '] as $locale => $prefix) {
                $license = $doc->createElement('license');
                $license->setAttribute('license-type', 'open-access');
                $license->setAttribute('xml:lang', $locale);
                $license->setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', $licenseUrl);
                $license->appendChild($doc->createElement('license-p', $this->text($prefix . $licenseUrl)));
                $permissions->appendChild($license);
            }
        }

        if ($permissions->hasChildNodes()) {
            $articleMeta->appendChild($permissions);
        }
    }

    private function appendPublicationDate(
        DOMDocument $doc,
        DOMElement $articleMeta,
        MetaforaPublication $publication
    ): void {
        $date = $publication->metadata['datePublished']
            ?? $publication->issue['datePublished']
            ?? null;

        if (!$date) {
            return;
        }

        $timestamp = strtotime((string) $date);

        if (!$timestamp) {
            return;
        }

        $pubDate = $doc->createElement('pub-date');
        $pubDate->setAttribute('publication-format', 'electronic');
        $pubDate->setAttribute('date-type', 'pub');

        $pubDate->appendChild(
            $doc->createElement('day', date('d', $timestamp))
        );
        $pubDate->appendChild(
            $doc->createElement('month', date('m', $timestamp))
        );
        $pubDate->appendChild(
            $doc->createElement('year', date('Y', $timestamp))
        );

        $articleMeta->appendChild($pubDate);
    }

    private function appendAbstracts(
        DOMDocument $doc,
        DOMElement $articleMeta,
        array $abstracts
    ): void {
        foreach ($abstracts as $locale => $abstract) {
            if (!is_string($abstract) || $this->plainText($abstract) === '') {
                continue;
            }

            $node = $doc->createElement('abstract');
            $node->setAttribute('xml:lang', (string) $locale);

            $node->appendChild(
                $doc->createElement(
                    'p',
                    $this->plainText($abstract)
                )
            );

            $articleMeta->appendChild($node);
        }
    }

    private function appendKeywords(
        DOMDocument $doc,
        DOMElement $articleMeta,
        array $keywords
    ): void {
        foreach ($keywords as $locale => $values) {
            if (is_string($values)) {
                $values = preg_split('/[,;]+/u', $values) ?: [];
            }

            if (!is_array($values) || $values === []) {
                continue;
            }

            $group = $doc->createElement('kwd-group');
            $group->setAttribute('xml:lang', (string) $locale);

            foreach ($values as $keyword) {
                if (!is_string($keyword) || trim($keyword) === '') {
                    continue;
                }

                $group->appendChild(
                    $doc->createElement('kwd', $this->text($keyword))
                );
            }

            if ($group->hasChildNodes()) {
                $articleMeta->appendChild($group);
            }
        }
    }

    private function appendReferences(
        DOMDocument $doc,
        DOMElement $back,
        array $references,
        string $language
    ): void {
        $refList = $doc->createElement('ref-list');
        $refList->setAttribute('xml:lang', $language ?: 'en');
        $back->appendChild($refList);

        foreach ($references as $index => $reference) {
            if (!is_string($reference) || trim($reference) === '') {
                continue;
            }

            $ref = $doc->createElement('ref');
            $ref->setAttribute('id', 'R' . ($index + 1));

            $mixedCitation = $doc->createElement(
                'mixed-citation',
                $this->text($reference)
            );

            $ref->appendChild($mixedCitation);
            $refList->appendChild($ref);
        }
    }

    private function localized(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && trim($item) !== '') {
                    return trim($item);
                }
            }
        }

        return '';
    }

    private function text(mixed $value): string
    {
        $value = (string) $value;

        $value = html_entity_decode(
            $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $value = str_replace(
            ["\u{00A0}", "&nbsp;"],
            ' ',
            $value
        );

        return trim($value);
    }

    private function plainText(string $value): string
    {
        return trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
