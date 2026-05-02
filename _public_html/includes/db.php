<?php
/**
 * DB Singleton Helper — Spencer's Website v7.0
 *
 * Replaces the pattern:
 *     $database = new Database();
 *     $db = $database->getConnection();
 *
 * With:
 *     $db = db();
 *
 * Benefits:
 *   - One shared PDO instance per request (fewer connections)
 *   - Lazy-instantiated — no overhead until actually needed
 *   - Progressive migration: call sites can adopt over time
 *   - Exposes dbStats() for the /health endpoint (Phase 7)
 */

if (!function_exists('db')) {
    /**
     * Return the shared PDO connection. Lazily instantiated.
     * Throws RuntimeException on connection failure (same as Database class).
     */
    function db(): PDO {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        return Database::getStaticConnection();
    }
}

if (!function_exists('dbStats')) {
    /**
     * Minimal DB health info for /api/health.php — no PII.
     */
    function dbStats(): array {
        try {
            $pdo = db();
            $start = microtime(true);
            $stmt = $pdo->query('SELECT 1');
            $stmt->fetchColumn();
            $stmt->closeCursor();
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            return [
                'status'     => 'ok',
                'latency_ms' => $latencyMs,
                'driver'     => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
                'version'    => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            ];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
