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

    const SMALL_TO_LARGE = 1;
    const LARGE_TO_SMALL = 2;

    const CONFIG_INI_FILE = 'CONFIG_INI_FILE';
    const CONFIG_JSON_FILE = 'CONFIG_JSON_FILE';

    protected $Config;

    protected static $ExpectedConfigEntries = [];
    protected static $ExpectedConfigSection;

    public function connect() {}

    public function closeConnection() {}

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

        foreach (static::$ExpectedConfigEntries as $entry) {
            if (!isset($Config[static::$ExpectedConfigSection][$entry])) {
                throw new DataRawr("Entry '$entry' was not found in config file '" . static::$ExpectedConfigSection . "' section",
                    DataRawr::INTERNAL_ERROR);
            }
            $this->Config[$entry] = $Config[static::$ExpectedConfigSection][$entry];
        }
    }

}