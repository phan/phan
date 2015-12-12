<?php
class ArrayProducer {
        /**
         * @return array[] array of arrays
         */
        public function getArrays()
        {
            return [['name' => 'lala'], ['name' => 'lulu']];
        }
}

$producer = new ArrayProducer;
$arrays = $producer->getArrays();
$test = $arrays[0]['name'];
