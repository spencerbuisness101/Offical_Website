<?php
/**
 * Error Handler — Spencer's Website v7.0
 *
 * Centralized error handling following best practices:
 * - Fail gracefully in production, loudly in development
 * - Never swallow errors silently
 * - Distinguish operational vs programmer errors
 * - Log with context but never leak sensitive data
 *
 * Usage:
 *   // Operational error (expected, handle gracefully)
 *   ErrorHandler::operational('Database timeout', ['user_id' => $id]);
 *
 *   // Programmer error (bug, should be fixed)
 *   ErrorHandler::programmer('Undefined index: foo');
 *
 *   // Wrap operation with retry
 *   $result = ErrorHandler::withRetry(function() use ($db) {
 *       return $db->query("SELECT...");
 *   }, ['retries' => 3]);
 */

class ErrorHandler {
    private static bool $initialized = false;
    private static string $environment = 'production';
    private static ?Logger $logger = null;
    
    /**
     * Initialize error handling
     */
    public static function init(string $env = 'production', ?Logger $logger = null): void {
        if (self::$initialized) return;
        
        self::$environment = $env;
        self::$logger = $logger;
        self::$initialized = true;
        
        // Set error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        
        // Handle fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * Handle PHP errors (warnings, notices, etc.)
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool {
        // Don't handle errors that are silenced with @
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error = [
            'type' => self::getErrorType($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('c')
        ];
        
        // Programmer errors should be logged and fixed
        if (in_array($severity, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::log('error', "Programmer error: {$message}", $error);
        } else {
            self::log('warning', "Runtime issue: {$message}", $error);
        }
        
        // In dev, let PHP handle it (loud failure)
        if (self::$environment === 'development') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException(Throwable $e): void {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
        
        // Determine error type
        if ($e instanceof PDOException) {
            // Database errors are operational
            self::log('error', 'Database error: ' . $e->getMessage(), $context);
            self::renderError(500, 'A database error occurred. Please try again.');
        } elseif ($e instanceof InvalidArgumentException || $e instanceof TypeError) {
            // Input/type errors are programmer errors
            self::log('error', 'Programmer error: ' . $e->getMessage(), $context);
            self::renderError(500, self::$environment === 'development' ? $e->getMessage() : 'An unexpected error occurred.');
        } else {
            // General operational errors
            self::log('error', 'Uncaught exception: ' . $e->getMessage(), $context);
            self::renderError(500, 'Something went wrong. Please try again.');
        }
    }
    
    /**
     * Handle fatal shutdown errors
     */
    public static function handleShutdown(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::log('fatal', 'Fatal error: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line']
            ]);
            
            if (!headers_sent()) {
                self::renderError(500, 'A critical error occurred. Please try again.');
            }
        }
    }
    
    /**
     * Log operational error (expected, recoverable)
     */
    public static function operational(string $message, array $context = []): void {
        self::log('warning', "[OPERATIONAL] {$message}", $context);
    }
    
    /**
     * Log programmer error (bug, should be fixed)
     */
    public static function programmer(string $message, array $context = []): void {
        self::log('error', "[PROGRAMMER] {$message}", $context);
        
        // In development, also trigger warning
        if (self::$environment === 'development') {
            trigger_error($message, E_USER_WARNING);
        }
    }
    
    /**
     * Execute function with retry logic for transient failures
     */
    public static function withRetry(callable $fn, array $options = []): mixed {
        $retries = $options['retries'] ?? 3;
        $baseDelay = $options['baseDelay'] ?? 300; // ms
        $maxDelay = $options['maxDelay'] ?? 5000;  // ms
        
        $lastException = null;
        
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                return $fn();
            } catch (PDOException $e) {
                // Only retry on transient errors
                if (!self::isTransientError($e) || $attempt === $retries) {
                    throw $e;
                }
                
                $lastException = $e;
                $delay = min($baseDelay * (2 ** $attempt), $maxDelay);
                usleep(($delay + random_int(0, 100)) * 1000); // Add jitter
                
                self::log('warning', "Retry attempt {$attempt} after error: {$e->getMessage()}");
            }
        }
        
        throw $lastException;
    }
    
    /**
     * Check if database error is transient (can be retried)
     */
    private static function isTransientError(PDOException $e): bool {
        $transientCodes = [
            '40001', // Serialization failure
            '40020', // Deadlock
            '40100', // Timeout
            'HY000', // General error (often transient)
        ];
        
        return in_array($e->getCode(), $transientCodes) ||
               strpos($e->getMessage(), 'deadlock') !== false ||
               strpos($e->getMessage(), 'timeout') !== false ||
               strpos($e->getMessage(), 'too many connections') !== false;
    }
    
    /**
     * Render error response
     */
    private static function renderError(int $code, string $message): void {
        http_response_code($code);
        
        // API request
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
            strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => [
                    'message' => $message,
                    'code' => $code
                ]
            ]);
        } else {
            // HTML request
            echo "<!DOCTYPE html><html><head><title>Error</title></head>";
            echo "<body style='font-family:sans-serif;text-align:center;padding:50px;'>";
            echo "<h1>Oops!</h1>";
            echo "<p>" . htmlspecialchars($message) . "</p>";
            echo "<p><a href='/'>Go Home</a></p>";
            echo "</body></html>";
        }
        
        exit;
    }
    
    /**
     * Internal logging
     */
    private static function log(string $level, string $message, array $context = []): void {
        // Use logger if available
        if (self::$logger) {
            self::$logger->$level($message, $context);
            return;
        }
        
        // Fallback to error_log
        $logEntry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8))
        ];
        
        error_log(json_encode($logEntry));
    }
    
    /**
     * Get human-readable error type
     */
    private static function getErrorType(int $severity): string {
        return match ($severity) {
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
            default => 'Unknown'
        };
    }
}

/**
 * Global helper functions
 */
if (!function_exists('operational_error')) {
    function operational_error(string $message, array $context = []): void {
        ErrorHandler::operational($message, $context);
    }
}

if (!function_exists('programmer_error')) {
    function programmer_error(string $message, array $context = []): void {
        ErrorHandler::programmer($message, $context);
    }
}

if (!function_exists('with_retry')) {
    function with_retry(callable $fn, array $options = []): mixed {
        return ErrorHandler::withRetry($fn, $options);
    }
}
