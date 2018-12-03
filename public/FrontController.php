<?php declare(strict_types = 1);

use HomeAssignment\VoteApplication;

require_once __DIR__ . '/../vendor/autoload.php';

exit(VoteApplication::routeRequest($_SERVER['REQUEST_METHOD'], explode('?', $_SERVER['REQUEST_URI'], 2)[0], $_POST));
