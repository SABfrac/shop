<?php
$redis = new Redis();
try {
    $redis->connect('redis', 6379);
    $redis->set('native_test', 'connected!', 10);
    echo "SUCCESS: " . $redis->get('native_test') . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
