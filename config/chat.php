<?php

return [
    // Max chat messages a user may send per day (0 = unlimited). Resets at
    // midnight in the timezone below.
    'daily_message_limit' => (int) env('CHAT_DAILY_MESSAGE_LIMIT', 30),

    'limit_timezone' => env('CHAT_LIMIT_TIMEZONE', 'Asia/Vientiane'),
];
