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

// The never-returning call may be nested under a switch, match, or other
// control-flow construct whose exit status is cached by a context-less
// BlockExitStatusChecker before this check runs. The cached status must not
// hide the inner never-returning call. See review of PR #5541.
function never_in_switch5929(int $x): never
{
    switch ($x) {
        case 1:
            redirect_any5929("/one");
            // fallthrough to default; both branches never return
        default:
            redirect_any5929("/x");
    }
}

function never_in_match5929(int $x): never
{
    match ($x) {
        1 => redirect_any5929("/one"),
        default => redirect_any5929("/x"),
    };
}

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

