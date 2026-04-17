<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

use Icmbio\ValidateRegister\Enums\DataCollectionSelectorKey;
use Icmbio\ValidateRegister\Validator\StructureSpreadsheetData;
use InvalidArgumentException;

class DataCollectionSpreadsheetReviewPipeline
{
    protected array $data_collection = [];
    protected array $validators = [];
    protected array $errors = [];

    public function setDataCollection(string $path): self
    {
        $this->data_collection = SpreadsheetReader::load($path);
        return $this;
    }

    public function transform(
        string|callable $fn,
        DataCollectionSelectorKey $selectorKey = DataCollectionSelectorKey::NAME,
        array $keys = []
    ): self {
        $indices = $this->resolveSelectedIndices($selectorKey, $keys);

        if ($indices === []) {
            return $this;
        }

        foreach ($this->data_collection as $rowIndex => $row) {
            foreach ($indices as $columnIndex) {
                if (! array_key_exists($columnIndex, $row) || $row[$columnIndex] === null) {
                    continue;
                }

                $this->data_collection[$rowIndex][$columnIndex] = $fn($row[$columnIndex]);
            }
        }

        return $this;
    }

    public function process(): array
    {
        if ($this->shouldStructureDataCollection()) {
            return (new StructureSpreadsheetData($this->data_collection))
                ->estruture()
                ->toArray();
        }

        return $this->data_collection;
    }

    public function validateCollection(): self
    {
        $this->data_collection = (new StructureSpreadsheetData($this->data_collection))
            ->estruture()
            ->toArray();

        $this->errors = [];

        foreach ($this->data_collection as $index => $collection) {
            if (! is_array($collection)) {
                continue;
            }

            $this->errors = array_merge(
                $this->errors,
                ValidateCollectionData::createErrorsList($collection, $this->validators, $index),
            );
        }

        return $this;
    }

    public function validateCollectionFromJson(array $validators = [], array $dynamicChoices = []): self
    {
        $this->validators = CreateValidatorsStructure::build($validators, $dynamicChoices);

        return $this;
    }

    public function valid(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    protected function resolveSelectedIndices(DataCollectionSelectorKey $selectorKey, array $keys): array
    {
        $indices = match ($selectorKey) {
            DataCollectionSelectorKey::INDICE => $this->resolveIndicesFromPosition($keys),
            DataCollectionSelectorKey::NAME => $this->resolveIndicesFromName($keys),
        };

        return array_values(array_unique($indices));
    }

    protected function resolveIndicesFromPosition(array $keys): array
    {
        $indices = [];

        foreach ($keys as $index) {
            if (! is_int($index) || $index < 0) {
                throw new InvalidArgumentException('Each "indice" entry must be a non-negative integer.');
            }

            $indices[] = $index;
        }

        return $indices;
    }

    protected function resolveIndicesFromName(array $keys): array
    {
        $indices = [];
        $headers = $this->data_collection[0] ?? [];

        foreach ($keys as $name) {
            $columnIndex = array_search($name, $headers, true);

            if ($columnIndex === false) {
                throw new InvalidArgumentException(sprintf('Column name "%s" was not found.', $name));
            }

            $indices[] = $columnIndex;
        }

        return $indices;
    }

    protected function shouldStructureDataCollection(): bool
    {
        if ($this->data_collection === []) {
            return false;
        }

        $firstRow = $this->data_collection[0] ?? null;

        return is_array($firstRow) && array_is_list($firstRow);
    }
}
