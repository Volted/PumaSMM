<?php

namespace PumaSMM;

abstract class Storage implements StorageInterface {
    const UNIQUE_INTEGER = 'UNIQUE_INTEGER';
    const UNIQUE_INTEGER_MAIN_KEY = 'UNIQUE_INTEGER_MAIN_KEY';
    const INTEGER = 'INTEGER';
    const STRING = 'STRING';
    const BOOLEAN = 'BOOLEAN';
    const DATE = 'DATE';
    const DATE_TIME = 'DATE_TIME';
    const FLOAT = 'FLOAT';
    const BLOB = 'BLOB';

    // conditions
    const AND = 'AND';
    const OR = 'OR';

    // acceptable date format
    const FORMAT_DATETIME = 'Y-m-d H:i:s';
    const FORMAT_TIME = 'H:i:s';
    const FORMAT_DATE = 'Y-m-d';

    const SMALL_TO_LARGE = 1;
    const LARGE_TO_SMALL = 2;

    protected array $Config;

    protected static array $ExpectedConfigEntries = [];
    protected static string $ExpectedConfigSection;

    public function connect() {
    }

    public function closeConnection() {
    }

    /**
     * @throws DataRawr
     */
    public function setConfig($file): void {
        $ext = pathinfo($file)['extension'] ?? '';
        switch ($ext) {
            case 'ini';
                $Config = @parse_ini_file($file, true);
                if (!$Config) {
                    throw new DataRawr('Failed to parse config ini file', DataRawr::INTERNAL_ERROR);
                }
                break;
            case 'json';
                $contents = @file_get_contents($file);
                if (!$contents) {
                    throw new DataRawr("config file not found in $file", DataRawr::INTERNAL_ERROR);
                }
                $Config = json_decode($contents, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new DataRawr('failed to parse config JSON', DataRawr::INTERNAL_ERROR);
                }
                break;
            default;
                throw new DataRawr('Config file type not supported', DataRawr::INTERNAL_ERROR);
        }

        foreach (static::$ExpectedConfigEntries as $entry) {
            if (!isset($Config[static::$ExpectedConfigSection][$entry])) {
                throw new DataRawr("Entry '$entry' was not found in config file '" . static::$ExpectedConfigSection . "' section",
                    DataRawr::INTERNAL_ERROR);
            }
            $this->Config[$entry] = $Config[static::$ExpectedConfigSection][$entry];
        }
    }

}