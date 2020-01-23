<?php
class Example {
    /** @return array */
    public function read($token)
    {
        if (!$token || !file_exists($file = 'somefile.txt')) {
            return [];
        }
        // Should infer the type of $file as string because the above condition would set $file if it did not return.
        return $file;
    }
}
