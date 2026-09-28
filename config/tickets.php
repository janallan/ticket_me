<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Limits for files attached to ticket messages. Files are stored on the
    | private disk and are only served to users who may view the message.
    |
    */

    'attachments' => [
        'disk' => env('TICKET_ATTACHMENTS_DISK', 'local'),

        'max_files' => 5,

        'max_size_kb' => 10 * 1024,

        'mimes' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf',
            'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
            'txt', 'csv',
            'zip',
        ],
    ],

];
