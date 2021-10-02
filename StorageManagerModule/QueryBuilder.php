<?php

namespace PumaSMM;

class QueryBuilder {

    const UPDATE = 'UPDATE';
    const SELECT = 'SELECT';
    const INSERT = 'INSERT';
    const DELETE = 'DELETE';

    private static $DataTypes = [
        Storage::STRING                  => "VARCHAR(255) NOT NULL DEFAULT ''",
        Storage::INTEGER                 => "INT NOT NULL DEFAULT '0'",
        Storage::UNIQUE_INTEGER          => "INT NOT NULL AUTO_INCREMENT",
        Storage::UNIQUE_INTEGER_MAIN_KEY => "INT NOT NULL AUTO_INCREMENT",
        Storage::BOOLEAN                 => "TINYINT NOT NULL DEFAULT '0'",
        Storage::DATE                    => "DATE NOT NULL DEFAULT '1979-04-20'",
        Storage::DATE_TIME               => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
        Storage::FLOAT                   => "DECIMAL(19,2) NOT NULL DEFAULT '0.00'",
        Storage::BLOB                    => "BLOB NOT NULL DEFAULT ''",
    ];

    private static $PreparedDataTypes = [
        'i' => [Storage::INTEGER, Storage::UNIQUE_INTEGER_MAIN_KEY, Storage::UNIQUE_INTEGER],
        'd' => [Storage::FLOAT],
        's' => [Storage::STRING],
        'b' => [Storage::BLOB],
    ];

    private $Method;
    private $RequestedColumns;
    private $Condition;
    private $LimitOffset;
    private $Sorting;

    private $PrimaryKey;
    private $PrimaryTable;
    private $DataManifest;


    private $ActiveTables;
    private $ActiveColumns;
    private $BoundSearchTerms;

    /**
     * @throws DataRawr
     */
    public function __construct($DataManifest) {
        $this->DataManifest = $DataManifest;
        $this->_setPrimaryKey();
    }

    public function setMethod($Method) {
        $this->Method = $Method;
    }


    /**
     * @throws DataRawr
     */
    public function getInsertIntoQueries($db, $nameValueArray): array {
        $RequestedTables = [];
        $RequestedPrimaryForeign = false;
        foreach ($this->DataManifest as $table => $columns) {
            foreach ($nameValueArray as $columnName => $value) {
                if ($columnName == $this->PrimaryKey) {
                    $RequestedPrimaryForeign = $value;
                    continue;
                }
                if (isset($columns[$columnName])) {
                    $RequestedTables[$table][$columnName] = $value;
                }
            }
        }
        if ($RequestedPrimaryForeign and isset($RequestedTables[$this->PrimaryTable])) {
            throw new DataRawr("Cannot insert into primary table and request different key for related tables", DataRawr::INTERNAL_ERROR);
        }
        $tableQueries = [];
        foreach ($RequestedTables as $activeTable => $columns) {
            $bound = [];
            $columnsList = [];
            $valuesList = [];

            if (!isset($columns[$this->PrimaryKey]) and $activeTable != $this->PrimaryTable) {
                $columnsList[] = "`$this->PrimaryKey`";
                $valuesList[] = '?';
                $bound[] = $RequestedPrimaryForeign ? [$RequestedPrimaryForeign, 'i'] : Storage::UNIQUE_INTEGER_MAIN_KEY;
            }

            foreach ($columns as $column => $value) {
                $bound[] = [$value, $this->_getPreparedDataType($column)];
                $columnsList[] = "`$column`";
                $valuesList[] = '?';
            }
            $columnsList = implode(',', $columnsList);
            $valuesList = implode(',', $valuesList);
            $tableQueries[$activeTable]['Query'] = "INSERT INTO `$db`.`$activeTable` ($columnsList) VALUES ($valuesList)";
            $tableQueries[$activeTable]['Bound'] = $bound;
        }
        return $tableQueries;
    }

    /**
     * @throws DataRawr
     */
    public function buildSelectFrom(array $propertiesList): void {
        foreach ($propertiesList as $column) {
            $this->_specifyColumn($column);
        }
        $selectFrom = 'FROM';
        if (count($this->ActiveTables) > 1) {
            unset($this->ActiveTables[$this->PrimaryTable]);
            $join = [];
            $this->ActiveColumns[" `$this->PrimaryTable`.`$this->PrimaryKey`"] = true;
            foreach ($this->ActiveTables as $activeTable => $true) {
                $join[] = " JOIN `$activeTable` ON `$activeTable`.`$this->PrimaryKey`=`$this->PrimaryTable`.`$this->PrimaryKey`";
                $this->ActiveColumns[$this->_getTablePrimaryKey($activeTable)] = true;
            }
            $selectFrom .= ' `' . $this->PrimaryTable . '` ' . implode(' ', $join);
        } else {
            $selectFrom .= ' `' . key($this->ActiveTables) . '`';
        }
        $selectOf = implode(',', array_keys($this->ActiveColumns));
        $this->RequestedColumns = $selectOf . ' ' . $selectFrom;
    }

    public function buildCondition(): void {
        $Bound = [];
        $condition = $this->Condition;
        foreach ($this->BoundSearchTerms as $term => $value) {
            $position = strpos($condition, $term);
            $Bound[$position] = $value;
            $this->Condition = str_replace($term, '?', $this->Condition);
        }
        $this->Condition = 'WHERE ' . $this->Condition;
        ksort($Bound);
        $this->BoundSearchTerms = $Bound;
    }

    public function getQuery(): string {
        return implode(' ', [$this->Method, $this->RequestedColumns, $this->Condition, $this->LimitOffset, $this->Sorting]);
    }

    public function getBound(): array {
        return $this->BoundSearchTerms;
    }

    /**
     * @throws DataRawr
     */
    private function _setPrimaryKey(): void {
        foreach ($this->DataManifest as $table => $columns) {
            foreach ($columns as $column => $type) {
                if ($type == Storage::UNIQUE_INTEGER_MAIN_KEY) {
                    $this->PrimaryKey = $column;
                    $this->PrimaryTable = $table;
                    return;
                }
            }
        }
        throw new DataRawr('Manifest Does not specify primary key', DataRawr::INTERNAL_ERROR);
    }

    /**
     * @throws DataRawr
     */
    public function fetchDataMatchingExactly(array $keyValuesArray): string {
        $conditionParts = [];
        foreach ($keyValuesArray as $column => $searchTerm) {
            if (is_numeric($column)) {
                $conditionParts[] = $searchTerm;
            } else {
                $conditionParts[] = $this->_specifyColumn($column) . "=?$column@";
                $this->BoundSearchTerms['?' . $column . '@'] = [$searchTerm, $this->_getPreparedDataType($column)];
            }
        }
        $result = "(" . implode(" AND ", $conditionParts) . ")";
        $this->Condition = $result;
        return $result;
    }

    /**
     * @throws DataRawr
     */
    private function _getTablePrimaryKey($table): string {
        $key = array_search(Storage::UNIQUE_INTEGER, $this->DataManifest[$table]);
        if (!$key) {
            throw new DataRawr("`$table` does not have a unique key", DataRawr::INTERNAL_ERROR);
        }
        return "`$table`.`$key`";

    }

    /**
     * @throws DataRawr
     */
    private function _specifyColumn($columnName): string {
        $columnsFound = [];
        foreach ($this->DataManifest as $table => $columns) {
            if (isset($this->DataManifest[$table][$columnName])) {
                $columnsFound[] = "`$table`.`$columnName`";
                $this->ActiveColumns["`$table`.`$columnName`"] = true;
                $this->ActiveTables[$table] = true;
            }
        }
        if (count($columnsFound) > 1) {
            throw new DataRawr("column found in " . count($columnsFound) . " places " . implode(',', $columnsFound), DataRawr::INTERNAL_ERROR);
        } else if (empty($columnsFound)) {
            throw new DataRawr("column `$columnName` not found", DataRawr::INTERNAL_ERROR);
        }
        return current($columnsFound);
    }


    private function _getSQLDataType($type): string {
        return self::$DataTypes[$type] ?? '__UNKNOWN__';
    }

    /**
     * @throws DataRawr
     */
    public function getCreateTablesFromManifestQuery($db): string {
        $tablesQuery = [];
        foreach ($this->DataManifest as $table => $columns) {
            $columnsQuery = [];
            $primaryKey = '';
            foreach ($columns as $column => $type) {
                $columnsQuery[] = "`$column` " . $this->_getSQLDataType($type);
                if ($type == Storage::UNIQUE_INTEGER or $type == Storage::UNIQUE_INTEGER_MAIN_KEY) {
                    $primaryKey = "PRIMARY KEY (`$column`)";
                }
            }
            if (!$primaryKey) {
                throw new DataRawr("primary key not specified for table `$table`", DataRawr::INTERNAL_ERROR);
            }
            $columnsQuery[] = $primaryKey;
            $clm = implode(' , ', $columnsQuery);
            $tablesQuery[] = "CREATE TABLE `$db`.`$table` ($clm) ENGINE = InnoDB";
        }
        return implode("; \n", $tablesQuery);
    }

    private function _getPreparedDataType($column): string {
        foreach ($this->DataManifest as $columns) {
            if (isset($columns[$column])) {
                foreach (self::$PreparedDataTypes as $typeName => $types) {
                    if (in_array($columns[$column], $types)) {
                        return $typeName;
                    }
                }
            }
        }
        return 's';
    }


}