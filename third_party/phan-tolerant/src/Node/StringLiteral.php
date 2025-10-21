<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node;

use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;

class StringLiteral extends Expression {
    /** @var Token|null */
    public $startQuote;

    /** @var Token[]|Node[]|Token */
    public $children;

    /** @var Token */
    public $endQuote;

    const CHILD_NAMES = [
        'startQuote',
        'children',
        'endQuote',
    ];

    public function getStringContentsText(): string {
        $stringContents = "";
        if (isset($this->startQuote)) {
            foreach ($this->children as $child) {
                $contents = $this->getFileContents();
                $stringContents .= $child->getFullText($contents);
            }
        } else {
            $children = $this->children;
            if ($children instanceof Token) {
                $value = (string)$children->getText($this->getFileContents());
                $startQuote = substr($value, 0, 1);

                if ($startQuote === '\'') {
                    return rtrim(substr($value, 1), '\'');
                }

                if ($startQuote === '"') {
                    return rtrim(substr($value, 1), '"');
                }
            }

            return '';
        }
        return $stringContents;
    }
}
