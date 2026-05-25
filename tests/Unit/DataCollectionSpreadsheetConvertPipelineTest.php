<?php

namespace Tests\ValidateRegister\Unit;

use Exception;
use Icmbio\ValidateRegister\DataCollectionSpreadsheetConvertPipeline;
use Icmbio\ValidateRegister\DataCollectionSpreadsheetReviewPipeline;
use DOMDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DataCollectionSpreadsheetConvertPipelineTest extends TestCase
{
    protected string $planilhaPath = __DIR__ . '/../files/planilhas/planilha_Form-test-example-with-images_modelo.xlsx';
    protected string $planilhaPathError = __DIR__ . '/../files/planilhas/planilha_Form-test-example-with-images_error_modelo.xlsx';

    #[Test]
    public function pipelineshouldGenerateXmls(): void
    {
        $input = [
            [
                'uc' => 123,
                'estacao_amostral' => 1001,
                'unidade_amostral' => 2001,
                'tipo' => 'esec',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
            [
                'uc' => 456,
                'estacao_amostral' => 1002,
                'unidade_amostral' => 2002,
                'tipo' => 'flona',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
            [
                'uc' => 789,
                'estacao_amostral' => 1003,
                'unidade_amostral' => 2003,
                'tipo' => 'resex',
                'check_point' => 18,
                'img_one' => null,
                'img_two' => null,
                'meta' => [],
            ],
        ];

        $expected = [
            $this->getFileExpected(__DIR__ . '/../files/instances/Form-test-example-with-images_1'),
            $this->getFileExpected(__DIR__ . '/../files/instances/Form-test-example-with-images_2'),
            $this->getFileExpected(__DIR__ . '/../files/instances/Form-test-example-with-images_3'),
        ];

        $result = (new DataCollectionSpreadsheetConvertPipeline)
            ->collection($input)
            ->instanceInfo('Form-test-example-with-images', '2026041601')
            ->timestamp('2026-04-10T18:01:45.000-03:00')
            ->generate();

        $this->assertCount(3, $result);

        foreach ($result as $index => $filePath) {
            $this->assertFileExists($filePath);
            $this->assertSame(
                $this->normalizeXml($expected[$index], true),
                $this->normalizeXml((string) file_get_contents($filePath), true),
            );
        }
    }

    #[Test]
    public function pipelineShouldKeepGivenInstanceIdWhenItAlreadyExistsInInput(): void
    {
        $input = [[
            'uc' => 789,
            'estacao_amostral' => 1003,
            'unidade_amostral' => 2003,
            'tipo' => 'resex',
            'check_point' => 18,
            'img_one' => null,
            'img_two' => null,
            'meta' => [
                'instanceID' => 'uuid:custom-instance-id',
            ],
        ]];

        $expected = $this->getFileExpected(__DIR__ . '/../files/instances/Form-test-example-with-images_com_uuid');

        $result = (new DataCollectionSpreadsheetConvertPipeline())
            ->collection($input)
            ->instanceInfo('Form-test-example-with-images_com_uuid', '2026041601')
            ->timestamp('2026-04-10T18:01:45.000-03:00')
            ->generate();

        $this->assertCount(1, $result);
        $this->assertFileExists($result[0]);
        $this->assertSame(
            $this->normalizeXml($expected, false),
            $this->normalizeXml((string) file_get_contents($result[0]), false),
        );
    }

    #[Test]
    public function pipelineShouldGenerateXmlsFromAValidReviewPipeline(): void
    {
        $reviewPipeline = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilhaPath)
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->validateCollection();

        $expected = [
            $this->getFileExpected(__DIR__ . '/../files/instances/Form-test-example-with-images_1'),
            $this->getFileExpected(__DIR__ . '/../files/instances/Form-test-example-with-images_2'),
            $this->getFileExpected(__DIR__ . '/../files/instances/Form-test-example-with-images_3'),
        ];

        $result = (new DataCollectionSpreadsheetConvertPipeline())
            ->collection($reviewPipeline)
            ->timestamp('2026-04-10T18:01:45.000-03:00')
            ->generate();

        $this->assertCount(3, $result);

        foreach ($result as $index => $filePath) {
            $this->assertFileExists($filePath);
            $this->assertSame(
                $this->normalizeXml($expected[$index], true),
                $this->normalizeXml((string) file_get_contents($filePath), true),
            );
        }
    }

    #[Test]
    public function pipelineShouldRejectAnInvalidReviewPipeline(): void
    {
        $reviewPipeline = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilhaPathError)
            ->validateCollectionFromJson($this->formExampleImagesJson())
            ->validateCollection();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The review pipeline contains validation errors.');

        (new DataCollectionSpreadsheetConvertPipeline())
            ->collection($reviewPipeline);
    }

    #[Test]
    public function pipelineShouldAllowManualFormInfoToOverrideReviewPipelineMetadata(): void
    {
        $reviewPipeline = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($this->planilhaPath)
            ->validateCollectionFromJson($this->formExampleImagesJson(), $this->dynamicChoices())
            ->validateCollection();

        $customForm = $this->formExampleImagesJson();
        $customForm['id_string'] = 'Form-test-example-with-images_custom';

        $result = (new DataCollectionSpreadsheetConvertPipeline())
            ->formInfo($customForm)
            ->collection($reviewPipeline)
            ->timestamp('2026-04-10T18:01:45.000-03:00')
            ->generate();

        $this->assertCount(3, $result);
        $this->assertStringContainsString('Form-test-example-with-images_custom_1', $result[0]);
    }

    protected function getFileExpected($path)
    {
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        throw new Exception('File dont exists');
    }

    protected function normalizeXml(string $xml, bool $maskInstanceId = false): string
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        $document->loadXML($xml);

        if ($maskInstanceId) {
            $instanceIdNodes = $document->getElementsByTagName('instanceID');
            foreach ($instanceIdNodes as $node) {
                while ($node->firstChild !== null) {
                    $node->removeChild($node->firstChild);
                }
                $node->appendChild($document->createTextNode('__INSTANCE_ID__'));
            }
        }

        return (string) $document->C14N();
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
}
