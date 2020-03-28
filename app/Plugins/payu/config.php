<?php

return [

    'payu' => [
        'mode'                => env('PAYU_MODE', 'sandbox'),
        'pos_id'              => env('PAYU_POS_ID', ''),
        'second_key'          => env('PAYU_SECOND_KEY', ''),
        'oauth_client_secret' => env('PAYU_OAUTH_CLIENT_SECRET', ''),
    ],

];
