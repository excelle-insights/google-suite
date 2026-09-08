<?php

declare(strict_types=1);

namespace ExcelleInsights\GoogleSuite\Repositories;

use PDO;

/**
 * Base repository providing shared PDO persistence operations.
 * Each concrete repository maps to a single database table.
 */
abstract class AbstractRepository
{
    abstract protected function table(): string;

    abstract protected function primaryKey(): string;

    /**
     * Columns that may be set via insert()/update() (excludes the PK).
     *
     * @return array<int, string>
     */
    abstract protected function fillable(): array;

    public function __construct(
        protected readonly PDO $pdo,
    ) {}

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function find(string|int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            sprintf('SELECT * FROM %s WHERE %s = ?', $this->quotedTable(), $this->primaryKey())
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findOneBy(array $conditions, string $orderBy = ''): ?array
    {
        [$where, $params] = $this->buildWhere($conditions);
        $sql = sprintf('SELECT * FROM %s WHERE %s', $this->quotedTable(), $where);
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findBy(array $conditions, string $orderBy = '', int $limit = 100, int $offset = 0): array
    {
        [$where, $params] = $this->buildWhere($conditions);
        $sql = sprintf('SELECT * FROM %s WHERE %s', $this->quotedTable(), $where);
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function all(string $orderBy = '', int $limit = 500, int $offset = 0): array
    {
        $sql = sprintf('SELECT * FROM %s', $this->quotedTable());
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $data): string|int
    {
        $columns = $this->filterFillable($data);
        if ($columns === []) {
            return '0';
        }

        $names  = array_keys($columns);
        $table  = $this->quotedTable();
        $sql    = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            '`' . implode('`, `', $names) . '`',
            rtrim(str_repeat('?, ', count($names)), ', ')
        );

        $this->pdo->prepare($sql)->execute(array_values($columns));
        return $this->pdo->lastInsertId();
    }

    public function update(string|int $id, array $data): int
    {
        $columns = $this->filterFillable($data);
        if ($columns === []) {
            return 0;
        }

        $sets = [];
        foreach (array_keys($columns) as $name) {
            $sets[] = "`$name` = ?";
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->quotedTable(),
            implode(', ', $sets),
            $this->primaryKey()
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([...array_values($columns), $id]);
        return $stmt->rowCount();
    }

    public function delete(string|int $id): int
    {
        $stmt = $this->pdo->prepare(
            sprintf('DELETE FROM %s WHERE %s = ?', $this->quotedTable(), $this->primaryKey())
        );
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Insert or update on a set of conflict keys.
     */
    public function upsert(array $data, array $conflictColumns): void
    {
        $columns = $this->filterFillable($data);
        $names   = array_keys($columns);
        $quoted  = '`' . implode('`, `', $names) . '`';

        $updates = [];
        foreach ($names as $name) {
            if (in_array($name, $conflictColumns, true)) {
                continue;
            }
            $updates[] = "`$name` = VALUES(`$name`)";
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)
             ON DUPLICATE KEY UPDATE %s, `updated_at` = NOW()',
            $this->quotedTable(),
            $quoted,
            rtrim(str_repeat('?, ', count($names)), ', '),
            $updates === [] ? '`updated_at` = NOW()' : implode(', ', $updates)
        );

        $this->pdo->prepare($sql)->execute(array_values($columns));
    }

    public function count(array $conditions = []): int
    {
        if ($conditions === []) {
            return (int) $this->pdo->query(
                sprintf('SELECT COUNT(*) FROM %s', $this->quotedTable())
            )->fetchColumn();
        }

        [$where, $params] = $this->buildWhere($conditions);
        $stmt = $this->pdo->prepare(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s', $this->quotedTable(), $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mark a row as synced, stamping the sync time and clearing the error.
     */
    public function markSynced(string|int $id, ?string $googleId = null): int
    {
        $sets      = ['sync_status = ?', 'error_message = NULL', 'synced_at = NOW()'];
        $params    = ['synced'];
        if ($googleId !== null) {
            $sets[]    = 'google_id = ?';
            $params[]  = $googleId;
        }
        $params[]  = $id;

        $stmt = $this->pdo->prepare(
            sprintf(
                'UPDATE %s SET %s WHERE %s = ?',
                $this->quotedTable(),
                implode(', ', $sets),
                $this->primaryKey()
            )
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Mark a row as failed, incrementing the retry counter.
     */
    public function markFailed(string|int $id, string $error): int
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'UPDATE %s
                 SET sync_status = ?,
                     error_message = ?,
                     retry_count = retry_count + 1,
                     last_attempt_at = NOW()
                 WHERE %s = ?',
                $this->quotedTable(),
                $this->primaryKey()
            )
        );
        $stmt->execute(['failed', $error, $id]);
        return $stmt->rowCount();
    }

    /**
     * Rows awaiting a retry sync (pending or failed and not exhausted).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPending(int $maxRetries = 5, int $userId = 1): array
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM %s
                 WHERE user_id = ?
                   AND sync_status IN (?, ?)
                   AND retry_count < ?
                 ORDER BY COALESCE(last_attempt_at, created_at) ASC
                 LIMIT 100',
                $this->quotedTable()
            )
        );
        $stmt->execute([$userId, 'pending', 'failed', $maxRetries]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function quotedTable(): string
    {
        return '`' . $this->table() . '`';
    }

    /**
     * @return array<string, mixed> Only the fillable columns present in $data.
     */
    protected function filterFillable(array $data): array
    {
        $fillable = array_flip($this->fillable());
        $filtered = [];
        foreach ($data as $key => $value) {
            if (isset($fillable[$key])) {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }

    /**
     * @return array{0:string, 1:array<int, mixed>} SQL WHERE fragment + params.
     */
    protected function buildWhere(array $conditions): array
    {
        $parts  = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $placeholders = [];
                foreach ($value as $item) {
                    $placeholders[] = '?';
                    $params[] = $item;
                }
                $parts[] = "`$column` IN (" . implode(', ', $placeholders) . ')';
            } else {
                $parts[]  = "`$column` = ?";
                $params[] = $value;
            }
        }
        return [implode(' AND ', $parts), $params];
    }
}