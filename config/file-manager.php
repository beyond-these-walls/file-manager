<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    | The Laravel filesystem disk to use for all file manager operations.
    | Defaults to the 'public' disk. Change to 's3' or any configured disk.
    */
    'disk' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Base Path
    |--------------------------------------------------------------------------
    | The root directory within the disk where files are stored.
    | No leading or trailing slash.
    */
    'base_path' => 'uploads',

    /*
    |--------------------------------------------------------------------------
    | Authorization Gate
    |--------------------------------------------------------------------------
    | Name of a Laravel gate to check before any mutating operation (upload,
    | delete, rename, move, AI generation). Set to null to allow any
    | authenticated user. Define the gate in your AuthServiceProvider.
    |
    | Example:
    |   Gate::define('use-file-manager', fn ($user) => $user->hasRole('editor'));
    */
    'auth_gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Thumbnails
    |--------------------------------------------------------------------------
    */
    'thumbnail' => [
        'width'  => 150,
        'height' => 150,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (requests per minute per authenticated user)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'upload'      => 20,
        'ai_generate' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    | Toggle individual features on or off.
    */
    'features' => [
        'ai_generator'  => true,
        'image_editor'  => true,
        'multi_select'  => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    | Providers shown in the AI image generator dropdown.
    | Remove any provider whose API key you have not configured.
    */
    'ai_providers' => [
        'cloudflare'  => 'Cloudflare Workers AI (FLUX)',
        'huggingface' => 'Hugging Face FLUX',
        'picsart'     => 'Picsart Gen AI',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    | Each entry defines accepted extensions, MIME types, and the maximum
    | file size in kilobytes.
    */
    'allowed_file_types' => [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
            'mimes'      => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'],
            'max_size'   => 10240,
        ],
        'media' => [
            'extensions' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', 'mp3', 'wav', 'ogg', 'flac', 'aac'],
            'mimes'      => [
                'video/mp4', 'video/avi', 'video/quicktime', 'video/x-ms-wmv',
                'video/x-flv', 'video/x-matroska', 'video/webm',
                'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/flac', 'audio/aac',
            ],
            'max_size'   => 102400,
        ],
        'file' => [
            'extensions' => ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx'],
            'mimes'      => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain', 'application/rtf',
                'application/vnd.oasis.opendocument.text',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/csv',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
            'max_size'   => 51200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dangerous Extensions (always blocked regardless of other settings)
    |--------------------------------------------------------------------------
    */
    'dangerous_extensions' => [
        'php', 'php3', 'php4', 'php5', 'phtml', 'asp', 'aspx', 'jsp', 'jspx',
        'exe', 'bat', 'cmd', 'com', 'scr', 'vbs', 'vbe', 'js', 'jar',
        'sh', 'bash', 'pl', 'py', 'rb', 'cgi', 'htaccess', 'htpasswd',
    ],

];
