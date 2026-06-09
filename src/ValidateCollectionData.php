<?php



namespace Icmbio\ValidateRegister;

use Icmbio\ValidateXpathExpression\Xpath;

class ValidateCollectionData
{
    private const INVALID_CHOICE_MESSAGE = 'O Valor (%s) nao e uma das escolhas validas: %s';
    private const INVALID_EXPRESSION_MESSAGE = 'Erro ao validar a expressao ("%s") [%s]';

    public function __construct() {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $validators
     * @param array<int, int>|null $taxons
     * @param array<string, mixed>|null $parentContext
     * @return list<array<string, mixed>>
     */
    public static function createErrorsList(
        array $data,
        array $validators,
        int $index = 0,
        ?array $parentContext = null,
        ?array $taxons = null,
    ): array {

        return (new self())->collectErrors($data, $validators, $index, $parentContext, $taxons);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $validators
     * @param array<int, int>|null $taxons
     * @param array<string, mixed>|null $parentContext
     * @return list<array<string, mixed>>
     */
    public function collectErrors(
        array $data,
        array $validators,
        int $index,
        ?array $parentContext,
        ?array $taxons,
    ): array {
        $errors = [];
        $context = $this->buildContext($data, $parentContext);

        foreach ($data as $fieldKey => $fieldValue) {
            if (! array_key_exists($fieldKey, $validators)) {
                continue;
            }

            $fieldValidator = $validators[$fieldKey];
            $fieldType = (string) ($fieldValidator['type'] ?? 'text');

            if ($fieldType === 'calculate' && str_ends_with($fieldKey, 'uuid') && $this->hasValue($fieldValue)) {
                if (! $this->isValidUuid((string) $fieldValue)) {
                    $errors[] = $this->buildError($index, $fieldKey, $fieldValue, 'UUID invalido.');
                }
            }

            if ($fieldType === 'integer' && $this->hasValue($fieldValue) && ! $this->isValidIntegerValue($fieldValue)) {
                $errors[] = $this->buildError($index, $fieldKey, $fieldValue, 'E esperado um valor inteiro.');
            }

            if ($fieldType === 'repeat' && $this->isRepeatGroupValue($fieldValue)) {
                $errors = array_merge(
                    $errors,
                    $this->validateRepeatGroup($fieldKey, $fieldValue, $fieldValidator, $validators, $index, $context, $taxons),
                );

                continue;
            }

            $errors = array_merge(
                $errors,
                $this->validateScalarField($fieldKey, $fieldValue, $fieldValidator, $context, $index, $taxons),
            );
        }

        return $errors;
    }

    protected function isValidIntegerValue(mixed $fieldValue): bool
    {
        if (is_int($fieldValue)) {
            return true;
        }

        if (! is_array($fieldValue) || ! array_is_list($fieldValue)) {
            return false;
        }

        foreach ($fieldValue as $value) {
            if (! is_int($value)) {
                return false;
            }
        }

        return true;
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
     * @param mixed $fieldValue
     * @param array<string, mixed> $fieldValidator
     * @param array<string, mixed> $context
     * @param array<int, int>|null $taxons
     * @return list<array<string, mixed>>
     */
    protected function validateScalarField(
        string $fieldKey,
        mixed $fieldValue,
        array $fieldValidator,
        array $context,
        int $index,
        ?array $taxons,
    ): array {
        $errors = [];
        $mustValidateConstraint = true;
        $relevanceExpression = (string) ($fieldValidator['relevant'] ?? 'true()');

        try {
            $isRelevant = (bool) Xpath::validate($relevanceExpression, $fieldValue, $context);
            if (! $isRelevant) {
                return $errors;
            }
        } catch (\Throwable $exception) {
            $errors[] = $this->buildError(
                $index,
                $fieldKey,
                $fieldValue,
                sprintf(self::INVALID_EXPRESSION_MESSAGE, $relevanceExpression, $exception->getMessage()),
            );

            return $errors;
        }

        $requiredExpression = (string) ($fieldValidator['required'] ?? 'false()');
        try {
            $isRequired = (bool) Xpath::validate($requiredExpression, $fieldValue, $context);
            if ($isRequired && $this->isEmptyRequiredValue($fieldValue)) {
                $mustValidateConstraint = false;
                $errors[] = $this->buildError(
                    $index,
                    $fieldKey,
                    $fieldValue,
                    (string) ($fieldValidator['required_message'] ?? 'Campo obrigatorio'),
                );
            }
        } catch (\Throwable $exception) {
            $errors[] = $this->buildError(
                $index,
                $fieldKey,
                $fieldValue,
                sprintf(self::INVALID_EXPRESSION_MESSAGE, $requiredExpression, $exception->getMessage()),
            );
        }

        $constraintExpression = (string) ($fieldValidator['constraint'] ?? 'true()');
        try {
            if ($mustValidateConstraint && ! $this->isEmptyConstraintValue($fieldValue)) {
                $isValid = (bool) Xpath::validate($constraintExpression, $fieldValue, $context);
                if (! $isValid) {
                    $errors[] = $this->buildError(
                        $index,
                        $fieldKey,
                        $fieldValue,
                        (string) ($fieldValidator['constraint_message'] ?? 'Erro'),
                    );
                }
            }
        } catch (\Throwable $exception) {
            $errors[] = $this->buildError(
                $index,
                $fieldKey,
                $fieldValue,
                sprintf(self::INVALID_EXPRESSION_MESSAGE, $constraintExpression, $exception->getMessage()),
            );
        }

        $errors = array_merge(
            $errors,
            $this->validateTaxonList($fieldKey, $fieldValue, $index, $taxons),
        );

        $errors = array_merge(
            $errors,
            $this->validateChoices($fieldKey, $fieldValue, $fieldValidator, $index),
        );

        return $errors;
    }

    /**
     * @param mixed $fieldValue
     * @param array<string, mixed> $fieldValidator
     * @param array<string, array<string, mixed>> $validators
     * @param array<string, mixed> $context
     * @param array<int, int>|null $taxons
     * @return list<array<string, mixed>>
     */
    protected function validateRepeatGroup(
        string $fieldKey,
        mixed $fieldValue,
        array $fieldValidator,
        array $validators,
        int $index,
        array $context,
        ?array $taxons,
    ): array {
        $errors = [];
        $relevanceExpression = (string) ($fieldValidator['relevant'] ?? 'true()');

        try {
            $isRelevant = (bool) Xpath::validate($relevanceExpression, null, $context);
        } catch (\Throwable $exception) {
            $errors[] = $this->buildError(
                $index,
                $fieldKey,
                $fieldValue,
                sprintf(self::INVALID_EXPRESSION_MESSAGE, $relevanceExpression, $exception->getMessage()),
            );

            return $errors;
        }

        if ($isRelevant) {
            $runningIndex = $index;
            foreach ((array) $fieldValue as $childNode) {
                $runningIndex++;
                if (! is_array($childNode)) {
                    continue;
                }
                $errors = array_merge(
                    $errors,
                    $this->collectErrors($childNode, $validators, $runningIndex, $context, $taxons),
                );
            }

            return $errors;
        }

        if ($fieldValue !== [] && $fieldValue !== null) {
            $errors[] = $this->buildError(
                $index,
                $fieldKey,
                '',
                sprintf(
                    'Nao e permitido entrar com valores para este grupo, pois nao atendeu o criterio %s',
                    $relevanceExpression,
                ),
            );
        }

        return $errors;
    }

    /**
     * @param mixed $fieldValue
     * @param array<int, int>|null $taxons
     * @return list<array<string, mixed>>
     */
    protected function validateTaxonList(string $fieldKey, mixed $fieldValue, int $index, ?array $taxons): array
    {
        $errors = [];
        if (! str_ends_with($fieldKey, 'taxon_lista') || ! $this->hasValue($fieldValue)) {
            return $errors;
        }

        $taxonValueAsString = (string) $fieldValue;
        if (! ctype_digit($taxonValueAsString)) {
            $errors[] = $this->buildError($index, $fieldKey, $fieldValue, 'O valor taxon_lista deve ser um numero.');

            return $errors;
        }

        $taxonValue = (int) $taxonValueAsString;
        if ($taxons !== null && ! in_array($taxonValue, $taxons, true)) {
            $errors[] = $this->buildError(
                $index,
                $fieldKey,
                $taxonValue,
                sprintf(self::INVALID_CHOICE_MESSAGE, (string) $taxonValue, implode(', ', $taxons)),
            );
        }

        return $errors;
    }

    /**
     * @param mixed $fieldValue
     * @param array<string, mixed> $fieldValidator
     * @return list<array<string, mixed>>
     */
    protected function validateChoices(string $fieldKey, mixed $fieldValue, array $fieldValidator, int $index): array
    {
        $errors = [];
        $choices = $fieldValidator['choices'] ?? null;
        if (! is_array($choices)) {
            return $errors;
        }

        if (
            str_ends_with($fieldKey, 'uc')
            || str_ends_with($fieldKey, 'estacao_amostral')
            || str_ends_with($fieldKey, 'unidade_amostral')
            || str_ends_with($fieldKey, 'taxon_lista')
        ) {
            return $errors;
        }

        $choiceListAsString = implode(', ', array_map('strval', $choices));
        $fieldType = (string) ($fieldValidator['type'] ?? 'text');

        if ($fieldType === 'select one') {
            if ($this->hasValue($fieldValue) && ! in_array((string) $fieldValue, array_map('strval', $choices), true)) {
                $errors[] = $this->buildError(
                    $index,
                    $fieldKey,
                    $fieldValue,
                    sprintf(self::INVALID_CHOICE_MESSAGE, (string) $fieldValue, $choiceListAsString),
                );
            }

            return $errors;
        }

        if ($fieldType === 'select all that apply') {
            $values = explode(' ', (string) $fieldValue);
            $stringChoices = array_map('strval', $choices);
            foreach ($values as $singleValue) {
                if ($singleValue !== '' && ! in_array($singleValue, $stringChoices, true)) {
                    $errors[] = $this->buildError(
                        $index,
                        $fieldKey,
                        $fieldValue,
                        sprintf(self::INVALID_CHOICE_MESSAGE, $singleValue, $choiceListAsString),
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @return array{index: int, key: string, value: mixed, message: string}
     */
    protected function buildError(int $index, string $fieldKey, mixed $fieldValue, string $message): array
    {
        return [
            'index' => $index,
            'key' => $fieldKey,
            'value' => $fieldValue,
            'message' => $message,
        ];
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

    protected function isEmptyRequiredValue(mixed $value): bool
    {
        if ($value === 0 || $value === '0') {
            return false;
        }

        return ! $this->hasValue($value);
    }

    protected function isEmptyConstraintValue(mixed $value): bool
    {
        if ($value === '' || $value === null) {
            return true;
        }

        if (is_float($value) && is_nan($value)) {
            return true;
        }

        return false;
    }

    protected function isRepeatGroupValue(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    protected function lastSegment(string $fieldKey): string
    {
        $segments = explode('/', $fieldKey);

        return (string) end($segments);
    }

    protected function isValidUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        );
    }
}
