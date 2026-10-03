<?php

return [
    'storage_disk' => env('DOCUMENT_STORAGE_DISK', 'local'),
    'max_upload_kilobytes' => (int) env('DOCUMENT_MAX_UPLOAD_KB', 20480),
    'history_page_size' => 12,
    'qr_size' => 720,
    'qr_margin' => 32,
];