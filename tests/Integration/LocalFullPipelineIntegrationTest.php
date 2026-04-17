<?php

declare(strict_types=1);

namespace Tests\ValidateRegister\Integration;

use DOMDocument;
use Icmbio\ValidateRegister\DataCollectionSpreadsheetConvertPipeline;
use Icmbio\ValidateRegister\DataCollectionSpreadsheetReviewPipeline;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LocalFullPipelineIntegrationTest extends TestCase
{
    #[Test]
    public function itRunsTheFullPipelineUsingLocalFixtures(): void
    {
        $spreadsheetPath = $this->findSingleFile($this->localPath('planilhas'), ['xlsx', 'xls', 'csv']);
        $jsonPath = $this->findSingleFile($this->localPath('jsons'), ['json']);
        $expectedInstancePaths = $this->findAllFiles($this->localPath('instances'));

        if ($spreadsheetPath === null || $jsonPath === null || $expectedInstancePaths === []) {
            self::markTestSkipped('Local fixtures are not available in tests/local.');
        }

        $formDefinition = json_decode(
            (string) file_get_contents($jsonPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $reviewPipeline = (new DataCollectionSpreadsheetReviewPipeline())
            ->setDataCollection($spreadsheetPath)
            ->validateCollectionFromJson($formDefinition, $this->dynamicChoices())
            ->validateCollection();

        self::assertTrue($reviewPipeline->valid(), json_encode($reviewPipeline->errors(), JSON_THROW_ON_ERROR));

        $outputDirectory = sprintf('%s/validade-upload-spreadsheet-%s', sys_get_temp_dir(), uniqid('', true));

        try {
            $generatedFiles = (new DataCollectionSpreadsheetConvertPipeline())
                ->collection($reviewPipeline)
                ->outputDirectory($outputDirectory)
                ->timestamp('2026-04-10T18:01:45.000-03:00')
                ->generate();

            self::assertCount(3, $generatedFiles);

            foreach ($generatedFiles as $index => $generatedFile) {
                self::assertFileExists($generatedFile);
                self::assertSame(
                    $this->normalizeXml((string) file_get_contents($expectedInstancePaths[$index])),
                    $this->normalizeXml((string) file_get_contents($generatedFile)),
                );
            }
        } finally {
            $this->removeDirectory($outputDirectory);
        }
    }

    private function localPath(string $relativePath): string
    {
        return __DIR__ . '/../local/' . $relativePath;
    }

    /**
     * @param list<string> $extensions
     */
    private function findSingleFile(string $directory, array $extensions): ?string
    {
        $files = $this->findFilesByExtensions($directory, $extensions);

        if (count($files) !== 1) {
            return null;
        }

        return $files[0];
    }

    /**
     * @return list<string>
     */
    private function findAllFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_file($path)) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    private function findFilesByExtensions(string $directory, array $extensions): array
    {
        $files = $this->findAllFiles($directory);

        return array_values(array_filter(
            $files,
            static fn (string $path): bool => in_array(strtolower((string) pathinfo($path, PATHINFO_EXTENSION)), $extensions, true),
        ));
    }

    private function normalizeXml(string $xml): string
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;
        $document->loadXML($xml);

        return (string) $document->C14N();
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
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
