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
        $formDirectories = $this->findFormDirectories();

        if ($formDirectories === []) {
            self::markTestSkipped('Local fixtures are not available in tests/local.');
        }

        foreach ($formDirectories as $formDirectory) {
            $spreadsheetPath = $this->findSingleFile($formDirectory . '/planilhas', ['xlsx', 'xls', 'csv']);
            $jsonPath = $this->findSingleFile($formDirectory . '/jsons', ['json']);
            $databasePath = $this->findSingleFile($formDirectory . '/database', ['php']);

            self::assertNotNull($spreadsheetPath, sprintf('Spreadsheet fixture not found for %s', basename($formDirectory)));
            self::assertNotNull($jsonPath, sprintf('JSON fixture not found for %s', basename($formDirectory)));
            self::assertNotNull($databasePath, sprintf('Database fixture not found for %s', basename($formDirectory)));

            $formDefinition = json_decode(
                (string) file_get_contents($jsonPath),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            /** @var array<string, list<array<string, mixed>>> $dynamicChoices */
            $dynamicChoices = require $databasePath;

            $reviewPipeline = (new DataCollectionSpreadsheetReviewPipeline())
                ->setDataCollection($spreadsheetPath)
                ->validateCollectionFromJson($formDefinition, $dynamicChoices)
                ->validateCollection();

            self::assertTrue(
                $reviewPipeline->valid(),
                sprintf(
                    "Form directory: %s\n%s",
                    basename($formDirectory),
                    $this->formatErrors($reviewPipeline->errors()),
                ),
            );

            $outputDirectory = $this->localPath('gerados/' . basename($formDirectory));
            $this->removeDirectory($outputDirectory);

            $generatedFiles = (new DataCollectionSpreadsheetConvertPipeline())
                ->collection($reviewPipeline)
                ->outputDirectory($outputDirectory)
                ->timestamp('2026-04-10T18:01:45.000-03:00')
                ->generate();

            $structuredCollection = $reviewPipeline->process();

            self::assertCount(count($structuredCollection), $generatedFiles);
            self::assertNotEmpty($generatedFiles);

            foreach ($generatedFiles as $generatedFile) {
                self::assertFileExists($generatedFile);

                $document = new DOMDocument();
                $document->preserveWhiteSpace = false;
                $document->formatOutput = false;
                $document->loadXML((string) file_get_contents($generatedFile));

                self::assertSame(
                    (string) ($formDefinition['id_string'] ?? $formDefinition['name'] ?? ''),
                    $document->documentElement->nodeName,
                );
                self::assertSame(
                    (string) ($formDefinition['id_string'] ?? $formDefinition['name'] ?? ''),
                    $document->documentElement->getAttribute('id'),
                );
                self::assertSame(
                    (string) ($formDefinition['version'] ?? '1'),
                    $document->documentElement->getAttribute('version'),
                );
                self::assertNotSame('', (string) $document->C14N());
            }
        }
    }

    private function localPath(string $relativePath): string
    {
        return __DIR__ . '/../local/' . $relativePath;
    }

    /**
     * @return list<string>
     */
    private function findFormDirectories(): array
    {
        $root = $this->localPath('');
        if (! is_dir($root)) {
            return [];
        }

        $entries = scandir($root);
        if ($entries === false) {
            return [];
        }

        $directories = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'gerados') {
                continue;
            }

            $path = $root . $entry;

            if (
                is_dir($path)
                && is_dir($path . '/database')
                && is_dir($path . '/jsons')
                && is_dir($path . '/planilhas')
            ) {
                $directories[] = $path;
            }
        }

        sort($directories);

        return $directories;
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

    /**
     * @param list<array<string, mixed>> $errors
     */
    private function formatErrors(array $errors): string
    {
        if ($errors === []) {
            return 'No validation errors.';
        }

        $lines = ["Validation errors:"];

        foreach ($errors as $error) {
            $index = $error['index'] ?? '?';
            $key = (string) ($error['key'] ?? '?');
            $value = $this->stringifyErrorValue($error['value'] ?? null);
            $message = (string) ($error['message'] ?? 'Unknown error');

            $lines[] = sprintf(
                '[index=%s] key=%s value=%s message=%s',
                (string) $index,
                $key,
                $value,
                $message,
            );
        }

        return implode(PHP_EOL, $lines);
    }

    private function stringifyErrorValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return var_export($value, true);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
