<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

use ReturnTypeWillChange;

class MissingToken extends Token {
    public function __construct(int $kind, int $fullStart) {
        parent::__construct($kind, $fullStart, $fullStart, 0);
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize() {
        return array_merge(
            ["error" => $this->getTokenKindNameFromValue(TokenKind::MissingToken)],
            parent::jsonSerialize()
        );
    }
}
