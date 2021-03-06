<?php /** @noinspection PhpUnused */

namespace PumaSMM;

use mysqli;

class DB extends Storage {

    protected static $ExpectedConfigSection = 'database';
    protected static $ExpectedConfigEntries = [
        'charset',
        'host',
        'db',
        'username',
        'password',
    ];

    protected $Manifest;

    /** @var $QueryBuilder QueryBuilder */
    private $QueryBuilder;

    /** @var $MySQLi mysqli */
    private $MySQLi;
    private $EnableLogs = false;

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
    public function connect() {
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

    public function closeConnection() {
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

    public function update(array $nameValueArray, array $byNameValueList): array {
        // NANODO: Implement updatePropertiesByProperties() method.
        return [];
    }

    public function delete(array $nameValueArray): array {
        // NANODO: Implement deletePropertiesByProperties() method.
        return [];
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

    public function startsWith(string $condition, $keyValuesArray) {
        // NANODO: Implement startsWith() method.
    }

    public function endsWith(string $condition, $keyValuesArray) {
        // NANODO: Implement endsWith() method.
    }

    //==========================
    //-------- Sorting ---------
    //==========================
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
    private function _prepareAndExecute($query, $binding, $uniqueKey = false) {
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
    public function createTablesFromManifest() {
        $query = $this->QueryBuilder->getCreateTablesFromManifestQuery($this->Config['db']);
        $this->MySQLi->multi_query($query);
        if ($this->MySQLi->errno) {
            throw new DataRawr('mysqli error: ' . $this->MySQLi->error);
        }
    }

}