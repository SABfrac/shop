<?php
namespace app\services\feed;

use yii\base\Component;

class CsvFeedParser extends Component
{
    public function streamParse(string $filePath, callable $chunkCallback, int $chunkSize = 1000): int
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \InvalidArgumentException("CSV file is not readable: $filePath");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: $filePath");
        }

        $headers = fgetcsv($handle, 0, ';');
        if ($headers === false) {
            fclose($handle);
            return 0;
        }

        // Удаляем BOM из первого заголовка
        $bom = "\xEF\xBB\xBF";
        if (substr($headers[0], 0, 3) === $bom) {
            $headers[0] = substr($headers[0], 3);
        }

        $rowCount = 0;
        $chunk = [];


        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (count($data) !== count($headers)) {
                continue; // или логировать
            }

            $chunk[] = array_combine($headers, $data);
            $rowCount++;

            if (count($chunk) >= $chunkSize) {
                $chunkCallback($chunk);
                $chunk = []; // сброс чанка
            }
        }

        // Отправляем остаток
        if (!empty($chunk)) {
            $chunkCallback($chunk);
        }

        fclose($handle);
        return $rowCount;
    }
}
