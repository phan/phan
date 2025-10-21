<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser\Node\Statement;

use Microsoft\PhpParser\Node\StatementNode;
use Microsoft\PhpParser\Node\DelimitedList\ExpressionList;
use Microsoft\PhpParser\Token;

/**
 * This represents either a literal echo statement (`echo expr`)
 * or a short echo tag (`<?= expr...`)
 */
class EchoStatement extends StatementNode {

    /**
     * @var Token|null this is null if generated from `<?=`
     */
    public $echoKeyword;

    /** @var ExpressionList */
    public $expressions;

    /** @var Token */
    public $semicolon;

    const CHILD_NAMES = [
        'echoKeyword',
        'expressions',
        'semicolon',
    ];
}
