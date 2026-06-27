<?php



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
            'grupo_a/item_uuid' => [
                'calculate' => 'uuid()',
            ],
        ];

        $data = [
            'grupo_a' => [
                [
                    'grupo_a/campo_a' => 'valor_a',
                    'grupo_a/item_uuid' => '',
                ],
                [
                    'grupo_a/campo_a' => 'valor_b',
                ],
            ],
        ];

        $actual = CalculateFields::apply($data, $validators);

        self::assertArrayHasKey('grupo_a/item_uuid', $actual['grupo_a'][0]);
        self::assertArrayHasKey('grupo_a/item_uuid', $actual['grupo_a'][1]);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[7][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $actual['grupo_a'][0]['grupo_a/item_uuid'],
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[7][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $actual['grupo_a'][1]['grupo_a/item_uuid'],
        );
        self::assertNotSame('', $actual['grupo_a'][0]['grupo_a/item_uuid']);
        self::assertNotSame('', $actual['grupo_a'][1]['grupo_a/item_uuid']);
    }

    #[Test]
    public function nao_sobrescreve_uuid_existente_quando_expressao_e_uuid(): void
    {
        $validators = [
            'grupo_a/item_uuid' => [
                'calculate' => 'uuid()',
            ],
        ];

        $existingUuid = 'f8f7dd3d-53f5-4eb0-becf-1f18e4dc31e4';
        $data = [
            'grupo_a' => [
                [
                    'grupo_a/campo_a' => 'valor_a',
                    'grupo_a/item_uuid' => $existingUuid,
                ],
            ],
        ];

        $actual = CalculateFields::apply($data, $validators);

        self::assertSame($existingUuid, $actual['grupo_a'][0]['grupo_a/item_uuid']);
    }
}
