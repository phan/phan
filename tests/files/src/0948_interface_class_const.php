<?php
// Prior to php 8.1, this would result in the following error:
// Fatal error: Cannot inherit previously-inherited or override constant A from interface NS948\I.
// When minimum_target_php_version_id < 80100, treat class constants in interfaces as final.
namespace NS948;

interface I {
    const A = 1;
}

class C implements I {
    const A = 2;
}
