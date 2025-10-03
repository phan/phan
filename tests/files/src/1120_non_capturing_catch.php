<?php

class Test48 {
    public function connect(string $t, string $u): void{
        try{
            mysqli_connect();
        } catch (Exception){
            self::save($t);
        }
    }

    private function save(string $t): void{
        echo $t;
    }
}
