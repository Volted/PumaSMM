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

    public function connect() {

    }

    public function closeConnection() {

    }

}