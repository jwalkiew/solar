<?php
require_once __DIR__ . '/bootstrap.php';
require_once APP_LIB_DIR . '/Http.php';
require_once APP_LIB_DIR . '/Context.php';
require_once APP_LIB_DIR . '/EventsService.php';

Http::guardGet();
$ctx = Context::fromQuery();
Http::sendJson((new EventsService($ctx))->build());
