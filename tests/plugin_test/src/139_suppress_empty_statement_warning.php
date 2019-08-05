<?php
try {
    // TODO comment exists
} finally {
    // does not exist
}

try {
    echo "Do something\n";
} finally {
    // TODO add a handler
}

if (rand() % 2 > 0) {
    // fixme check this
}

if (rand() % 2 > 0) {
    // should warn
}

while (rand() % 2 > 0) {
    // TODO implement
}

while (rand() % 2 > 0) {
    // should warn
}

foreach ([2] as $_) {
    // TODO implement
}

foreach ([2] as $i) {
    // should warn
}

for (; rand() % 2 > 0; ) {
    // deliberately empty, should not warn
}
for (; rand() % 2 > 0; ) {
    // should warn
}
