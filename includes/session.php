<?php
/**
 * Database-backed PHP sessions.
 *
 * Why: on Vercel every request may run on a different short-lived serverless
 * instance with its own /tmp, so file-based sessions randomly "log you out".
 * Storing sessions in MySQL makes login work everywhere (Vercel, XAMPP, any host).
 */

class DbSessionHandler implements SessionHandlerInterface
{
    private mysqli $db;
    private int $ttl;

    public function __construct(mysqli $db, int $ttl = 60 * 60 * 24 * 14)
    {
        $this->db = $db;
        $this->ttl = $ttl;
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        try {
            $stmt = $this->db->prepare('SELECT data FROM php_sessions WHERE id = ? AND updated_at > ?');
        } catch (mysqli_sql_exception $e) {      // table missing → create it on the fly
            $this->db->query('CREATE TABLE IF NOT EXISTS php_sessions (id varchar(128) NOT NULL, data mediumblob NOT NULL, updated_at int(11) NOT NULL, PRIMARY KEY (id), KEY updated_at (updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $stmt = $this->db->prepare('SELECT data FROM php_sessions WHERE id = ? AND updated_at > ?');
        }
        $min = time() - $this->ttl;
        $stmt->bind_param('si', $id, $min);
        $stmt->execute();
        $stmt->bind_result($data);
        $found = $stmt->fetch();
        $stmt->close();
        return $found ? (string) $data : '';
    }

    public function write(string $id, string $data): bool
    {
        $now = time();
        $stmt = $this->db->prepare('REPLACE INTO php_sessions (id, data, updated_at) VALUES (?, ?, ?)');
        $stmt->bind_param('ssi', $id, $data, $now);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM php_sessions WHERE id = ?');
        $stmt->bind_param('s', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function gc(int $max_lifetime): int|false
    {
        $min = time() - $this->ttl;
        $stmt = $this->db->prepare('DELETE FROM php_sessions WHERE updated_at < ?');
        $stmt->bind_param('i', $min);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return $n;
    }
}

function app_session_start(mysqli $conn): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('PCSESSID');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 14,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');

    try {
        session_set_save_handler(new DbSessionHandler($conn), true);
    } catch (Throwable $e) {
        error_log('Session handler failed, falling back to files: ' . $e->getMessage());
    }
    session_start();
}
