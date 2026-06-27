<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

final class TypeNormalizerRegistry
{
    /**
     * @param list<TypeNormalizer> $normalizers
     */
    public function __construct(private readonly array $normalizers = [])
    {
    }

    public static function default(): self
    {
        return new self([
            new IntegerTypeNormalizer(),
            new DecimalTypeNormalizer(),
        ]);
    }

    public function normalize(string $type, mixed $value): mixed
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($type)) {
                return $normalizer->normalize($value);
            }
        }

        return $value;
    }
}
