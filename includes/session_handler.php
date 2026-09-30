<?php

declare(strict_types=1);

/**
 * PostgreSQL-backed session handler for Vercel deployment.
 *
 * On Render, filesystem sessions live on the persistent disk via
 * session_save_path(APP_ROOT/.sessions). On Vercel, the filesystem is
 * read-only and ephemeral, so sessions must be stored in PostgreSQL.
 *
 * This handler implements SessionHandlerInterface, preserving all
 * existing session behavior: same session cookie name, same cookie
 * params (HttpOnly, SameSite, Secure), session_regenerate_id on login,
 * CSRF tokens in $_SESSION['_csrf'], flash messages, and timeout via
 * $_SESSION['last_activity'].
 *
 * The handler is registered BEFORE session_start() in config/app.php.
 * On local dev (APP_ENV !== 'production'), it's skipped and the
 * filesystem handler is used — which is simpler and doesn't require
 * the app_sessions table.
 */

/**
 * Database-backed session handler using PostgreSQL.
 *
 * Table (created by database/schema.sql or a migration):
 *   CREATE TABLE trac_jhs_sarms.app_sessions (
 *       sess_id       VARCHAR(128) PRIMARY KEY,
 *       sess_data     BYTEA NOT NULL,
 *       sess_expire   TIMESTAMPTZ NOT NULL,
 *       sess_ip       INET,
 *       sess_ua       TEXT
 *   );
 *
 * The table is in the trac_jhs_sarms schema (same as all app tables).
 * The PDO connection has search_path set to trac_jhs_sarms, public,
 * so unqualified table names resolve correctly.
 */
class PgSessionHandler implements SessionHandlerInterface
{
    /** @var PDO */
    private PDO $db;

    /** @var int Session lifetime in seconds (from session.gc_maxlifetime) */
    private int $lifetime;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->lifetime = (int) ini_get('session.gc_maxlifetime');
        if ($this->lifetime <= 0) {
            $this->lifetime = SESSION_TIMEOUT;
        }
    }

    public function open(string $path, string $name): bool
    {
        // The PDO connection is already established by db(). No additional
        // connection needed. Return true so PHP considers the session store open.
        return true;
    }

    public function close(): bool
    {
        // PDO uses persistent connections via the singleton in db().
        // On Vercel, each function invocation gets its own PDO instance
        // and it's cleaned up when the function exits. Nothing to do here.
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT sess_data FROM app_sessions
                 WHERE sess_id = :id AND sess_expire > NOW()'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                return (string) $row['sess_data'];
            }
            return '';
        } catch (PDOException $e) {
            error_log('PgSessionHandler::read failed: ' . $e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        $expire = gmdate('Y-m-d H:i:s', time() + $this->lifetime);
        $ip = client_ip() ?? '';
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

        try {
            // Upsert: INSERT ... ON CONFLICT (sess_id) DO UPDATE
            // The sess_id is the PRIMARY KEY, so conflicts are deterministic.
            $stmt = $this->db->prepare(
                'INSERT INTO app_sessions (sess_id, sess_data, sess_expire, sess_ip, sess_ua)
                 VALUES (:id, :data, :expire, :ip, :ua)
                 ON CONFLICT (sess_id) DO UPDATE
                 SET sess_data = EXCLUDED.sess_data,
                     sess_expire = EXCLUDED.sess_expire,
                     sess_ip = EXCLUDED.sess_ip,
                     sess_ua = EXCLUDED.sess_ua'
            );
            $stmt->execute([
                'id' => $id,
                'data' => $data,
                'expire' => $expire,
                'ip' => $ip ?: null,
                'ua' => $ua !== '' ? $ua : null,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log('PgSessionHandler::write failed: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM app_sessions WHERE sess_id = :id');
            $stmt->execute(['id' => $id]);
            return true;
        } catch (PDOException $e) {
            error_log('PgSessionHandler::destroy failed: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $maxLifetime): int|false
    {
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM app_sessions WHERE sess_expire <= NOW()'
            );
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('PgSessionHandler::gc failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Validate that the sessions table exists. Called once during
     * bootstrap to avoid a confusing 'table not found' error on first
     * visit. Creates the table defensively if missing.
     */
    public static function ensureTable(PDO $pdo, string $schema): void
    {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS {$schema}.app_sessions (
                    sess_id       VARCHAR(128) PRIMARY KEY,
                    sess_data     BYTEA NOT NULL,
                    sess_expire   TIMESTAMPTZ NOT NULL,
                    sess_ip       INET,
                    sess_ua       TEXT
                )
            ");
        } catch (PDOException $e) {
            // Table creation might fail if schema doesn't exist yet.
            // The error will surface as a session write failure, which
            // is logged. Don't crash the bootstrap over this.
            error_log('PgSessionHandler::ensureTable failed: ' . $e->getMessage());
        }
    }
}

/**
 * Factory: returns a session handler appropriate for the current
 * environment. In production (Vercel), uses PostgreSQL. In local dev,
 * returns null (falls back to PHP's native filesystem handler).
 */
function session_handler(): ?SessionHandlerInterface
{
    $appEnv = getenv('APP_ENV') ?: '';

    // Production = always use DB-backed sessions
    // Local dev = filesystem sessions (simpler, no table required)
    if ($appEnv !== 'production') {
        return null;
    }

    // In production, we need the DB connection. But db() exits with HTML
    // on connection failure — we catch that risk by testing connec
    // before registering the handler.
    try {
        $pdo = db();
    } catch (Throwable) {
        // If DB is unavailable, fall back to filesystem sessions.
        // This is a graceful degradation — auth won't work anyway
        // if the DB is down, but the healthcheck and error pages
        // can still render.
        error_log('Session handler: DB unavailable, falling back to filesystem');
        return null;
    }

    $schema = DB_SCHEMA;
    PgSessionHandler::ensureTable($pdo, $schema);

    return new PgSessionHandler($pdo);
}
