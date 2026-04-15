<?php

declare (strict_types=1);

namespace Icmbio\SrcPhp\Tests\Unit;

use Icmbio\ValidateRegister\PhotoAttachments;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhotoAttachmentsTest extends TestCase
{
    #[Test]
    public function coleta_campos_de_foto_com_caminho_de_indice(): void
    {
        $validators = [
            'foto_geral' => ['type' => 'photo'],
            'coletor/foto' => ['type' => 'photo'],
            'coletor/nome' => ['type' => 'text'],
        ];

        $data = [
            'foto_geral' => 'capa.jpg',
            'coletor' => [
                ['coletor/nome' => 'Ana', 'coletor/foto' => 'ana.jpg'],
                ['coletor/nome' => 'Bruno', 'coletor/foto' => 'bruno.jpg'],
            ],
        ];

        $photos = PhotoAttachments::getPhotosFromCollection($data, $validators);

        self::assertSame(
            [
                ['foto_geral', 'capa.jpg', ''],
                ['coletor/foto', 'ana.jpg', '-0'],
                ['coletor/foto', 'bruno.jpg', '-1'],
            ],
            $photos,
        );
    }

    #[Test]
    public function retorna_erros_para_fotos_ausentes_na_lista_zip(): void
    {
        $validators = [
            'coletor/foto' => ['type' => 'photo'],
        ];

        $data = [
            'coletor' => [
                ['coletor/foto' => 'ana.jpg'],
                ['coletor/foto' => 'bruno.jpg'],
            ],
        ];

        $errors = PhotoAttachments::validatePhotos(
            $data,
            $validators,
            ['ana.jpg'],
        );

        self::assertSame(
            ['Foto bruno.jpg nao encontrada no ZIP'],
            $errors,
        );
    }
}
