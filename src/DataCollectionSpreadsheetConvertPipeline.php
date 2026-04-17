<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

use RuntimeException;

class DataCollectionSpreadsheetConvertPipeline
{
    /** @var list<array<string, mixed>> */
    protected array $collection = [];

    protected string $outputDirectory;
    protected array $formDefinition = [];
    protected bool $hasManualFormInfo = false;
    protected ?string $instanceName = null;
    protected string $instanceVersion = '1';
    protected ?string $timestamp = null;

    public function __construct()
    {
        $this->outputDirectory = getcwd() . '/xmls';
    }

    /** @param list<array<string, mixed>>|DataCollectionSpreadsheetReviewPipeline $collection */
    public function collection(array|DataCollectionSpreadsheetReviewPipeline $collection): self
    {
        if ($collection instanceof DataCollectionSpreadsheetReviewPipeline) {
            if (! $collection->valid()) {
                throw new RuntimeException('The review pipeline contains validation errors.');
            }

            if (! $this->hasManualFormInfo) {
                $this->formDefinition = $collection->formDefinition();
            }

            /** @var list<array<string, mixed>> $structuredCollection */
            $structuredCollection = $collection->process();
            $this->collection = $structuredCollection;

            return $this;
        }

        $this->collection = $collection;

        return $this;
    }

    public function formInfo(array $formDefinition): self
    {
        $this->formDefinition = $formDefinition;
        $this->hasManualFormInfo = true;

        return $this;
    }

    public function outputDirectory(string $outputDirectory): self
    {
        $this->outputDirectory = rtrim($outputDirectory, '/');

        return $this;
    }

    public function instanceInfo(string $instanceName, string|int $instanceVersion = '1'): self
    {
        $this->instanceName = $instanceName;
        $this->instanceVersion = (string) $instanceVersion;

        return $this;
    }

    public function timestamp(?string $timestamp = null): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /** @return list<string> */
    public function generate(): array
    {
        $this->ensureOutputDirectoryExists();

        $generatedFiles = [];

        foreach ($this->collection as $index => $item) {
            $preparedItem = $this->prepareItem($item);
            ['name' => $instanceName, 'version' => $instanceVersion] = $this->resolvedInstanceInfo();
            $xml = XformXmlBuilder::build(
                $preparedItem,
                $this->extractKeysFromItem($preparedItem),
                $instanceName,
                $instanceName,
                $instanceVersion,
                null,
                $this->resolveTimestamp(),
            );

            $filePath = sprintf('%s/%s_%d', $this->outputDirectory, $instanceName, $index + 1);

            if (file_put_contents($filePath, $xml) === false) {
                throw new RuntimeException(sprintf('Unable to write XML file to %s', $filePath));
            }

            $generatedFiles[] = $filePath;
        }

        return $generatedFiles;
    }

    protected function ensureOutputDirectoryExists(): void
    {
        if (is_dir($this->outputDirectory)) {
            return;
        }

        if (! mkdir($this->outputDirectory, 0777, true) && ! is_dir($this->outputDirectory)) {
            throw new RuntimeException(sprintf('Unable to create output directory %s', $this->outputDirectory));
        }
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function prepareItem(array $item): array
    {
        if (
            ! array_key_exists('instanceID', $item)
            && isset($item['meta'])
            && is_array($item['meta'])
            && array_key_exists('instanceID', $item['meta'])
        ) {
            $item['instanceID'] = $item['meta']['instanceID'];
        }

        $item['instanceID'] = $item['instanceID'] ?? null;

        return $item;
    }

    protected function resolveInstanceName(): string
    {
        if ($this->instanceName !== null && trim($this->instanceName) !== '') {
            return $this->instanceName;
        }

        $nameFromForm = $this->formDefinition['id_string'] ?? $this->formDefinition['name'] ?? null;

        if (! is_string($nameFromForm) || trim($nameFromForm) === '') {
            throw new RuntimeException('Instance name was not provided.');
        }

        return $nameFromForm;
    }

    protected function resolveInstanceVersion(): string
    {
        if ($this->instanceVersion !== '1' || $this->instanceName !== null) {
            return $this->instanceVersion;
        }

        $versionFromForm = $this->formDefinition['version'] ?? null;

        if (! is_string($versionFromForm) && ! is_int($versionFromForm)) {
            return '1';
        }

        return (string) $versionFromForm;
    }

    /**
     * @return array{name: string, version: string}
     */
    protected function resolvedInstanceInfo(): array
    {
        return [
            'name' => $this->resolveInstanceName(),
            'version' => $this->resolveInstanceVersion(),
        ];
    }

    protected function resolveTimestamp(): string
    {
        if ($this->timestamp !== null && trim($this->timestamp) !== '') {
            return $this->timestamp;
        }

        return (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.000-03:00');
    }

    /** @return list<string> */
    protected function extractKeysFromItem(array $item): array
    {
        $keys = [];

        foreach ($item as $key => $value) {
            if (is_array($value) && ! array_is_list($value)) {
                $keys[] = (string) $key;
                foreach ($this->extractNestedKeys((string) $key, $value) as $nestedKey) {
                    $keys[] = $nestedKey;
                }
                continue;
            }

            $keys[] = (string) $key;
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    protected function extractNestedKeys(string $prefix, array $item): array
    {
        $keys = [];

        foreach ($item as $key => $value) {
            $currentKey = sprintf('%s/%s', $prefix, $key);
            $keys[] = $currentKey;

            if (is_array($value) && ! array_is_list($value)) {
                foreach ($this->extractNestedKeys($currentKey, $value) as $nestedKey) {
                    $keys[] = $nestedKey;
                }
            }
        }

        return $keys;
    }
}
