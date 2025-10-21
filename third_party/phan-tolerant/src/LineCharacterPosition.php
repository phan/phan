<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

class LineCharacterPosition {
    public $line;
    public $character;

    public function __construct(int $line, int $character) {
        $this->line = $line;
        $this->character = $character;
    }
}
