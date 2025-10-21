<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

trait ModifiedTypeTrait {
    /** @var Token[] */
    public $modifiers;
    
    public function hasModifier(int $targetModifier): bool {
        if ($this->modifiers === null) {
            return false;
        }

        foreach ($this->modifiers as $modifier) {
            if ($modifier->kind === $targetModifier) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convenience method to check for the existence of the "public" modifier.
     * Does not necessarily need to be defined for that type.
     *
     * @return bool
     */
    public function isPublic(): bool {
        return $this->hasModifier(TokenKind::PublicKeyword);
    }

    /**
     * Convenience method to check for the existence of the "static" modifier.
     * Does not necessarily need to be defined for that type.
     *
     * @return bool
     */
    public function isStatic(): bool {
        return $this->hasModifier(TokenKind::StaticKeyword);
    }
}
