<?php

use app\models\Offers;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\OffersSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Offers';
$this->params['breadcrumbs'][] = $this->title;


// Пути к собранным файлам Vite (или через asset manager)
$vueJs = '/dist/assets/index-Ciq_gD-F.js'; // имя файла после билда


?>

<?php if (YII_ENV_DEV): ?>
    <script type="module" src="https://shop.local:5173/@vite/client"></script>
    <script type="module" src="https://shop.local:5173/src/main.js"></script>

<?php endif; ?>

<div class="offers-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Offers', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'product_id',
            'vendor_id',
            'price',
            'stock',
            //'sku',
            //'condition',
            //'status:boolean',
            //'sort_order',
            //'created_at',
            //'updated_at',
            [
                'class' => ActionColumn::className(),
                'urlCreator' => function ($action, Offers $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->id]);
                 }
            ],
        ],
    ]); ?>


    <div id="app">
        <!-- Здесь появится Vue -->
    </div>


</div>
