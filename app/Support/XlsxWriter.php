<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * A small .xlsx writer with no extra packages (needs PHP's zip extension, which Composer also likes).
 *
 * $sheets = [['name' => 'Summary', 'widths' => [30, 12], 'rows' => [[...cells], ...]], ...]
 * A cell is a string, an int/float (written as a number), null (empty), or
 * ['v' => value, 's' => style] where style is one of: title, bold, money, money_bold, decimal, int.
 */
final class XlsxWriter
{
    private const STYLES = ['title' => 1, 'bold' => 2, 'money' => 3, 'money_bold' => 4, 'decimal' => 5, 'int' => 6];

    /** @param list<array{name: string, widths?: list<int|float>, rows: list<list<mixed>>}> $sheets */
    public static function build(array $sheets): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Excel export needs the PHP zip extension.');
        }

        if ($sheets === []) {
            throw new RuntimeException('An Excel file needs at least one sheet.');
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Excel file.');
        }

        $names = [];
        $contentTypes = '';
        $workbookSheets = '';
        $rels = '';

        foreach (array_values($sheets) as $i => $sheet) {
            $n = $i + 1;
            $names[] = $name = self::sheetName($sheet['name'], $names);

            $zip->addFromString("xl/worksheets/sheet{$n}.xml", self::sheetXml($sheet));
            $contentTypes .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbookSheets .= '<sheet name="'.self::esc($name).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }

        $styleRel = count($sheets) + 1;
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

        $zip->addFromString('[Content_Types].xml', $head
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$contentTypes.'</Types>');

        $zip->addFromString('_rels/.rels', $head
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', $head
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$workbookSheets.'</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', $head
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels
            .'<Relationship Id="rId'.$styleRel.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', $head
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0"/></numFmts>'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                       // 0 default
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                         // 1 title
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                         // 2 bold
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                 // 3 money
            .'<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'                   // 4 money bold
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                               // 5 decimal
            .'<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                 // 6 int
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');

        $zip->close();

        $content = (string) file_get_contents($path);
        @unlink($path);

        return $content;
    }

    /** @param array{widths?: list<int|float>, rows: list<list<mixed>>} $sheet */
    private static function sheetXml(array $sheet): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        if (! empty($sheet['widths'])) {
            $xml .= '<cols>';
            foreach (array_values($sheet['widths']) as $i => $width) {
                $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.(float) $width.'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        foreach (array_values($sheet['rows']) as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';

            foreach (array_values($row) as $c => $cell) {
                $xml .= self::cellXml(self::column($c).($r + 1), $cell);
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function cellXml(string $ref, mixed $cell): string
    {
        $style = null;

        if (is_array($cell)) {
            $style = self::STYLES[$cell['s'] ?? ''] ?? null;
            $cell = $cell['v'] ?? null;
        }

        $s = $style !== null ? ' s="'.$style.'"' : '';

        if ($cell === null || $cell === '') {
            return $style !== null ? '<c r="'.$ref.'"'.$s.'/>' : '';
        }

        if (is_bool($cell)) {
            $cell = $cell ? 'Yes' : 'No';
        }

        if (is_int($cell) || is_float($cell)) {
            return '<c r="'.$ref.'"'.$s.'><v>'.(is_float($cell) ? rtrim(rtrim(sprintf('%.10F', $cell), '0'), '.') : $cell).'</v></c>';
        }

        return '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $cell).'</t></is></c>';
    }

    /** 0 => A, 25 => Z, 26 => AA */
    private static function column(int $index): string
    {
        $name = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    /** Sheet names: at most 31 characters, none of []:*?/\ , and not used twice. */
    private static function sheetName(string $name, array $taken): string
    {
        $name = trim((string) preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name));
        $name = mb_substr($name !== '' ? $name : 'Sheet', 0, 31);
        $base = $name;

        for ($i = 2; in_array(mb_strtolower($name), array_map('mb_strtolower', $taken), true); $i++) {
            $name = mb_substr($base, 0, 31 - strlen((string) $i) - 1).' '.$i;
        }

        return $name;
    }

    private static function esc(string $text): string
    {
        // Control characters are not allowed in XML.
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
