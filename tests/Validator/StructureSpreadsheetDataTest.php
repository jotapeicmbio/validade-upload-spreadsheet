<?php
namespace Tests\ValidateRegister\Validator;

use Icmbio\ValidateRegister\Validator\StructureSpreadsheetData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StructureSpreadsheetDataTest extends TestCase
{

    #[Test]
    public function devePreencherAsLinhasVaziasComDadosDaLinhaValida(): void
    {
        $input = [
            ['header_1', 'header_2', 'header_3', 'header_4', 'header_5'],
            ['Label 1', 'Label 2', 'Label 3', 'Label 4', 'Label 5'],
            ['Info 1', 'Info 21', 'Info 3', 'Info 4', 'Info 51'],
            [null, 'Info 22', null, null, 'Info 52'],
            [null, 'Info 23', null, null, 'Info 53'],
            [null, 'Info 24', null, null, 'Info 54'],
            [null, 'Info 25', null, null, 'Info 55'],
            ['OtherInfo 1', 'OtherInfo 2', 'OtherInfo 3', 'OtherInfo 4', 'OtherInfo 51'],
            [null, "", null, null, 'OtherInfo 52'],
            [null, '', null, null, 'OtherInfo 53'],
            [null, " ", null, null, 'OtherInfo 54'],
            [null, ' ', null, null, 'OtherInfo 55'],
        ];

        $expected = [
            ['header_1', 'header_2', 'header_3', 'header_4', 'header_5'],
            ['Label 1', 'Label 2', 'Label 3', 'Label 4', 'Label 5'],
            ['Info 1', ['Info 21', 'Info 22', 'Info 23', 'Info 24', 'Info 25'], 'Info 3', 'Info 4', ['Info 51', 'Info 52', 'Info 53', 'Info 54', 'Info 55']],
            ['OtherInfo 1', 'OtherInfo 2', 'OtherInfo 3', 'OtherInfo 4', ['OtherInfo 51', 'OtherInfo 52', 'OtherInfo 53', 'OtherInfo 54', 'OtherInfo 55']],
        ];

        $actual = (new StructureSpreadsheetData($input))->output();

        $this->assertEquals($expected, $actual);
    }

}
