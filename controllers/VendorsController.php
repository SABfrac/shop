<?php

namespace app\controllers;

use app\models\Vendors;
use app\models\Offers;
use yii\web\Controller;
use app\traits\VendorAuthTrait;
use yii\data\ActiveDataProvider;
use yii\caching\Dependency;
use yii\caching\ExpressionDependency;



use yii;

class VendorsController extends Controller
{
    use VendorAuthTrait;

    public function actionRegister()
    {
        $model = new Vendors();
        $data = Yii::$app->request->getBodyParams();

        if ($model->load($data, '') && $model->validate()) {
            $model->setPassword($model->password);
            $model->generateEmailConfirmToken();
            $model->status = Vendors::STATUS_ACTIVE;

            if ($model->save(false)) {

                return $this->asJson([
                    'success' => true,
                    'message' => 'Регистрация успешна! Проверьте ваш email для подтверждения.'
                ]);
            }
        }

        return $this->asJson([
            'success' => false,
            'message' => 'Ошибка регистрации',
            'errors' => $model->errors]);
    }

    public function actionConfirmEmail($token)
    {
        $vendor = Vendors::findOne(['email_confirm_token' => $token]);

        if ($vendor) {
            $vendor->removeEmailConfirmToken();
            $vendor->status = Vendors::STATUS_ACTIVE;
            $vendor->save(false);

            return $this->asJson(['success' => true, 'message' => 'Email подтверждён']);
        }

        return $this->asJson(['success' => false, 'message' => 'Неверный токен']);
    }

    public function actionLogin()
    {


        $body = Yii::$app->request->getBodyParams();
        $email = $body['email'];
        $password = $body['password'];

        $vendor = Vendors::findByEmail($email);

        if ($vendor && $vendor->validatePassword($password)) {
            if ($vendor->status !== Vendors::STATUS_ACTIVE) {
                return $this->asJson(['success' => false, 'message' => 'Аккаунт не подтверждён']);
            }

            try {
                $token = Yii::$app->jwt->generateToken(['vendor_id' => $vendor->id]);
            } catch (\Throwable $e) {
                Yii::error('JWT error: ' . $e->getMessage(), __METHOD__);
                Yii::$app->response->statusCode = 500;
                return $this->asJson(['success' => false, 'message' => 'Ошибка генерации токена']);
            }

            // ✅ Устанавливаем HttpOnly cookie
            Yii::$app->response->cookies->add(new \yii\web\Cookie([
                'name' => 'auth_token',
                'value' => $token,
                'httpOnly' => true,
                'secure' => true,
                'sameSite' => \yii\web\Cookie::SAME_SITE_NONE,
                'expire' => time() + 24 * 60 * 60, // 1 день
            ]));

            return $this->asJson([
                'success' => true,
                'vendor_id' => $vendor->id,
                'profile' => [
                    'name' => $vendor->name,
                    'email' => $vendor->email,
                    'passport' => $vendor->passport,
                    'status' => $vendor->status,
                    'balance' => $vendor->balance,

                ]

            ]);
        }

        return $this->asJson(['success' => false, 'message' => 'Неверный логин или пароль']);
    }


    public function actionLogout()
    {

        Yii::$app->response->cookies->remove('auth_token');

        // добавить в blacklist
        $token = $this->getAuthTokenFromCookie();
        if ($token) {
            try {
                $payload = Yii::$app->jwt->decodeToken($token);
                $jti = $payload['jti'] ?? null;
                $exp = $payload['exp'] ?? null;
                if ($jti && $exp) {
                    $ttl = $exp - time();
                    if ($ttl > 0) {
                        Yii::$app->redis->setex("blacklist:jti:{$jti}", $ttl, '1');
                    }
                }
            } catch (\Exception $e) {
                // Игнорируем ошибки декодирования
            }
        }

        return $this->asJson(['success' => true, 'message' => 'Выход выполнен']);
    }


    /**
     * Возвращает профиль текущего авторизованного продавца
     */
    public function actionMe()
    {
        try {

            $vendorId = $this->getAuthorizedVendorId(); // может бросить UnauthorizedHttpException
            $cacheKey = ['vendor', 'profile', (int)$vendorId];
            $vendor = Yii::$app->cache->getOrSet(
                $cacheKey,
                function () use ($vendorId) {
                    $data = Vendors::find()
                        ->select(['id', 'name', 'email', 'passport', 'status', 'balance'])
                        ->where(['id' => $vendorId])
                        ->asArray()
                        ->one();

                    if ($data === null) {
                        throw new \yii\web\NotFoundHttpException('Вендор не найден');
                    }

                    return $data;
                }, 300);

            return $this->asJson([
                'success' => true,
                'vendor_id' => $vendor['id'],
                'profile' => [
                    'name' => $vendor['name'],
                    'email' => $vendor['email'],
                    'passport' => $vendor['passport'],
                    'status' => $vendor['status'],
                    'balance' => (float)$vendor['balance'],
                ],
            ]);
        } catch (\yii\web\UnauthorizedHttpException $e) {
            Yii::$app->response->statusCode = 401;
            return $this->asJson(['success' => false, 'message' => $e->getMessage()]);
        } catch (\yii\web\NotFoundHttpException $e) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), __METHOD__);
            Yii::$app->response->statusCode = 500;
            return $this->asJson(['success' => false, 'message' => 'Внутренняя ошибка сервера']);
        }
    }

    public function actionOffers()
    {

        try {
            $vendorId = $this->getAuthorizedVendorId();
        } catch (\Exception $e) {
            throw new \yii\web\ForbiddenHttpException('Не авторизован как продавец');
        }

        $request = Yii::$app->request;
        $searchQuery = trim($request->get('search', ''));
        $status = $request->get('status', '');
        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('per-page', 10);
        $pageSize = max(1, min($pageSize, 100));
        $page = max(1, $page);

        $cacheKey = 'vendor_offers_os:' . md5(implode(':', [
                $vendorId,
                $searchQuery,
                $status,
                $page,
                $pageSize,
            ]));

        if (($cached = Yii::$app->cache->get($cacheKey)) !== false) {
            return $this->asJson($cached);
        }

        $boolFilter = [
            'must' => [
                ['term' => ['vendor_id' => $vendorId]],
            ],
        ];

        if ($status !== '') {
            $boolFilter['must'][] = ['term' => ['status' => (int)$status]];
        }

        $query = ['bool' => $boolFilter];

        if ($searchQuery !== '') {
            $query = [
                'bool' => [
                    'must' => array_merge(
                        $boolFilter['must'],
                        [
                            [
                                'multi_match' => [
                                    'query' => $searchQuery,
                                    'fields' => ['product_name^3', 'full_search'],
                                    'type' => 'best_fields',
                                    'fuzziness' => 'AUTO',
                                ],
                            ],
                        ]
                    ),
                ],
            ];
        }

        $from = ($page - 1) * $pageSize;

        $searchParams = [
            'index' => 'products',
            'body' => [
                'query' => $query,
                'from' => $from,
                'size' => $pageSize,
                'sort' => ['updated_at' => 'desc'],
                '_source' => [
                    'includes' => [
                        'product_id', 'sku_id', 'product_name', 'brand_name', 'brand_id', 'category_id',
                        'vendor_sku', 'price', 'stock', 'warranty', 'condition', 'status'
                    ]
                ]
            ],
        ];


        $os = Yii::$app->opensearch;
        $response = $os->getClient()->search($searchParams);

        $hits = $response['hits']['hits'] ?? [];
        $totalCount = $response['hits']['total']['value'] ?? 0;
        $totalPages = (int) ceil($totalCount / $pageSize);

        $items = [];
        foreach ($hits as $hit) {
            $src = $hit['_source'];
            $items[] = [
                'id' => (int) $hit['_id'], // ← это offer_id — идеально!
                'price' => (float) ($src['price'] ?? 0),
                'vendor_sku' => (string) ($src['vendor_sku'] ?? ''),
                'stock' => (int) ($src['stock'] ?? 0),
                'warranty' => isset($src['warranty']) ? (int) $src['warranty'] : null,
                'status' => (int) ($src['status'] ?? 0),
                'condition' => (string) ($src['condition'] ?? 'new'),
                'sku' => [
                    'id' => (string) ($src['sku_id'] ?? ''),
                    'product' => [
                        'id' => (int) ($src['product_id'] ?? 0),
                        'name' => (string) ($src['product_name'] ?? ''),
                        'category_id' => (int) ($src['category_id'] ?? 0),
                        'brand_id' => (int) ($src['brand_id'] ?? 0),
                    ],
                ],
            ];
        }

        $result = [
            'items' => $items,
            'meta' => [
                'totalCount' => $totalCount,
                'page' => $page,
                'pageSize' => $pageSize,
                'totalPages' => $totalPages,
            ],
        ];

        Yii::$app->cache->set($cacheKey, $result, 6); // 10 секунд — баланс между актуальностью и нагрузкой

        return $this->asJson($result);


    }
}