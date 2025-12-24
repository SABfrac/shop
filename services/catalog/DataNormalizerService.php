<?php

namespace app\services\catalog;
use yii\base\Component;

class DataNormalizerService extends Component
{

    private array $patternCache = [];


    private const DASH_REPLACEMENTS = [
        '‐' => '-',
        '‑' => '-',
        '‒' => '-',
        '–' => '-',
        '—' => '-',
        '−' => '-'
    ];
    public  function baseNormalize(?string $value, bool $toLowerCase = true): ?string
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
    public  function normalizer(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        return $this->baseNormalize($name);
    }

    /**
     * Нормализует артикул модели - максимально простая функция
     */
    public  function normalizeModelNumber(?string $modelNumber): ?string
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
    public  function mathKeyNormalizer(?string $name, ?string $brand = null): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $name = mb_strtolower($name, 'UTF-8');

        // Удаляем бренд только если он передан
        if ($brand !== null && $brand !== '') {
            $brand = mb_strtolower($brand, 'UTF-8');
            if (!isset($this->patternCache[$brand])) {
                $this->patternCache[$brand] = '/\b' . preg_quote($brand, '/') . '\b\s*/u';
            }

            $name = preg_replace($this->patternCache[$brand], '', $name);
        }

        // Используем уже оптимизированную базовую нормализацию
        return $this->baseNormalize($name, false);
    }

    public  function buildMatchKeyForGlobalProduct(string $productName, ?string $brandName, int $categoryId): string
    {
        $normName = $this->mathKeyNormalizer($productName, $brandName);
        $normBrand = $this->normalizer($brandName);

        // Используем sprintf для построения строки - быстрее конкатенации
        return sprintf('%s|%s|%d', $normBrand ?? 'null', $normName, $categoryId);
    }
}