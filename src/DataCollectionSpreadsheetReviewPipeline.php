<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

use Icmbio\ValidateRegister\Enums\DataCollectionSelectorKey;
use Icmbio\ValidateRegister\Validator\StructureSpreadsheetData;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class DataCollectionSpreadsheetReviewPipeline
{
    private const SPREADSHEET_DATA_START_LINE = 3;

    protected array $data_collection = [];
    protected array $validators = [];
    protected array $errors = [];
    protected array $dynamic_choices = [];
    protected array $form_definition = [];
    protected array $uuid_field_paths = [];
    protected array $repeat_group_keys = [];
    protected array $structured_row_lines = [];
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

    /**
     * @param array<int, string> $paths
     */
    public function fillMissingUuidFields(array $paths): self
    {
        $normalizedPaths = [];

        foreach ($paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $path = trim($path);
            if ($path === '') {
                continue;
            }

            $normalizedPaths[] = $path;
        }

        $this->uuid_field_paths = array_values(array_unique($normalizedPaths));
        $this->data_collection_prepared = false;

        return $this;
    }

    public function collection(): array
    {
        if ($this->shouldStructureDataCollection()) {
            return (new StructureSpreadsheetData($this->data_collection, $this->repeat_group_keys))
                ->estruture()
                ->toArray();
        }

        return $this->data_collection;
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

            $sourceLine = $this->structured_row_lines[$index] ?? null;
            $validationErrors = array_merge(
                $validationErrors,
                ValidateCollectionData::createErrorsList($collection, $this->validators, $index, null, null, $sourceLine),
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
        $this->repeat_group_keys = $this->extractRepeatGroupKeys($formDefinition['children'] ?? []);

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

    /**
     * @param array<int, mixed> $nodes
     * @return array<int, string>
     */
    protected function extractRepeatGroupKeys(array $nodes): array
    {
        $repeatGroupKeys = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $name = (string) ($node['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if (($node['type'] ?? null) === 'repeat') {
                $repeatGroupKeys[] = $name;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $repeatGroupKeys = array_merge($repeatGroupKeys, $this->extractRepeatGroupKeys($node['children']));
            }
        }

        return array_values(array_unique($repeatGroupKeys));
    }

    protected function prepareDataCollection(): void
    {
        if ($this->data_collection_prepared) {
            return;
        }

        $this->structured_row_lines = [];

        if ($this->shouldStructureDataCollection()) {
            $structureSpreadsheetData = (new StructureSpreadsheetData($this->data_collection, $this->repeat_group_keys))
                ->estruture();

            $this->data_collection = $structureSpreadsheetData->toArray();
            $this->structured_row_lines = $structureSpreadsheetData->structuredRowLines();
        }

        $this->errors = [];
        $this->data_collection = $this->sanitizeCollectionValues($this->data_collection);
        $this->data_collection = $this->resolveDynamicChoices($this->data_collection);
        $this->data_collection = $this->castCollectionValues($this->data_collection);
        $this->data_collection = $this->fillMissingUuidValues($this->data_collection);
        $this->data_collection_prepared = true;
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @return list<array<string, mixed>>
     */
    protected function fillMissingUuidValues(array $collections): array
    {
        if ($this->uuid_field_paths === []) {
            return $collections;
        }

        foreach ($collections as $index => $collection) {
            if (! is_array($collection)) {
                continue;
            }

            $collections[$index] = $this->fillMissingUuidValuesInNode($collection);
        }

        return $collections;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    protected function fillMissingUuidValuesInNode(array $node): array
    {
        foreach ($this->uuid_field_paths as $uuidFieldPath) {
            $node = $this->fillMissingUuidValueAtPath($node, $uuidFieldPath);
        }

        foreach ($node as $fieldName => $fieldValue) {
            if (! is_array($fieldValue)) {
                continue;
            }

            if (array_is_list($fieldValue)) {
                foreach ($fieldValue as $childIndex => $childValue) {
                    if (! is_array($childValue)) {
                        continue;
                    }

                    $fieldValue[$childIndex] = $this->fillMissingUuidValuesInNode($childValue);
                }

                $node[$fieldName] = $fieldValue;

                continue;
            }

            $node[$fieldName] = $this->fillMissingUuidValuesInNode($fieldValue);
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    protected function fillMissingUuidValueAtPath(array $node, string $uuidFieldPath): array
    {
        if (array_key_exists($uuidFieldPath, $node)) {
            if (! $this->hasValue($node[$uuidFieldPath])) {
                $node[$uuidFieldPath] = $this->generateUuidV7();
            }

            return $node;
        }

        $fieldPrefix = $this->parentPath($uuidFieldPath);
        if ($fieldPrefix === '') {
            return $node;
        }

        if ($this->nodeHasDirectFieldPrefix($node, $fieldPrefix)) {
            $node[$uuidFieldPath] = $this->generateUuidV7();
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $node
     */
    protected function nodeHasDirectFieldPrefix(array $node, string $fieldPrefix): bool
    {
        $prefix = rtrim($fieldPrefix, '/') . '/';

        foreach ($node as $fieldName => $_fieldValue) {
            if (! is_string($fieldName)) {
                continue;
            }

            if (str_starts_with($fieldName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function generateUuidV7(): string
    {
        return Uuid::uuid7()->toString();
    }

    protected function sanitizeCollectionValues(array $collections): array
    {
        foreach ($collections as $index => $collection) {
            if (! is_array($collection)) {
                continue;
            }

            $collections[$index] = $this->sanitizeNodeValues($collection);
        }

        return $collections;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    protected function sanitizeNodeValues(array $node): array
    {
        foreach ($node as $fieldName => $fieldValue) {
            if (is_array($fieldValue)) {
                if (array_is_list($fieldValue)) {
                    foreach ($fieldValue as $childIndex => $childValue) {
                        if (is_array($childValue)) {
                            $fieldValue[$childIndex] = $this->sanitizeNodeValues($childValue);
                            continue;
                        }

                        $fieldValue[$childIndex] = $this->applySanitizers($childValue);
                    }

                    $node[$fieldName] = $fieldValue;
                    continue;
                }

                $node[$fieldName] = $this->sanitizeNodeValues($fieldValue);
                continue;
            }

            $node[$fieldName] = $this->applySanitizers($fieldValue);
        }

        return $node;
    }

    protected function applySanitizers(mixed $value): mixed
    {
        foreach ($this->sanitizers() as $sanitizer) {
            $value = $sanitizer($value);
        }

        return $value;
    }

    /** @return list<callable(mixed): mixed> */
    protected function sanitizers(): array
    {
        return [
            fn (mixed $value): mixed => $this->sanitizeTrim($value),
        ];
    }

    protected function sanitizeTrim(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return trim($value);
    }

    protected function castCollectionValues(array $collections): array
    {
        foreach ($collections as $index => $collection) {
            if (! is_array($collection)) {
                continue;
            }

            $collections[$index] = $this->castNodeValues($collection);
        }

        return $collections;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    protected function castNodeValues(array $node): array
    {
        foreach ($node as $fieldName => $fieldValue) {
            $fieldType = (string) ($this->validators[$fieldName]['type'] ?? '');

            if ($fieldType === 'repeat' && is_array($fieldValue) && array_is_list($fieldValue)) {
                foreach ($fieldValue as $childIndex => $childNode) {
                    if (! is_array($childNode)) {
                        continue;
                    }

                    $fieldValue[$childIndex] = $this->castNodeValues($childNode);
                }

                $node[$fieldName] = $fieldValue;
                continue;
            }

            $node[$fieldName] = $this->castValueByType($fieldType, $fieldValue);
        }

        return $node;
    }

    protected function castValueByType(string $fieldType, mixed $fieldValue): mixed
    {
        if ($fieldType !== 'integer') {
            if ($fieldType !== 'decimal') {
                return $fieldValue;
            }

            return $this->normalizeDecimalValue($fieldValue);
        }

        if (is_string($fieldValue)) {
            $trimmedValue = trim($fieldValue);

            if ($trimmedValue !== '' && preg_match('/^-?\d+$/', $trimmedValue) === 1) {
                return (int) $trimmedValue;
            }
        }

        if (! is_array($fieldValue) || ! array_is_list($fieldValue)) {
            return $fieldValue;
        }

        foreach ($fieldValue as $index => $item) {
            if (! is_string($item)) {
                continue;
            }

            $trimmedItem = trim($item);

            if ($trimmedItem !== '' && preg_match('/^-?\d+$/', $trimmedItem) === 1) {
                $fieldValue[$index] = (int) $trimmedItem;
            }
        }

        return $fieldValue;
    }

    protected function normalizeDecimalValue(mixed $fieldValue): mixed
    {
        if (is_int($fieldValue) || is_float($fieldValue)) {
            return $this->normalizeDecimalString((string) $fieldValue);
        }

        if (! is_string($fieldValue)) {
            return $fieldValue;
        }

        $trimmedValue = trim($fieldValue);
        if ($trimmedValue === '') {
            return $fieldValue;
        }

        $normalizedValue = str_replace(',', '.', $trimmedValue);
        if (! is_numeric($normalizedValue)) {
            return $fieldValue;
        }

        return $this->normalizeDecimalString($normalizedValue);
    }

    protected function normalizeDecimalString(string $value): string
    {
        if (str_contains($value, '.')) {
            $normalized = rtrim(rtrim($value, '0'), '.');

            return str_contains($normalized, '.') ? $normalized : $normalized . '.0';
        }

        return $value . '.0';
    }

    protected function parentPath(string $fieldPath): string
    {
        $segments = explode('/', $fieldPath);
        array_pop($segments);

        return implode('/', $segments);
    }

    protected function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && $value === []) {
            return false;
        }

        return true;
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
                    $sourceLine = $this->structured_row_lines[$index] ?? null;
                    $this->errors[] = [
                        'index' => $index,
                        'line' => $sourceLine ?? $this->spreadsheetLineFromIndex($index),
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

    protected function spreadsheetLineFromIndex(int $index): int
    {
        return $index + self::SPREADSHEET_DATA_START_LINE;
    }
}
