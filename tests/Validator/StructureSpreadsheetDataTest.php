<?php

declare(strict_types=1);

namespace Tests\ValidateRegister\Validator;

use Icmbio\ValidateRegister\Validator\StructureSpreadsheetData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StructureSpreadsheetDataTest extends TestCase
{
    #[Test]
    public function devePropagarDadosDoRepeatPaiQuandoApenasSubRepeatRecebeNovaLinha(): void
    {
        $input = [
            [
                'id_coleta',
                'individuos_registro/numero_troncos',
                'individuos_registro/observacoes_individuo',
                'individuos_registro/troncos_registro/etiqueta_atual',
                'individuos_registro/troncos_registro/cap_cm',
            ],
            ['ID', 'Numero de troncos', 'Observacoes', 'Etiqueta atual', 'CAP'],
            ['coleta_001', '2', 'ok', 'T001', '10'],
            [null, null, null, 'T002', '12'],
        ];

        $actual = (new StructureSpreadsheetData($input, ['individuos_registro']))
            ->estruture()
            ->toArray();

        self::assertIsArray($actual[0]['individuos_registro'] ?? null);
        self::assertSame('2', $actual[0]['individuos_registro']['individuos_registro/numero_troncos']);
        self::assertSame('ok', $actual[0]['individuos_registro']['individuos_registro/observacoes_individuo']);
        self::assertArrayHasKey('troncos_registro', $actual[0]['individuos_registro']);
        self::assertSame(
            'T001',
            $actual[0]['individuos_registro']['troncos_registro'][0]['individuos_registro/troncos_registro/etiqueta_atual'],
        );
        self::assertSame(
            'T002',
            $actual[0]['individuos_registro']['troncos_registro'][1]['individuos_registro/troncos_registro/etiqueta_atual'],
        );
    }

    #[Test]
    public function devePreencherAsLinhasVaziasComDadosDaLinhaValida(): void
    {
        $input = [
            ['header_1', 'header_2/sub_header_2', 'header_3', 'header_4', 'header_5/sub_header_5'],
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
            ['header_1', 'header_2/sub_header_2', 'header_3', 'header_4', 'header_5/sub_header_5'],
            ['Info 1', ['Info 21', 'Info 22', 'Info 23', 'Info 24', 'Info 25'], 'Info 3', 'Info 4', ['Info 51', 'Info 52', 'Info 53', 'Info 54', 'Info 55']],
            ['OtherInfo 1', 'OtherInfo 2', 'OtherInfo 3', 'OtherInfo 4', ['OtherInfo 51', 'OtherInfo 52', 'OtherInfo 53', 'OtherInfo 54', 'OtherInfo 55']],
        ];

        $actual = (new StructureSpreadsheetData($input))->output();

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function deveRetornarUmaListaDeColetas(): void
    {
        $input = [
            ['header_1', 'header_2/sub_header_2', 'header_3', 'header_4', 'header_5/sub_header_5'],
            ['Label 1', 'Label 2', 'Label 3', 'Label 4', 'Label 5'],
            ['Info 1', 'Info 21', 'Info 3', 'Info 4', 'Info 51'],
            [null, 'Info 22', null, null, 'Info 52'],
            [null, 'Info 25', null, null, 'Info 55'],
            ['OtherInfo 1', 'OtherInfo 2', 'OtherInfo 3', 'OtherInfo 4', 'OtherInfo 51'],
            [null, "", null, null, 'OtherInfo 52'],
            [null, '', null, null, 'OtherInfo 53'],
        ];

        $expected = [
            [
                'header_1' => 'Info 1',
                'header_2' => [
                    ['header_2/sub_header_2' => 'Info 21'],
                    ['header_2/sub_header_2' => 'Info 22'],
                    ['header_2/sub_header_2' => 'Info 25'],
                ],
                'header_3' => 'Info 3',
                'header_4' => 'Info 4',
                'header_5' => [
                    ['header_5/sub_header_5' => 'Info 51'],
                    ['header_5/sub_header_5' => 'Info 52'],
                    ['header_5/sub_header_5' => 'Info 55'],
                ],
            ],

            [
                'header_1' => 'OtherInfo 1',
                'header_2' => [
                    'sub_header_2' => 'OtherInfo 2',
                ],
                'header_3' => 'OtherInfo 3',
                'header_4' => 'OtherInfo 4',
                'header_5' => [
                    ['header_5/sub_header_5' => 'OtherInfo 51'],
                    ['header_5/sub_header_5' => 'OtherInfo 52'],
                    ['header_5/sub_header_5' => 'OtherInfo 53'],
                ],
            ],
        ];

        $actual = (new StructureSpreadsheetData($input))
            ->estruture()
            ->toArray();

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function devePreservarAsLinhasFisicasDosRegistrosEstruturados(): void
    {
        $input = [
            ['id', 'grupo/campo'],
            ['Label id', 'Label campo'],
            ['registro_1', 'valor_1'],
            [null, 'valor_2'],
            ['registro_2', 'valor_3'],
        ];

        $structure = (new StructureSpreadsheetData($input))
            ->estruture();

        $actual = $structure->structuredRowLines();

        $this->assertSame([3, 5], $actual);
    }

    #[Test]
    public function deveRetornarUmaListaDeColetasComGrupos(): void
    {
        $input = [
            ['uc', 'estacao_amostral', 'unidade_amostral', 'coletor/nome', 'coletor/cpf'],
            ['Unidade de Conservação', 'Estacão Amostral', 'Unidade Amostral', 'Nome', 'CPF'],
            ['Reserva Extrativista do Tapajós', 'EA-003 Teste', 'UA-003 Teste', 'Joao', '01234567890'],
            [null, null, null, 'Maria', '11234567891'],
            ['Floresta Nacional de Brasília', 'EA-002 Teste', 'UA-002 Teste', 'Marcio', '31234567893'],
            [null, null, null, 'Thiago', '41234567894'],
        ];

        $expected = [
            [
                'uc' => 'Reserva Extrativista do Tapajós',
                'estacao_amostral' => 'EA-003 Teste',
                'unidade_amostral' => 'UA-003 Teste',
                'coletor' => [
                    [
                        'coletor/nome' => 'Joao',
                        'coletor/cpf' => '01234567890',
                    ],
                    [
                        'coletor/nome' => 'Maria',
                        'coletor/cpf' => '11234567891',
                    ],
                ],
            ],
            [
                'uc' => 'Floresta Nacional de Brasília',
                'estacao_amostral' => 'EA-002 Teste',
                'unidade_amostral' => 'UA-002 Teste',
                'coletor' => [
                    [
                        'coletor/nome' => 'Marcio',
                        'coletor/cpf' => '31234567893',
                    ],
                    [
                        'coletor/nome' => 'Thiago',
                        'coletor/cpf' => '41234567894',
                    ],
                ],
            ],
        ];

        $actual = (new StructureSpreadsheetData($input))
            ->estruture()
            ->toArray();

        $this->assertEquals($expected, $actual);
    }

    #[Test]
    public function devePropagarCamposDiretosDoRepeatMesmoQuandoORegistroTemSubRepeat(): void
    {
        $input = [
            [
                'producao_registro/grupo_captura',
                'producao_registro/especie_captura',
                'producao_registro/total_individuos',
                'producao_registro/biometria_registro/comprimento_total_cm',
            ],
            [
                'Grupo',
                'Espécie',
                'Total',
                'Comprimento',
            ],
            [
                'pacu',
                'pacu-branco',
                '2',
                '25',
            ],
            [
                null,
                null,
                null,
                '20',
            ],
        ];

        $actual = (new StructureSpreadsheetData($input, ['producao_registro']))
            ->estruture()
            ->toArray();

        self::assertCount(1, $actual);
        self::assertIsArray($actual[0]['producao_registro']);
        self::assertSame('pacu', $actual[0]['producao_registro']['producao_registro/grupo_captura']);
        self::assertSame('pacu-branco', $actual[0]['producao_registro']['producao_registro/especie_captura']);
        self::assertSame(
            '25',
            $actual[0]['producao_registro']['biometria_registro'][0]['producao_registro/biometria_registro/comprimento_total_cm'],
        );
        self::assertSame(
            '20',
            $actual[0]['producao_registro']['biometria_registro'][1]['producao_registro/biometria_registro/comprimento_total_cm'],
        );
    }

}
