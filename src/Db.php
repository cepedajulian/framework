<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

class Db
{
    protected PDO $db;

    protected int $lastInsertId = 0;

    public function __construct()
    {
        $this->db();
    }

    /** Open the connection. Constants are retained for existing applications. */
    public function db(): void
    {
        $this->db = new PDO(
            'mysql:host=' . DatabaseServer . ';dbname=' . Database . ';charset=utf8mb4',
            DatabaseUser,
            DatabasePassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = 'TRADITIONAL'",
            ]
        );
    }

    /** Identifiers cannot be bound as query parameters; validate them explicitly. */
    private static function identifier(string $name): string
    {
        $parts = explode('.', $name);
        foreach ($parts as $part) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $part)) {
                throw new InvalidArgumentException('Invalid SQL identifier.');
            }
        }

        return '`' . implode('`.`', $parts) . '`';
    }

    /** @return object|false */
    public function find(string $table, $parameter1, $parameter2 = null)
    {
        $column = $parameter2 === null ? 'id' : (string) $parameter1;
        $parameter = $parameter2 === null ? $parameter1 : $parameter2;
        $statement = $this->db->prepare(
            'SELECT * FROM ' . self::identifier($table) .
            ' WHERE ' . self::identifier($column) . ' = :parameter LIMIT 1'
        );
        $statement->execute(['parameter' => $parameter]);

        try {
            return $statement->fetch();
        } finally {
            $statement->closeCursor();
        }
    }

    /**
     * SELECT/queries with result sets return rows; writes return affected row count.
     * Transactions are explicit rather than being started for every query.
     *
     * @param mixed $parameters
     * @return array<int, object>|int
     */
    public function query(string $strinsql, mixed $parameters = null): array|int
    {
        $statement = $this->db->prepare($strinsql);
        $statement->execute((array) $parameters);

        try {
            if (preg_match('/^\s*INSERT\b/i', $strinsql)) {
                $this->lastInsertId = (int) $this->db->lastInsertId();
            }

            return $statement->columnCount() > 0
                ? $statement->fetchAll()
                : $statement->rowCount();
        } finally {
            $statement->closeCursor();
        }
    }

    public function lastInsertId(): int
    {
        return $this->lastInsertId;
    }

    /** Run several related statements atomically when needed. */
    public function transaction(callable $operation): mixed
    {
        if ($this->db->inTransaction()) {
            throw new PDOException('Nested transactions are not supported.');
        }

        $this->db->beginTransaction();

        try {
            $result = $operation($this);
            $this->db->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
