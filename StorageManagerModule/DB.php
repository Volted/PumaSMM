<?php

namespace PumaSMM;

use mysqli;

class DB extends Storage {


    const CONFIG_INI_FILE = 'CONFIG_INI_FILE';
    const CONFIG_JSON_FILE = 'CONFIG_JSON_FILE';

    private static $Sorting = [
        self::SMALL_TO_LARGE => 'ASC',
        self::LARGE_TO_SMALL => 'DESC',
    ];

    private $Config;
    private static $ExpectedConfigEntries = [
        'charset',
        'host',
        'db',
        'username',
        'password',
    ];
    private static $ExpectedConfigSection = 'database';


    private $Manifest;

    /** @var $QueryBuilder QueryBuilder */
    private $QueryBuilder;

    /** @var $MySQLi mysqli */
    private $MySQLi;

    /**
     * @throws DataRawr
     */
    public function __construct($Manifest) {
        $this->Manifest = $Manifest;
        $this->QueryBuilder = new QueryBuilder($this->Manifest);
        return $this;
    }

    /**
     * @throws DataRawr
     */
    public function setConfigFromFile($path, $configFileType = self::CONFIG_INI_FILE) {
        switch ($configFileType) {
            case self::CONFIG_INI_FILE;
                $Config = @parse_ini_file($path, true);
                if (!$Config) {
                    throw new DataRawr('Failed to parse config ini file', DataRawr::INTERNAL_ERROR);
                }
                break;
            case self::CONFIG_JSON_FILE;
                $file = @file_get_contents($path);
                if (!$file) {
                    throw new DataRawr("config file not found in $path", DataRawr::INTERNAL_ERROR);
                }
                $Config = json_decode($file, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new DataRawr('failed to parse config JSON', DataRawr::INTERNAL_ERROR);
                }
                break;
            default;
                throw new DataRawr('Must specify config file type', DataRawr::INTERNAL_ERROR);
        }

        foreach (self::$ExpectedConfigEntries as $entry) {
            if (!isset($Config[self::$ExpectedConfigSection][$entry])) {
                throw new DataRawr("Entry '$entry' was not found in config file '" . self::$ExpectedConfigSection . "' section",
                    DataRawr::INTERNAL_ERROR);
            }
            $this->Config[$entry] = $Config[self::$ExpectedConfigSection][$entry];
        }
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
    public function read(array $propertiesList, $byNameValueList, $sortBy = false, int $sortingMethod = Storage::SMALL_TO_LARGE): array {
        $this->QueryBuilder->setMethod(QueryBuilder::SELECT);
        $this->QueryBuilder->buildSelectFrom($propertiesList);
        $this->QueryBuilder->buildCondition();
        $query = $this->QueryBuilder->getQuery();
        error_log(print_r([
            'query' => $query,
            'bound' => $this->QueryBuilder->getBound(),
        ], true));
        return [];
    }

    /**
     * @throws DataRawr
     */
    public function create(array $nameValueArray): array {
        $this->QueryBuilder->setMethod(QueryBuilder::INSERT);
        $queries = $this->QueryBuilder->getInsertIntoQueries($this->Config['db'], $nameValueArray);

        $uniqueKey = false;
        foreach ($queries as $table => $queryData) {


        }


        error_log(print_r($queries, true));

        // NANODO: Implement createPropertiesByProperties() method.
        return [];
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
    public function byExactMatch(array $keyValuesArray): string {
        return $this->QueryBuilder->fetchDataMatchingExactly($keyValuesArray);
    }

    public function bySimilar(array $keyValuesArray): void {
        // NANODO: Implement similar() method.

    }

    public function byEither(array $keyValuesArray): void {
        // NANODO: Implement either() method.

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