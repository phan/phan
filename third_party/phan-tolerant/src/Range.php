<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

class Range {
    public $start;
    public $end;

    public function __construct(LineCharacterPosition $start, LineCharacterPosition $end) {
        $this->start = $start;
        $this->end = $end;
    }
}
