<?php
/**
 * Created by PhpStorm.
 * User: Avian
 * Date: 06/11/2018
 * Time: 8:54
 */

/**
 * Credential Authentication item
 */
return [
    'Auth' => [
        'AppToken' => '',
        'ApiKey' => '',
        'ApiSecret' => '',
        'ClientId' => '',
        'ClientSecret' => '',
        'AuthBase64' => '',
        'CorporateId' => ''
    ],
    'Urls' => [
        'Dev' => [
            'BaseUrl' => 'https://devapi.klikbca.com:443',
            'TokenUrl' => '/api/oauth/token'
        ]
    ],
    'timeout' => '30'
];