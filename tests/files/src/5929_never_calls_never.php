<?php

// Regression test for https://github.com/phan/phan/issues/5535
// A function/method declared `: never` whose only/last statement is a call to
// another `never`-returning userland function must not be flagged with
// PhanTypeMissingReturnReal, since the inner call provably never returns.

function redirect_any5929(string $url): never
{
    header("Location: " . $url, true, 301);
    exit;
}

function redirect5929(string $url): never
{
    redirect_any5929($url);
}

function doRedirectAndExit5929(): never
{
    $url = "...";
    redirect5929($url);
}

class Redirector5929
{
    public function bail(): never
    {
        redirect_any5929("/login");
    }

    public function bailStatic(): never
    {
        self::doExit();
    }

    public static function doExit(): never
    {
        exit;
    }
}

// The never-returning call may be nested under an if/else and must still be
// recognized as never returning.
function never_in_if5929(bool $x): never
{
    if ($x) {
        redirect_any5929("/a");
    } else {
        redirect_any5929("/b");
    }
}

// A closure/arrow-function body that ends by calling a never-returning function
// must likewise be recognized as never returning (visitClosure path).
$never_closure5929 = function (): never {
    redirect_any5929("/closure");
};
$never_closure5929();

