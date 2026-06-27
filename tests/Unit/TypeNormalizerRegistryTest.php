<?php

declare(strict_types=1);

namespace Tests\Unit;

use Icmbio\ValidateRegister\TypeNormalizerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TypeNormalizerRegistryTest extends TestCase
{
    #[Test]
    public function normalizes_integer_and_decimal_values(): void
    {
        $registry = TypeNormalizerRegistry::default();

        self::assertSame(12, $registry->normalize('integer', '12'));
        self::assertSame('1.2', $registry->normalize('decimal', '1,2'));
        self::assertSame('1.200', $registry->normalize('decimal', '1,200'));
        self::assertSame('1200.50', $registry->normalize('decimal', '1.200,50'));
        self::assertSame('1.20', $registry->normalize('decimal', '1.20'));
        self::assertSame('abc', $registry->normalize('text', 'abc'));
    }
}
