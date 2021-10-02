<?php

namespace PumaSMM;

use Exception;

class DataRawr extends Exception {

    const INTERNAL_ERROR = 500;
    const METHOD_NOT_ALLOWED = 405;
    const NOT_FOUND = 404;
    const FORBIDDEN = 403;
    const UNAUTHORIZED = 401;
    const BAD_REQUEST = 400;


    private static $Manifest = [
        self::INTERNAL_ERROR     => ['error' => 'server error'],
        self::METHOD_NOT_ALLOWED => ['error' => 'method not allowed'],
        self::NOT_FOUND          => ['error' => 'not found'],
        self::FORBIDDEN          => ['error' => 'access denied'],
        self::UNAUTHORIZED       => ['error' => 'access denied'],
        self::BAD_REQUEST        => ['error' => 'bad request'],
    ];


    public function handleException() {
        $this->_logError();
        $this->_sendResponse();
        exit();
    }

    private function _logError() {
        $errorData['ErrorCode'] = $this->getCode();
        $errorData['Message'] = $this->getMessage();
        $errorData['InFile'] = $this->getFile();
        $errorData['Trace'] = [];
        $backtrace = $this->getTrace();
        foreach ($backtrace as $id => $data) {
            $Class = $data['class'] ?? 'Unknown';
            $Method = $data['function'] ?? 'Unknown';
            $Line = $data['line'] ?? 'Unknown';
            $errorData['Trace'][$id] = basename($Class) . '::' . $Method . '() [ line:' . $Line . ']';
        }
        error_log(print_r($errorData, true));
    }

    private function _sendResponse() {
        http_response_code($this->getCode());
        header("Content-Type:application/json");
        exit(json_encode(self::$Manifest[$this->getCode()] ?? []));
    }
}