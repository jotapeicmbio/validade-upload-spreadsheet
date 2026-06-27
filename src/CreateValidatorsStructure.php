<?php



namespace Icmbio\ValidateRegister;

class CreateValidatorsStructure
{
    /** @var list<string> */
    protected const SELECT_FIELD_TYPES = ['select one', 'select all that apply'];

    public function __construct() {}

    /**
     * Contrato alvo para portar a funcao Python create_validators_estructure.
     *
     * @param array<string, mixed>|list<array<string, mixed>> $input
     * @param array<string, string> $dynamicChoices
     * @return array<string, array<string, mixed>>
     */
    public static function build(array $input, array $dynamicChoices = [], ?string $prefix = null): array
    {
        return (new self())->buildValidatorsFromNode($input, $dynamicChoices, $prefix);
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $formNodeOrNodeList
     * @param array<string, string> $dynamicChoices
     * @return array<string, array<string, mixed>>
     */
    public function buildValidatorsFromNode(array $formNodeOrNodeList, array $dynamicChoices, ?string $prefix): array
    {
        $validatorsByFieldPath = [];

        if ($this->isAssociativeArray($formNodeOrNodeList)) {
            /** @var array<string, mixed> $formFieldNode */
            $formFieldNode = $formNodeOrNodeList;
            $fieldName = (string) ($formFieldNode['name'] ?? '');
            if ($fieldName === '') {
                return $validatorsByFieldPath;
            }

            $fieldPath = $prefix !== null && $prefix !== ''
                ? sprintf('%s/%s', $prefix, $fieldName)
                : $fieldName;

            $fieldType = (string) ($formFieldNode['type'] ?? 'text');
            $fieldLabel = $this->normalizeLabelValue($formFieldNode['label'] ?? $fieldName, $fieldName);

            $calculationExpression = null;
            $availableChoices = null;
            $availableChoiceLabels = null;
            $constraintExpression = 'true()';
            $constraintMessage = 'Erro';
            $relevanceExpression = 'true()';
            $requiredExpression = 'false()';
            $requiredMessage = 'Campo obrigatorio';

            if ($this->shouldExtractStaticChoices($formFieldNode, $fieldType, $fieldPath, $dynamicChoices)) {
                [$availableChoices, $availableChoiceLabels] = $this->extractChoicesFromChildren($formFieldNode);
            }

            if (isset($formFieldNode['bind']) && is_array($formFieldNode['bind'])) {
                    $bindOptions = $formFieldNode['bind'];
                    foreach ($bindOptions as $bindKey => $bindValue) {
                        match ((string) $bindKey) {
                            'constraint' => $constraintExpression = $this->normalizeExpression((string) $bindValue),
                            'jr:constraintMsg' => $constraintMessage = (string) $bindValue,
                            'required' => $requiredExpression = $this->normalizeExpression((string) $bindValue === 'yes' ? 'true()' : (string) $bindValue),
                            'jr:requiredMsg' => $requiredMessage = (string) $bindValue,
                            'relevant' => $relevanceExpression = $this->normalizeExpression((string) $bindValue),
                            'calculate' => $calculationExpression = $this->normalizeExpression((string) $bindValue),
                            default => null,
                    };
                }
            }

            $childrenValidators = match ($fieldType) {
                'group', 'repeat' => (isset($formFieldNode['children']) && is_array($formFieldNode['children']))
                    ? $this->buildValidatorsFromNode($formFieldNode['children'], $dynamicChoices, $fieldPath)
                    : [],
                default => [],
            };

            foreach ($childrenValidators as $childFieldPath => $childValidator) {
                $validatorsByFieldPath[$childFieldPath] = $childValidator;
            }

            $validatorsByFieldPath[$fieldPath] = [
                'calculate' => $calculationExpression,
                'choices' => $availableChoices,
                'choices_labels' => $availableChoiceLabels,
                'constraint' => $constraintExpression,
                'constraint_message' => $constraintMessage,
                'label' => $fieldLabel,
                'relevant' => $relevanceExpression,
                'required' => $requiredExpression,
                'required_message' => $requiredMessage,
                'type' => $fieldType,
            ];

            return $validatorsByFieldPath;
        }

        foreach ($formNodeOrNodeList as $childFormNode) {
            if (! is_array($childFormNode)) {
                continue;
            }
            $childValidators = $this->buildValidatorsFromNode($childFormNode, $dynamicChoices, $prefix);
            foreach ($childValidators as $childFieldPath => $childValidator) {
                $validatorsByFieldPath[$childFieldPath] = $childValidator;
            }
        }

        return $validatorsByFieldPath;
    }

    /**
     * @param array<string, mixed> $formFieldNode
     * @param array<string, string> $dynamicChoices
     */
    protected function shouldExtractStaticChoices(
        array $formFieldNode,
        string $fieldType,
        string $fieldPath,
        array $dynamicChoices,
    ): bool {
        return isset($formFieldNode['children'])
        && is_array($formFieldNode['children'])
        && in_array($fieldType, self::SELECT_FIELD_TYPES, true)
        && ! array_key_exists($fieldPath, $dynamicChoices);
    }

    /**
     * @param array<string, mixed> $formFieldNode
     * @return array{0: ?array<int, string>, 1: ?array<string, string>}
     */
    protected function extractChoicesFromChildren(array $formFieldNode): array
    {
        $choiceNames = [];
        $choiceLabels = [];

        /** @var list<mixed> $children */
        $children = (array) ($formFieldNode['children'] ?? []);
        foreach ($children as $choiceNode) {
            if (! is_array($choiceNode)) {
                continue;
            }
            $choiceName = (string) ($choiceNode['name'] ?? '');
            if ($choiceName === '') {
                continue;
            }

            $choiceNames[] = $choiceName;

            if (array_key_exists('label', $choiceNode)) {
                $choiceLabels[$choiceName] = $this->normalizeLabelValue($choiceNode['label'], $choiceName);
            }
        }

        $choiceNameList = $choiceNames !== [] ? $choiceNames : null;
        $choiceLabelMap = $choiceLabels !== [] ? $choiceLabels : null;

        return [$choiceNameList, $choiceLabelMap];
    }

    /**
     * @param mixed $labelValue
     */
    protected function normalizeLabelValue(mixed $labelValue, string $fallback): string
    {
        if (is_array($labelValue)) {
            $firstLabelValue = reset($labelValue);
            if (is_string($firstLabelValue) && $firstLabelValue !== '') {
                return $firstLabelValue;
            }

            return $fallback;
        }

        if (is_string($labelValue) && $labelValue !== '') {
            return $labelValue;
        }

        return $fallback;
    }

    /**
     * @param array<mixed> $value
     */
    protected function isAssociativeArray(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    protected function normalizeExpression(string $expression): string
    {
        return (string) preg_replace_callback(
            '~(?<![A-Za-z0-9_])(/[A-Za-z0-9_]+(?:/[A-Za-z0-9_]+)+)~',
            static function (array $matches): string {
                $segments = explode('/', trim($matches[1], '/'));
                $fieldName = (string) end($segments);

                return '${' . $fieldName . '}';
            },
            $expression,
        );
    }
}
