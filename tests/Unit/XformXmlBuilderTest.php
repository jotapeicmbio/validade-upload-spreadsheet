<?php



namespace Icmbio\SrcPhp\Tests\Unit;

use DOMDocument;
use DOMXPath;
use Icmbio\ValidateRegister\XformXmlBuilder;
use PHPUnit\Framework\TestCase;

final class XformXmlBuilderTest extends TestCase
{
    public function test_build_generates_xform_xml_with_defaults_and_meta(): void
    {
        $data = [
            'uc' => 'valor_a',
            '_attachments' => [
                ['name' => 'nao_deve_aparecer'],
            ],
            'grupo_a' => [
                ['grupo_a/campo_a' => 'valor_b', 'grupo_a/campo_b' => 'valor_c'],
                ['grupo_a/campo_a' => 'valor_d', 'grupo_a/campo_b' => 'valor_e'],
            ],
        ];

        $keys = ['uc', 'grupo_a'];
        $xml = XformXmlBuilder::build(
            $data,
            $keys,
            'xml',
            'xml_id',
            '1.0',
            null,
            '2026-04-01T10:20:30.000-03:00',
        );

        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);

        self::assertSame('xml', $doc->documentElement->nodeName);
        self::assertSame('xml_id', $doc->documentElement->getAttribute('id'));
        self::assertSame('1.0', $doc->documentElement->getAttribute('version'));
        self::assertSame('http://openrosa.org/javarosa', $doc->documentElement?->getAttribute('xmlns:jr'));

        self::assertSame('valor_a', $xpath->evaluate('string(/xml/uc)'));
        self::assertMatchesRegularExpression(
            '/^uuid:[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $xpath->evaluate('string(/xml/meta/instanceID)'),
        );
        self::assertSame('2026-04-01T10:20:30.000-03:00', $xpath->evaluate('string(/xml/starttime)'));
        self::assertSame('2026-04-01T10:20:30.000-03:00', $xpath->evaluate('string(/xml/endtime)'));
        self::assertSame('monitora.sisicmbio.icmbio.gov.br', $xpath->evaluate('string(/xml/deviceid)'));
        self::assertSame('simserial not found', $xpath->evaluate('string(/xml/simid)'));

        self::assertSame('2', (string) $xpath->evaluate('count(/xml/grupo_a)'));
        self::assertSame('valor_b', $xpath->evaluate('string(/xml/grupo_a[1]/campo_a)'));
        self::assertSame('valor_c', $xpath->evaluate('string(/xml/grupo_a[1]/campo_b)'));
        self::assertSame('valor_d', $xpath->evaluate('string(/xml/grupo_a[2]/campo_a)'));
        self::assertSame('valor_e', $xpath->evaluate('string(/xml/grupo_a[2]/campo_b)'));

        self::assertSame('0', (string) $xpath->evaluate('count(/xml/_attachments)'));
    }

    public function test_build_uses_given_uuid_in_meta_instance_id(): void
    {
        $data = ['uc' => 'valor_a'];
        $keys = ['uc'];
        $xml = XformXmlBuilder::build(
            $data,
            $keys,
            'xml',
            'xml_id',
            '1.0',
            'my-uuid-value',
            '2026-04-01T10:20:30.000-03:00',
        );

        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);

        self::assertSame('uuid:my-uuid-value', $xpath->evaluate('string(/xml/meta/instanceID)'));
    }

    public function test_build_should_include_troncos_uuid_in_nested_repeat_from_form_definition(): void
    {
        $data = [
            'grupo_a' => [[
                'grupo_a/grupo_b' => [[
                    'grupo_a/grupo_b/campo_a' => 'valor_a',
                ]],
            ]],
        ];

        $keys = [
            'grupo_a',
            'grupo_a/grupo_b',
            'grupo_a/grupo_b/campo_a',
        ];

        $formDefinition = [
            'children' => [[
                'name' => 'grupo_a',
                'type' => 'repeat',
                'children' => [[
                    'name' => 'grupo_b',
                    'type' => 'repeat',
                    'children' => [
                        [
                            'name' => 'campo_a',
                            'type' => 'text',
                        ],
                        [
                            'name' => 'item_uuid',
                            'type' => 'calculate',
                            'bind' => [
                                'calculate' => 'uuid()',
                            ],
                        ],
                    ],
                ]],
            ]],
        ];

        $xml = XformXmlBuilder::build(
            $data,
            $keys,
            'xml',
            'xml_id',
            '1.0',
            null,
            '2026-04-01T10:20:30.000-03:00',
            $formDefinition,
        );

        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);

        self::assertNotSame('', $xpath->evaluate('string(/xml/grupo_a/grupo_b/item_uuid)'));
    }

    public function test_build_should_omit_empty_repeat_when_no_item_is_present(): void
    {
        $data = [
            'campo_a' => 'valor_a',
        ];

        $formDefinition = [
            'children' => [
                ['name' => 'campo_a', 'type' => 'text'],
                [
                    'name' => 'grupo_a',
                    'type' => 'repeat',
                    'children' => [
                        ['name' => 'campo_b', 'type' => 'text'],
                    ],
                ],
            ],
        ];

        $xml = XformXmlBuilder::build(
            $data,
            ['campo_a', 'grupo_a/campo_b'],
            'xml',
            'xml_id',
            '1.0',
            null,
            '2026-04-01T10:20:30.000-03:00',
            $formDefinition,
        );

        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);

        self::assertSame('valor_a', $xpath->evaluate('string(/xml/campo_a)'));
        self::assertSame('0', (string) $xpath->evaluate('count(/xml/grupo_a)'));
    }

    public function test_build_should_normalize_decimal_comma_to_point(): void
    {
        $data = ['campo_decimal' => '5,5'];
        $keys = ['campo_decimal'];
        $formDefinition = [
            'children' => [
                ['name' => 'campo_decimal', 'type' => 'decimal'],
            ],
        ];

        $xml = XformXmlBuilder::build(
            $data,
            $keys,
            'xml',
            'xml_id',
            '1.0',
            null,
            '2026-04-01T10:20:30.000-03:00',
            $formDefinition,
        );

        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);

        self::assertSame('5.5', $xpath->evaluate('string(/xml/campo_decimal)'));
    }
}
