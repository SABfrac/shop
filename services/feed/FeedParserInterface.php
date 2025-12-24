<?php
namespace app\services\feed;
interface FeedParserInterface
{
    /**
     * @param string $filePath
     * @return array<array<string, mixed>> List of parsed rows
     */
    public function parse(string $filePath): array;
}
