<?php

namespace app\helper;
use yii\base\Component;


/**
 * Нормализует имя бренда: удаляет пробелы по краям, приводит к нижнему регистру,
 * унифицирует тире, удаляет лишние символы, схлопывает пробелы.
 * Пустые строки и null возвращаются как null.
 */
class DataNormalizer extends Component
{

    private const DASH_REPLACEMENTS = [
        '‐' => '-',
        '‑' => '-',
        '‒' => '-',
        '–' => '-',
        '—' => '-',
        '−' => '-'
    ];

    /**
     * Базовая нормализация с оптимизациями
     */
    private static function baseNormalize(?string $value, bool $toLowerCase = true): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($toLowerCase) {
            $value = mb_strtolower($value, 'UTF-8');
        }

        // Заменяем все "тире" на обычный дефис — даже если потом удалим
        $value = strtr($value, self::DASH_REPLACEMENTS);

        // УДАЛЯЕМ ВСЁ, кроме букв, цифр и пробелов — дефис НЕ разрешён!
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);

        // Схлопываем пробелы
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Стандартная нормализация
     */
    public static function normalizer(?string $name): ?string
    {
        return self::baseNormalize($name);
    }

    /**
     * Нормализует артикул модели - максимально простая функция
     */
    public static function normalizeModelNumber(?string $modelNumber): ?string
    {
        if ($modelNumber === null || $modelNumber === '') {
            return null;
        }

        $modelNumber = trim($modelNumber);
        if ($modelNumber === '') {
            return null;
        }

        return mb_strtoupper($modelNumber, 'UTF-8');
    }

    /**
     * Нормализация для ключей сопоставления
     */
    public static function mathKeyNormalizer(?string $name, ?string $brand = null): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $name = mb_strtolower($name, 'UTF-8');

        // Удаляем бренд только если он передан
        if ($brand !== null && $brand !== '') {
            $brand = mb_strtolower($brand, 'UTF-8');
            $quotedBrand = preg_quote($brand, '/');

            $pattern = '/(?<!\w)' . $quotedBrand . '(?!\w)/u';
            $name = preg_replace($pattern, '', $name);
        }

        // Используем уже оптимизированную базовую нормализацию
        return self::baseNormalize($name, false);
    }

    public static function buildMatchKeyForGlobalProduct(string $productName, ?string $brandName, int $categoryId): string
    {
        $normName = self::mathKeyNormalizer($productName, $brandName);
        $normBrand = self::normalizer($brandName);

        // Используем sprintf для построения строки - быстрее конкатенации
        return sprintf('%s|%s|%d', $normBrand ?? 'null', $normName, $categoryId);
    }
}


