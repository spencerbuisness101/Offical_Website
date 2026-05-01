<?php
/**
 * Database Configuration
 */

if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(403);
    die('Direct access forbidden');
}

class Database {
    private readonly string $host;
    private readonly string $db_name;
    private readonly string $username;
    private readonly string $password;
    public ?PDO $conn = null;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
        $this->db_name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'thespencerwebsite_db');
        $this->username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'thespencerwebsite_user');
        $this->password = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');
        if (empty($this->password)) {
            error_log('CRITICAL: DB_PASS environment variable is not set');
            throw new \RuntimeException('Database password not configured');
        }
    }

    public function getConnection(): PDO {
        $this->conn = null;
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
        } elseif (!$isLocal) {
            error_log('CRITICAL: SSL CA not configured for remote DB host ' . $this->host);
            throw new \RuntimeException('Secure database connection requires SSL certificate');
        }

        while ($retryCount < $maxRetries) {
            try {
                $this->conn = new PDO(
                    dsn: "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                    username: $this->username,
                    password: $this->password,
                    options: $options
                );

                $stmt = $this->conn->query("SELECT 1");
                $stmt->closeCursor();

                static $logged = false;
                if (!$logged) {
                    $sslStmt = $this->conn->query("SHOW STATUS LIKE 'Ssl_cipher'");
                    $sslStatus = $sslStmt->fetch();
                    $sslStmt->closeCursor();
                    $isEncrypted = !empty($sslStatus['Value']);
                    if (!$isEncrypted && $this->host !== 'localhost' && $this->host !== '127.0.0.1') {
                        error_log("DB SECURITY WARNING: Connection to {$this->host} is NOT encrypted (no SSL)");
                    }
                    $logged = true;
                }
                break;

            } catch (PDOException $exception) {
                $retryCount++;
                error_log("MySQL Connection attempt {$retryCount} failed: " . $exception->getMessage());

                if ($retryCount >= $maxRetries) {
                    error_log("MySQL Connection failed after {$maxRetries} attempts");
                    throw new \RuntimeException("Database connection failed", 0, $exception);
                }

                usleep(500000);
            }
        }

        return $this->conn;
    }

    public function createAnalyticsTables(): bool {
        return true;
    }
}

