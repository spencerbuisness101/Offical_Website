<?php
$url = 'http://localhost/auth/login_with_security.php';
$data = http_build_query([
    'identifier' => 'test',
    'password' => 'test',
    'csrf_token' => 'dummy'
]);
$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => $data,
    ]
];
$context = stream_context_create($options);
$result = @file_get_contents($url, false, $context);
var_dump($result, $http_response_header);
