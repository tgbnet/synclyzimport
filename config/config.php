<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'video_cms',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => getenv('APP_NAME') ?: 'Synclyz Video CMS',
        'base_url' => getenv('APP_BASE_URL') ?: '',
        'video_upload_dir' => __DIR__ . '/../public/uploads/videos/',
        'photo_upload_dir' => __DIR__ . '/../public/uploads/photos/',
        'max_video_size_mb' => 250,
        'max_photo_size_mb' => 10,
    ],
];
