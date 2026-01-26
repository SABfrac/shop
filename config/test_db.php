<?php
$db = require __DIR__ . '/db.php';
// offerBulkImportService2 database! Important not to run tests on production or development databases
$db['dsn'] = 'pgsql:host=yii2_pgs;dbname=shop_test';

return $db;
