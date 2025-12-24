<?php
namespace app\helper;
class DateTimeHelper
{
    private static ?\DateTimeZone $moscowTz = null;
    private static ?\DateTimeZone $utcTz = null;

    /**
     * Быстрая конвертация Moscow → UTC ISO8601
     */
    public static function toUtc(?string $datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        // Самый быстрый вариант для highload
        return gmdate('c', strtotime($datetime . ' Europe/Moscow'));
    }

    /**
     * Batch конвертация для массовых операций
     */
    public static function toUtcBatch(array $datetimes): array
    {
        $result = [];
        foreach ($datetimes as $key => $datetime) {
            $result[$key] = $datetime
                ? gmdate('c', strtotime($datetime . ' Europe/Moscow'))
                : null;
        }
        return $result;
    }
}
