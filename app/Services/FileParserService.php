<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Maatwebsite\Excel\Facades\Excel;

class FileParserService
{
    /**
     * Parse file and extract text content.
     */
    public function parse(string $filePath, string $fileType): string
    {
        // Get full path
        $fullPath = Storage::path($filePath);

        return match(strtolower($fileType)) {
            'pdf' => $this->parsePdf($fullPath),
            'doc', 'docx' => $this->parseWord($fullPath),
            'xls', 'xlsx' => $this->parseExcel($fullPath),
            default => throw new \Exception("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Parse PDF file.
     */
    protected function parsePdf(string $filePath): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($filePath);

            $text = $pdf->getText();

            // Clean up the text
            $text = $this->cleanText($text);

            if (empty(trim($text))) {
                throw new \Exception('PDF appears to be empty or contains only images');
            }

            return $text;

        } catch (\Exception $e) {
            throw new \Exception("Failed to parse PDF: " . $e->getMessage());
        }
    }

    /**
     * Parse Word document (DOC/DOCX).
     */
    protected function parseWord(string $filePath): string
    {
        try {
            $phpWord = WordIOFactory::load($filePath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    // Handle text elements
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    }

                    // Handle text run elements
                    if (method_exists($element, 'getElements')) {
                        foreach ($element->getElements() as $childElement) {
                            if (method_exists($childElement, 'getText')) {
                                $text .= $childElement->getText() . " ";
                            }
                        }
                        $text .= "\n";
                    }
                }
            }

            $text = $this->cleanText($text);

            if (empty(trim($text))) {
                throw new \Exception('Word document appears to be empty');
            }

            return $text;

        } catch (\Exception $e) {
            throw new \Exception("Failed to parse Word document: " . $e->getMessage());
        }
    }

    /**
     * Parse Excel file (XLS/XLSX).
     */
    protected function parseExcel(string $filePath): string
    {
        try {
            $data = Excel::toArray([], $filePath);

            if (empty($data)) {
                throw new \Exception('Excel file appears to be empty');
            }

            $text = '';

            // Process first sheet (or all sheets if needed)
            foreach ($data[0] as $row) {
                $text .= implode(' | ', array_filter($row)) . "\n";
            }

            $text = $this->cleanText($text);

            if (empty(trim($text))) {
                throw new \Exception('Excel file contains no readable data');
            }

            return $text;

        } catch (\Exception $e) {
            throw new \Exception("Failed to parse Excel file: " . $e->getMessage());
        }
    }

    /**
     * Clean extracted text.
     */
    protected function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove control characters except newlines
        $text = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $text);

        // Trim
        $text = trim($text);

        return $text;
    }

    /**
     * Extract structured data from text (basic heuristics).
     */
    public function extractStructuredData(string $text): array
    {
        $data = [
            'skills_mentioned' => [],
            'experience_indicators' => [],
            'team_size_indicators' => [],
            'timeline_indicators' => [],
        ];

        // Extract potential skills (words/phrases that look like tech skills)
        // This is basic - AI will do the real extraction
        $skillKeywords = [
            'PHP', 'JavaScript', 'Python', 'Java', 'Laravel', 'React', 'Vue',
            'Angular', 'Node.js', 'MySQL', 'PostgreSQL', 'MongoDB', 'AWS',
            'Docker', 'Kubernetes', 'Magento', 'WordPress', 'Shopify',
        ];

        foreach ($skillKeywords as $skill) {
            if (stripos($text, $skill) !== false) {
                $data['skills_mentioned'][] = $skill;
            }
        }

        // Extract numbers that might indicate team size
        if (preg_match_all('/(\d+)\s*(developer|engineer|trainee|person|people|team member)/i', $text, $matches)) {
            $data['team_size_indicators'] = array_unique($matches[1]);
        }

        // Extract timeline indicators
        if (preg_match_all('/(\d+)\s*(week|month|day)/i', $text, $matches)) {
            $data['timeline_indicators'] = $matches[0];
        }

        return $data;
    }
}
