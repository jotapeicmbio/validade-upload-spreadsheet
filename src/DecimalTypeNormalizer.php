<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

final class DecimalTypeNormalizer extends AbstractTypeNormalizer
{
    public function supports(string $type): bool
    {
        return $type === 'decimal';
    }

    public function normalize(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmedValue = trim($value);
        if ($trimmedValue === '') {
            return $value;
        }

        $normalizedValue = $this->normalizeDecimalSeparators($trimmedValue);
        if (! is_numeric($normalizedValue)) {
            return $value;
        }

        return $normalizedValue;
    }

    protected function normalizeDecimalSeparators(string $value): string
    {
        if (str_contains($value, ',') && str_contains($value, '.')) {
            return str_replace(',', '.', str_replace('.', '', $value));
        }

        return str_replace(',', '.', $value);
    }
}
