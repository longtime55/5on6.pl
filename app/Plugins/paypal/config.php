<?php

return [

    'paypal' => [
        'mode'      => env('PAYPAL_MODE', 'live'),
        'username'  => env('PAYPAL_USERNAME', 'info_api1.gallado.eu'),//info_api1.gallado.eumicroprixs_api1.gmail.com'),
        'password'  => env('PAYPAL_PASSWORD', '34J4UZ7HR6HYUT9W'),//69ECLRYMJ9XHZNY7'),
        'signature' => env('PAYPAL_SIGNATURE', 'ALNBYmXrvyVtieirc7k0hHDUQk5RAgAOQZgyyNfgq1udvVtY2bNUcWOk'),//Aa4vn17JyzWGobrAxa.nyqjDZ7X0A5T52ikrFf3.F1M2OHORGFQrw3TR'),
    ],

];
