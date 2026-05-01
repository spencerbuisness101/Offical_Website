<?php
/**
 * Structured Logger — Spencer's Website v7.0
 *
 * Provides structured JSON logging with severity levels, context, and correlation IDs.
 * Supports file, database, and error_log outputs.
 *
 * Usage:
 *   $logger = new Logger();
 *   $logger->info('User logged in', ['user_id' => 123]);
 *   $logger->error('Database connection failed', ['error' => $e->getMessage()]);
 */

class Logger {
    private string $channel;
    private string $minLevel;
    private ?PDO $db;
    private string $logDir;
    
    // Severity levels (RFC 5424)
    private const LEVELS = [
        'DEBUG'     => 100,
        'INFO'      => 200,
        'NOTICE'    => 250,
        'WARNING'   => 300,
        'ERROR'     => 400,
        'CRITICAL'  => 500,
        'ALERT'     => 550,
        'EMERGENCY' => 600
    ];
    
    public function __construct(string $channel = 'app', string $minLevel = 'INFO', ?PDO $db = null) {
        $this->channel = $channel;
        $this->minLevel = strtoupper($minLevel);
        $this->db = $db;
        $this->logDir = __DIR__ . '/../logs';
        
        // Ensure log directory exists
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0750, true);
        }
    }
    
    /**
     * Log a message with the given level and context
     */
    public function log(string $level, string $message, array $context = []): void {
        $level = strtoupper($level);
        
        // Check minimum level
        if (self::LEVELS[$level] < self::LEVELS[$this->minLevel]) {
            return;
        }
        
        // Build log entry
        $entry = [
            'timestamp'    => date('c'),
            'channel'      => $this->channel,
            'level'        => $level,
            'message'      => $message,
            'context'      => $context,
            'request_id'   => $this->getRequestId(),
            'user_id'      => $_SESSION['user_id'] ?? null,
            'ip'           => $this->getClientIp(),
            'uri'          => $_SERVER['REQUEST_URI'] ?? null,
            'method'       => $_SERVER['REQUEST_METHOD'] ?? null,
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'memory_mb'    => round(memory_get_usage(true) / 1024 / 1024, 2),
            'hostname'     => gethostname()
        ];
        
        // Clean null values
        $entry = array_filter($entry, fn($v) => $v !== null);
        
        // Write to file
        $this->writeToFile($entry);
        
        // Write to database if available and high severity
        if ($this->db && self::LEVELS[$level] >= self::LEVELS['WARNING']) {
            $this->writeToDatabase($entry);
        }
        
        // Critical errors also go to error_log
        if (self::LEVELS[$level] >= self::LEVELS['CRITICAL']) {
            error_log("[{$level}] {$message} | " . json_encode($context));
        }
    }
    
    // Convenience methods
    public function debug(string $message, array $context = []): void {
        $this->log('DEBUG', $message, $context);
    }
    
    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }
    
    public function notice(string $message, array $context = []): void {
        $this->log('NOTICE', $message, $context);
    }
    
    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }
    
    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }
    
    public function critical(string $message, array $context = []): void {
        $this->log('CRITICAL', $message, $context);
    }
    
    public function alert(string $message, array $context = []): void {
        $this->log('ALERT', $message, $context);
    }
    
    public function emergency(string $message, array $context = []): void {
        $this->log('EMERGENCY', $message, $context);
    }
    
    /**
     * Log an exception with full context
     */
    public function exception(Throwable $e, string $level = 'ERROR', array $extraContext = []): void {
        $context = array_merge([
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'code'      => $e->getCode(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString()
        ], $extraContext);
        
        $this->log($level, 'Exception: ' . $e->getMessage(), $context);
    }
    
    /**
     * Write log entry to JSON Lines file
     */
    private function writeToFile(array $entry): void {
        $date = date('Y-m-d');
        $filename = "{$this->logDir}/{$this->channel}-{$date}.log";
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
        
        // Write with locking to prevent corruption under concurrent load
        $fh = fopen($filename, 'a');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                fwrite($fh, $line);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
        }
    }
    
    /**
     * Write high-severity logs to database for admin viewing
     */
    private function writeToDatabase(array $entry): void {
        if (!$this->db) return;
        
        try {
            $stmt = $this->db->prepare("INSERT INTO system_logs 
                (channel, level, message, context, request_id, user_id, ip, uri, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $entry['channel'],
                $entry['level'],
                $entry['message'],
                json_encode($entry['context'] ?? []),
                $entry['request_id'] ?? null,
                $entry['user_id'] ?? null,
                $entry['ip'] ?? null,
                $entry['uri'] ?? null
            ]);
        } catch (Exception $e) {
            // Fail silently to avoid breaking the application
            error_log('Logger DB error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get or generate a request correlation ID
     */
    private function getRequestId(): string {
        if (!isset($_SERVER['HTTP_X_REQUEST_ID'])) {
            $_SERVER['HTTP_X_REQUEST_ID'] = bin2hex(random_bytes(8));
        }
        return $_SERVER['HTTP_X_REQUEST_ID'];
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        return 'unknown';
    }
    
    /**
     * Query recent logs (for admin log viewer)
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array {
        if (!$this->db) {
            return ['error' => 'Database not available'];
        }
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['level'])) {
            $where[] = 'level = ?';
            $params[] = strtoupper($filters['level']);
        }
        
        if (!empty($filters['channel'])) {
            $where[] = 'channel = ?';
            $params[] = $filters['channel'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = (int)$filters['user_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(message LIKE ? OR context LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['since'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['since'];
        }
        
        $sql = "SELECT id, channel, level, message, context, request_id, user_id, ip, uri, method, created_at FROM system_logs WHERE " . implode(' AND ', $where) . " 
                ORDER BY created_at DESC 
                LIMIT {$offset}, {$limit}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get log statistics for dashboard
     */
    public function getStats(string $since = '-24 hours'): array {
        if (!$this->db) {
            return ['error' => 'Database not available'];
        }
        
        $stmt = $this->db->prepare("SELECT 
            level, COUNT(*) as count 
            FROM system_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY level");
        $stmt->execute();
        
        $byLevel = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Recent errors
        $stmt = $this->db->query("SELECT COUNT(*) FROM system_logs 
            WHERE level IN ('ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY') 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $recentErrors = (int)$stmt->fetchColumn();
        
        return [
            'by_level' => $byLevel,
            'recent_errors_1h' => $recentErrors,
            'checked_at' => date('c')
        ];
    }
}

/**
 * Global helper function for quick logging
 */
if (!function_exists('log_info')) {
    function log_info(string $message, array $context = []): void {
        static $logger = null;
        if (!$logger) $logger = new Logger();
        $logger->info($message, $context);
    }
}

if (!function_exists('log_error')) {
    function log_error(string $message, array $context = []): void {
        static $logger = null;
        if (!$logger) $logger = new Logger();
        $logger->error($message, $context);
    }
}

if (!function_exists('log_exception')) {
    function log_exception(Throwable $e, string $level = 'ERROR'): void {
        static $logger = null;
        if (!$logger) $logger = new Logger();
        $logger->exception($e, $level);
    }
}
