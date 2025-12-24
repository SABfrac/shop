<?php
$r = new Redis();
$r->connect('redis', 6379);
$r->set('test_after_rebuild', 'it_works', 10);
echo 'OK';

