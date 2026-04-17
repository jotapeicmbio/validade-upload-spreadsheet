<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

use RuntimeException;

class DataCollectionSpreadsheetConvertPipeline
{
    /** @var list<array<string, mixed>> */
    protected array $collection = [];

    protected string $outputDirectory;
    protected ?string $instanceName = null;
    protected string $instanceVersion = '1';
    protected ?string $timestamp = null;

    public function __construct()
    {
        $this->outputDirectory = getcwd() . '/xmls';
    }

    /** @param list<array<string, mixed>> $collection */
    public function collection(array $collection): self
    {
        $this->collection = $collection;

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
            $instanceName = $this->resolveInstanceName();
            $xml = XformXmlBuilder::build(
                $preparedItem,
                $this->extractKeysFromItem($preparedItem),
                $instanceName,
                $instanceName,
                $this->instanceVersion,
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
        if ($this->instanceName === null || trim($this->instanceName) === '') {
            throw new RuntimeException('Instance name was not provided.');
        }

        return $this->instanceName;
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
