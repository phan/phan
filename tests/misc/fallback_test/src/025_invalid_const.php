<?php

const X25 = function() {};
echo X25;
class Example25 {
    // This will emit PhanInvalidConstantExpression and then be inferred as type mixed
    // to avoid impossible results or crashes.
    const X = function() {};
}
// This won't warn, because the type is inferred as mixed instead
echo count(Example25::X);
echo Example25::X;
