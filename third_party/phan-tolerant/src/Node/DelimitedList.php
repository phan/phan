<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\TokenKind;

abstract class DelimitedList extends Node {
    /** @var Token[]|Node[] */
    public $children;

    const CHILD_NAMES = [
        'children'
    ];

    const DELIMITERS = [TokenKind::CommaToken, TokenKind::BarToken, TokenKind::SemicolonToken];

    public function getElements() : \Generator {
        foreach ($this->children as $child) {
            if ($child instanceof Node) {
                yield $child;
            } elseif ($child instanceof Token && !\in_array($child->kind, self::DELIMITERS)) {
                yield $child;
            }
        }
    }

    public function getValues() {
        foreach ($this->children as $idx=>$value) {
            if ($idx % 2 == 0) {
                yield $value;
            }
        }
    }

    public function addElement($node) {
        if ($node === null) {
            return;
        }
        if ($this->children === null) {
            $this->children = [$node];
            return;
        }
        $this->children[] = $node;
    }
}
