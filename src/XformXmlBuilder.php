<?php



namespace Icmbio\ValidateRegister;

use DOMDocument;
use DOMElement;

class XformXmlBuilder
{
    protected ?TypeNormalizerRegistry $type_normalizers = null;

    public function __construct() {}

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     */
    public static function build(
        array $data,
        array $keys,
        string $rootName,
        string $rootId,
        string $rootVersion,
        ?string $uuid = null,
        ?string $timestamp = null,
        ?array $formDefinition = null,
    ): string {
        $builder = new self();

        return $builder->buildXmlDocument($data, $keys, $rootName, $rootId, $rootVersion, $uuid, $timestamp, $formDefinition);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     */
    public function buildXmlDocument(
        array $data,
        array $keys,
        string $rootName,
        string $rootId,
        string $rootVersion,
        ?string $uuid,
        ?string $timestamp,
        ?array $formDefinition = null,
    ): string {
        if ($this->hasFormStructure($formDefinition)) {
            $preparedData = $this->prepareDataUsingFormDefinition(
                $data,
                $formDefinition['children'],
            );
        } else {
            $normalizedData = $this->addEmptyKeysInDict($data, $keys);
            $preparedData = $this->prepareDataToXml([$normalizedData])[0] ?? [];
        }
        $preparedDataWithMeta = $this->prepareMetaXml($preparedData, $uuid);

        $dateTime = $timestamp ?? (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.000-03:00');
        if (! $this->hasValue($preparedDataWithMeta['starttime'] ?? null)) {
            $preparedDataWithMeta['starttime'] = $dateTime;
        }
        if (! $this->hasValue($preparedDataWithMeta['endtime'] ?? null)) {
            $preparedDataWithMeta['endtime'] = $dateTime;
        }
        if (! $this->hasValue($preparedDataWithMeta['deviceid'] ?? null)) {
            $preparedDataWithMeta['deviceid'] = 'monitora.sisicmbio.icmbio.gov.br';
        }
        if (! $this->hasValue($preparedDataWithMeta['simid'] ?? null)) {
            $preparedDataWithMeta['simid'] = 'simserial not found';
        }
        if (! $this->hasValue($preparedDataWithMeta['subscriberid'] ?? null)) {
            $preparedDataWithMeta['subscriberid'] = 'subscriberid not found';
        }
        if (! $this->hasValue($preparedDataWithMeta['devicephonenum'] ?? null)) {
            $preparedDataWithMeta['devicephonenum'] = 'phonenumber not found';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;
        $rootElement = $this->createRootElement($document, $rootName, $rootId, $rootVersion);
        $document->appendChild($rootElement);

        $this->appendDataToXml($document, $rootElement, $preparedDataWithMeta);

        /** @var string $xml */
        $xml = $document->saveXML();

        return $xml;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    protected function addEmptyKeysInDict(array $data, array $keys): array
    {
        foreach ($keys as $fullKeyPath) {
            $data = $this->ensurePathExists($data, $fullKeyPath, 0);
        }

        return $data;
    }

    protected function hasFormStructure(?array $formDefinition): bool
    {
        return isset($formDefinition['children'])
            && is_array($formDefinition['children'])
            && $formDefinition['children'] !== [];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    protected function prepareDataUsingFormDefinition(array $data, array $children): array
    {
        $preparedData = [];

        foreach ($children as $child) {
            $name = $child['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }
            if (str_starts_with($name, '_')) {
                continue;
            }

            $type = $child['type'] ?? null;
            $value = $this->valueForField($data, $name);

            if ($type === 'group') {
                $groupData = is_array($value) && ! array_is_list($value) ? $value : [];
                $preparedData[$name] = $this->prepareDataUsingFormDefinition($groupData, $child['children'] ?? []);

                continue;
            }

            if ($type === 'repeat') {
                if (is_array($value) && array_is_list($value) && $value !== []) {
                    $preparedData[$name] = array_map(
                        fn (mixed $item): array => $this->prepareDataUsingFormDefinition(
                            is_array($item) ? $item : [],
                            $child['children'] ?? [],
                        ),
                        $value,
                    );

                    continue;
                }

                continue;
            }

            $preparedData[$name] = $this->normalizeValueByDefinition($child, $value);
        }

        return $preparedData;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function valueForField(array $data, string $fieldName): mixed
    {
        if (array_key_exists($fieldName, $data)) {
            return $data[$fieldName];
        }

        $suffix = '/' . $fieldName;
        foreach ($data as $key => $value) {
            if (is_string($key) && str_ends_with($key, $suffix)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $definition
     */
    protected function normalizeValueByDefinition(array $definition, mixed $value): mixed
    {
        $type = $definition['type'] ?? null;
        $calculate = $definition['bind']['calculate'] ?? null;

        if ($calculate === 'uuid()') {
            return $this->hasValue($value) ? (string) $value : $this->generateUuid();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return $this->typeNormalizers()->normalize((string) $type, $value);
    }

    protected function typeNormalizers(): TypeNormalizerRegistry
    {
        if ($this->type_normalizers === null) {
            $this->type_normalizers = TypeNormalizerRegistry::default();
        }

        return $this->type_normalizers;
    }

    protected function generateUuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid7()->toString();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function ensurePathExists(array $data, string $fullKeyPath, int $depth): array
    {
        $pathSegments = explode('/', $fullKeyPath);
        $currentPath = implode('/', array_slice($pathSegments, 0, $depth + 1));
        $lastPathSegment = $pathSegments[array_key_last($pathSegments)];

        if (
            count($pathSegments) === $depth + 1
            && $currentPath === $fullKeyPath
            && ! array_key_exists($lastPathSegment, $data)
            && ! array_key_exists($fullKeyPath, $data)
        ) {
            $data[$lastPathSegment] = null;
        }

        if (array_key_exists($currentPath, $data) && is_array($data[$currentPath]) && array_is_list($data[$currentPath])) {
            foreach ($data[$currentPath] as $rowIndex => $rowData) {
                if (! is_array($rowData)) {
                    continue;
                }
                $data[$currentPath][$rowIndex] = $this->ensurePathExists($rowData, $fullKeyPath, $depth + 1);
            }
        }

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $dataList
     * @return list<array<string, mixed>>
     */
    protected function prepareDataToXml(array $dataList): array
    {
        if ($dataList === []) {
            return [[]];
        }

        foreach ($dataList as $rowIndex => $row) {
            if ($row === []) {
                continue;
            }
            $newRow = $row;
            foreach ($row as $key => $value) {
                if (str_starts_with((string) $key, '_')) {
                    unset($newRow[$key]);

                    continue;
                }
                $newKey = $this->lastSegment((string) $key);
                if (is_array($value) && array_is_list($value)) {
                    unset($newRow[$key]);
                    $newRow[$newKey] = $this->prepareDataToXml($value);

                    continue;
                }
                if (is_array($value)) {
                    unset($newRow[$key]);
                    $newRow[$newKey] = $this->prepareAssociativeDataToXml($value);

                    continue;
                }
                unset($newRow[$key]);
                $newRow[$newKey] = $value;
            }
            $dataList[$rowIndex] = $newRow;
        }

        return $dataList;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareAssociativeDataToXml(array $data): array
    {
        $preparedData = [];

        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            $newKey = $this->lastSegment((string) $key);

            if (is_array($value) && array_is_list($value)) {
                $preparedData[$newKey] = $this->prepareDataToXml($value);

                continue;
            }

            if (is_array($value)) {
                $preparedData[$newKey] = $this->prepareAssociativeDataToXml($value);

                continue;
            }

            $preparedData[$newKey] = $value;
        }

        return $preparedData;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareMetaXml(array $data, ?string $uuid): array
    {
        $nestedMetaInstanceId = null;
        if (
            isset($data['meta'])
            && is_array($data['meta'])
            && array_key_exists('instanceID', $data['meta'])
        ) {
            $nestedMetaInstanceId = $data['meta']['instanceID'];
        }

        $topLevelInstanceId = array_key_exists('instanceID', $data) ? $data['instanceID'] : null;
        if ($this->hasValue($topLevelInstanceId)) {
            $instanceId = (string) $topLevelInstanceId;
        } elseif ($this->hasValue($nestedMetaInstanceId)) {
            $instanceId = (string) $nestedMetaInstanceId;
        } elseif ($this->hasValue($uuid)) {
            $instanceId = sprintf('uuid:%s', $uuid);
        } else {
            $instanceId = sprintf('uuid:%s', $this->generateUuid());
        }

        unset($data['instanceID']);
        $data['meta'] = [['instanceID' => $instanceId]];

        return $data;
    }

    protected function createRootElement(
        DOMDocument $document,
        string $rootName,
        string $rootId,
        string $rootVersion,
    ): DOMElement {
        $rootElement = $document->createElement($rootName);
        $rootElement->setAttribute('version', $rootVersion);
        $rootElement->setAttribute('id', $rootId);
        $rootElement->setAttribute('xmlns:ev', 'http://www.w3.org/2001/xml-events');
        $rootElement->setAttribute('xmlns:orx', 'http://openrosa.org/xforms');
        $rootElement->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $rootElement->setAttribute('xmlns:odk', 'http://www.opendatakit.org/xforms');
        $rootElement->setAttribute('xmlns:h', 'http://www.w3.org/1999/xhtml');
        $rootElement->setAttribute('xmlns:jr', 'http://openrosa.org/javarosa');

        return $rootElement;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function appendDataToXml(DOMDocument $document, DOMElement $parentElement, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $listItem) {
                    $childElement = $document->createElement((string) $key);
                    $parentElement->appendChild($childElement);
                    if (is_array($listItem)) {
                        $this->appendDataToXml($document, $childElement, $listItem);
                    }
                }

                continue;
            }

            if (is_array($value)) {
                $childElement = $document->createElement((string) $key);
                $parentElement->appendChild($childElement);
                $this->appendDataToXml($document, $childElement, $value);

                continue;
            }

            $childElement = $document->createElement((string) $key);
            if ($value !== null) {
                $childElement->appendChild($document->createTextNode((string) $value));
            }
            $parentElement->appendChild($childElement);
        }
    }

    protected function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }

        return true;
    }

    protected function lastSegment(string $fieldPath): string
    {
        $segments = explode('/', $fieldPath);

        return (string) end($segments);
    }
}
