<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

use ReturnTypeWillChange;

class SkippedToken extends Token {
    public function __construct(Token $token) {
        parent::__construct($token->kind, $token->fullStart, $token->start, $token->length);
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize() {
        return array_merge(
            ["error" => $this->getTokenKindNameFromValue(TokenKind::SkippedToken)],
            parent::jsonSerialize()
        );
    }
}
