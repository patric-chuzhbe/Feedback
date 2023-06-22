<?php

return [
    [
        'method' => 'get',
        'uri' => 'feedback',
        'uses' => 'Feedback@index',
        'middleware' => ['exceptionHandlers:flash']
    ],
    [
        'method' => 'post',
        'uri' => 'feedback/save',
        'uses' => 'Feedback@save',
        'middleware' => ['exceptionHandlers:flash']
    ],
    [
        'method' => 'post',
        'uri' => 'feedback/load',
        'uses' => 'Feedback@load',
        'middleware' => ['exceptionHandlers:flash']
    ],
];
