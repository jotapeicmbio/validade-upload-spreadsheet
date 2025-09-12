<?php

namespace Icmbio\ValidateRegister\XformExpression;

use function Jotapegue\Phpxform\helpers\dd;

class XformExpression
{
    protected const FUNCTIONS = [
        // Funções internas XForm → nome do método interno
        'selected' => '_fn_selected',
        'string-length' => '_fn_string_length',
        'string_length' => '_fn_string_length',
        'int' => '_fn_int',
        'floor' => '_fn_floor',
        'ceiling' => '_fn_ceiling',
        'number' => '_fn_number',
        'string' => '_fn_string',
        'contains' => '_fn_contains',
        'starts-with' => '_fn_starts_with',
        'normalize-space' => '_fn_normalize_space',
        'choose' => '_fn_choose',
        'not' => '_fn_not',
        'true' => '_fn_true',
        'false' => '_fn_false',
        'uuid' => '_fn_uuid',
        'format-date-time' => '_fn_format_date_time',
        'substring-after' => '_fn_substring_after',
        'substring-before' => '_fn_substring_before',
    ];

    public static function validate(string $expression, $value, array $context = [], bool $returnsBool = true)
    {
        return (new static())->execute(...func_get_args());
    }

    public static function escapeExpression(string $expr): string
    {
        return str_replace('${', '\${', $expr);
    }

    public function execute(string $expression, $value, array $context = [], bool $returnsBool = true)
    {
        $expr = $this->prepareExpression($expression, $value, $context);
        $result = $this->evaluateExpression($expr);

        return $returnsBool ? (bool) $result : $result;
    }

    protected function prepareExpression(string $expression, $value, array $context): string
    {
        // 1. Substitui "." pelo valor atual
        $expression = preg_replace_callback(
            '/(?<![a-zA-Z0-9_])\.(?![a-zA-Z0-9_])/',
            fn() => var_export($value, true),
            $expression
        );

        // 2. Substitui ${var} pelas variáveis do contexto
        $expression = preg_replace_callback(
            '/\$\{([a-zA-Z0-9_]+)\}/',
            fn($m) => var_export($context[$m[1]] ?? null, true),
            $expression
        );

        // 3. Substitui funções conhecidas por chamadas dinâmicas
        foreach (self::FUNCTIONS as $xpathFn => $method) {
            $pattern = '/\b' . preg_quote($xpathFn, '/') . '\s*\(/';
            $replacement = '$this->' . $method . '(';
            $expression = preg_replace($pattern, $replacement, $expression);
        }

        // 4. Substitui operadores XPath para PHP
        $replacements = [
            'and' => '&&',
            'or' => '||',
            '!=' => '!=',
            'div' => '/',
            'mod' => '%',
        ];

        // Primeiro substituímos operadores compostos lógicos e aritméticos
        $expression = preg_replace_callback(
            '/\b(and|or|div|mod)\b|!=/',
            fn($m) => $replacements[$m[0]] ?? $m[0],
            $expression
        );

        // Depois substituímos "=" isolado por "==", sem afetar >= ou <=
        $expression = preg_replace('/(?<![<>!])=(?!=)/', '==', $expression);

        return $expression;
    }

    protected function evaluateExpression(string $expr)
    {
        try {
            return eval('return ' . $expr . ';');
        } catch (\Throwable $e) {
            throw new \RuntimeException("Erro ao avaliar expressão: $expr. " . $e->getMessage());
        }
    }

    // --- Funções internas ---
    protected function _fn_selected($set, $value): bool
    {
        if ($set === null || $value === null) return false;
        $tokens = preg_split('/\s+/', trim((string)$set));
        return in_array((string)$value, $tokens, true);
    }

    protected function _fn_string_length($s): int
    {
        return mb_strlen((string)$s);
    }

    protected function _fn_int($v)
    {
        return intval($v);
    }

    protected function _fn_floor($v)
    {
        // return (int) floor((float)$v);
        return floor($v);
    }

    protected function _fn_ceiling($v)
    {
        return ceil($v);
    }

    protected function _fn_number($v)
    {
        return floatval($v);
    }

    protected function _fn_string($v)
    {
        return strval($v);
    }

    protected function _fn_contains($s, $substr)
    {
        return mb_strpos((string)$s, (string)$substr) !== false;
    }

    protected function _fn_starts_with($s, $prefix)
    {
        return str_starts_with((string)$s, (string)$prefix);
    }

    protected function _fn_normalize_space($s)
    {
        return preg_replace('/\s+/', ' ', trim((string)$s));
    }

    protected function _fn_choose($cond, $a, $b)
    {
        return $cond ? $a : $b;
    }

    protected function _fn_not($v)
    {
        return !$v;
    }

    protected function _fn_true()
    {
        return true;
    }

    protected function _fn_false()
    {
        return false;
    }

    protected function _fn_uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x', str_split($data));
    }

    protected function _fn_format_date_time($date, $format)
    {
        if (!$date) return null;
        try {
            // Remove Z no final
            $datetime = rtrim($date, 'Z');

            $dt = new \DateTime($datetime);

            // Mapeamento de formato XPath/strftime → PHP DateTime
            $map = [
                '%Y' => 'Y', // Ano completo
                '%m' => 'm', // Mês 01-12
                '%d' => 'd', // Dia 01-31
                '%H' => 'H', // Hora 00-23
                '%M' => 'i', // Minutos 00-59
                '%S' => 's', // Segundos 00-59
            ];

            $phpFormat = strtr($format, $map);

            return $dt->format($phpFormat);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function _fn_substring_after(string $haystack, string $needle): string
    {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return '';
        }
        return substr($haystack, $pos + strlen($needle));
    }

    protected function _fn_substring_before(string $haystack, string $needle): string
    {
        $pos = strpos($haystack, $needle);
        if ($pos === false) {
            return '';
        }
        return substr($haystack, 0, $pos);
    }
}
