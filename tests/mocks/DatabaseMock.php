<?php
/**
 * Database Mock for Testing
 *
 * Provides an in-memory database mock for testing database operations
 * without requiring a real database connection.
 */

class DatabaseMock
{
    /** @var array In-memory data storage */
    private $tables = [];

    /** @var int Auto-increment counter */
    private $autoIncrement = 1;

    /** @var array Query log for debugging */
    private $queryLog = [];

    /**
     * Constructor - Initialize mock database
     */
    public function __construct()
    {
        $this->initializeTables();
    }

    /**
     * Initialize database tables with schema
     */
    private function initializeTables(): void
    {
        $this->tables = [
            'reviewer_certificates' => [],
            'reviewer_certificate_templates' => [],
            'reviewer_certificate_settings' => [],
            'plugin_settings' => [],
        ];
    }

    /**
     * Execute a SELECT query
     *
     * @param string $table
     * @param array $conditions
     * @param array $fields
     * @return array
     */
    public function select(string $table, array $conditions = [], array $fields = ['*']): array
    {
        $this->logQuery('SELECT', $table, $conditions);

        if (!isset($this->tables[$table])) {
            return [];
        }

        $results = $this->tables[$table];

        // Apply conditions
        if (!empty($conditions)) {
            $results = array_filter($results, function ($row) use ($conditions) {
                foreach ($conditions as $key => $value) {
                    if (!isset($row[$key]) || $row[$key] != $value) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Select specific fields
        if ($fields !== ['*']) {
            $results = array_map(function ($row) use ($fields) {
                return array_intersect_key($row, array_flip($fields));
            }, $results);
        }

        return array_values($results);
    }

    /**
     * Execute an INSERT query
     *
     * @param string $table
     * @param array $data
     * @return int Insert ID
     */
    public function insert(string $table, array $data): int
    {
        $this->logQuery('INSERT', $table, $data);

        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }

        // Auto-generate ID if not provided
        if (!isset($data['id']) && !isset($data[$table . '_id'])) {
            $idField = $table . '_id';
            if ($table === 'reviewer_certificates') {
                $idField = 'certificate_id';
            } elseif ($table === 'reviewer_certificate_templates') {
                $idField = 'template_id';
            }
            $data[$idField] = $this->autoIncrement++;
        }

        $this->tables[$table][] = $data;

        // Return the ID
        $idField = $table . '_id';
        if ($table === 'reviewer_certificates') {
            $idField = 'certificate_id';
        } elseif ($table === 'reviewer_certificate_templates') {
            $idField = 'template_id';
        }

        return $data[$idField] ?? $this->autoIncrement - 1;
    }

    /**
     * Execute an UPDATE query
     *
     * @param string $table
     * @param array $data
     * @param array $conditions
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, array $conditions): int
    {
        $this->logQuery('UPDATE', $table, ['data' => $data, 'conditions' => $conditions]);

        if (!isset($this->tables[$table])) {
            return 0;
        }

        $affectedRows = 0;

        foreach ($this->tables[$table] as &$row) {
            $matches = true;
            foreach ($conditions as $key => $value) {
                if (!isset($row[$key]) || $row[$key] != $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $row = array_merge($row, $data);
                $affectedRows++;
            }
        }

        return $affectedRows;
    }

    /**
     * Execute a DELETE query
     *
     * @param string $table
     * @param array $conditions
     * @return int Number of deleted rows
     */
    public function delete(string $table, array $conditions): int
    {
        $this->logQuery('DELETE', $table, $conditions);

        if (!isset($this->tables[$table])) {
            return 0;
        }

        $originalCount = count($this->tables[$table]);

        $this->tables[$table] = array_filter($this->tables[$table], function ($row) use ($conditions) {
            foreach ($conditions as $key => $value) {
                if (!isset($row[$key]) || $row[$key] != $value) {
                    return true; // Keep row
                }
            }
            return false; // Delete row
        });

        $this->tables[$table] = array_values($this->tables[$table]);

        return $originalCount - count($this->tables[$table]);
    }

    /**
     * Get a single row by ID
     *
     * @param string $table
     * @param int $id
     * @param string $idField
     * @return array|null
     */
    public function getById(string $table, int $id, ?string $idField = null): ?array
    {
        if ($idField === null) {
            if ($table === 'reviewer_certificates') {
                $idField = 'certificate_id';
            } elseif ($table === 'reviewer_certificate_templates') {
                $idField = 'template_id';
            } else {
                $idField = $table . '_id';
            }
        }

        $results = $this->select($table, [$idField => $id]);
        return $results[0] ?? null;
    }

    /**
     * Count rows in a table
     *
     * @param string $table
     * @param array $conditions
     * @return int
     */
    public function count(string $table, array $conditions = []): int
    {
        return count($this->select($table, $conditions));
    }

    /**
     * Clear all data from a table
     *
     * @param string $table
     */
    public function truncate(string $table): void
    {
        if (isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }
    }

    /**
     * Clear all data from all tables
     */
    public function reset(): void
    {
        $this->initializeTables();
        $this->autoIncrement = 1;
        $this->queryLog = [];
    }

    /**
     * Log a query for debugging
     *
     * @param string $type
     * @param string $table
     * @param array $data
     */
    private function logQuery(string $type, string $table, array $data): void
    {
        $this->queryLog[] = [
            'type' => $type,
            'table' => $table,
            'data' => $data,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * Get query log
     *
     * @return array
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Get all data from a table (for debugging)
     *
     * @param string $table
     * @return array
     */
    public function getTableData(string $table): array
    {
        return $this->tables[$table] ?? [];
    }

    /**
     * Create a mock database result object
     *
     * @param array $rows
     * @return DatabaseMockResult
     */
    public function createResult(array $rows): DatabaseMockResult
    {
        return new DatabaseMockResult($rows);
    }
}

/**
 * Mock database result class
 */
class DatabaseMockResult
{
    private $rows;
    private $position = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function current()
    {
        return $this->rows[$this->position] ?? null;
    }

    public function next()
    {
        $this->position++;
    }

    public function valid()
    {
        return isset($this->rows[$this->position]);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function count()
    {
        return count($this->rows);
    }

    public function toArray()
    {
        return $this->rows;
    }
}
