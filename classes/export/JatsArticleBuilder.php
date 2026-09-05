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
    public function build(MetaforaPublication $publication): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $language = (string) ($publication->metadata['language'] ?? 'en');

        $article = $doc->createElement('article');
        $article->setAttribute('article-type', 'research-article');
        $article->setAttribute('dtd-version', '1.4');
        $article->setAttribute('xml:lang', $language ?: 'en');

        $doc->appendChild($article);

        $front = $doc->createElement('front');
        $article->appendChild($front);

        $this->appendJournalMeta($doc, $front, $publication);
        $this->appendArticleMeta($doc, $front, $publication);

        $body = $doc->createElement('body');
        $article->appendChild($body);

        if ($publication->references !== []) {
            $back = $doc->createElement('back');
            $article->appendChild($back);
            $this->appendReferences($doc, $back, $publication->references);
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

            $orcid = trim((string) ($author['orcid'] ?? ''));

            if ($orcid !== '') {
                $contribId = $doc->createElement(
                    'contrib-id',
                    $this->text($orcid)
                );
                $contribId->setAttribute('contrib-id-type', 'orcid');
                $contrib->appendChild($contribId);
            }

            $affiliation = $this->localized($author['affiliation'] ?? null);

            if ($affiliation !== '') {
                $aff = $doc->createElement('aff');
                $aff->appendChild(
                    $doc->createElement(
                        'institution',
                        $this->text($affiliation)
                    )
                );
                $contrib->appendChild($aff);
            }
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
            if (!is_string($abstract) || trim(strip_tags($abstract)) === '') {
                continue;
            }

            $node = $doc->createElement('abstract');
            $node->setAttribute('xml:lang', (string) $locale);

            $node->appendChild(
                $doc->createElement(
                    'p',
                    $this->text(strip_tags($abstract))
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
        array $references
    ): void {
        $refList = $doc->createElement('ref-list');
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
}
