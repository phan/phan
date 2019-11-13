<?php

namespace NS823;

class Section {
}

class SubSection extends Section {
}

class HiddenSubSection extends SubSection {
}

class SubSubSection extends SubSection {
}

function test(Section $s) {
    if ($s instanceof SubSubSection && rand(0,2)) {
        return true;
    }
    '@phan-debug-var $s';
    if (!$s instanceof SubSection) {
        return false;
    }
    '@phan-debug-var $s';
    if ($s instanceof HiddenSubSection) {
        return true;
    }
    return false;
}
