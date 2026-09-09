<?php

class Database
{
    private PDO $pdo;
    public string $driver;
    private array $aliases = [];
    public string $lastSqlError = '';

    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    public function aliases(): array
    {
        return $this->aliases;
    }

    public function table(string $logical): string
    {
        return $this->aliases[$logical] ?? $logical;
    }

    public function __construct(array $cfg)
    {
        $this->driver = $cfg['driver'] ?? 'sqlite';
        if ($this->driver === 'mysql') {
            $attempts = [];
            foreach (array_unique(array_filter([
                $cfg['host'] ?? 'localhost',
                'localhost',
                '127.0.0.1',
            ])) as $host) {
                $attempts[] = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $host,
                    $cfg['port'] ?? 3306,
                    $cfg['name'],
                    $cfg['charset'] ?? 'utf8mb4'
                );
            }
            foreach (['/tmp/mysql.sock', '/var/lib/mysql/mysql.sock', '/var/run/mysqld/mysqld.sock'] as $sock) {
                $attempts[] = sprintf(
                    'mysql:unix_socket=%s;dbname=%s;charset=%s',
                    $sock,
                    $cfg['name'],
                    $cfg['charset'] ?? 'utf8mb4'
                );
            }
            $last = null;
            foreach ($attempts as $dsn) {
                try {
                    $this->pdo = new PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_TIMEOUT => 8,
                    ]);
                    $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $last = null;
                    break;
                } catch (Throwable $e) {
                    $last = $e;
                }
            }
            if ($last) {
                $onLive = (bool) preg_match('/youpendimmoselect\.com$/i', $_SERVER['HTTP_HOST'] ?? '');
                if (!empty($cfg['fallback_sqlite']) && !$onLive) {
                    $this->driver = 'sqlite';
                    $this->openSqlite($cfg);
                    return;
                }
                throw $last;
            }
        } else {
            $this->openSqlite($cfg);
        }
    }

    private function openSqlite(array $cfg): void
    {
        $path = $cfg['sqlite_path'] ?? dirname(__DIR__) . '/storage/youpendi.sqlite';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function q(string $sql, array $params = []): PDOStatement
    {
        $sql = $this->rewriteTables($this->adapt($sql));
        $st = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $key = is_int($k) ? $k + 1 : $k;
            $type = is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $st->bindValue($key, $v, $type);
        }
        $st->execute();
        return $st;
    }

    public function all(string $sql, array $params = []): array
    {
        try {
            return $this->q($sql, $params)->fetchAll();
        } catch (Throwable $e) {
            $this->sqlFail($e, $sql);
            return [];
        }
    }

    public function one(string $sql, array $params = []): ?array
    {
        try {
            $row = $this->q($sql, $params)->fetch();
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            $this->sqlFail($e, $sql);
            return null;
        }
    }

    public function val(string $sql, array $params = [])
    {
        try {
            $v = $this->q($sql, $params)->fetchColumn();
            return $v === false ? null : $v;
        } catch (Throwable $e) {
            $this->sqlFail($e, $sql);
            return null;
        }
    }

    public function exec(string $sql, array $params = []): int
    {
        try {
            return $this->q($sql, $params)->rowCount();
        } catch (Throwable $e) {
            $this->sqlFail($e, $sql);
            return 0;
        }
    }

    private function sqlFail(Throwable $e, string $sql): void
    {
        $this->lastSqlError = $e->getMessage();
        @file_put_contents(
            dirname(__DIR__) . '/storage/sql-errors.log',
            date('c') . ' ' . $e->getMessage() . ' | ' . substr($sql, 0, 200) . "\n",
            FILE_APPEND
        );
    }

    public function insert(string $table, array $data): int
    {
        try {
            $cols = array_keys($data);
            $ph = implode(', ', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO ' . $this->table($table) . ' (' . implode(', ', $cols) . ') VALUES (' . $ph . ')';
            $this->q($sql, array_values($data));
            return (int) $this->pdo->lastInsertId();
        } catch (Throwable $e) {
            $this->sqlFail($e, 'INSERT ' . $table);
            throw $e;
        }
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        $table = $this->table($table);
        return $this->exec("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $params));
    }

    public function lastId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** Adapt MySQL-ish SQL for SQLite when needed */
    private function adapt(string $sql): string
    {
        if ($this->driver === 'mysql') {
            $sql = str_replace('ON CONFLICT(k) DO UPDATE SET v = excluded.v, updated_at = excluded.updated_at',
                'ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = VALUES(updated_at)', $sql);
        }
        return $sql;
    }

    private function rewriteTables(string $sql): string
    {
        if (!$this->aliases) {
            return $sql;
        }
        $keys = array_keys($this->aliases);
        usort($keys, fn($a, $b) => strlen($b) <=> strlen($a));
        foreach ($keys as $logical) {
            $phys = $this->aliases[$logical];
            if ($phys === $logical) {
                continue;
            }
            $sql = preg_replace('/\b' . preg_quote($logical, '/') . '\b/', $phys, $sql) ?? $sql;
        }
        return $sql;
    }
}

function db(): Database
{
    static $db;
    if (!empty($GLOBALS['__db_override']) && $GLOBALS['__db_override'] instanceof Database) {
        return $GLOBALS['__db_override'];
    }
    if (!$db) {
        $db = new Database(config('db'));
    }
    return $db;
}
