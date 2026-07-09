<?php

namespace App\Services\WorkerRoster;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Fills the styled 명부양식 template (resources/templates/email-to-worker.xlsx)
 * by editing a copy of the archive's own XML — the template's fonts, table
 * styling, borders, print setup and zoom survive byte-for-byte, which no
 * spreadsheet writer re-render can guarantee. Port of the retired
 * leenmore-storage `ReportGenerator` XLSX engine, trimmed to rows that are
 * already keyed by column letter (A–L).
 *
 * The template's shape (fixed by the firm's form): row 1 is the title row with
 * the `project` / `(worker)` placeholders, row 2 the column headers, row 3 the
 * prototype data row that gets cloned per roster entry.
 */
class RosterTemplateWorkbook
{
    /** SpreadsheetML main namespace. */
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** Officedocument relationship namespace (the `r:` prefix in workbook.xml). */
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** OPC package relationships namespace (the *.rels parts). */
    private const RELS_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** OPC content-types namespace ([Content_Types].xml). */
    private const TYPES_NS = 'http://schemas.openxmlformats.org/package/2006/content-types';

    /** Page Break Preview zoom applied to every generated sheet. */
    private const VIEW_ZOOM_SCALE = 37;

    public function __construct(private readonly string $templatePath) {}

    /**
     * Write one worker's roster: the template's single sheet filled with the
     * rows, the title placeholders replaced, and the table / print area
     * stretched down to the last row.
     *
     * @param  list<array<string, int|string>>  $rows  column letter => value
     */
    public function writeSingle(string $outputPath, array $rows, string $projectName, string $workerName): void
    {
        if ($rows === []) {
            throw new RuntimeException('No roster rows to write');
        }

        $zip = $this->openCopy($outputPath);

        try {
            $sharedStrings = $this->parseSharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));

            $worksheetEntry = $this->findZipEntry($zip, '#^xl/worksheets/sheet\d+\.xml$#');
            if ($worksheetEntry === null) {
                throw new RuntimeException('Worksheet part not found in template');
            }

            [$worksheetXml, $lastRow, $dataRowXfIndexes] = $this->fillWorksheetXml(
                (string) $zip->getFromName($worksheetEntry),
                $rows,
                $sharedStrings,
                $projectName,
                $workerName,
            );
            $zip->addFromString($worksheetEntry, $worksheetXml);

            $tableEntry = $this->findZipEntry($zip, '#^xl/tables/table\d+\.xml$#');
            if ($tableEntry !== null) {
                $zip->addFromString($tableEntry, $this->stretchTableXml((string) $zip->getFromName($tableEntry), $lastRow));
            }

            $workbookXml = $zip->getFromName('xl/workbook.xml');
            if (is_string($workbookXml) && $workbookXml !== '') {
                $zip->addFromString('xl/workbook.xml', $this->stretchPrintAreaXml($workbookXml, $lastRow));
            }

            $this->forceExplicitDataRowBorders($zip, $dataRowXfIndexes);
        } catch (Throwable $error) {
            $zip->unchangeAll();
            $zip->close();
            @unlink($outputPath);

            throw $error;
        }

        $this->closeOrFail($zip);
    }

    /**
     * Write the 명부양식 전체 다운로드 workbook: one sheet per worker, each filled
     * from the same template sheet. Excel tables can't repeat across cloned
     * sheets, so table parts are dropped (the explicit cell borders keep the
     * grid) and the print names are regenerated per sheet.
     *
     * @param  list<array{title: string, workerName: string, rows: list<array<string, int|string>>}>  $sheets
     */
    public function writeBundle(string $outputPath, array $sheets, string $projectName): void
    {
        if ($sheets === []) {
            throw new RuntimeException('No sheets to write');
        }

        $zip = $this->openCopy($outputPath);

        try {
            $sharedStrings = $this->parseSharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));

            $templateEntry = $this->findZipEntry($zip, '#^xl/worksheets/sheet\d+\.xml$#');
            if ($templateEntry === null) {
                throw new RuntimeException('Worksheet part not found in template');
            }
            $templateSheetXml = (string) $zip->getFromName($templateEntry);

            $dataRowXfIndexes = [];
            $sheetMeta = [];

            foreach (array_values($sheets) as $index => $sheet) {
                [$worksheetXml, $lastRow, $xfIndexes] = $this->fillWorksheetXml(
                    $templateSheetXml,
                    $sheet['rows'],
                    $sharedStrings,
                    $projectName,
                    $sheet['workerName'],
                );

                $zip->addFromString(
                    'xl/worksheets/sheet'.($index + 1).'.xml',
                    $this->detachSheetRelationships($worksheetXml),
                );

                $dataRowXfIndexes += $xfIndexes;
                $sheetMeta[] = ['title' => $sheet['title'], 'lastRow' => $lastRow];
            }

            // The cloned sheets no longer reference the table or the printer
            // settings, so drop those parts and the sheet rels wholesale.
            foreach (['#^xl/tables/table\d+\.xml$#', '#^xl/worksheets/_rels/.+$#', '#^xl/printerSettings/.+$#'] as $pattern) {
                while (($entry = $this->findZipEntry($zip, $pattern)) !== null) {
                    if (! $zip->deleteName($entry)) {
                        break;
                    }
                }
            }

            $zip->addFromString('xl/workbook.xml', $this->buildBundleWorkbookXml((string) $zip->getFromName('xl/workbook.xml'), $sheetMeta));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildBundleWorkbookRels((string) $zip->getFromName('xl/_rels/workbook.xml.rels'), count($sheetMeta)));
            $zip->addFromString('[Content_Types].xml', $this->buildBundleContentTypes((string) $zip->getFromName('[Content_Types].xml'), count($sheetMeta)));

            $this->forceExplicitDataRowBorders($zip, $dataRowXfIndexes);
        } catch (Throwable $error) {
            $zip->unchangeAll();
            $zip->close();
            @unlink($outputPath);

            throw $error;
        }

        $this->closeOrFail($zip);
    }

    private function openCopy(string $outputPath): ZipArchive
    {
        if (! is_file($this->templatePath)) {
            throw new RuntimeException('Roster template not found: '.$this->templatePath);
        }

        if (! @copy($this->templatePath, $outputPath)) {
            throw new RuntimeException('Unable to create workbook at '.$outputPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException('Unable to open workbook archive');
        }

        return $zip;
    }

    private function closeOrFail(ZipArchive $zip): void
    {
        if ($zip->close() !== true) {
            throw new RuntimeException('Unable to finalize workbook archive');
        }
    }

    private function findZipEntry(ZipArchive $zip, string $pattern): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && preg_match($pattern, $name) === 1) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function parseSharedStrings(string $xml): array
    {
        if (trim($xml) === '') {
            return [];
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS);

        $strings = [];
        foreach ($xpath->query('//m:sst/m:si') as $stringItem) {
            $text = '';
            foreach ($xpath->query('.//m:t', $stringItem) as $textNode) {
                $text .= $textNode->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * Clone the template's prototype data row once per roster row, then adjust
     * the sheet extents (dimension, zoom) and replace the title placeholders.
     *
     * @param  list<array<string, int|string>>  $rows
     * @param  array<int, string>  $sharedStrings
     * @return array{0: string, 1: int, 2: array<int, true>} edited worksheet XML,
     *                                                       the last data row, and the cellXf indexes used by data row cells.
     */
    private function fillWorksheetXml(string $xml, array $rows, array $sharedStrings, string $projectName, string $workerName): array
    {
        $dom = new DOMDocument;
        if (trim($xml) === '' || ! $dom->loadXML($xml)) {
            throw new RuntimeException('Unable to parse worksheet XML');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS);

        $sheetData = $xpath->query('//m:sheetData')->item(0);
        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('Worksheet has no sheetData element');
        }

        $prototype = null;
        foreach ($xpath->query('m:row', $sheetData) as $rowNode) {
            $prototype = $rowNode;
        }
        if (! $prototype instanceof DOMElement) {
            throw new RuntimeException('Worksheet has no data row to clone');
        }

        // Record the cellXf indexes the prototype uses so the styles.xml pass
        // knows which border records to make explicit.
        $dataRowXfIndexes = [];
        foreach ($xpath->query('m:c', $prototype) as $cellNode) {
            if (! $cellNode instanceof DOMElement) {
                continue;
            }
            $styleIndex = $cellNode->getAttribute('s');
            if ($styleIndex !== '' && ctype_digit($styleIndex)) {
                $dataRowXfIndexes[(int) $styleIndex] = true;
            }
        }

        $firstDataRow = (int) $prototype->getAttribute('r');
        $lastRow = $firstDataRow + max(count($rows), 1) - 1;

        foreach (array_values($rows) as $offset => $cells) {
            $rowNumber = $firstDataRow + $offset;

            /** @var DOMElement $rowElement */
            $rowElement = $prototype->cloneNode(true);
            $rowElement->setAttribute('r', (string) $rowNumber);
            if ($offset > 0) {
                // thickTop pairs the first data row with the header; later rows omit it.
                $rowElement->removeAttribute('thickTop');
            }

            foreach (iterator_to_array($rowElement->childNodes) as $cellNode) {
                if (! $cellNode instanceof DOMElement || $cellNode->localName !== 'c') {
                    continue;
                }

                $columnLetter = rtrim($cellNode->getAttribute('r'), '0123456789');
                $cellNode->setAttribute('r', $columnLetter.$rowNumber);

                if (array_key_exists($columnLetter, $cells)) {
                    $this->setCellNodeValue($dom, $cellNode, $cells[$columnLetter]);
                }
            }

            $sheetData->insertBefore($rowElement, $prototype);
        }

        $sheetData->removeChild($prototype);

        $dimension = $xpath->query('//m:dimension')->item(0);
        if ($dimension instanceof DOMElement) {
            $ref = $dimension->getAttribute('ref');
            if ($ref !== '') {
                $dimension->setAttribute('ref', (string) preg_replace('/\d+$/', (string) $lastRow, $ref));
            }
        }

        $sheetView = $xpath->query('//m:sheetViews/m:sheetView')->item(0);
        if ($sheetView instanceof DOMElement) {
            $sheetView->setAttribute('zoomScale', (string) self::VIEW_ZOOM_SCALE);
            $sheetView->setAttribute('zoomScaleNormal', (string) self::VIEW_ZOOM_SCALE);
            $sheetView->setAttribute('zoomScaleSheetLayoutView', (string) self::VIEW_ZOOM_SCALE);
        }

        if ($projectName !== '' || $workerName !== '') {
            $this->applyTitlePlaceholders($dom, $xpath, $firstDataRow - 1, $sharedStrings, $projectName, $workerName);
        }

        return [(string) $dom->saveXML(), $lastRow, $dataRowXfIndexes];
    }

    /**
     * Replace the "project" / "(worker)" placeholders in the rows above the
     * header. Only cell values change; styles stay untouched.
     *
     * @param  array<int, string>  $sharedStrings
     */
    private function applyTitlePlaceholders(DOMDocument $dom, DOMXPath $xpath, int $headerRow, array $sharedStrings, string $projectName, string $workerName): void
    {
        for ($rowNumber = 1; $rowNumber < $headerRow; $rowNumber++) {
            $rowNode = $xpath->query('//m:sheetData/m:row[@r="'.$rowNumber.'"]')->item(0);
            if (! $rowNode instanceof DOMElement) {
                continue;
            }

            foreach ($xpath->query('m:c', $rowNode) as $cellNode) {
                if (! $cellNode instanceof DOMElement) {
                    continue;
                }

                $normalized = mb_strtolower($this->readCellText($cellNode, $xpath, $sharedStrings), 'UTF-8');
                if ($normalized === '') {
                    continue;
                }

                if ($projectName !== '' && $normalized === 'project') {
                    $this->setCellNodeValue($dom, $cellNode, $projectName);
                } elseif ($workerName !== '' && str_contains($normalized, 'worker')) {
                    $this->setCellNodeValue($dom, $cellNode, '('.$workerName.')');
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function readCellText(DOMElement $cellNode, DOMXPath $xpath, array $sharedStrings): string
    {
        $type = $cellNode->getAttribute('t');

        if ($type === 'inlineStr') {
            $text = '';
            foreach ($xpath->query('m:is//m:t', $cellNode) as $textNode) {
                $text .= $textNode->textContent;
            }

            return trim($text);
        }

        $valueNode = $xpath->query('m:v', $cellNode)->item(0);
        if ($valueNode === null) {
            return '';
        }

        $raw = trim($valueNode->textContent);
        if ($type === 's') {
            return trim($sharedStrings[(int) $raw] ?? '');
        }

        return $raw;
    }

    /**
     * Write a value into a <c> node as a number or an inline string, clearing
     * any previous content. The cell keeps its style index.
     */
    private function setCellNodeValue(DOMDocument $dom, DOMElement $cell, int|float|string|null $value): void
    {
        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }
        if ($cell->hasAttribute('t')) {
            $cell->removeAttribute('t');
        }

        if ($value === null) {
            return;
        }

        if (is_int($value) || is_float($value)) {
            $cell->appendChild($dom->createElementNS(self::NS, 'v', (string) $value));

            return;
        }

        if ($value === '') {
            return;
        }

        $cell->setAttribute('t', 'inlineStr');
        $inlineString = $dom->createElementNS(self::NS, 'is');
        $textElement = $dom->createElementNS(self::NS, 't');
        $textElement->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $textElement->appendChild($dom->createTextNode($value));
        $inlineString->appendChild($textElement);
        $cell->appendChild($inlineString);
    }

    /**
     * Stretch the table range and its autofilter down to the last data row.
     */
    private function stretchTableXml(string $xml, int $lastRow): string
    {
        $result = preg_replace_callback(
            '/ref="([A-Z]+\d+:[A-Z]+)\d+"/',
            static fn (array $matches): string => 'ref="'.$matches[1].$lastRow.'"',
            $xml,
        );

        return is_string($result) ? $result : $xml;
    }

    /**
     * Stretch the Print_Area defined name's bottom row down to the last data row.
     */
    private function stretchPrintAreaXml(string $xml, int $lastRow): string
    {
        $result = preg_replace_callback(
            '#(<definedName\b[^>]*name="_xlnm\.Print_Area"[^>]*>)(.*?)(</definedName>)#s',
            static function (array $matches) use ($lastRow): string {
                $range = preg_replace('/\d+$/', (string) $lastRow, $matches[2]);

                return $matches[1].(is_string($range) ? $range : $matches[2]).$matches[3];
            },
            $xml,
        );

        return is_string($result) ? $result : $xml;
    }

    /**
     * Remove the table reference and the printer-settings relationship from a
     * bundle sheet — its rels part is deleted, so nothing may point at one.
     */
    private function detachSheetRelationships(string $xml): string
    {
        $dom = new DOMDocument;
        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('Unable to parse worksheet XML');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS);

        foreach ($xpath->query('//m:tableParts') as $tableParts) {
            $tableParts->parentNode?->removeChild($tableParts);
        }

        foreach ($xpath->query('//m:pageSetup') as $pageSetup) {
            if ($pageSetup instanceof DOMElement) {
                $pageSetup->removeAttributeNS(self::R_NS, 'id');
            }
        }

        return (string) $dom->saveXML();
    }

    /**
     * Rewrite workbook.xml for the bundle: one <sheet> per worker and
     * regenerated per-sheet print names (area stretched to each sheet's rows).
     *
     * @param  list<array{title: string, lastRow: int}>  $sheetMeta
     */
    private function buildBundleWorkbookXml(string $xml, array $sheetMeta): string
    {
        $dom = new DOMDocument;
        if (trim($xml) === '' || ! $dom->loadXML($xml)) {
            throw new RuntimeException('Unable to parse workbook XML');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS);

        $sheetsElement = $xpath->query('//m:sheets')->item(0);
        if (! $sheetsElement instanceof DOMElement) {
            throw new RuntimeException('Workbook has no sheets element');
        }

        while ($sheetsElement->firstChild !== null) {
            $sheetsElement->removeChild($sheetsElement->firstChild);
        }

        foreach ($sheetMeta as $index => $meta) {
            $sheetElement = $dom->createElementNS(self::NS, 'sheet');
            $sheetElement->setAttribute('name', $meta['title']);
            $sheetElement->setAttribute('sheetId', (string) ($index + 1));
            $sheetElement->setAttributeNS(self::R_NS, 'r:id', 'rSheet'.($index + 1));
            $sheetsElement->appendChild($sheetElement);
        }

        foreach ($xpath->query('//m:definedNames') as $definedNames) {
            $definedNames->parentNode?->removeChild($definedNames);
        }

        $definedNames = $dom->createElementNS(self::NS, 'definedNames');
        foreach ($sheetMeta as $index => $meta) {
            $quoted = "'".str_replace("'", "''", $meta['title'])."'";

            $printArea = $dom->createElementNS(self::NS, 'definedName', $quoted.'!$A$1:$L$'.$meta['lastRow']);
            $printArea->setAttribute('name', '_xlnm.Print_Area');
            $printArea->setAttribute('localSheetId', (string) $index);
            $definedNames->appendChild($printArea);

            $printTitles = $dom->createElementNS(self::NS, 'definedName', $quoted.'!$1:$2');
            $printTitles->setAttribute('name', '_xlnm.Print_Titles');
            $printTitles->setAttribute('localSheetId', (string) $index);
            $definedNames->appendChild($printTitles);
        }

        // Schema order requires definedNames directly after sheets.
        $sheetsElement->parentNode?->insertBefore($definedNames, $sheetsElement->nextSibling);

        return (string) $dom->saveXML();
    }

    /**
     * Rewrite the workbook rels for the bundle: keep the non-worksheet
     * relationships (styles, theme, sharedStrings) and relink one worksheet
     * relationship per sheet.
     */
    private function buildBundleWorkbookRels(string $xml, int $sheetCount): string
    {
        $dom = new DOMDocument;
        if (trim($xml) === '' || ! $dom->loadXML($xml)) {
            throw new RuntimeException('Unable to parse workbook rels XML');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('r', self::RELS_NS);

        foreach ($xpath->query('//r:Relationship') as $relationship) {
            if ($relationship instanceof DOMElement && str_ends_with($relationship->getAttribute('Type'), '/worksheet')) {
                $relationship->parentNode?->removeChild($relationship);
            }
        }

        $root = $dom->documentElement;
        if (! $root instanceof DOMElement) {
            throw new RuntimeException('Workbook rels XML has no root element');
        }

        for ($index = 1; $index <= $sheetCount; $index++) {
            $relationship = $dom->createElementNS(self::RELS_NS, 'Relationship');
            $relationship->setAttribute('Id', 'rSheet'.$index);
            $relationship->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet');
            $relationship->setAttribute('Target', 'worksheets/sheet'.$index.'.xml');
            $root->appendChild($relationship);
        }

        return (string) $dom->saveXML();
    }

    /**
     * Rewrite [Content_Types].xml for the bundle: declare every sheet part and
     * drop the removed table part.
     */
    private function buildBundleContentTypes(string $xml, int $sheetCount): string
    {
        $dom = new DOMDocument;
        if (trim($xml) === '' || ! $dom->loadXML($xml)) {
            throw new RuntimeException('Unable to parse content types XML');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ct', self::TYPES_NS);

        $declaredSheets = [];
        foreach ($xpath->query('//ct:Override') as $override) {
            if (! $override instanceof DOMElement) {
                continue;
            }

            $partName = $override->getAttribute('PartName');
            if (str_starts_with($partName, '/xl/tables/')) {
                $override->parentNode?->removeChild($override);
            } elseif (preg_match('#^/xl/worksheets/sheet(\d+)\.xml$#', $partName, $matches) === 1) {
                $declaredSheets[(int) $matches[1]] = true;
            }
        }

        $root = $dom->documentElement;
        if (! $root instanceof DOMElement) {
            throw new RuntimeException('Content types XML has no root element');
        }

        for ($index = 1; $index <= $sheetCount; $index++) {
            if (isset($declaredSheets[$index])) {
                continue;
            }

            $override = $dom->createElementNS(self::TYPES_NS, 'Override');
            $override->setAttribute('PartName', '/xl/worksheets/sheet'.$index.'.xml');
            $override->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml');
            $root->appendChild($override);
        }

        return (string) $dom->saveXML();
    }

    /**
     * Rewrite the border records the data row cellXfs point at so every side
     * is a thin black line. The template's dxf table borders don't survive
     * LibreOffice / mPDF conversion, but explicit cell borders do — and Excel
     * renders identically either way.
     *
     * @param  array<int, true>  $dataRowXfIndexes
     */
    private function forceExplicitDataRowBorders(ZipArchive $zip, array $dataRowXfIndexes): void
    {
        if ($dataRowXfIndexes === []) {
            return;
        }

        $stylesXml = $zip->getFromName('xl/styles.xml');
        if (! is_string($stylesXml) || trim($stylesXml) === '') {
            return;
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($stylesXml)) {
            return;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS);

        $borderIdsToRewrite = [];
        $position = 0;
        foreach ($xpath->query('//m:cellXfs/m:xf') as $xfNode) {
            if (isset($dataRowXfIndexes[$position]) && $xfNode instanceof DOMElement) {
                $borderId = $xfNode->getAttribute('borderId');
                if ($borderId !== '' && ctype_digit($borderId)) {
                    $borderIdsToRewrite[(int) $borderId] = true;
                }
            }
            $position++;
        }

        if ($borderIdsToRewrite === []) {
            return;
        }

        $position = 0;
        $rewroteAny = false;
        foreach ($xpath->query('//m:borders/m:border') as $borderNode) {
            if (isset($borderIdsToRewrite[$position]) && $borderNode instanceof DOMElement) {
                $this->replaceBorderWithThinBox($dom, $borderNode);
                $rewroteAny = true;
            }
            $position++;
        }

        if (! $rewroteAny) {
            return;
        }

        $zip->addFromString('xl/styles.xml', (string) $dom->saveXML());
    }

    private function replaceBorderWithThinBox(DOMDocument $dom, DOMElement $borderNode): void
    {
        while ($borderNode->firstChild !== null) {
            $borderNode->removeChild($borderNode->firstChild);
        }

        foreach (['left', 'right', 'top', 'bottom'] as $side) {
            $sideElement = $dom->createElementNS(self::NS, $side);
            $sideElement->setAttribute('style', 'thin');
            $color = $dom->createElementNS(self::NS, 'color');
            $color->setAttribute('indexed', '64');
            $sideElement->appendChild($color);
            $borderNode->appendChild($sideElement);
        }

        $borderNode->appendChild($dom->createElementNS(self::NS, 'diagonal'));
    }
}
