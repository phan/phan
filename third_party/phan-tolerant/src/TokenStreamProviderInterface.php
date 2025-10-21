<?php
/*---------------------------------------------------------------------------------------------
 * Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

interface TokenStreamProviderInterface {
    public function scanNextToken() : Token;

    public function getCurrentPosition() : int;

    public function setCurrentPosition(int $pos);

    public function getEndOfFilePosition() : int;

    public function getTokensArray() : array;
}
