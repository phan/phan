<?php

if (isset($_GET['a'])) {
        $_GET['b'] = $_GET['a'];
}

array_merge([], $_GET);
