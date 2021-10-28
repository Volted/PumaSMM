<?php

namespace PumaSMM;

class QueryBuilder {


    const CONDITION_MATCHING = 1;
    const CONDITION_FEATURING = 2;

    private static $SortingTypes = [
        Storage::SMALL_TO_LARGE => 'ASC',
        Storage::LARGE_TO_SMALL => 'DESC',
    ];

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
        'i' => [Storage::INTEGER, Storage::UNIQUE_INTEGER_MAIN_KEY, Storage::UNIQUE_INTEGER, Storage::BOOLEAN],
        'd' => [Storage::FLOAT],
        's' => [Storage::STRING],
        'b' => [Storage::BLOB],
    ];

    private $PrimaryKey;
    private $PrimaryTable;
    private $DataManifest;

    // columns relation
    private $ColumnsRelation = [];
    private $Indexes = [];

    // cache
    private $Cache = [
        'RequestedColumns' => NULL,
        'Condition'        => NULL,
        'Limit'            => NULL,
        'Sort'             => NULL,
        'ActiveTables'     => NULL,
        'ActiveColumns'    => NULL,
        'BoundSearchTerms' => NULL,
        'BoundingIndex'    => NULL,
    ];
    /**
     * @throws DataRawr
     */
    public function __construct($DataManifest) {
        $this->DataManifest = $DataManifest;
        $this->_setPrimaries();
        $this->_setColumnsRelations();
    }
    //=================================================
    //------------------- QUERIES ---------------------
    //=================================================
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
                $bound[] = $RequestedPrimaryForeign ? [$RequestedPrimaryForeign, 'i'] : [Storage::UNIQUE_INTEGER_MAIN_KEY, 'i'];
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
        $this->_clearCache();
        return $tableQueries;
    }

    /**
     * @throws DataRawr
     */
    public function getSelectFromQuery($propertiesList): array {
        $this->_buildSelectFrom($propertiesList);
        $this->_buildCondition();
        $result = [
            'Query' => implode(' ', ['SELECT', $this->Cache['RequestedColumns'], $this->Cache['Condition'], $this->Cache['Sort'], $this->Cache['Limit']]),
            'Bound' => $this->Cache['BoundSearchTerms'],
        ];
        $this->_clearCache();
        return $result;
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

    //=================================================
    //--------------- PUBLIC UTILITY ------------------
    //=================================================
    /**
     * @throws DataRawr
     */
    public function getIndexColumn($table) {
        if (isset($this->Indexes[$table])) {
            return $this->Indexes[$table];
        } else {
            throw new DataRawr("`$table` index was not found", DataRawr::INTERNAL_ERROR);
        }
    }

    /**
     * @throws DataRawr
     */
    public function getColumnTable($column) {
        if (isset($this->ColumnsRelation[$column])) {
            return $this->ColumnsRelation[$column];
        } else {
            throw new DataRawr("column `$column` was not found in manifest", DataRawr::INTERNAL_ERROR);
        }
    }

    /**
     * @return mixed
     */
    public function getPrimaryTable() {
        return $this->PrimaryTable;
    }

    /**
     * @throws DataRawr
     */
    public function setCondition(int $type, string $condition, $keyValuesArray): string {
        $this->Cache['BoundingIndex'] = ($this->Cache['BoundingIndex'] === NULL) ? 0 : $this->Cache['BoundingIndex'];
        $this->Cache['BoundingIndex']++;
        $conditionParts = [];
        foreach ($keyValuesArray as $column => $searchTerm) {
            if (is_numeric($column)) {
                $conditionParts[] = $searchTerm;
            } else {
                switch ($type) {
                    case self::CONDITION_MATCHING;
                        $operator = '=';
                        break;
                    case self::CONDITION_FEATURING;
                        $operator = ' LIKE ';
                        $searchTerm = "%$searchTerm%";
                        break;
                    default;
                        $operator = '=';
                }
                $conditionParts[] = $this->_registerColumn($column) . "$operator?".$this->Cache['BoundingIndex']."$column@";
                $this->Cache['BoundSearchTerms']['?' . $this->Cache['BoundingIndex'] . $column . '@'] = [$searchTerm, $this->_getPreparedDataType($column)];
            }
        }
        $result = "(" . implode(" $condition ", $conditionParts) . ")";
        $this->Cache['Condition'] = $result;
        return $result;
    }


    /**
     * @throws DataRawr
     */
    public function setLimit($page, $ofRecords) {
        if ($page > 0) {
            $offset = ($page - 1) * $ofRecords;
            $this->Cache['Limit'] = "LIMIT $offset,$ofRecords";
            return;
        }
        throw new DataRawr("page number must be greater then 0", DataRawr::INTERNAL_ERROR);
    }

    /**
     * @throws DataRawr
     */
    public function setSort($column, $method) {
        $table = $this->getColumnTable($column);
        $this->Cache['Sort'] = "ORDER BY `$table`.`$column` " . self::$SortingTypes[$method];
        $this->Cache['ActiveColumns']["`$table`.`$column`"] = true;
        $this->Cache['ActiveTables'][$table] = true;
    }

    //=================================================
    //--------------- PRIVATE UTILITY -----------------
    //=================================================

    /**
     * @throws DataRawr
     */
    private function _buildSelectFrom(array $propertiesList): void {
        foreach ($propertiesList as $column) {
            $this->_registerColumn($column);
        }
        $selectFrom = 'FROM';
        if (count($this->Cache['ActiveTables']) > 1) {
            $join = [];
            foreach ($this->Cache['ActiveTables'] as $activeTable => $true) {
                if ($activeTable == $this->PrimaryTable) continue;
                $join[] = " JOIN `$activeTable` ON `$activeTable`.`$this->PrimaryKey`=`$this->PrimaryTable`.`$this->PrimaryKey`";
                $this->Cache['ActiveColumns']["`$activeTable`.`$this->PrimaryKey` AS '{$activeTable}_$this->PrimaryKey'"] = true;
            }
            $selectFrom .= " `$this->PrimaryTable` " . implode(' ', $join);
        } else {
            $selectFrom .= ' `' . key($this->Cache['ActiveTables']) . '`';
        }
        $selectOf = implode(',', array_keys($this->Cache['ActiveColumns']));
        $this->Cache['RequestedColumns'] = $selectOf . ' ' . $selectFrom;
    }

    private function _buildCondition(): void {
        $Bound = [];
        $condition = $this->Cache['Condition'];
        foreach ($this->Cache['BoundSearchTerms'] as $term => $value) {
            $position = strpos($condition, $term);
            $Bound[$position] = $value;
            $this->Cache['Condition'] = str_replace($term, '?', $this->Cache['Condition']);
        }
        $this->Cache['Condition'] = 'WHERE ' . $this->Cache['Condition'];
        ksort($Bound);
        $this->Cache['BoundSearchTerms'] = $Bound;
    }

    /**
     * @throws DataRawr
     */
    private function _setPrimaries(): void {
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
    private function _registerColumn($columnName): string {
        $table = $this->getColumnTable($columnName);
        $result = "`$table`.`$columnName`";
        $this->Cache['ActiveColumns'][$result] = true;
        $this->Cache['ActiveTables'][$table] = true;
        $index = $this->getIndexColumn($table);
        if ($columnName != $index) {
            $this->_registerColumn($index);
        }
        return $result;
    }


    private function _getSQLDataType($type): string {
        return self::$DataTypes[$type] ?? '__UNKNOWN__';
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

    private function _clearCache() {
        foreach ($this->Cache as $item => $value) {
            $this->Cache[$item] = NULL;
        }
    }

    /**
     * @throws DataRawr
     */
    private function _setColumnsRelations() {
        foreach ($this->DataManifest as $table => $columnsData) {
            foreach ($columnsData as $column => $type) {
                if ($column == $this->PrimaryKey) {
                    if ($table == $this->PrimaryTable) {
                        $this->ColumnsRelation[$column] = $this->PrimaryTable;
                        $this->Indexes[$this->PrimaryTable] = $this->PrimaryKey;
                    } else {
                        $this->ColumnsRelation[$table . '_' . $column] = $table;
                    }
                } else {
                    if (isset($this->ColumnsRelation[$column])) {
                        throw new DataRawr("Conflicting column names found `$column`", DataRawr::INTERNAL_ERROR);
                    }
                    $this->ColumnsRelation[$column] = $table;
                    if ($type == Storage::UNIQUE_INTEGER) {
                        $this->Indexes[$table] = $column;
                    }
                }
            }
        }
    }


}