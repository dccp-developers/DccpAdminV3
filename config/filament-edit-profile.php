<?php

return [
    'locale_column' => 'locale',
    'theme_color_column' => 'theme_color',
    'avatar_column' => 'avatar',
    'disk' => env('FILESYSTEM_DISK', 'private'),
    'visibility' => 'public', // or replace by filesystem disk visibility with fallback value
];
