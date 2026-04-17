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
    protected array $dynamic_choices = [];
    protected array $form_definition = [];
    protected bool $data_collection_prepared = false;

    public function setDataCollection(string $path): self
    {
        $this->data_collection = SpreadsheetReader::load($path);
        $this->data_collection_prepared = false;
        $this->errors = [];
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

        $this->data_collection_prepared = false;

        return $this;
    }

    public function process(): array
    {
        $this->prepareDataCollection();

        return $this->data_collection;
    }

    public function validateCollection(): self
    {
        $this->prepareDataCollection();
        $validationErrors = [];

        foreach ($this->data_collection as $index => $collection) {
            if (! is_array($collection)) {
                continue;
            }

            $validationErrors = array_merge(
                $validationErrors,
                ValidateCollectionData::createErrorsList($collection, $this->validators, $index),
            );
        }

        $this->errors = array_merge($this->errors, $validationErrors);

        return $this;
    }

    public function validateCollectionFromJson(array $formDefinition = [], array $dynamicChoices = []): self
    {
        $this->form_definition = $formDefinition;
        $this->dynamic_choices = $dynamicChoices;
        $this->validators = CreateValidatorsStructure::build($formDefinition['children'] ?? [], $dynamicChoices);

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

    public function formDefinition(): array
    {
        return $this->form_definition;
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

    protected function prepareDataCollection(): void
    {
        if ($this->data_collection_prepared) {
            return;
        }

        if ($this->shouldStructureDataCollection()) {
            $this->data_collection = (new StructureSpreadsheetData($this->data_collection))
                ->estruture()
                ->toArray();
        }

        $this->errors = [];
        $this->data_collection = $this->resolveDynamicChoices($this->data_collection);
        $this->data_collection_prepared = true;
    }

    protected function resolveDynamicChoices(array $collections): array
    {
        if ($this->dynamic_choices === []) {
            return $collections;
        }

        foreach ($collections as $index => $collection) {
            if (! is_array($collection)) {
                continue;
            }

            foreach ($this->dynamic_choices as $fieldName => $options) {
                if (! array_key_exists($fieldName, $collection) || ! is_array($options)) {
                    continue;
                }

                $currentValue = $collection[$fieldName];

                if ($this->valueAlreadyResolved($currentValue, $options)) {
                    continue;
                }

                $matchedOption = $this->findDynamicChoiceByLabel($currentValue, $options);

                if ($matchedOption === null) {
                    $this->errors[] = [
                        'index' => $index,
                        'key' => $fieldName,
                        'value' => $currentValue,
                        'message' => sprintf(
                            'O valor (%s) nao foi encontrado nas escolhas dinamicas de %s.',
                            (string) $currentValue,
                            $fieldName,
                        ),
                    ];
                    continue;
                }

                $collections[$index][$fieldName] = $matchedOption['name'];
            }
        }

        return $collections;
    }

    /** @param list<array<string, mixed>> $options */
    protected function valueAlreadyResolved(mixed $currentValue, array $options): bool
    {
        foreach ($options as $option) {
            if (! is_array($option) || ! array_key_exists('name', $option)) {
                continue;
            }

            if ($option['name'] === $currentValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $options
     * @return array<string, mixed>|null
     */
    protected function findDynamicChoiceByLabel(mixed $currentValue, array $options): ?array
    {
        foreach ($options as $option) {
            if (! is_array($option) || ! array_key_exists('label', $option)) {
                continue;
            }

            if ((string) $option['label'] === (string) $currentValue) {
                return $option;
            }
        }

        return null;
    }
}
