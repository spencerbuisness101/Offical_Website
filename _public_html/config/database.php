<?php
/**
 * Database Configuration
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

class Database {
    private static ?PDO $instance = null;
    private readonly string $host;
    private readonly string $db_name;
    private readonly string $username;
    private readonly string $password;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
        $this->db_name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'thespencerwebsite_db');
        $this->username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'thespencerwebsite_user');
        $this->password = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');
        if (empty($this->password) && $this->host !== 'localhost') {
            error_log('CRITICAL: DB_PASS environment variable is not set');
            throw new \RuntimeException('Database password not configured');
        }
    }

    /**
     * Singleton accessor for the PDO connection.
     * SEC-P1: Reuses a single connection across the entire request lifecycle.
     */
    public static function getStaticConnection(): PDO {
        if (self::$instance === null) {
            $db = new self();
            self::$instance = $db->getConnection();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        // Return existing instance if available
        if (self::$instance !== null) return self::$instance;

        $maxRetries = 3;
        $retryCount = 0;

        $options = [
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        $sslCa = getenv('DB_SSL_CA') ?: '';
        $isLocal = in_array($this->host, ['localhost', '127.0.0.1', '::1']);
        
        if ($sslCa && file_exists($sslCa)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        } elseif (!$isLocal && (getenv('DB_FORCE_SSL') === 'true')) {
            error_log('CRITICAL: SSL CA not configured for remote DB host ' . $this->host);
            throw new \RuntimeException('Secure database connection requires SSL certificate');
        }

        while ($retryCount < $maxRetries) {
            try {
                self::$instance = new PDO(
                    dsn: "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                    username: $this->username,
                    password: $this->password,
                    options: $options
                );
                
                // Verification query
                self::$instance->query("SELECT 1")->closeCursor();
                break;

            } catch (PDOException $exception) {
                $retryCount++;
                if ($retryCount >= $maxRetries) {
                    error_log("MySQL Connection failed after {$maxRetries} attempts: " . $exception->getMessage());
                    throw new \RuntimeException("Database connection failed", 0, $exception);
                }
                usleep(500000);
            }
        }

        return self::$instance;
    }

    public function createAnalyticsTables(): bool {
        return true;
    }
}

