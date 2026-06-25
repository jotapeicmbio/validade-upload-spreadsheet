<?php

declare(strict_types=1);

namespace Tests\ValidateRegister\Unit;

use Icmbio\ValidateRegister\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpreadsheetReaderTest extends TestCase
{
    #[Test]
    public function itReadsFormattedDatesFromTheSpreadsheet(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(SpreadsheetReader::SHEET_NAME);
        $sheet->setCellValue('A1', 'data');
        $sheet->setCellValue('A2', Date::PHPToExcel(new \DateTimeImmutable('2023-09-14')));
        $sheet->getStyle('A2')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        $path = tempnam(sys_get_temp_dir(), 'spreadsheet-reader-');
        self::assertIsString($path);

        $xlsxPath = $path . '.xlsx';
        rename($path, $xlsxPath);

        try {
            (new Xlsx($spreadsheet))->save($xlsxPath);

            $result = SpreadsheetReader::load($xlsxPath);

            self::assertSame('14/09/2023', $result[1][0]);
        } finally {
            if (is_file($xlsxPath)) {
                unlink($xlsxPath);
            }
        }
    }
}
