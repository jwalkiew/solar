<?php
// lib/ lies next to the document root (Docker, recommended hosting layout)
// or inside it under app/ (single-directory hosting, protected by app/.htaccess)
define('APP_LIB_DIR', is_dir(__DIR__ . '/../app/lib') ? __DIR__ . '/../app/lib' : __DIR__ . '/../../lib');
