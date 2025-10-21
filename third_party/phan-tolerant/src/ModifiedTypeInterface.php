<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

/**
 * Use the ModifiedTypeTrait for convenience in order to implement this interface.
 */
interface ModifiedTypeInterface {
    public function hasModifier(int $targetModifier): bool;
    public function isPublic(): bool;
    public function isStatic(): bool;
}
