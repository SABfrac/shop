<?php
namespace app\services;

class ProductSkuVariantHashBuilder
{
    public function buildVariantHash(array $values): array
    {
        $canon = [];

        foreach ($values as $i => $raw) {
            // 🔒 Защита от ошибок
            if (!is_array($raw)) {
                throw new \InvalidArgumentException(
                    "Элемент #{$i} в variant values не является массивом. Получено: " . gettype($raw)
                );
            }

            $attrId = (int)($raw['attribute_id'] ?? 0);
            if (!$attrId) {
                throw new \InvalidArgumentException("attribute_id обязателен (элемент #{$i})");
            }


            $declaredType = $raw['type'] ?? null;
            $type = null;
            $val = null;

            if ($declaredType === 'select') {
                $type = 'o';
                $val = (int)($raw['attribute_option_id'] ?? 0);
                if (!$val) throw new \InvalidArgumentException("attribute_option_id обязателен для select (attribute_id={$attrId})");
            } elseif ($declaredType === 'integer') {
                $type = 'i';
                if (!array_key_exists('value_int', $raw)) {
                    throw new \InvalidArgumentException("value_int обязателен для integer (attribute_id={$attrId})");
                }
                $val = (string)(int)$raw['value_int'];
            } elseif ($declaredType === 'float') {
                $type = 'f';
                if (!array_key_exists('value_float', $raw)) {
                    throw new \InvalidArgumentException("value_float обязателен для float (attribute_id={$attrId})");
                }
                $val = rtrim(rtrim(number_format((float)$raw['value_float'], 6, '.', ''), '0'), '.');
                if ($val === '') $val = '0';
            } elseif ($declaredType === 'bool') {
                $type = 'b';
                $val = ((bool)($raw['value_bool'] ?? false)) ? '1' : '0';
            } elseif ($declaredType === 'string') {
                $type = 's';
                $str = (string)($raw['value_string'] ?? '');
                $val = mb_strtolower(trim($str), 'UTF-8');
                if ($val === '') {
                    throw new \InvalidArgumentException("value_string обязателен для string (attribute_id={$attrId})");
                }
            } else {
                // Авто-детект
                if (isset($raw['attribute_option_id']) && $raw['attribute_option_id'] !== null && $raw['attribute_option_id'] !== '') {
                    $type = 'o';
                    $val = (string)(int)$raw['attribute_option_id'];
                } elseif (isset($raw['value_string']) && $raw['value_string'] !== '') {
                    $type = 's';
                    $val = mb_strtolower(trim((string)$raw['value_string']), 'UTF-8');
                } elseif (isset($raw['value_int']) && $raw['value_int'] !== null && $raw['value_int'] !== '') {
                    $type = 'i';
                    $val = (string)(int)$raw['value_int'];
                } elseif (isset($raw['value_float']) && $raw['value_float'] !== null && $raw['value_float'] !== '') {
                    $type = 'f';
                    $val = rtrim(rtrim(number_format((float)$raw['value_float'], 6, '.', ''), '0'), '.');
                    if ($val === '') $val = '0';
                } elseif (array_key_exists('value_bool', $raw) && ($raw['value_bool'] === true || $raw['value_bool'] === 1 || $raw['value_bool'] === '1')) {
                    $type = 'b';
                    $val = '1';
                } else {
                    throw new \InvalidArgumentException("Не удалось определить тип/значение для attribute_id={$attrId}. Рекомендуется передавать поле 'type'.");
                }
            }

            $canon[] = ['attribute_id' => $attrId, 't' => $type, 'v' => (string)$val];
        }

        usort($canon, static fn($a, $b) => $a['attribute_id'] <=> $b['attribute_id']);

        $parts = array_map(static fn($x) => $x['attribute_id'] . ':' . $x['t'] . ':' . $x['v'], $canon);
        $key = implode('|', $parts);
        $hash = hash('sha256', $key);

        return [$hash, $key];

    }
}