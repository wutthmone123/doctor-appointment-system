<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/json_store.php';

function db(): JsonStore
{
    return jsondb();
}
