<?php

namespace PumaSMM;

interface StorageInterface {

    /**
     * @param array $propertiesList
     * @param  $byNameValueList
     * @param       $sortBy bool|array
     * @param int   $sortingMethod
     * @return array empty array on nothing found
     *
     */
    public function read(array $propertiesList, $byNameValueList, $sortBy = false, int $sortingMethod = Storage::SMALL_TO_LARGE): array;

    /**
     * @param array $nameValueArray
     * @return array returns array of [name => value] of unique ids created for each category
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

    public function byExactMatch(array $keyValuesArray);

    public function bySimilar(array $keyValuesArray): void;

    /**
     * @param array $keyValuesArray
     */
    public function byEither(array $keyValuesArray): void;


}