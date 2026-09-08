<?php

namespace APP\plugins\importexport\metafora\classes\export;

class PdfReferenceExtractor
{
    public function extract(string $pdfPath): array
    {
        if (!is_file($pdfPath) || !is_readable($pdfPath) || !function_exists('exec')) {
            return [];
        }
        $binary = $this->binary();
        if ($binary === null) {
            return [];
        }
        $output = [];
        $status = 1;
        exec(escapeshellarg($binary) . ' -layout -nopgbrk ' . escapeshellarg($pdfPath) . ' - 2>/dev/null', $output, $status);
        if ($status !== 0 || $output === []) {
            return [];
        }
        return $this->sections($output);
    }

    private function binary(): ?string
    {
        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function sections(array $lines): array
    {
        $groups = [];
        $locale = null;
        $buffer = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if (preg_match('/^(список\s+литературы|литература|библиографический\s+список)\s*$/ui', $line)) {
                $this->flush($groups, $locale, $buffer);
                $locale = 'ru';
                $buffer = [];
                continue;
            }
            if (preg_match('/^(references|bibliography)\s*$/ui', $line)) {
                $this->flush($groups, $locale, $buffer);
                $locale = 'en';
                $buffer = [];
                continue;
            }
            if ($locale !== null) {
                if (preg_match('/^(acknowledg(e)?ments?|funding|сведения\s+об\s+авторах|information\s+about\s+the\s+authors?)\s*$/ui', $line)) {
                    $this->flush($groups, $locale, $buffer);
                    $locale = null;
                    $buffer = [];
                } elseif ($line !== '') {
                    $buffer[] = $line;
                }
            }
        }
        $this->flush($groups, $locale, $buffer);
        return $groups;
    }

    private function flush(array &$groups, ?string $locale, array $lines): void
    {
        if ($locale === null || $lines === []) {
            return;
        }
        $references = [];
        $current = '';
        foreach ($lines as $line) {
            if (preg_match('/^(?:\[?\d+\]?\s*[.)]?|\d+\s+)\s*/u', $line) && $current !== '') {
                $references[] = trim($current);
                $current = '';
            }
            $current .= ($current === '' ? '' : ' ') . $line;
        }
        if ($current !== '') {
            $references[] = trim($current);
        }
        if ($references !== []) {
            $groups[$locale] = $references;
        }
    }
}
