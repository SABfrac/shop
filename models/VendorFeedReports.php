<?php

namespace app\models;

use app\models\FeedChunkResul;
use yii\db\ActiveRecord;
/**
 * @property int $id
 * @property int $vendor_id
 * @property int $total_chunks
 * @property int $total_rows
 * @property string|null $errors_json
 * @property string $status
 * @property string|null $file_path
 * @property string $created_at
 * @property string $updated_at
 * @property string $total_failed
 * @property string|null $started_at
 * @property string|null $finished_at
 * @property float|null $total_duration_sec
 * @property float|null $total_indexing_sec
 */

class VendorFeedReports extends ActiveRecord
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PARSING = 'parsing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CHUNKS_QUEUED = 'chunks_queued';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_FAILED = 'failed';

    public static function tableName(): string
    {
        return '{{%vendor_feed_reports}}';
    }

    public function rules(): array
    {
        return [
            // Обязательные integer-поля
            [['vendor_id', 'total_chunks','total_failed','total_rows'], 'integer'],
            [['vendor_id'], 'required'],

            // Статус — строка из допустимого списка
            ['status', 'in', 'range' => [
                self::STATUS_QUEUED,
                self::STATUS_PARSING,
                self::STATUS_CHUNKS_QUEUED,
                self::STATUS_PROCESSING,
                self::STATUS_COMPLETED,
                self::STATUS_COMPLETED_WITH_ERRORS,
                self::STATUS_FAILED,
            ]],
            ['status', 'string', 'max' => 32],

            // Даты — строки в формате TIMESTAMP (если вы управляете ими вручную)
            [['created_at', 'updated_at', 'started_at', 'finished_at'], 'safe'],


            // Время импорта и индексации — неотрицательные float
            [['total_duration_sec', 'total_indexing_sec'], 'number', 'min' => 0],

            // Опциональные строковые поля
            ['errors_json', 'string'],
            ['file_path', 'string', 'max' => 512],

            // Уникальность (если нужно)
            // [['vendor_id', 'created_at'], 'unique', 'targetAttribute' => ['vendor_id', 'created_at']],
        ];
    }


    public static function getFinalStatuses(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_COMPLETED_WITH_ERRORS,
            self::STATUS_FAILED,
        ];
    }

    public static function isActiveStatuses(): array
    {
        return [
            self::STATUS_QUEUED,
            self::STATUS_PARSING,
            self::STATUS_PROCESSING,
            self::STATUS_CHUNKS_QUEUED,
        ];
    }





}