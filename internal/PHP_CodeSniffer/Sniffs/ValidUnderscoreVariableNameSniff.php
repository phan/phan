<?php
declare(strict_types = 1);
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

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\AbstractVariableSniff;

class ValidUnderscoreVariableNameSniff extends AbstractVariableSniff
{


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcs_file The file being scanned.
     * @param int                         $stack_ptr  The position of the current token in the
     *                                                stack passed in $tokens.
     *
     * @return void
     */
    protected function processVariable(File $phpcs_file, $stack_ptr)
    {
        if ($this->isExcluded($phpcs_file)) {
            return;
        }
        $tokens  = $phpcs_file->getTokens();
        $var_name = ltrim($tokens[$stack_ptr]['content'], '$');

        // If it's a php reserved var, then its ok.
        if (isset($this->phpReservedVars[$var_name]) === true) {
            return;
        }

        $obj_operator = $phpcs_file->findNext([T_WHITESPACE], ($stack_ptr + 1), null, true);
        if ($tokens[$obj_operator]['code'] === T_OBJECT_OPERATOR) {
            // Check to see if we are using a variable from an object.
            // TODO: This workaround doesn't check variables that are the expression of a member property access expression
            $var = $phpcs_file->findNext([T_WHITESPACE], ($obj_operator + 1), null, true);
            if ($tokens[$var]['code'] === T_STRING) {
                return;
            }//end if
        }//end if

        // There is no way for us to know if the var is public or private,
        // so we have to ignore a leading underscore if there is one and just
        // check the main part of the variable name.
        $original_var_name = $var_name;
        if (substr($var_name, 0, 1) === '_') {
            // Let PSR-12 checks deal with this
            return;
        }

        if (self::isUnderscoreVariableName($original_var_name) === false) {
            $error = 'Variable "%s" is not in valid underscore name format';
            $data  = [$original_var_name];
            $phpcs_file->addError($error, $stack_ptr, 'NotUnderscore', $data);
        } elseif (preg_match('|\d|', $var_name) === 1) {
            $warning = 'Variable "%s" contains numbers but this is discouraged';
            $data    = [$original_var_name];
            $phpcs_file->addWarning($warning, $stack_ptr, 'ContainsNumbers', $data);
        }
    }//end processVariable()


    /**
     * Processes class member variables.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcs_file The file being scanned.
     * @param int                         $stack_ptr  The position of the current token in the
     *                                               stack passed in $tokens.
     *
     * @return void
     */
    protected function processMemberVar(File $phpcs_file, $stack_ptr)
    {
        if ($this->isExcluded($phpcs_file)) {
            return;
        }
        $tokens      = $phpcs_file->getTokens();
        $var_name     = ltrim($tokens[$stack_ptr]['content'], '$');
        $member_props = $phpcs_file->getMemberProperties($stack_ptr);
        if (empty($member_props) === true) {
            // Exception encountered.
            return;
        }

        $public = ($member_props['scope'] === 'public');

        if (substr($var_name, 0, 1) === '_') {
            // Phan's coding style uses PSR-12.
            // PSR-12 already checks for this, so skip these
            return;
        }

        if ($var_name === 'backupStaticAttributesBlacklist') {
            // PHPUnit built-in
            return;
        }
        // Remove a potential underscore prefix for testing CamelCaps.
        if (self::isUnderscoreVariableName($var_name) === false) {
            $error = 'Member variable "%s" is not in valid underscore format';
            $data  = [$var_name];
            $phpcs_file->addError($error, $stack_ptr, 'MemberVarNotUnderscore', $data);
        } elseif (preg_match('|\d|', $var_name) === 1) {
            $warning = 'Member variable "%s" contains numbers but this is discouraged';
            $data    = [$var_name];
            $phpcs_file->addWarning($warning, $stack_ptr, 'MemberVarContainsNumbers', $data);
        }
    }//end processMemberVar()


    /**
     * Processes the variable found within a double quoted string.
     *
     * @param \PHP_CodeSniffer\Files\File $phpcs_file The file being scanned.
     * @param int                         $stack_ptr  The position of the double quoted
     *                                               string.
     *
     * @return void
     */
    protected function processVariableInString(File $phpcs_file, $stack_ptr)
    {
        if ($this->isExcluded($phpcs_file)) {
            return;
        }
        $tokens = $phpcs_file->getTokens();

        if (preg_match_all('|[^\\\]\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)|', $tokens[$stack_ptr]['content'], $matches) !== 0) {
            foreach ($matches[1] as $var_name) {
                // If it's a php reserved var, then its ok.
                if (isset($this->phpReservedVars[$var_name]) === true) {
                    continue;
                }

                if (self::isUnderscoreVariableName($var_name) === false) {
                    $error = 'Variable "%s" is not in valid undercase format';
                    $data  = [$var_name];
                    $phpcs_file->addError($error, $stack_ptr, 'StringVarNotUnderscore', $data);
                } elseif (preg_match('|\d|', $var_name) === 1) {
                    $warning = 'Variable "%s" contains numbers but this is discouraged';
                    $data    = [$var_name];
                    $phpcs_file->addWarning($warning, $stack_ptr, 'StringVarContainsNumbers', $data);
                }
            }//end foreach
        }//end if
    }//end processVariableInString()

    protected static function isUnderscoreVariableName(string $var_name)
    {
        if ($var_name === '_') {
            return true;
        }
        if (preg_match("/^[a-z]/", $var_name) === 0) {
            return false;
        }
        // Check that the name only contains legal characters.
        $legal_chars = 'a-z_0-9';
        if (preg_match("|[^$legal_chars]|", $var_name) > 0) {
            return false;
        }
        if (strpos($var_name, '__') !== false) {
            return false;
        }
        return true;
    }

    // Can't get exclude-pattern to work in ruleset.xml, so just hardcode this
    private function isExcluded(File $phpcs_file) : bool
    {
        return preg_match('@[/\\\\]LanguageServer[/\\\\]@', $phpcs_file->path) > 0;
    }
}//end class
