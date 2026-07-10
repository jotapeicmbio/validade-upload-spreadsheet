<?php

declare(strict_types=1);

namespace Tests\ValidateRegister\Unit;

use Icmbio\ValidateRegister\SpreadsheetInstanceSeparator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpreadsheetInstanceSeparatorTest extends TestCase
{
    #[Test]
    public function itSeparatesSpreadsheetRowsIntoIndependentInstancesUsingTheFirstColumn(): void
    {
        $worksheet = [
            ['header_1', 'header_2'],
            ['Label 1', 'Label 2'],
            ['instancia_1', 'valor_1'],
            [null, 'valor_2'],
            [null, null],
            ['instancia_2', 'valor_3'],
            [null, 'valor_4'],
        ];

        $expected = [
            'headers' => ['header_1', 'header_2'],
            'labels' => ['Label 1', 'Label 2'],
            'collects' => [
                [
                    'start_line' => 3,
                    'collect' => [
                        ['instancia_1', 'valor_1'],
                        [null, 'valor_2'],
                        [null, null],
                    ],
                ],
                [
                    'start_line' => 6,
                    'collect' => [
                        ['instancia_2', 'valor_3'],
                        [null, 'valor_4'],
                    ],
                ],
            ],
        ];

        self::assertSame($expected, (new SpreadsheetInstanceSeparator())->separate($worksheet));
    }
}
