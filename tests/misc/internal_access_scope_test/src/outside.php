<?php
namespace Elsewhere;

// A namespace that is in no package: reported, as with 'namespace'.
function outside(): int {
    return \App\Core::inner();
}
