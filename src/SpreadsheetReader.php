<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SpreadsheetReader
{
    protected $path;
    public const SHEET_NAME = 'Preenchimento';

    public function __construct($path)
    {
        $this->path = $this->fileExistsOrThrow($path);
    }

    public static function load($path)
    {
        return (new static($path))->getArrayFromSpreadsheet();
    }

    public function getArrayFromSpreadsheet(): array
    {
        $sheet = $this->resolveWorksheet($this->loadSpreadsheet());

        return $sheet->rangetoArray(
            range: 'A1:' . $sheet->getHighestDataColumn() . $sheet->getHighestDataRow(),
            nullValue: null,
            calculateFormulas: true,
            formatData: false
        );
    }

    protected function loadSpreadsheet(): Spreadsheet
    {
        return IOFactory::load(
            filename: $this->path,
            flags: IReader::READ_DATA_ONLY | IReader::IGNORE_EMPTY_CELLS
        );
    }

    protected function resolveWorksheet(Spreadsheet $spreadsheet): Worksheet
    {
        if ($spreadsheet->getSheetCount() === 1) {
            return $spreadsheet->getSheet(0);
        }

        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);

        if ($sheet instanceof Worksheet) {
            return $sheet;
        }

        throw new Exception(sprintf(
            'Worksheet "%s" was not found and the workbook contains more than one sheet.',
            self::SHEET_NAME,
        ));
    }

    protected function fileExistsOrThrow($path): string
    {
        if (file_exists($path)) {
            return $path;
        }

        throw new Exception('File does not exist: ' . $path);
    }
}
