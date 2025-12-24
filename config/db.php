<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'pgsql:host=yii2_pgs;dbname=mydb',//host=yii2_pgs для docker сети для openserver сети localhost
    'username' => 'myuser',
    'password' => 'root',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
