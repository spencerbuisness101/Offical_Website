<?php
// Silence is golden - prevent directory listing
http_response_code(403);
header('Location: /');
exit;
