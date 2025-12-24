<?php
namespace app\services\feed;

use yii\base\Component;

class JsonFeedParser extends Component implements FeedParserInterface
{
    public function parse(string $filePath): array
    {
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = fgetcsv($handle, 0, ',');
            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                $rows[] = array_combine($headers, $data);
            }
            fclose($handle);
        }
        return $rows;
    }
}
