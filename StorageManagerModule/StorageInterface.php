<?php

namespace PumaSMM;

interface StorageInterface {

    /**
     * @param array $propertiesList
     * @param       $byNameValueList
     * @return array empty array on nothing found
     */
    public function read(array $propertiesList, $byNameValueList): array;

    /**
     * @param array $nameValueArray
     * @return array returns array of [table => index] of unique ids created for each category
     * returns empty on failure
     */
    public function create(array $nameValueArray): array;

    /**
     * @param array $nameValueArray
     * @param array $byNameValueList
     * @return array returns array of unique Ids in updated categories
     * returns empty on nothing updated
     */
    public function update(array $nameValueArray, array $byNameValueList): array;

    /**
     * @param array $nameValueArray
     */
    public function delete(array $nameValueArray): array;


    //===================================
    //----------- Conditions ------------
    //===================================
    /**
     * @param array $keyValuesArray
     */
    public function matching(array $keyValuesArray);

    /**
     * @param array $keyValuesArray
     */
    public function featuring(array $keyValuesArray);

    /**
     * @param array $keyValuesArray
     */
    public function startsWith(array $keyValuesArray);

    /**
     * @param array $keyValuesArray
     */
    public function endsWith(array $keyValuesArray);

    /**
     * @param array $keyValuesArray
     */
    public function either(array $keyValuesArray);


    //===================================
    //------------- Sorting -------------
    //===================================
    public function sort($column, $method);

    public function limit(int $page, int $ofRecords);

}