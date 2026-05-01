<?php
// API directory - prevent directory listing
http_response_code(403);
header('Content-Type: application/json');
echo json_encode(['error' => 'Direct access not allowed']);
exit;
