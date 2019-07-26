<?php
/** @throws RuntimeException */
function infiniteloops728() {
    while (true) {}
    for (;true;) {}
    do {echo "."; } while (true);
    while (1) {}
    for (;1;) {}
    do {echo "."; } while (1);
    while (new stdClass()) {}
    for (;new stdClass();) {}
    do {echo "."; } while (new stdClass());

    while (true) { throw new RuntimeException("x"); }
    for (;true;) {throw new RuntimeException("x");}
    do {echo "."; throw new RuntimeException("x"); } while (true);
    for (;;) {}
    return 2;
}
