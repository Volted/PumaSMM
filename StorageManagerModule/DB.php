<?php

namespace PumaSMM;

use mysqli;
use mysqli_stmt;

class DB extends Storage {

    protected static string $ExpectedConfigSection = 'database';
    protected static array $ExpectedConfigEntries = [
        'charset',
        'host',
        'db',
        'username',
        'password',
    ];

    protected string $Manifest;
    private QueryBuilder $QueryBuilder;
    private mysqli $MySQLi;
    private bool $EnableLogs = false;

    /**
     * @throws DataRawr
     */
    public function __construct($Manifest) {
        $this->Manifest = $Manifest;
        $this->QueryBuilder = new QueryBuilder($this->Manifest);
        return $this;
    }


    public function enableLogs(): void {
        $this->EnableLogs = true;
    }

    /**
     * @throws DataRawr
     */
    public function connect(): void {
        list($charset, $host, $db, $username, $password) = array_values($this->Config);
        mysqli_report(MYSQLI_REPORT_OFF);
        $this->MySQLi = new mysqli($host, $username, $password, $db);
        if ($this->MySQLi->connect_errno) {
            throw new DataRawr('mysqli connection error: ' . $this->MySQLi->connect_error, DataRawr::INTERNAL_ERROR);
        }
        $this->MySQLi->set_charset($charset);
        if ($this->MySQLi->errno) {
            throw new DataRawr('mysqli error: ' . $this->MySQLi->error);
        }
    }

    public function closeConnection(): void {
        $this->MySQLi->close();
    }

    /**
     * @throws DataRawr
     */
    public function read(array $propertiesList, $byNameValueList): array {
        $query = $this->QueryBuilder->getSelectFromQuery($propertiesList);
        $result = ($this->_prepareAndExecute($query['Query'], $query['Bound']))->get_result();
        $record = 0;
        if ($result->num_rows > 0) {
            $resultSet = [];
            while ($row = $result->fetch_assoc()) {
                foreach ($row as $column => $value) {
                    if(in_array($column,$propertiesList)){
                        $resultSet[$record][$column] = $value;
                    }
                }
                $record++;
            }
            return $resultSet;
        }
        return [];
    }

    /**
     * @throws DataRawr
     */
    public function create(array $nameValueArray): array {
        $resultTablesInsertedIds = [];
        $queries = $this->QueryBuilder->getInsertIntoQueries($this->Config['db'], $nameValueArray);
        $uniqueKey = false;
        foreach ($queries as $table => $queryData) {
            $result = $this->_prepareAndExecute($queryData['Query'], $queryData['Bound'], $uniqueKey);
            if ($this->QueryBuilder->getPrimaryTable() == $table) {
                $uniqueKey = $result->insert_id;
            }
            $resultTablesInsertedIds[$table] = $result->insert_id;
        }
        return $resultTablesInsertedIds;
    }

    /**
     * @throws DataRawr
     */
    public function update(array $nameValueArray, array $byNameValueList): array {
        $resultTablesAffectedRows = [];
        $queries = $this->QueryBuilder->getUpdateQueries($nameValueArray);
        foreach ($queries as $table => $queryData) {
            $result = $this->_prepareAndExecute($queryData['Query'], $queryData['Bound']);
            $resultTablesAffectedRows[$table] = $result->affected_rows;
        }
        return $resultTablesAffectedRows;
    }

    /**
     * @throws DataRawr
     */
    public function delete(array $nameValueArray): array {
        $resultTablesAffectedRows = [];
        $queries = $this->QueryBuilder->getDeleteQueries($nameValueArray);
        foreach ($queries as $table => $queryData) {
            $result = $this->_prepareAndExecute($queryData['Query'], $queryData['Bound']);
            $resultTablesAffectedRows[$table] = $result->affected_rows;
        }
        return $resultTablesAffectedRows;
    }

    //==========================
    //------- Conditions -------
    //==========================
    /**
     * @throws DataRawr
     */
    public function matching(string $condition, $keyValuesArray): string {
        return $this->QueryBuilder->setCondition(QueryBuilder::CONDITION_MATCHING, $condition, $keyValuesArray);
    }

    /**
     * @throws DataRawr
     */
    public function featuring(string $condition, $keyValuesArray): string {
        return $this->QueryBuilder->setCondition(QueryBuilder::CONDITION_FEATURING, $condition, $keyValuesArray);
    }
    /**
     * @throws DataRawr
     */
    public function startsWith(string $condition, $keyValuesArray): string {
        return $this->QueryBuilder->setCondition(QueryBuilder::CONDITION_STARTS_WITH, $condition, $keyValuesArray);
    }
    /**
     * @throws DataRawr
     */
    public function endsWith(string $condition, $keyValuesArray): string {
        return $this->QueryBuilder->setCondition(QueryBuilder::CONDITION_ENDS_WITH, $condition, $keyValuesArray);
    }

    /**
     * @throws DataRawr
     */
    public function sort($column, $method): DB {
        $this->QueryBuilder->setSort($column, $method);
        return $this;
    }

    /**
     * @throws DataRawr
     */
    public function limit(int $page, int $ofRecords): DB {
        $this->QueryBuilder->setLimit($page, $ofRecords);
        return $this;
    }

    //==========================
    //-------- Utility ---------
    //==========================
    /**
     * @throws DataRawr
     */
    private function _prepareAndExecute($query, $binding, $uniqueKey = false): false|mysqli_stmt {
        if ($this->EnableLogs) {
            error_log(print_r([
                'Query'   => $query,
                'Binding' => $binding,
            ], true));
        }
        $types = [];
        $params = [];
        foreach ($binding as $bound) {
            $types[] = $bound[1];
            if ($bound[0] === Storage::UNIQUE_INTEGER_MAIN_KEY) {
                if ($uniqueKey) {
                    $params[] = $uniqueKey;
                } else {
                    throw new DataRawr('Unique foreign key requested but is not set', DataRawr::INTERNAL_ERROR);
                }
            } else {
                $params[] = $bound[0];
            }
        }
        $typesString = implode('', $types);
        $preparedQuery = $this->MySQLi->prepare($query);
        $preparedQuery->bind_param($typesString, ...$params);
        if ($preparedQuery->execute()) {
            return $preparedQuery;
        } else {
            throw new DataRawr('failed to execute query "' . $query . '"', DataRawr::INTERNAL_ERROR);
        }
    }

    /**
     * @throws DataRawr
     */
    public function createTablesFromManifest(): void {
        $query = $this->QueryBuilder->getCreateTablesFromManifestQuery($this->Config['db']);
        $this->MySQLi->multi_query($query);
        if ($this->MySQLi->errno) {
            throw new DataRawr('mysqli error: ' . $this->MySQLi->error);
        }
    }

}