<?php

declare(strict_types=1);

use Icmbio\ValidateRegister\DataCollectionSpreadsheetReviewPipeline;
use Icmbio\ValidateRegister\Enums\DataCollectionSelectorKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DataCollectionSpreadsheetReviewPipelineTest extends TestCase
{
    protected string $planilha_path = __DIR__ . '/../files/planilhas/planilha_Form-test-example-with-images_modelo.xlsx';
    protected string $planilha_path_error = __DIR__ . '/../files/planilhas/planilha_Form-test-example-with-images_error_modelo.xlsx';

    #[Test]
    public function pipelineShouldReturnSpreeadSheetAsArray(): void
    {
        $expected = [
            [
                'uc' => 123,
                'estacao_amostral' => 1001,
                'unidade_amostral' => 2001,
                'tipo' => 'esec',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'uc' => 456,
                'estacao_amostral' => 1002,
                'unidade_amostral' => 2002,
                'tipo' => 'flona',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'uc' => 789,
                'estacao_amostral' => 1003,
                'unidade_amostral' => 2003,
                'tipo' => 'resex',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
        ];

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldKeepOneRegisterWhenRepeatIsDeclaredInXform(): void
    {
        $pipeline = $this->makePipelineWithSpreadsheet([
            [
                'campo_1',
                'campo_2',
                'grupo_1/campo_1',
                'grupo_1/grupo_campo_1',
                'grupo_1/biometria_registro/comprimento_total_cm',
            ],
            [
                'Campo 1',
                'Campo 2',
                'Grupo 1',
                'Grupo campo 1',
                'Comprimento',
            ],
            ['registro_1', 'base_1', 'grupo_1_a', 'grupo_campo_1_a', 18],
            [null, null, null, null, 17],
            [null, null, null, null, 23],
            [null, null, null, null, 19],
        ]);

        $result = $pipeline
            ->validateCollectionFromJson($this->repeatFormDefinition())
            ->collection();

        $this->assertCount(1, $result);
        $this->assertSame('registro_1', $result[0]['campo_1']);
        $this->assertSame('base_1', $result[0]['campo_2']);
        $this->assertCount(4, $result[0]['grupo_1']);
        $this->assertSame('grupo_1_a', $result[0]['grupo_1'][0]['grupo_1/campo_1']);
        $this->assertSame('grupo_campo_1_a', $result[0]['grupo_1'][0]['grupo_1/grupo_campo_1']);
        $this->assertCount(1, $result[0]['grupo_1'][0]['biometria_registro']);
        $this->assertSame(
            18,
            $result[0]['grupo_1'][0]['biometria_registro'][0]['grupo_1/biometria_registro/comprimento_total_cm'],
        );
        $this->assertSame(
            19,
            $result[0]['grupo_1'][3]['biometria_registro'][0]['grupo_1/biometria_registro/comprimento_total_cm'],
        );
    }

    #[Test]
    public function pipelineShouldExposeStructuredCollectionBeforeProcessing(): void
    {
        $expected = [
            [
                'uc' => 'Estação Ecológica da Terra do Meio',
                'estacao_amostral' => 'EA-001 Teste',
                'unidade_amostral' => 'UA-001 Teste',
                'tipo' => 'esec',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'uc' => 'Floresta Nacional de Brasília',
                'estacao_amostral' => 'EA-002 Teste',
                'unidade_amostral' => 'UA-002 Teste',
                'tipo' => 'flona',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'uc' => 'Reserva Extrativista do Tapajós',
                'estacao_amostral' => 'EA-003 Teste',
                'unidade_amostral' => 'UA-003 Teste',
                'tipo' => 'resex',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
        ];

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->collection();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldTransformTheCollectionWhenAFfunctionIsPassedToIt(): void
    {
        $expected = [
            [
                'UC' => 'ESTAÇÃO ECOLÓGICA DA TERRA DO MEIO',
                'estacao_amostral' => 1001,
                'unidade_amostral' => 2001,
                'tipo' => 'esec',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'UC' => 'FLORESTA NACIONAL DE BRASÍLIA',
                'estacao_amostral' => 1002,
                'unidade_amostral' => 2002,
                'tipo' => 'flona',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'UC' => 'RESERVA EXTRATIVISTA DO TAPAJÓS',
                'estacao_amostral' => 1003,
                'unidade_amostral' => 2003,
                'tipo' => 'resex',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
        ];

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->transform(fn($item) => mb_strtoupper($item), DataCollectionSelectorKey::INDICE, [0])
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldTransformTheCollectionWhenColumnNameIsPassed(): void
    {
        $expected = [
            [
                'uc' => 123,
                'ESTACAO_AMOSTRAL' => 'EA-001 TESTE',
                'unidade_amostral' => 2001,
                'tipo' => 'esec',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'uc' => 456,
                'ESTACAO_AMOSTRAL' => 'EA-002 TESTE',
                'unidade_amostral' => 2002,
                'tipo' => 'flona',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'uc' => 789,
                'ESTACAO_AMOSTRAL' => 'EA-003 TESTE',
                'unidade_amostral' => 2003,
                'tipo' => 'resex',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
        ];

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->transform(fn($item) => trim(mb_strtoupper($item)), keys: ['estacao_amostral'])
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldKeepCollectionUntouchedWhenNoKeysArePassed(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->process();

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->transform(fn($item) => trim(mb_strtoupper($item)), DataCollectionSelectorKey::NAME, [])
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldKeepCollectionUntouchedWhenOnlyFunctionIsPassed(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->process();

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->transform(fn($item) => trim(mb_strtoupper($item)))
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldNormalizeDecimalCommaToPoint(): void
    {
        $result = $this->makePipelineWithSpreadsheet([
            ['decimal_field'],
            ['Decimal field'],
            ['5,5'],
        ])
            ->validateCollectionFromJson([
                'children' => [
                    ['name' => 'decimal_field', 'type' => 'decimal'],
                ],
            ])
            ->process();

        $this->assertSame('5.5', $result[0]['decimal_field']);
    }

    #[Test]
    public function pipelineShouldFailWhenSpreadsheetIsMissingRequiredXformColumns(): void
    {
        $pipeline = $this->makePipelineWithSpreadsheet([
            ['campo_1'],
            ['Campo 1'],
            ['valor_1'],
        ]);

        $pipeline->validateCollectionFromJson([
            'children' => [
                ['name' => 'campo_1', 'type' => 'text'],
                ['name' => 'campo_2', 'type' => 'text'],
            ],
        ]);

        $pipeline->validateCollection();

        $this->assertFalse($pipeline->valid());
        $this->assertCount(1, $pipeline->errors());
        $this->assertSame('campo_2', $pipeline->errors()[0]['key']);
        $this->assertStringContainsString('Campo "campo_2" e necessario informar', $pipeline->errors()[0]['message']);
    }

    #[Test]
    public function pipelineShouldFailWhenSpreadsheetHasExtraColumns(): void
    {
        $pipeline = $this->makePipelineWithSpreadsheet([
            ['campo_1', 'campo_2', 'campo_extra'],
            ['Campo 1', 'Campo 2', 'Campo extra'],
            ['valor_1', 'valor_2', 'valor_extra'],
        ]);

        $pipeline->validateCollectionFromJson([
            'children' => [
                ['name' => 'campo_1', 'type' => 'text'],
                ['name' => 'campo_2', 'type' => 'text'],
            ],
        ]);

        $pipeline->validateCollection();

        $this->assertFalse($pipeline->valid());
        $this->assertCount(1, $pipeline->errors());
        $this->assertSame('campo_extra', $pipeline->errors()[0]['key']);
        $this->assertStringContainsString('Campo "campo_extra" nao existe no xform', $pipeline->errors()[0]['message']);
    }

    #[Test]
    public function pipelineShouldIgnoreCompatibilityFieldByName(): void
    {
        $pipeline = $this->makePipelineWithSpreadsheet([
            ['campo_1', 'meta/instanceID'],
            ['Campo 1', 'Instance ID'],
            ['valor_1', 'uuid:abc'],
        ]);

        $pipeline->validateCollectionFromJson([
            'children' => [
                ['name' => 'campo_1', 'type' => 'text'],
            ],
        ]);

        $pipeline->validateCollection();

        $this->assertTrue($pipeline->valid());
    }

    #[Test]
    public function pipelineShouldIgnoreCompatibilityFieldByType(): void
    {
        $pipeline = $this->makePipelineWithSpreadsheet([
            ['campo_1', 'campo_calculado'],
            ['Campo 1', 'Campo calculado'],
            ['valor_1', 'valor_2'],
        ]);

        $pipeline->validateCollectionFromJson([
            'children' => [
                ['name' => 'campo_1', 'type' => 'text'],
                ['name' => 'campo_calculado', 'type' => 'calculate', 'bind' => ['calculate' => 'uuid()']],
            ],
        ]);

        $pipeline->validateCollection();

        $this->assertTrue($pipeline->valid());
    }

    #[Test]
    public function pipelineShouldBypassCompatibilityValidationWhenDisabled(): void
    {
        $pipeline = $this->makePipelineWithSpreadsheet([
            ['campo_1'],
            ['Campo 1'],
            ['valor_1'],
        ]);

        $pipeline
            ->enableCompatibilityValidation(false)
            ->validateCollectionFromJson([
                'children' => [
                    ['name' => 'campo_1', 'type' => 'text'],
                    ['name' => 'campo_2', 'type' => 'text'],
                ],
            ])
            ->validateCollection();

        $this->assertTrue($pipeline->valid());
    }

    #[Test]
    public function pipelineShouldResolveDynamicChoicesToIdsWhenProcessingCollection(): void
    {
        $expected = [
            [
                'uc' => 123,
                'estacao_amostral' => 1001,
                'unidade_amostral' => 2001,
                'tipo' => 'esec',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'uc' => 456,
                'estacao_amostral' => 1002,
                'unidade_amostral' => 2002,
                'tipo' => 'flona',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
            [
                'uc' => 789,
                'estacao_amostral' => 1003,
                'unidade_amostral' => 2003,
                'tipo' => 'resex',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => ['instanceID' => null],
            ],
        ];

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldNotStoreErrorsWhenCollectionValidationPasses(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->validateCollectionFromJson($this->formExampleImagesJson())
            ->validateCollection();

        $this->assertTrue($expected->valid());
        $this->assertSame([], $expected->errors());
    }

    #[Test]
    public function pipelineShouldStoreErrorsWhenCollectionValidationFails(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path_error)
            ->validateCollectionFromJson($this->formExampleImagesJson())
            ->validateCollection();

        $this->assertFalse($expected->valid());
        $this->assertCount(3, $expected->errors());
        $this->assertSame('check_point', $expected->errors()[0]['key']);
        $this->assertSame('E esperado um valor inteiro.', $expected->errors()[0]['message']);
    }

    #[Test]
    public function pipelineShouldAppendErrorWhenDynamicChoiceDoesNotMatchSpreadsheetValue(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoicesWithMissingUc())
            ->validateCollection();

        $this->assertFalse($expected->valid());
        $this->assertCount(1, $expected->errors());
        $this->assertSame('uc', $expected->errors()[0]['key']);
        $this->assertStringContainsString('nao foi encontrado nas escolhas dinamicas', $expected->errors()[0]['message']);
    }

    #[Test]
    public function pipelineShouldFillMissingUuidFieldsWhenPathIsDeclared(): void
    {
        $pipeline = new class extends DataCollectionSpreadsheetReviewPipeline {
            public function seed(array $data): self
            {
                $this->data_collection = $data;
                $this->data_collection_prepared = false;

                return $this;
            }
        };

        $existingUuid = '019eae51-20fc-72f8-a380-f1c31f9391f6';
        $input = [[
            'coletor' => [
                [
                    'coletor/nome' => 'Ana',
                ],
                [
                    'coletor/nome' => 'Bruno',
                    'coletor/uuid' => $existingUuid,
                ],
            ],
        ]];

        $result = $pipeline
            ->seed($input)
            ->fillMissingUuidFields(['coletor/uuid'])
            ->process();

        $this->assertArrayHasKey('coletor/uuid', $result[0]['coletor'][0]);
        $this->assertArrayHasKey('coletor/uuid', $result[0]['coletor'][1]);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result[0]['coletor'][0]['coletor/uuid'],
        );
        $this->assertSame($existingUuid, $result[0]['coletor'][1]['coletor/uuid']);
    }

    private function formExampleImagesJson(): array
    {
        return json_decode(
            (string) file_get_contents(__DIR__ . '/../files/jsons/Form-test-example-with-images.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    private function dynamicChoices(): array
    {
        return [
            'uc' => [
                ['label' => 'Estação Ecológica da Terra do Meio', 'name' => 123],
                ['label' => 'Floresta Nacional de Brasília', 'name' => 456],
                ['label' => 'Reserva Extrativista do Tapajós', 'name' => 789],
            ],
            'estacao_amostral' => [
                ['label' => 'EA-001 Teste', 'name' => 1001],
                ['label' => 'EA-002 Teste', 'name' => 1002],
                ['label' => 'EA-003 Teste', 'name' => 1003],
            ],
            'unidade_amostral' => [
                ['label' => 'UA-001 Teste', 'name' => 2001],
                ['label' => 'UA-002 Teste', 'name' => 2002],
                ['label' => 'UA-003 Teste', 'name' => 2003],
            ],
        ];
    }

    private function dynamicChoicesWithMissingUc(): array
    {
        return [
            'uc' => [
                ['label' => 'Floresta Nacional de Brasília', 'name' => 456],
                ['label' => 'Reserva Extrativista do Tapajós', 'name' => 789],
            ],
            'estacao_amostral' => [
                ['label' => 'EA-001 Teste', 'name' => 1001],
                ['label' => 'EA-002 Teste', 'name' => 1002],
                ['label' => 'EA-003 Teste', 'name' => 1003],
            ],
            'unidade_amostral' => [
                ['label' => 'UA-001 Teste', 'name' => 2001],
                ['label' => 'UA-002 Teste', 'name' => 2002],
                ['label' => 'UA-003 Teste', 'name' => 2003],
            ],
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $worksheet
     */
    private function makePipelineWithSpreadsheet(array $worksheet): DataCollectionSpreadsheetReviewPipeline
    {
        $pipeline = new DataCollectionSpreadsheetReviewPipeline();
        $reflection = new ReflectionClass($pipeline);
        $property = $reflection->getProperty('data_collection');
        $property->setAccessible(true);
        $property->setValue($pipeline, $worksheet);

        $headersProperty = $reflection->getProperty('spreadsheet_headers');
        $headersProperty->setAccessible(true);
        $headersProperty->setValue($pipeline, $worksheet[0] ?? []);

        $preparedProperty = $reflection->getProperty('data_collection_prepared');
        $preparedProperty->setAccessible(true);
        $preparedProperty->setValue($pipeline, false);

        $compatibilityProperty = $reflection->getProperty('compatibility_checked');
        $compatibilityProperty->setAccessible(true);
        $compatibilityProperty->setValue($pipeline, false);

        return $pipeline;
    }

    /**
     * @return array<string, mixed>
     */
    private function repeatFormDefinition(): array
    {
        return [
            'children' => [
                ['name' => 'campo_1', 'type' => 'text'],
                ['name' => 'campo_2', 'type' => 'text'],
                [
                    'name' => 'grupo_1',
                    'type' => 'repeat',
                    'children' => [
                        ['name' => 'campo_1', 'type' => 'text'],
                        ['name' => 'grupo_campo_1', 'type' => 'text'],
                        [
                            'name' => 'biometria_registro',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'comprimento_total_cm', 'type' => 'decimal'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
