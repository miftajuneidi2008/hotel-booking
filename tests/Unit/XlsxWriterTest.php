<?php

namespace Tests\Unit;

use App\Support\XlsxWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

/** Section 5.8 (FR-31): the Excel export builds a real .xlsx file without extra packages. */
class XlsxWriterTest extends TestCase
{
    public function test_it_builds_a_zip_with_one_sheet_per_table(): void
    {
        $file = XlsxWriter::build([
            ['name' => 'Summary', 'rows' => [[['v' => 'Daily report', 's' => 'title']], ['Arrivals', 3]]],
            ['name' => 'Summary', 'rows' => [['same name twice']]],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
        file_put_contents($path, $file);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet2.xml'));
        $this->assertStringContainsString('Summary 2', (string) $zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);
    }

    public function test_text_is_escaped_so_the_file_stays_valid(): void
    {
        $file = XlsxWriter::build([['name' => 'A', 'rows' => [['Tom & <Jerry> "x"']]]]);

        $path = tempnam(sys_get_temp_dir(), 'xlsx-test');
        file_put_contents($path, $file);

        $zip = new ZipArchive;
        $zip->open($path);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertStringContainsString('Tom &amp; &lt;Jerry&gt;', $sheet);
        $this->assertNotFalse(simplexml_load_string($sheet));
    }

    public function test_it_needs_at_least_one_sheet(): void
    {
        $this->expectException(RuntimeException::class);

        XlsxWriter::build([]);
    }
}
