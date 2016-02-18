<?php

class C implements Serializable {
    public function serialize() : string {
        return 'string';
    }

    public function unserialize($serialized) {
    }
}
