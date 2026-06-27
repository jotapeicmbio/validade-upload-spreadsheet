<?php

declare(strict_types=1);

namespace Tests\Unit;

use Icmbio\ValidateRegister\XformSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class XformSchemaTest extends TestCase
{
    #[Test]
    public function collapses_group_wrapped_repeat_with_same_name(): void
    {
        $schema = XformSchema::fromArray([
            'children' => [
                [
                    'name' => 'grupo_a',
                    'type' => 'group',
                    'children' => [
                        [
                            'name' => 'grupo_a',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'campo_a', 'type' => 'text'],
                                ['name' => 'campo_b', 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $node = $schema->find('grupo_a');
        $child = $schema->find('grupo_a/campo_a');

        self::assertNotNull($node);
        self::assertTrue($node->isRepeat());
        self::assertCount(2, $node->children);
        self::assertSame('campo_a', $node->children[0]->name);
        self::assertSame('campo_b', $node->children[1]->name);
        self::assertNotNull($child);
        self::assertSame('grupo_a/campo_a', $child->path);
    }
}
