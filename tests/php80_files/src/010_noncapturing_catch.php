<?php
try {
    throw new RuntimeException();
} catch (RuntimeException) {
    echo "Caught\n";
} catch (UnknownException) {
    echo "Impossible\n";
}
function myFunction() {
    try {
        throw new RuntimeException();
    } catch (RuntimeException) {
        echo "Caught\n";
    } catch (UnknownException) {
        echo "Impossible\n";
        throw new InvalidArgumentException();
    }
}
myFunction();
