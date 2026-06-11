<?php

namespace App\Services\CV;

use Smalot\PdfParser\Parser;

class CVTextExtractionService
{
    public function extractFromPath(string $absolutePath): string
    {
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => $this->extractFromPdf($absolutePath),
            'docx' => $this->extractFromDocx($absolutePath),
            'doc' => '',
            default => '',
        };
    }

    private function extractFromPdf(string $path): string
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($path);

            return trim($pdf->getText());
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function extractFromDocx(string $path): string
    {
        try {
            $zip = new \ZipArchive;

            if ($zip->open($path) === true) {
                $index = $zip->locateName('word/document.xml');

                if ($index === false) {
                    $zip->close();

                    return '';
                }

                $data = $zip->getFromIndex($index);
                $zip->close();

                $text = strip_tags(str_replace('</w:p>', PHP_EOL, $data));

                return trim(html_entity_decode($text));
            }

            return '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
