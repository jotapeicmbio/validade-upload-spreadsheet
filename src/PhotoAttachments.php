<?php

declare (strict_types=1);

namespace Icmbio\ValidateRegister;

class PhotoAttachments
{
    public function __construct() {}

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $validators
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function getPhotosFromCollection(array $data, array $validators): array
    {
        $photoCollector = new self();

        return $photoCollector->collectPhotosFromNode($data, $validators);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $validators
     * @param list<string> $existingPhotos
     * @return list<string>
     */
    public static function validatePhotos(array $data, array $validators, array $existingPhotos): array
    {
        $errors              = [];
        $photosByPath        = self::getPhotosFromCollection($data, $validators);
        $existingPhotoLookup = array_flip($existingPhotos);

        foreach ($photosByPath as $photoTuple) {
            [, $photoName] = $photoTuple;
            if (! array_key_exists($photoName, $existingPhotoLookup)) {
                $errors[] = sprintf('Foto %s nao encontrada no ZIP', $photoName);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<string, mixed>> $validators
     * @param list<array{0: string, 1: string, 2: string}>|null $photos
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function collectPhotosFromNode(
        array $data,
        array $validators,
        ?array $photos = null,
        string $indexPath = '',
    ): array {
        $photos ??= [];

        foreach ($data as $fieldKey => $fieldValue) {
            if (is_array($fieldValue) && array_is_list($fieldValue)) {
                foreach ($fieldValue as $itemIndex => $childNode) {
                    if (! is_array($childNode)) {
                        continue;
                    }
                    $photos = $this->collectPhotosFromNode(
                        $childNode,
                        $validators,
                        $photos,
                        $indexPath . '-' . $itemIndex,
                    );
                }

                continue;
            }

            if (
                array_key_exists($fieldKey, $validators)
                && (string) ($validators[$fieldKey]['type'] ?? '') === 'photo'
                && $fieldValue !== null
                && trim((string) $fieldValue) !== ''
            ) {
                $photos[] = [$fieldKey, (string) $fieldValue, $indexPath];
            }
        }

        return $photos;
    }
}
