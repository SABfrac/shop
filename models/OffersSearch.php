<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Offers;

/**
 * OffersSearch represents the model behind the search form of `app\models\Offers`.
 */
class OffersSearch extends Offers
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'global_product_id', 'vendor_id', 'stock', 'sort_order'], 'integer'],
            [['price'], 'number'],
            [['sku_id', 'condition', 'created_at', 'updated_at'], 'safe'],
            [['status'], 'boolean'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        // bypass scenarios() implementation in the parent class
        return Model::scenarios();
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     * @param string|null $formName Form name to be used into `->load()` method.
     *
     * @return ActiveDataProvider
     */
    public function search($params, $formName = null)
    {
        $query = Offers::find();

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params, $formName);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        // grid filtering conditions
        $query->andFilterWhere([
            'id' => $this->id,
            'product_id' => $this->product_id,
            'vendor_id' => $this->vendor_id,
            'price' => $this->price,
            'stock' => $this->stock,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ]);

        $query->andFilterWhere(['ilike', 'sku', $this->sku])
            ->andFilterWhere(['ilike', 'condition', $this->condition]);

        return $dataProvider;
    }
}



