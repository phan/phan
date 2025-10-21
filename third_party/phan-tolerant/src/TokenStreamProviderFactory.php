<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

class TokenStreamProviderFactory {
    public static function GetTokenStreamProvider($content) {
        //    return new Lexer($content);
        return new PhpTokenizer($content);
    }
}
