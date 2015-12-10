<?php
class Test {
    /** @var Client */
    public $client;

    function fn(Client $client) {
        $this->client = $client;
        $this->client->test();
    }
}
