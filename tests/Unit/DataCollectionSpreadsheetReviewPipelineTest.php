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
}
