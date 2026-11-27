<?php
namespace Elsewhere;

// Another namespace in a file of the project: not reported.
function outside(): int {
    return \App\Core::inner();
}
