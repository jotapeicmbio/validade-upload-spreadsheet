<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

final class IntegerTypeNormalizer extends AbstractTypeNormalizer
{
    public function supports(string $type): bool
    {
        return $type === 'integer';
    }

    public function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $trimmedValue = trim($value);

            if ($trimmedValue !== '' && preg_match('/^-?\d+$/', $trimmedValue) === 1) {
                return (int) $trimmedValue;
            }

            return $value;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return $value;
        }

        return $this->normalizeNumericList($value, static function (mixed $item): mixed {
            if (! is_string($item)) {
                return $item;
            }

            $trimmedItem = trim($item);

            if ($trimmedItem !== '' && preg_match('/^-?\d+$/', $trimmedItem) === 1) {
                return (int) $trimmedItem;
            }

            return $item;
        });
    }
}
