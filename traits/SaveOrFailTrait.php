<?php
namespace app\traits;

use yii\db\Exception;

trait SaveOrFailTrait
{
    /**
     * @param bool $runValidation
     * @param array|null $attributeNames
     * @return bool
     * @throws Exception
     */
    public function saveOrFail(bool $runValidation = true, ?array $attributeNames = null): bool
    {
        if (!$this->save($runValidation, $attributeNames)) {
            throw new Exception('Model save failed: ' . json_encode($this->getErrors()));
        }
        return true;
    }
}
