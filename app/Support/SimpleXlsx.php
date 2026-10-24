<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SimpleXlsx
{
    /** @return array<int, array<string, string>> */
    public function read(string $path, array $columns, int $maximumRows = 1000): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true || ! ($sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml'))) {
            throw ValidationException::withMessages(['file' => 'XLSX tidak dapat dibaca. Gunakan worksheet pertama.']);
        }

        $shared = [];
        if ($xml = $zip->getFromName('xl/sharedStrings.xml')) {
            $sharedDocument = new DOMDocument;
            $sharedDocument->loadXML($xml);
            $sharedXPath = new DOMXPath($sharedDocument);
            $sharedXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($sharedXPath->query('//x:si') as $item) {
                $value = '';
                foreach ($sharedXPath->query('.//x:t', $item) as $text) {
                    $value .= $text->nodeValue;
                }
                $shared[] = $value;
            }
        }

        $sheetDocument = new DOMDocument;
        $sheetDocument->loadXML($sheetXml);
        $sheetXPath = new DOMXPath($sheetDocument);
        $sheetXPath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $matrix = [];
        foreach ($sheetXPath->query('//x:sheetData/x:row') as $row) {
            $values = array_fill(0, count($columns), '');
            foreach ($sheetXPath->query('./x:c', $row) as $cell) {
                preg_match('/^[A-Z]+/', $cell->attributes->getNamedItem('r')?->nodeValue ?? '', $match);
                $column = $this->columnNumber($match[0] ?? '');
                if ($column < 1 || $column > count($columns)) {
                    continue;
                }

                $type = $cell->attributes->getNamedItem('t')?->nodeValue;
                $value = $sheetXPath->query('./x:v', $cell)->item(0)?->nodeValue ?? '';
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = '';
                    foreach ($sheetXPath->query('.//x:t', $cell) as $text) {
                        $value .= $text->nodeValue;
                    }
                }
                $values[$column - 1] = $value;
            }
            $matrix[] = $values;
        }
        $zip->close();

        if (array_shift($matrix) !== $columns) {
            throw ValidationException::withMessages(['file' => 'Header XLSX tidak sama dengan template modul yang dipilih.']);
        }
        if (count($matrix) > $maximumRows) {
            throw ValidationException::withMessages(['file' => "Maksimal {$maximumRows} baris per import."]);
        }

        return array_map(fn (array $row) => array_combine($columns, $row), $matrix);
    }

    /** @param array<int, array<int, int|string|null>> $rows */
    public function create(string $path, string $sheetName, array $columns, array $rows = []): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('File XLSX tidak dapat dibuat.');
        }

        $allRows = [$columns, ...$rows];
        $sheetRows = '';
        foreach ($allRows as $rowIndex => $row) {
            $cells = '';
            foreach (array_values($row) as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $escaped = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $cells .= "<c r=\"{$reference}\" t=\"inlineStr\"{$style}><is><t xml:space=\"preserve\">{$escaped}</t></is></c>";
            }
            $sheetRows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        $lastColumn = $this->columnName(count($columns));
        $widths = '';
        foreach ($columns as $index => $column) {
            $width = min(45, max(14, strlen($column) + 3));
            $position = $index + 1;
            $widths .= "<col min=\"{$position}\" max=\"{$position}\" width=\"{$width}\" customWidth=\"1\"/>";
        }

        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$widths.'</cols><sheetData>'.$sheetRows.'</sheetData>'
            .'<autoFilter ref="A1:'.$lastColumn.max(1, count($allRows)).'"/></worksheet>';

        $safeSheetName = htmlspecialchars(mb_substr($sheetName, 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$safeSheetName.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF202020"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
        $zip->close();
    }

    private function columnNumber(string $letters): int
    {
        $column = 0;
        foreach (str_split($letters) as $letter) {
            $column = $column * 26 + ord($letter) - 64;
        }

        return $column;
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
