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
                'uc' => 'Estação Ecológica da Terra do Meio',
                'estacao_amostral' => 'EA-001 Teste',
                'unidade_amostral' => 'UA-001 Teste',
                'tipo' => 'esec',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
            [
                'uc' => 'Floresta Nacional de Brasília',
                'estacao_amostral' => 'EA-002 Teste',
                'unidade_amostral' => 'UA-002 Teste',
                'tipo' => 'flona',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
            [
                'uc' => 'Reserva Extrativista do Tapajós',
                'estacao_amostral' => 'EA-003 Teste',
                'unidade_amostral' => 'UA-003 Teste',
                'tipo' => 'resex',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
        ];

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldTransformTheCollectionWhenAFfunctionIsPassedToIt(): void
    {
        $expected = [
            [
                'UC' => 'ESTAÇÃO ECOLÓGICA DA TERRA DO MEIO',
                'estacao_amostral' => 'EA-001 Teste',
                'unidade_amostral' => 'UA-001 Teste',
                'tipo' => 'esec',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
            [
                'UC' => 'FLORESTA NACIONAL DE BRASÍLIA',
                'estacao_amostral' => 'EA-002 Teste',
                'unidade_amostral' => 'UA-002 Teste',
                'tipo' => 'flona',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
            [
                'UC' => 'RESERVA EXTRATIVISTA DO TAPAJÓS',
                'estacao_amostral' => 'EA-003 Teste',
                'unidade_amostral' => 'UA-003 Teste',
                'tipo' => 'resex',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
        ];

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->transform(fn($item) => mb_strtoupper($item), DataCollectionSelectorKey::INDICE, [0])
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldTransformTheCollectionWhenColumnNameIsPassed(): void
    {
        $expected = [
            [
                'uc' => 'Estação Ecológica da Terra do Meio',
                'ESTACAO_AMOSTRAL' => 'EA-001 TESTE',
                'unidade_amostral' => 'UA-001 Teste',
                'tipo' => 'esec',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
            [
                'uc' => 'Floresta Nacional de Brasília',
                'ESTACAO_AMOSTRAL' => 'EA-002 TESTE',
                'unidade_amostral' => 'UA-002 Teste',
                'tipo' => 'flona',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
            [
                'uc' => 'Reserva Extrativista do Tapajós',
                'ESTACAO_AMOSTRAL' => 'EA-003 TESTE',
                'unidade_amostral' => 'UA-003 Teste',
                'tipo' => 'resex',
                'check_point' => [18, 28, 38],
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
        ];

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->transform(fn($item) => trim(mb_strtoupper($item)), keys: ['estacao_amostral'])
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldKeepCollectionUntouchedWhenNoKeysArePassed(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->process();

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->transform(fn($item) => trim(mb_strtoupper($item)), DataCollectionSelectorKey::NAME, [])
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldKeepCollectionUntouchedWhenOnlyFunctionIsPassed(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->process();

        $result = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->transform(fn($item) => trim(mb_strtoupper($item)))
            ->process();

        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function pipelineShouldNotStoreErrorsWhenCollectionValidationPasses(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path)
            ->validateCollectionFromJson($this->formExampleImagesJson()['children'])
            ->validateCollection();

        $this->assertTrue($expected->valid());
        $this->assertSame([], $expected->errors());
    }

    #[Test]
    public function pipelineShouldStoreErrorsWhenCollectionValidationFails(): void
    {
        $expected = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilha_path_error)
            ->validateCollectionFromJson($this->formExampleImagesJson()['children'])
            ->validateCollection();

        $this->assertFalse($expected->valid());
        $this->assertCount(3, $expected->errors());
        $this->assertSame('check_point', $expected->errors()[0]['key']);
        $this->assertSame('E esperado um valor inteiro.', $expected->errors()[0]['message']);
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
}
