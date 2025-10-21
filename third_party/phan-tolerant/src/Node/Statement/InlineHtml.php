<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Token;

class InlineHtml extends StatementNode {
    /** @var Token|null */
    public $scriptSectionEndTag;

    /** @var Token */
    public $text;

    /** @var Token|null */
    public $scriptSectionStartTag;

    /**
     * @var EchoStatement|null used to represent the expression echoed by `<?=` while parsing.
     *
     * This should always be null in the returned AST,
     * and is deliberately excluded from CHILD_NAMES.
     *
     * This will be null under any of these conditions:
     *
     * - The scriptSectionStartTag isn't TokenKind::ScriptSectionStartWithEchoTag,
     * - The echoStatement was normalized and moved into a statement list.
     *   If a caller doesn't do this, that's a bug.
     */
    public $echoStatement;

    const CHILD_NAMES = [
        'scriptSectionEndTag',
        'text',
        'scriptSectionStartTag',
    ];
}
