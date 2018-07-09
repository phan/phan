<?php
/**
 * Checks the naming of variables and member variables.
 *
 * This is a modified version of Zend.Sniffs.NamingConventions.ValidVariableNameSniff by Greg Sherwood <gsherwood@squiz.net>
 *
 * The original file's license:
 *
 * @copyright 2006-2015 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 */

namespace PHP_CodeSniffer\Standards\Phan\Sniffs\NamingConventions;

// use PHP_CodeSniffer\Standards\Zend\Sniffs\NamingConventions;
use PHP_CodeSniffer\Sniffs\AbstractVariableSniff;
use PHP_CodeSniffer\Files\File;

class ValidUnderscoreVariableNameSniff extends AbstractVariableSniff
{


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function processVariable(File $phpcsFile, $stackPtr)
    {
        if ($this->isExcluded($phpcsFile)) {
            return;
        }
        $tokens  = $phpcsFile->getTokens();
        $varName = ltrim($tokens[$stackPtr]['content'], '$');

        // If it's a php reserved var, then its ok.
        if (isset($this->phpReservedVars[$varName]) === true) {
            return;
        }

        $objOperator = $phpcsFile->findNext([T_WHITESPACE], ($stackPtr + 1), null, true);
        if ($tokens[$objOperator]['code'] === T_OBJECT_OPERATOR) {
            // Check to see if we are using a variable from an object.
            // TODO: This workaround doesn't check variables that are the expression of a member property access expression
            $var = $phpcsFile->findNext([T_WHITESPACE], ($objOperator + 1), null, true);
            if ($tokens[$var]['code'] === T_STRING) {
                return;
            }//end if
        }//end if

        // There is no way for us to know if the var is public or private,
        // so we have to ignore a leading underscore if there is one and just
        // check the main part of the variable name.
        $originalVarName = $varName;
        if (substr($varName, 0, 1) === '_') {
            // Let PSR-12 checks deal with this
            return;
        }

        if (self::isUnderscoreVariableName($originalVarName) === false) {
            $error = 'Variable "%s" is not in valid underscore name format';
            $data  = [$originalVarName];
            $phpcsFile->addError($error, $stackPtr, 'NotUnderscore', $data);
        } else if (preg_match('|\d|', $varName) === 1) {
            $warning = 'Variable "%s" contains numbers but this is discouraged';
            $data    = [$originalVarName];
            $phpcsFile->addWarning($warning, $stackPtr, 'ContainsNumbers', $data);
        }

    }//end processVariable()


    /**
     * Processes class member variables.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function processMemberVar(File $phpcsFile, $stackPtr)
    {
        if ($this->isExcluded($phpcsFile)) {
            return;
        }
        $tokens      = $phpcsFile->getTokens();
        $varName     = ltrim($tokens[$stackPtr]['content'], '$');
        $memberProps = $phpcsFile->getMemberProperties($stackPtr);
        if (empty($memberProps) === true) {
            // Exception encountered.
            return;
        }

        $public = ($memberProps['scope'] === 'public');

        if (substr($varName, 0, 1) === '_') {
            // Phan's coding style uses PSR-12.
            // PSR-12 already checks for this, so skip these
            return;
        }

        // Remove a potential underscore prefix for testing CamelCaps.
        if (self::isUnderscoreVariableName($varName) === false) {
            $error = 'Member variable "%s" is not in valid underscore format';
            $data  = [$varName];
            $phpcsFile->addError($error, $stackPtr, 'MemberVarNotUnderscore', $data);
        } else if (preg_match('|\d|', $varName) === 1) {
            $warning = 'Member variable "%s" contains numbers but this is discouraged';
            $data    = [$varName];
            $phpcsFile->addWarning($warning, $stackPtr, 'MemberVarContainsNumbers', $data);
        }

    }//end processMemberVar()


    /**
     * Processes the variable found within a double quoted string.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcsFile The file being scanned.
     * @param int                         $stackPtr  The position of the double quoted
     *                                               string.
     *
     * @return void
     */
    protected function processVariableInString(File $phpcsFile, $stackPtr)
    {
        if ($this->isExcluded($phpcsFile)) {
            return;
        }
        $tokens = $phpcsFile->getTokens();

        if (preg_match_all('|[^\\\]\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|', $tokens[$stackPtr]['content'], $matches) !== 0) {
            foreach ($matches[1] as $varName) {
                // If it's a php reserved var, then its ok.
                if (isset($this->phpReservedVars[$varName]) === true) {
                    continue;
                }

                if (self::isUnderscoreVariableName($varName) === false) {
                    $error = 'Variable "%s" is not in valid undercase format';
                    $data  = [$varName];
                    $phpcsFile->addError($error, $stackPtr, 'StringVarNotUnderscore', $data);
                } else if (preg_match('|\d|', $varName) === 1) {
                    $warning = 'Variable "%s" contains numbers but this is discouraged';
                    $data    = [$varName];
                    $phpcsFile->addWarning($warning, $stackPtr, 'StringVarContainsNumbers', $data);
                }
            }//end foreach
        }//end if

    }//end processVariableInString()

    protected static function isUnderscoreVariableName(string $varName) {
        if ($varName === '_') {
            return true;
        }
        if (preg_match("/^[a-z]/", $varName) === 0) {
            return false;
        }
        // Check that the name only contains legal characters.
        $legalChars = 'a-z_0-9';
        if (preg_match("|[^$legalChars]|", $varName) > 0) {
            return false;
        }
        if (strpos($varName, '__') !== false) {
            return false;
        }
        return true;
    }

    // Can't get exclude-pattern to work in ruleset.xml, so just hardcode this
    private function isExcluded(File $phpcsFile) : bool {
        return preg_match('@[/\\\\]LanguageServer[/\\\\]@', $phpcsFile->path) > 0;
    }
}//end class
