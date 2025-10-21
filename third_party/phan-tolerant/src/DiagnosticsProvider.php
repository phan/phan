<?php
/*---------------------------------------------------------------------------------------------
 *  Copyright (c) Microsoft Corporation. All rights reserved.
 *  Licensed under the MIT License. See License.txt in the project root for license information.
 *--------------------------------------------------------------------------------------------*/

namespace Microsoft\PhpParser;

use Microsoft\PhpParser\Node;

class DiagnosticsProvider {

    /**
     * @var string[] maps the token kind to the corresponding name
     */
    private static $tokenKindToText;

    /**
     * @param int $kind (must be a valid token kind)
     * @return string
     */
    public static function getTextForTokenKind($kind) {
        return self::$tokenKindToText[$kind];
    }

    /**
     * This is called when this class is loaded, at the bottom of this file.
     * @return void
     */
    public static function initTokenKindToText() {
        self::$tokenKindToText = \array_flip(\array_merge(
            TokenStringMaps::OPERATORS_AND_PUNCTUATORS,
            TokenStringMaps::KEYWORDS,
            TokenStringMaps::RESERVED_WORDS
        ));
    }

    /**
     * Returns the diagnostic for $node, or null.
     * @param \Microsoft\PhpParser\Node|\Microsoft\PhpParser\Token $node
     * @return Diagnostic|null
     */
    public static function checkDiagnostics($node) {
        if ($node instanceof Token) {
            if (\get_class($node) === Token::class) {
                return null;
            }
            return self::checkDiagnosticForUnexpectedToken($node);
        }

        if ($node instanceof Node) {
            return $node->getDiagnosticForNode();
        }

        /** @phpstan-ignore-next-line because it says "unreachable" statement */
        return null;
    }

    /**
     * @param Token $token
     * @return Diagnostic|null
     */
    private static function checkDiagnosticForUnexpectedToken($token) {
        if ($token instanceof SkippedToken) {
            // TODO - consider also attaching parse context information to skipped tokens
            // this would allow us to provide more helpful error messages that inform users what to do
            // about the problem rather than simply pointing out the mistake.
            return new Diagnostic(
                DiagnosticKind::Error,
                "Unexpected '" .
                (self::$tokenKindToText[$token->kind]
                    ?? Token::getTokenKindNameFromValue($token->kind)) .
                "'",
                $token->start,
                $token->getEndPosition() - $token->start
            );
        } elseif ($token instanceof MissingToken) {
            return new Diagnostic(
                DiagnosticKind::Error,
                "'" .
                (self::$tokenKindToText[$token->kind]
                    ?? Token::getTokenKindNameFromValue($token->kind)) .
                "' expected.",
                $token->start,
                $token->getEndPosition() - $token->start
            );
        }
        return null;
    }

    /**
     * Traverses AST to generate diagnostics.
     * @param \Microsoft\PhpParser\Node $n
     * @return Diagnostic[]
     */
    public static function getDiagnostics(Node $n) : array {
        $diagnostics = [];

        /**
         * @param \Microsoft\PhpParser\Node|\Microsoft\PhpParser\Token $node
         */
        $n->walkDescendantNodesAndTokens(function($node) use (&$diagnostics) {
            if (($diagnostic = self::checkDiagnostics($node)) !== null) {
                $diagnostics[] = $diagnostic;
            }
        });

        return $diagnostics;
    }
}

DiagnosticsProvider::initTokenKindToText();
