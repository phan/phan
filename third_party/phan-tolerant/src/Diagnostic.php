<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

class Diagnostic {
    /** @var int */
    public $kind;

    /** @var string */
    public $message;

    /** @var int */
    public $start;

    /** @var int */
    public $length;

    public function __construct(int $kind, string $message, int $start, int $length) {
        $this->kind = $kind;
        $this->message = $message;
        $this->start = $start;
        $this->length = $length;
    }
}
