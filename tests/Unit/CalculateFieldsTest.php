<?php

declare (strict_types=1);

namespace Icmbio\SrcPhp\Tests\Unit;

use Icmbio\ValidateRegister\CalculateFields;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CalculateFieldsTest extends TestCase
{
    #[Test]
    public function aplica_o_calculo_de_UUID_para_repetir_linhas_sem_UUID(): void
    {
        $validators = [
            'coletor/uuid' => [
                'calculate' => 'uuid()',
            ],
        ];

        $data = [
            'coletor' => [
                [
                    'coletor/nome' => 'Ana',
                    'coletor/uuid' => '',
                ],
                [
                    'coletor/nome' => 'Bruno',
                ],
            ],
        ];

        $actual = CalculateFields::apply($data, $validators);

        self::assertArrayHasKey('coletor/uuid', $actual['coletor'][0]);
        self::assertArrayHasKey('coletor/uuid', $actual['coletor'][1]);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $actual['coletor'][0]['coletor/uuid'],
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $actual['coletor'][1]['coletor/uuid'],
        );
        self::assertNotSame('', $actual['coletor'][0]['coletor/uuid']);
        self::assertNotSame('', $actual['coletor'][1]['coletor/uuid']);
    }

    #[Test]
    public function nao_sobrescreve_uuid_existente_quando_expressao_e_uuid(): void
    {
        $validators = [
            'coletor/uuid' => [
                'calculate' => 'uuid()',
            ],
        ];

        $existingUuid = 'f8f7dd3d-53f5-4eb0-becf-1f18e4dc31e4';
        $data = [
            'coletor' => [
                [
                    'coletor/nome' => 'Ana',
                    'coletor/uuid' => $existingUuid,
                ],
            ],
        ];

        $actual = CalculateFields::apply($data, $validators);

        self::assertSame($existingUuid, $actual['coletor'][0]['coletor/uuid']);
    }
}
