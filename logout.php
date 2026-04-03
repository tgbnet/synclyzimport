<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

session_destroy();
redirect('index.php');
