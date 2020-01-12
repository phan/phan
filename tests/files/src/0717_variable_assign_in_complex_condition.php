<?php
class Example {
    /** @return array */
    public function read($token)
    {
        if (!$token || !file_exists($file = 'somefile.txt')) {
            return [];
        }
        // TODO: Fix handling of assignments in arguments
        // Should infer the type of $file
        return $file;
    }
}
