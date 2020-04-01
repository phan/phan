<?php
namespace NS2;
if (\PHP_VERSION_ID >= 70200) {
class IsDuplicate {
    const X = 2;
}
} else {
class IsDuplicate {
    const X = 3;
}
}
echo IsDuplicate::X;  // should not optimize
echo IsDuplicate::MISSING;  // should not crash
