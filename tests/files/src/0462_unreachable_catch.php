<?php

try {
    throw new RuntimeException("message");
} catch (Exception $e) {
    echo "Caught";
} catch (Throwable $e) {
    echo "Caught error\n";
} catch (Error $e) {  // should warn about Throwable
    echo "Caught error\n";
} catch (RuntimeException $e) {  // should warn about Exception or Throwable
    echo "Caught RuntimeException\n";
}

class ExceptionA extends Exception {}
class ExceptionB extends ExceptionA {}
try {
    // ...
} catch (ExceptionA $exception) {
    // ...
} catch (ExceptionB $exception) {  // should warn
}

try {
    // ...
} catch (ExceptionB $exception) {

} catch (ExceptionA $exception) {  // should not warn
    // ...
} catch (Error $exception) {
} catch (Throwable $exception) {
}

try {
    // ...
} catch (ExceptionB $exception) {

} catch (ExceptionB|ExceptionA|Error $exception) {  // should warn about ExceptionB
    // ...
} catch (TypeError $exception) {  // should warn about Error
}

// In Zend PHP, you can catch interfaces as well (but not in HHVM)
interface MyExceptionInterface {}
class ExceptionC extends ExceptionA implements MyExceptionInterface {}

try {
    // ...
} catch (MyExceptionInterface $exception) {
    // ...
} catch (InvalidArgumentException $exception) {
    // ...
} catch (ExceptionC $exception) {  // Should warn about MyExceptionInterface
    // ...
} catch (ExceptionA $exception) {
}
