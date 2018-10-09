<?php
class Test {
    /** @var ClientOfPropertyTypeCheck58 */
    public $client;

    function fn(ClientOfPropertyTypeCheck58 $client) {
        $this->client = $client;
        $this->client->test();
    }
}
