<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

abstract class AbstractTypeNormalizer implements TypeNormalizer
{
    protected function normalizeNumericList(array $value, callable $callback): array
    {
        foreach ($value as $index => $item) {
            $value[$index] = $callback($item);
        }

        return $value;
    }
}
