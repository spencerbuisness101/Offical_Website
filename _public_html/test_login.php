<?php
$ch = curl_init('http://localhost/auth/login_with_security.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'identifier' => 'test',
    'password' => 'test',
    'csrf_token' => 'dummy'
]));
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP CODE: $http_code\n";
echo "RESPONSE:\n$response\n";
