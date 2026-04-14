<?php

declare (strict_types=1);

namespace Icmbio\ValidateRegister;

use Icmbio\ValidateXpathExpression\Xpath;
use Ramsey\Uuid\Uuid;

class CalculateFields
{
    public function __construct() {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $validators
     * @return array<string, mixed>
     */
    public static function apply(array $data, array $validators): array
    {
        return (new self())->applyCalculatedFields($data, $validators);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $validators
     * @return array<string, mixed>
     */
    public function applyCalculatedFields(array $data, array $validators): array
    {
        foreach ($validators as $fieldPath => $validator) {
            $calculationExpression = $validator['calculate'] ?? null;
            if (! is_string($calculationExpression) || trim($calculationExpression) === '') {
                continue;
            }
            $data = $this->addCalculatedValueToData($data, (string) $fieldPath, $calculationExpression);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $parentContext
     * @return array<string, mixed>
     */
    protected function buildContext(array $data, ?array $parentContext): array
    {
        $context = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                continue;
            }
            $context[$this->lastSegment((string) $key)] = $value;
        }

        if ($parentContext !== null) {
            foreach ($parentContext as $key => $value) {
                if (is_array($value) && array_is_list($value)) {
                    continue;
                }
                $context[$this->lastSegment((string) $key)] = $value;
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $parentContext
     * @return array<string, mixed>
     */
    protected function addCalculatedValueToData(
        array $data,
        string $fieldPath,
        string $calculationExpression,
        ?array $parentContext = null,
    ): array {
        $context         = $this->buildContext($data, $parentContext);
        $parentFieldPath = $this->parentPath($fieldPath);

        foreach ($data as $key => $value) {
            if ($key === $parentFieldPath && is_array($value) && array_is_list($value)) {
                foreach ($value as $rowIndex => $rowData) {
                    if (! is_array($rowData)) {
                        continue;
                    }
                    try {
                        if (
                            $calculationExpression === 'uuid()'
                            && array_key_exists($fieldPath, $rowData)
                            && $rowData[$fieldPath] !== null
                            && trim((string) $rowData[$fieldPath]) !== ''
                        ) {
                            continue;
                        }
                        $rowData[$fieldPath] = $calculationExpression === 'uuid()'
                            ? $this->generateUuidV4()
                            : Xpath::validate(
                                $calculationExpression,
                                null,
                                $context,
                                false,
                            );
                    } catch (\Throwable) {
                        continue;
                    }
                    $value[$rowIndex] = $rowData;
                }
                $data[$key] = $value;
                break;
            }

            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $rowIndex => $rowData) {
                    if (! is_array($rowData)) {
                        continue;
                    }
                    $value[$rowIndex] = $this->addCalculatedValueToData(
                        $rowData,
                        $fieldPath,
                        $calculationExpression,
                        $context,
                    );
                }
                $data[$key] = $value;
            }
        }

        return $data;
    }

    protected function generateUuidV4(): string
    {
        return Uuid::uuid4()->toString();
    }

    protected function parentPath(string $fieldPath): string
    {
        $segments = explode('/', $fieldPath);
        array_pop($segments);

        return implode('/', $segments);
    }

    protected function lastSegment(string $fieldPath): string
    {
        $segments = explode('/', $fieldPath);
        return (string) end($segments);
    }
}
