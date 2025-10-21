<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

spl_autoload_register(function ($class) {
    $path = str_replace("\\", "/", __DIR__ . "/" . \substr($class, 20) . ".php");
    if (file_exists($path)) {
        require_once $path;
    }
});
