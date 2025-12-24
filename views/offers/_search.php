<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\models\OffersSearch $model */
/** @var yii\widgets\ActiveForm $form */
?>

<div class="offers-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'product_id') ?>

    <?= $form->field($model, 'vendor_id') ?>

    <?= $form->field($model, 'price') ?>

    <?= $form->field($model, 'stock') ?>

    <?php // echo $form->field($model, 'sku') ?>

    <?php // echo $form->field($model, 'condition') ?>

    <?php // echo $form->field($model, 'status')->checkbox() ?>

    <?php // echo $form->field($model, 'sort_order') ?>

    <?php // echo $form->field($model, 'created_at') ?>

    <?php // echo $form->field($model, 'updated_at') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
