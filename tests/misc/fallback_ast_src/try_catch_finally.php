<?php
try {
    echo "try";
} catch (Exception $e) {
    echo "catch 1";
} catch (bar\FooException $e2) {
    echo "catch 2";
} finally {
    echo "finally";
}
