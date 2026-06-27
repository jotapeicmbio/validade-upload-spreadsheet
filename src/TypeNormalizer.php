<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

interface TypeNormalizer
{
    public function supports(string $type): bool;

    public function normalize(mixed $value): mixed;
}
