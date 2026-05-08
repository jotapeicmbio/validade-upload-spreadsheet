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
            'uc' => 'Unidade 1',
            '_attachments' => [
                ['name' => 'nao deve aparecer'],
            ],
            'coletor' => [
                ['coletor/cpf' => '11111111111', 'coletor/nome' => 'Ana'],
                ['coletor/cpf' => '22222222222', 'coletor/nome' => 'Bruno'],
            ],
        ];

        $keys = ['uc', 'coletor'];
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

        self::assertSame('Unidade 1', $xpath->evaluate('string(/xml/uc)'));
        self::assertSame('uuid:None', $xpath->evaluate('string(/xml/meta/instanceID)'));
        self::assertSame('2026-04-01T10:20:30.000-03:00', $xpath->evaluate('string(/xml/starttime)'));
        self::assertSame('2026-04-01T10:20:30.000-03:00', $xpath->evaluate('string(/xml/endtime)'));
        self::assertSame('monitora.sisicmbio.icmbio.gov.br', $xpath->evaluate('string(/xml/deviceid)'));
        self::assertSame('simserial not found', $xpath->evaluate('string(/xml/simid)'));

        self::assertSame('2', (string) $xpath->evaluate('count(/xml/coletor)'));
        self::assertSame('11111111111', $xpath->evaluate('string(/xml/coletor[1]/cpf)'));
        self::assertSame('Ana', $xpath->evaluate('string(/xml/coletor[1]/nome)'));
        self::assertSame('22222222222', $xpath->evaluate('string(/xml/coletor[2]/cpf)'));
        self::assertSame('Bruno', $xpath->evaluate('string(/xml/coletor[2]/nome)'));

        self::assertSame('0', (string) $xpath->evaluate('count(/xml/_attachments)'));
    }

    public function test_build_uses_given_uuid_in_meta_instance_id(): void
    {
        $data = ['uc' => 'Unidade 1'];
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
}
