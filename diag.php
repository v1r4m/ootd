<?php

/**
 * 임시 진단 스크립트 — 실제 HTTP 요청을 CLI에서 그대로 돌려 500 원인을 표면화한다.
 *   docker compose -f compose.prod.yaml exec app php diag.php
 * 원인 확인 후 삭제할 것.
 */

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/@viram', 'GET');

$response = $kernel->handle($request);

echo "STATUS: ".$response->getStatusCode()."\n";
echo str_repeat('-', 60)."\n";
echo substr($response->getContent(), 0, 6000)."\n";
