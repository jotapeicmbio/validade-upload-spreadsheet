<?php

namespace Tests\ValidateRegister\Unit;

use Exception;
use Icmbio\ValidateRegister\DataCollectionSpreadsheetConvertPipeline;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DataCollectionSpreadsheetConvertPipelineTest extends TestCase
{
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
                $this->normalizeXml($expected[$index]),
                $this->normalizeXml((string) file_get_contents($filePath)),
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
            $this->normalizeXml($expected),
            $this->normalizeXml((string) file_get_contents($result[0])),
        );
    }

    protected function getFileExpected($path)
    {
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        throw new Exception('File dont exists');
    }

    protected function normalizeXml(string $xml): string
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        $document->loadXML($xml);

        return (string) $document->C14N();
    }
}
