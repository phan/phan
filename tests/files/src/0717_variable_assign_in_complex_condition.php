<?php
class Example {
    /** @return array */
    public function read($token)
    {
        if (!$token || !file_exists($file = 'somefile.txt')) {
            return [];
        }

        // Should infer the type of $file
        return $file;
    }
}
