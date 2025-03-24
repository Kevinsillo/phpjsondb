<?php

declare(strict_types=1);

namespace Kevinsillo\PhpJsonDB;

use Exception;
use InvalidArgumentException;

/**
 * Class to manage databases in JSON files.
 * Allows creating, reading, updating, and deleting records in tables stored as JSON files.
 * 
 * @author Kevin Illanas <kevin.illanas@gmail.com>
 * @version 1.0.0
 * 
 * Headers created by:
 * @link https:#blocks.jkniest.dev/
 */
class PhpJsonDB
{
    private string $directory;
    private string $currentTable;
    private array $selectedFields = [];
    private array $records = [];
    private const CONTROL_FILE_SUFFIX = ".control.json";
    public const OPERATOR_EQUAL = '==';
    public const OPERATOR_NOT_EQUAL = '!=';
    public const OPERATOR_GREATER_EQUAL = '>=';
    public const OPERATOR_LESS_EQUAL = '<=';
    public const OPERATOR_LIKE = 'LIKE';
    public const OPERATOR_IN = 'IN';

    /**
     * Constructor.
     *
     * @param string $directory Base directory where the data will be stored.
     * @throws Exception If the directory cannot be created.
     */
    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, "/");

        if (!file_exists($this->directory)) {
            if (!mkdir($this->directory, 0755, true)) {
                throw new Exception("Could not create the base directory: {$this->directory}");
            }
        }
    }

    /**
     * ============================================================
     * ====              METHODS TO MANAGE TABLES              ====
     * ============================================================
     */

    /**
     * Gets the complete path of the current table.
     *
     * @return string
     */
    private function getTablePath(): string
    {
        return $this->directory . "/" . $this->currentTable;
    }

    /**
     * Gets a list of all tables.
     *
     * @return array
     */
    public function getTables(): array
    {
        $dir = $this->directory;
        $tables = array_filter(scandir($dir), function ($item) use ($dir) {
            return is_dir($dir . '/' . $item) && !in_array($item, ['.', '..']);
        });
        return array_values($tables);
    }

    /**
     * Gets detailed information of all tables.
     *
     * @return array
     */
    public function getTablesInfo(): array
    {
        $tables = $this->getTables();
        $tablesInfo = [];
        foreach ($tables as $tableName) {
            $this->selectTable($tableName);
            $tablesInfo[$tableName] = $this->readControlFile();
        }
        return $tablesInfo;
    }

    /**
     * Return the number of all records in the table.
     *
     * @return int
     */
    public function tableRecords(): int
    {
        $control = $this->readControlFile();
        return $control['records_count'] ?? 0;
    }

    /**
     * Checks if a table (directory) exists.
     *
     * @param string $tableName
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        return file_exists($this->directory . "/" . $tableName);
    }

    /**
     * Selects a table (directory) to work with.
     *
     * @param string $tableName Name of the table.
     * @return self
     * @throws Exception If the table does not exist.
     */
    public function selectTable(string $tableName): self
    {
        $this->currentTable = $tableName;
        $tableDir = $this->getTablePath();

        if (!file_exists($tableDir)) {
            throw new Exception("Could not create table directory: {$tableDir}");
        }

        return $this;
    }

    /**
     * Creates a new table (directory).
     *
     * @param string $tableName Name of the table.
     * @param array $structure Table structure (key => data type).
     * @return bool
     * @throws Exception If the table already exists.
     */
    public function createTable(string $tableName, array $structure): bool
    {
        $tableDir = $this->directory . "/" . $tableName;

        if (file_exists($tableDir)) {
            throw new Exception("Table already exists: {$tableName}");
        }

        if (!mkdir($tableDir, 0755, true)) {
            throw new Exception("Could not create the table directory: {$tableDir}");
        }

        $this->selectTable($tableName);
        $this->updateControlFile([
            'auto_increment' => 1,
            'records_count' => 0,
            'structure' => $structure,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Truncates the table, removing all records but keeping the table and its control file.
     *
     * @return bool
     */
    public function truncate(): bool
    {
        $tableDir = $this->getTablePath();
        $files = glob($tableDir . "/*.json");

        foreach ($files as $file) {
            unlink($file);
        }

        $this->resetRecordsCount();
        $this->resetAutoIncrement();

        return true;
    }

    /**
     * Deletes a table (directory) and all its records.
     *
     * @param string $tableName Name of the table.
     * @return bool
     */
    public function dropTable(string $tableName): bool
    {
        $tableDir = $this->directory . "/" . $tableName;

        if (!file_exists($tableDir)) {
            return false;
        }

        $files = glob($tableDir . "/*.json");

        foreach ($files as $file) {
            if (!unlink($file)) {
                return false;
            }
        }
        if (!rmdir($tableDir)) {
            return false;
        }

        return true;
    }

    /**
     * ============================================================
     * ====             METHODS TO MANAGE RECORDS              ====
     * ============================================================
     */

    /**
     * Gets the complete path of a record.
     *
     * @param string $id Record ID.
     * @return string
     */
    private function getRecordPath(string $id): string
    {
        return $this->getTablePath() . "/" . $id . ".json";
    }

    /**
     * Selects specific fields to be returned in the query result.
     *
     * @param array $fields Fields to select
     * @return self
     * @throws Exception If a field does not exist in the table structure
     */
    public function select(array $fields): self
    {
        // Validate that the selected fields exist in the table structure
        $control = $this->readControlFile();
        $structure = $control['structure'] ?? [];

        foreach ($fields as $field) {
            if ($fields === ['*']) {
                return $this;
            }
            if (!array_key_exists($field, $structure) && $field !== 'id') {
                throw new Exception("Field '{$field}' does not exist in the table structure");
            }
        }

        $this->selectedFields = $fields;
        return $this;
    }

    /**
     * Applies search criteria with type-safe operators
     *
     * @param array $criteria Search criteria
     * @return self
     * @throws \InvalidArgumentException
     */
    public function where(array $criteria = []): self
    {
        $control = $this->readControlFile();
        $records = $this->getAllRegistries();
        $filteredRecords = [];
        foreach ($records as $id => $record) {
            $match = true;

            foreach ($criteria as $criterion) {
                # Validate criterion array
                if (!is_array($criterion)) {
                    throw new InvalidArgumentException("Each criterion must be an array");
                }

                # Validate criterion format
                if (count($criterion) !== 3) {
                    throw new InvalidArgumentException(
                        "Each criterion must be an array with 3 elements: [field, operator, value]"
                    );
                }

                # Extract criterion elements
                [$field, $operator, $value] = $criterion;

                # Validate field exists in the table structure
                if (!array_key_exists($field, $control['structure']) && $field !== 'id') {
                    throw new InvalidArgumentException("Field '{$field}' does not exist in the table structure");
                }

                # Validate operator
                if (!in_array($operator, self::getAllowedOperators())) {
                    throw new InvalidArgumentException(
                        "Unsupported operator: {$operator}. Allowed operators: " .
                            implode(', ', self::getAllowedOperators())
                    );
                }

                # Check if the field exists in the record
                if (!isset($record[$field])) {
                    $match = false;
                    break;
                }

                $recordValue = $record[$field];

                # Perform comparison based on the operator
                switch ($operator) {
                    case self::OPERATOR_EQUAL:
                        $match = $recordValue == $value;
                        break;
                    case self::OPERATOR_NOT_EQUAL:
                        $match = $recordValue != $value;
                        break;
                    case self::OPERATOR_GREATER_EQUAL:
                        $match = $recordValue >= $value;
                        break;
                    case self::OPERATOR_LESS_EQUAL:
                        $match = $recordValue <= $value;
                        break;
                    case self::OPERATOR_LIKE:
                        if (!is_string($recordValue)) {
                            $match = false;
                            break;
                        }
                        $match = stripos($recordValue, $value) !== false;
                        break;
                    case self::OPERATOR_IN:
                        $match = is_array($value) && in_array($recordValue, $value);
                        break;
                }

                # If any criterion doesn't match, break the loop
                if (!$match) {
                    break;
                }
            }

            # Add record to filtered results if all criteria match
            if ($match) {
                $filteredRecords[$id] = $record;
            }
        }

        $this->records = array_values($filteredRecords);
        return $this;
    }

    /**
     * Finds a record by its ID.
     *
     * @param string $id Record ID.
     * @return array|null
     */
    public function findById(string $id): ?array
    {
        $filePath = $this->getRecordPath($id);

        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $record = json_decode($content, true);
            if (isset($record['id']) && $record['id'] !== $id) {
                throw new Exception("Record ID in data does not match requested ID.");
            }
            return $record;
        }

        return null;
    }

    /**
     * Inserts a new record using an auto-incremental ID.
     *
     * @param array $data Data to save.
     * @return string|false The generated ID or false on failure.
     * @throws Exception
     */
    public function insertAuto(array $data): string|false
    {
        $id = $this->getNextAutoIncrement();
        $stringId = (string) $id;

        if ($this->insert($stringId, $data)) {
            return $stringId;
        }

        return false;
    }

    /**
     * Inserts a new record.
     *
     * @param string $id Record ID.
     * @param array $data Data to save.
     * @return bool
     * @throws Exception
     */
    public function insert(string $id, array $data): bool|Exception
    {
        $this->validateStructureTable($data, true);
        $filePath = $this->getRecordPath($id);

        if (file_exists($filePath)) {
            return false;
        }

        $data['id'] = $id; # Store the ID with the main data.
        $data['_metadata'] = [
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $result = file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT)) !== false;

        if ($result) {
            $this->incrementRecordsCount();
        }

        return $result;
    }

    /**
     * Updates records selected.
     *
     * @param array $data Data to update.
     * @return bool
     */
    public function update(array $data): bool
    {

        $result = false;
        foreach ($this->records as $record) {
            $result = $this->updateById($record['id'], $data);
        }

        return $result;
    }

    /**
     * Updates an existing record.
     *
     * @param string $id Record ID.
     * @param array $data Data to update.
     * @return bool
     * @throws Exception If the record does not exist or the data structure is invalid.
     */
    public function updateById(string $id, array $data): bool|Exception
    {
        $filePath = $this->getRecordPath($id);

        if (!file_exists($filePath)) {
            throw new Exception("Record not found");
        }

        $existingData = $this->findById($id);

        $this->validateStructureTable($data);

        $data['id'] = $id;
        $data['_metadata'] = $existingData['_metadata'] ?? [];
        $data['_metadata']['updated_at'] = date('Y-m-d H:i:s');

        $updatedData = array_merge($existingData, $data);

        return file_put_contents($filePath, json_encode($updatedData, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Deletes records selected.
     *
     * @return bool
     */
    public function delete(): bool
    {
        $result = false;
        foreach ($this->records as $record) {
            $result = $this->deleteById($record['id']);
        }

        return $result;
    }

    /**
     * Deletes a record.
     *
     * @param string $id Record ID.
     * @return bool
     */
    public function deleteById(string $id): bool
    {
        $filePath = $this->getRecordPath($id);

        if (file_exists($filePath)) {
            $result = unlink($filePath);

            if ($result) {
                $this->decrementRecordsCount();
            }

            return $result;
        }

        return false;
    }

    /**
     * Groups records by a field and applies aggregation functions.
     * @param string $field 
     * @param bool $count 
     * @param array $aggregate 
     * @return array 
     */
    public function groupBy(string $field, bool $count = false, array $aggregate = []): array
    {
        $result = [];

        # If there are no records, return an empty result
        if (empty($this->records)) {
            return $result;
        }

        # Iterate over each record
        foreach ($this->records as $record) {
            if (!isset($record[$field])) {
                continue;
            }

            $key = (string)$record[$field];

            if (!isset($result[$key])) {
                $result[$key] = [
                    'key' => $record[$field],
                    'records' => []
                ];

                if ($count) {
                    $result[$key]['count'] = 0;
                }

                # Initialize accumulators for aggregation functions
                foreach ($aggregate as $aggField => $function) {
                    $result[$key]["_{$function}_{$aggField}"] = [
                        'sum' => 0,
                        'count' => 0,
                        'min' => null,
                        'max' => null
                    ];
                }
            }

            # Add record to the group
            $result[$key]['records'][] = $record;

            # Increment count if needed
            if ($count) {
                $result[$key]['count']++;
            }

            # Update accumulators for aggregation functions
            foreach ($aggregate as $aggField => $function) {
                if (isset($record[$aggField]) && is_numeric($record[$aggField])) {
                    $value = $record[$aggField];
                    $accumulator = &$result[$key]["_{$function}_{$aggField}"];

                    # Sum/Count
                    $accumulator['sum'] += $value;
                    $accumulator['count']++;

                    # Min/Max
                    if ($accumulator['min'] === null || $value < $accumulator['min']) {
                        $accumulator['min'] = $value;
                    }
                    if ($accumulator['max'] === null || $value > $accumulator['max']) {
                        $accumulator['max'] = $value;
                    }
                }
            }
        }

        # Calculate aggregated values
        foreach ($result as &$group) {
            foreach ($aggregate as $aggField => $function) {
                $accumulator = $group["_{$function}_{$aggField}"];

                switch ($function) {
                    case 'sum':
                        $group["sum_{$aggField}"] = $accumulator['sum'];
                        break;
                    case 'avg':
                        $group["avg_{$aggField}"] = $accumulator['count'] > 0 ?
                            $accumulator['sum'] / $accumulator['count'] : 0;
                        break;
                    case 'min':
                        $group["min_{$aggField}"] = $accumulator['min'];
                        break;
                    case 'max':
                        $group["max_{$aggField}"] = $accumulator['max'];
                        break;
                }

                # Remove accumulator
                unset($group["_{$function}_{$aggField}"]);
            }
        }

        return array_values($result);
    }

    /**
     * Applies pagination to the query.
     *
     * @param int $limit Maximum number of records per page.
     * @param int $offset Number of records to skip (offset).
     * @return self
     */
    public function limit(int $limit, int $offset = 0): self
    {
        if ($offset !== null || $limit !== null) {
            $this->records = array_slice($this->records, $offset, $limit);
        }
        return $this;
    }

    /**
     * Applies ordering to the result set.
     *
     * @param array $fields Array of field names to order by.  Use 'field_name' for ASC, and 'field_name DESC' for DESC
     * @return self
     */
    public function orderBy(array $fields): self
    {
        if (!empty($fields)) {
            usort($this->records, function ($a, $b) use ($fields) {
                foreach ($fields as $order) {
                    $direction = 'ASC';
                    $field = trim($order);
                    if (stripos($field, ' DESC') !== false) {
                        $direction = 'DESC';
                        $field = str_ireplace(' DESC', '', $field);
                    }

                    if (!isset($a[$field]) || !isset($b[$field])) {
                        continue;
                    }

                    $valueA = $a[$field];
                    $valueB = $b[$field];

                    if (is_numeric($valueA) && is_numeric($valueB)) {
                        $comparison = $valueA <=> $valueB;
                    } else {
                        $comparison = strcmp((string) $valueA, (string) $valueB);
                    }

                    if ($comparison != 0) {
                        return ($direction == 'ASC') ? $comparison : -$comparison;
                    }
                }
                return 0;
            });
        }
        return $this;
    }

    /**
     * Gets the records in the current result set.
     *
     * @return array
     */
    public function getRecords(): array
    {
        $records = $this->records;

        # Apply field selection if fields were specified
        if (!empty($this->selectedFields)) {
            $filteredRecords = [];
            foreach ($records as $record) {
                $filteredRecord = ['id' => $record['id']];
                foreach ($this->selectedFields as $field) {
                    if (isset($record[$field])) {
                        $filteredRecord[$field] = $record[$field];
                    }
                }
                $filteredRecords[] = $filteredRecord;
            }
            $records = $filteredRecords;
        }

        $this->cleanProperties();
        return $records;
    }

    /**
     * Gets the count of records in the current result set.
     * 
     * @return int 
     */
    public function countRecords(): int
    {
        $count = count($this->records);
        $this->cleanProperties();
        return $count;
    }

    /**
     * Checks if a record with the given ID exists.
     *
     * @param string $id Record ID
     * @return bool
     */
    public function existsById(string $id): bool
    {
        $filePath = $this->getRecordPath($id);
        return file_exists($filePath);
    }

    /**
     * Checks if any records exist in the current result set.
     *
     * @return bool
     */
    public function hasRecords(): bool
    {
        $hasRecords = !empty($this->records);
        $this->cleanProperties();
        return $hasRecords;
    }

    /**
     * Retrieves all records from the table.
     *
     * @return self
     */
    private function getAllRegistries(): array
    {
        $tableDir = $this->getTablePath();
        $files = glob($tableDir . "/*.json");

        $records = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $records[$content['id']] = json_decode($content, true);
        }

        return $records;
    }

    /**
     * Cleans the records array.
     *
     * @return void
     */
    private function cleanProperties(): void
    {
        $this->selectedFields = [];
        $this->records = [];
    }

    /**
     * ============================================================
     * ====          METHODS TO MANAGE CONTROL FILES           ====
     * ============================================================
     */

    /**
     * Gets the path to the table's control file.
     *
     * @return string
     */
    private function getControlFilePath(): string
    {
        return $this->directory . "/" . $this->currentTable . self::CONTROL_FILE_SUFFIX;
    }

    /**
     * Reads the table's control file.
     *
     * @return array
     */
    private function readControlFile(): array
    {
        $controlFilePath = $this->getControlFilePath();
        if (!file_exists($controlFilePath)) {
            return [
                'auto_increment' => 1,
                'records_count' => 0,
                'structure' => [],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        $content = file_get_contents($controlFilePath);
        return json_decode($content, true);
    }

    /**
     * Writes to the table's control file.
     *
     * @param array $data
     * @return void
     */
    private function writeControlFile(array $data): void
    {
        $controlFilePath = $this->getControlFilePath();
        file_put_contents($controlFilePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Updates the table's control information.
     *
     * @param array $data Data to update.
     * @return void
     */
    private function updateControlFile(array $data): void
    {
        $controlData = $this->readControlFile();
        $mergedData = array_merge($controlData, $data);
        $this->writeControlFile($mergedData);
    }

    /**
     * Gets the next auto-incremental value and increments it.
     *
     * @return int
     */
    private function getNextAutoIncrement(): int
    {
        $control = $this->readControlFile();
        $nextId = $control['auto_increment'] ?? 1;

        $this->updateControlFile([
            'auto_increment' => $nextId + 1,
        ]);

        return $nextId;
    }

    /**
     * Resets the auto-increment counter.
     *
     * @return void
     */
    private function resetAutoIncrement(): void
    {
        $this->updateControlFile([
            'auto_increment' => 1,
        ]);
    }

    /**
     * Increments the record count in the table.
     *
     * @return void
     */
    private function incrementRecordsCount(): void
    {
        $control = $this->readControlFile();
        $count = $control['records_count'] ?? 0;

        $this->updateControlFile([
            'records_count' => $count + 1,
        ]);
    }

    /**
     * Decrements the record count in the table.
     *
     * @return void
     */
    private function decrementRecordsCount(): void
    {
        $control = $this->readControlFile();
        $count = max(0, ($control['records_count'] ?? 0) - 1);

        $this->updateControlFile([
            'records_count' => $count,
        ]);
    }

    /**
     * Resets the record counter.
     *
     * @return void
     */
    private function resetRecordsCount(): void
    {
        $this->updateControlFile([
            'records_count' => 0,
        ]);
    }

    /**
     * Validates the data to be saved or updated.
     *
     * @param array $data
     * @param bool $isInsert Indicates whether it is an insert (true) or update (false) operation.
     * @return bool
     * @throws Exception
     */
    private function validateStructureTable(array $data, bool $isInsert = false): bool
    {
        $control = $this->readControlFile();
        $structure = $control['structure'] ?? [];

        if ($isInsert) {
            foreach ($structure as $key => $expectedType) {
                if (!array_key_exists($key, $data)) {
                    throw new Exception("Missing required property: '{$key}'");
                }
            }
        }

        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $structure)) {
                throw new Exception("'{$key}' does not exist in the table");
            }

            $expectedTypes = explode('|', $structure[$key]);

            $isValidType = false;
            foreach ($expectedTypes as $expectedType) {
                if (gettype($value) === $expectedType) {
                    $isValidType = true;
                    break;
                }
            }

            if (!$isValidType) {
                throw new Exception(
                    "Invalid data type for '{$key}'. Expected one of: '" . implode("', '", $expectedTypes) . "' and received '" . gettype($value) . "'"
                );
            }
        }

        return true;
    }

    /**
     * ============================================================
     * ====       METHODS TO EXPORT AND IMPORT DATABASES       ====
     * ============================================================
     */

    /**
     * Exports the entire database to a JSON file.
     *
     * @param string $filePath Output file path.
     * @return bool
     */
    public function exportDatabase(string $filePath): bool
    {
        $tables = $this->getTables();
        $data = ['tables' => []];

        foreach ($tables as $tableName) {
            $this->selectTable($tableName);
            $data['tables'][$tableName] = [
                'control' => $this->readControlFile(),
                'records' => $this->getAllRegistries(),
            ];
        }
        $data['base_directory'] = $this->directory;

        return file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Imports a database from a JSON file.
     *
     * @param string $filePath Input file path.
     * @return bool
     * @throws Exception
     */
    public function importDatabase(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        if (isset($data['base_directory'])) {
            $this->directory = $data['base_directory'];
        }

        if (isset($data['tables']) && is_array($data['tables'])) {
            foreach ($data['tables'] as $tableName => $tableData) {
                # Check if control data exists
                if (isset($tableData['control'])) {
                    $this->createTable($tableName, $tableData['control']['structure'] ?? []);
                    $this->selectTable($tableName);
                    $this->writeControlFile($tableData['control']);
                } else {
                    $this->createTable($tableName, []);
                    $this->selectTable($tableName);
                }


                if (isset($tableData['records']) && is_array($tableData['records'])) {
                    foreach ($tableData['records'] as $recordId => $recordData) {
                        $this->insert($recordId, $recordData);
                    }
                }
            }
        }
        return true;
    }

    /**
     * ============================================================
     * ====                   OTHER METHODS                    ====
     * ============================================================
     */

    /**
     * Validates and returns allowed operators
     * 
     * @return array
     */
    private function getAllowedOperators(): array
    {
        return [
            self::OPERATOR_EQUAL,
            self::OPERATOR_NOT_EQUAL,
            self::OPERATOR_GREATER_EQUAL,
            self::OPERATOR_LESS_EQUAL,
            self::OPERATOR_LIKE,
            self::OPERATOR_IN
        ];
    }
}
