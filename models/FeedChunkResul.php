<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $report_id
 * @property int $processed_rows
 * @property string|null $errors_json
 * @property string $status
 * @property string $created_at
 * @property float|null $duration_sec
 */
class FeedChunkResul
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PROCESSING = 'processing';

    public static function tableName(): string
    {
        return '{{%feed_chunk_result}}';
    }

    public function rules(): array
    {
        return [
            [['report_id', 'processed_rows'], 'required'],
            [['report_id', 'processed_rows'], 'integer'],
            [['errors_json'], 'string'],
            [['status'], 'string', 'max' => 20],
            [['created_at'], 'safe'],
            [['duration_sec'], 'number'],
            [['status'], 'in', 'range' => [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_PROCESSING]],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'report_id' => 'Report ID',
            'processed_rows' => 'Success Count',
            'errors_json' => 'Errors (JSON)',
            'status' => 'Status',
            'created_at' => 'Created At',
            'duration_sec' => 'Duration (sec)',
        ];
    }

    /**
     * Decodes errors_json into an array (if valid JSON).
     */
    public function getErrorsArray(): array
    {
        if (empty($this->errors_json)) {
            return [];
        }

        $decoded = json_decode($this->errors_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Encodes an array of errors into errors_json.
     */
    public function setErrorsArray(array $errors): void
    {
        $this->errors_json = empty($errors) ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

}