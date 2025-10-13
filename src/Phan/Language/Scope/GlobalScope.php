<?php

declare(strict_types=1);

namespace Phan\Language\Scope;

use AssertionError;
use Phan\CodeBase;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope;

/**
 * Represents the global scope (and stores global variables)
 */
final class GlobalScope extends Scope
{
    /**
     * Deliberate no-op
     */
    public function __construct()
    {
    }

    /**
     * @var array<string,Variable>
     * A map from name to variables for all
     * variables registered under $GLOBALS.
     */
    private static $global_variable_map = [];

    /**
     * @var ?CodeBase
     * Reference to the CodeBase for recording undo operations.
     * This is set during initialization to enable incremental analysis.
     */
    private static $code_base = null;

    /**
     * @var array<string,array<string,array{action:string,old?:Variable}>>
     * Tracks per-file contributions to global variables.
     * Maps file path -> variable name -> contribution record.
     * Each record contains 'action' (added|modified) and optionally 'old' (previous Variable).
     * Used for incremental analysis to undo only a specific file's changes.
     */
    private static $file_contributions = [];

    /**
     * @var ?string
     * The file currently being analyzed (set during analysis phase).
     * Used to track which file contributes which global variables.
     */
    private static $current_analyzing_file = null;

    /**
     * @return bool
     * True if we're in a class scope
     */
    public function isInClassScope(): bool
    {
        return false;
    }

    /**
     * @return bool
     * True if we're in a method/function/closure scope
     */
    public function isInFunctionLikeScope(): bool
    {
        return false;
    }

    public function isInElementScope(): bool
    {
        return false;
    }

    public function isInMethodLikeScope(): bool
    {
        return false;
    }

    public function hasAnyTemplateType(): bool
    {
        return false;
    }

    public function getTemplateTypeMap(): array
    {
        return [];
    }

    /**
     * @return bool
     * True if a variable with the given name is defined
     * within this scope
     */
    public function hasVariableWithName(string $name): bool
    {
        return \array_key_exists($name, self::$global_variable_map);
    }

    public function getVariableByName(string $name): Variable
    {
        return self::$global_variable_map[$name];
    }

    public function getVariableByNameOrNull(string $name): ?Variable
    {
        return self::$global_variable_map[$name] ?? null;
    }

    /**
     * @return array<string|int,Variable> (keys are variable names, which are *almost* always strings)
     * A map from name to Variable in the global scope.
     */
    public function getVariableMap(): array
    {
        return self::$global_variable_map;
    }

    /**
     * @unused-param $scope
     * @return array<string|int,Variable> (keys are variable names, which are *almost* always strings)
     * A map from name to Variable in the global scope.
     */
    public function getVariableMapExcludingScope(?Scope $scope): array
    {
        // Phan always generates a branch scope in front of the branch scope.
        // The global scope can have hundreds or thousands of variables in some projects, avoid merging variables from it.
        return [];
    }

    /**
     * @param Variable $variable
     * A variable to add to the local scope
     */
    public function withVariable(Variable $variable): Scope
    {
        $this->addVariable($variable);
        return $this;
    }

    public function addVariable(Variable $variable): void
    {
        $variable_name = $variable->getName();
        if (Variable::isHardcodedGlobalVariableWithName($variable_name)) {
            // Silently ignore globally replacing $_POST, $argv, runkit superglobals, etc.
            // with superglobals.
            // TODO: Add a warning for incompatible assignments in callers.
            return;
        }

        // Track this file's contribution for incremental analysis
        $current_file = self::$current_analyzing_file;
        if ($current_file !== null) {
            if (!isset(self::$global_variable_map[$variable_name])) {
                // This file is adding a new global variable
                self::$file_contributions[$current_file][$variable_name] = ['action' => 'added'];
            } elseif (self::$global_variable_map[$variable_name] !== $variable) {
                // This file is modifying an existing global variable
                // Only record the first modification by this file
                if (!isset(self::$file_contributions[$current_file][$variable_name])) {
                    self::$file_contributions[$current_file][$variable_name] = [
                        'action' => 'modified',
                        'old' => self::$global_variable_map[$variable_name]
                    ];
                }
            }
        }

        self::$global_variable_map[$variable->getName()] = $variable;
    }

    /**
     * @param Variable $variable
     * A variable to add to the set of global variables
     */
    public function addGlobalVariable(Variable $variable): void
    {
        $this->addVariable($variable);
    }

    /**
     * @return bool
     * True if a global variable with the given name exists
     */
    public function hasGlobalVariableWithName(string $name): bool
    {
        return $this->hasVariableWithName($name);
    }

    /**
     * @return Variable
     * The global variable with the given name
     */
    public function getGlobalVariableByName(string $name): Variable
    {
        return $this->getVariableByName($name);
    }

    /**
     * @return bool
     * True if this scope has a parent scope
     */
    public function hasParentScope(): bool
    {
        return false;
    }

    /**
     * @return never
     * Get the parent scope of this scope
     */
    public function getParentScope(): Scope
    {
        throw new AssertionError("Global scope has no parent scope");
    }

    /**
     * @return never
     */
    public function getClassFQSEN(): FullyQualifiedClassName
    {
        throw new AssertionError("Cannot get class FQSEN on scope");
    }

    /**
     * @return never
     */
    public function getPropertyFQSEN(): FullyQualifiedPropertyName
    {
        throw new AssertionError("Cannot get class FQSEN on scope");
    }

    /**
     * @return null
     */
    public function getClassFQSENOrNull(): ?FullyQualifiedClassName
    {
        return null;
    }

    /**
     * @return never
     */
    public function getFunctionLikeFQSEN()
    {
        throw new AssertionError("Cannot get method/function/closure FQSEN on scope");
    }

    /**
     * @unused-param $template_type_identifier
     */
    public function hasTemplateType(
        string $template_type_identifier
    ): bool {
        return false;
    }

    /**
     * Set the CodeBase reference for recording undo operations during incremental analysis.
     * This should be called during Phan initialization.
     */
    public static function setCodeBase(CodeBase $code_base): void
    {
        self::$code_base = $code_base;
    }

    /**
     * Clear the CodeBase reference. Used for cleanup and testing.
     */
    public static function clearCodeBase(): void
    {
        self::$code_base = null;
    }

    /**
     * Reset all global variables. Used for testing and daemon mode cleanup.
     */
    public static function reset(): void
    {
        self::$global_variable_map = [];
        self::$code_base = null;
        self::$file_contributions = [];
        self::$current_analyzing_file = null;
    }

    /**
     * Mark the start of analyzing a file.
     * This tracks which file is responsible for adding/modifying global variables.
     *
     * @param string $file_path The file about to be analyzed
     */
    public static function startAnalyzingFile(string $file_path): void
    {
        self::$current_analyzing_file = $file_path;
        // Initialize contribution tracking for this file
        if (!isset(self::$file_contributions[$file_path])) {
            self::$file_contributions[$file_path] = [];
        }
    }

    /**
     * Mark the end of analyzing a file.
     */
    public static function finishAnalyzingFile(): void
    {
        self::$current_analyzing_file = null;
    }

    /**
     * Undo the global variable changes made by a specific file.
     * This removes variables added by the file and restores variables modified by the file.
     * Variables from other files are left untouched.
     *
     * @param string $file_path The file being undone
     */
    public static function undoAnalysisForFile(string $file_path): void
    {
        if (!isset(self::$file_contributions[$file_path])) {
            return;
        }

        // Undo this file's contributions in reverse order
        foreach (self::$file_contributions[$file_path] as $variable_name => $contribution) {
            if ($contribution['action'] === 'added') {
                // This file added this variable - remove it
                unset(self::$global_variable_map[$variable_name]);
            } elseif ($contribution['action'] === 'modified' && isset($contribution['old'])) {
                // This file modified this variable - restore the old value
                self::$global_variable_map[$variable_name] = $contribution['old'];
            }
        }

        unset(self::$file_contributions[$file_path]);
    }

    /**
     * Register undo operation for a file during parse phase.
     * This is called during the parse phase when undo tracking is enabled.
     * The registered closure will restore globals when the file changes.
     *
     * @param string $file_path The file being parsed
     */
    public static function registerUndoForFile(string $file_path): void
    {
        $code_base = self::$code_base;
        if ($code_base !== null) {
            $undo_tracker = $code_base->getUndoTracker();
            if ($undo_tracker) {
                $undo_tracker->recordUndo(static function (CodeBase $_) use ($file_path): void {
                    self::undoAnalysisForFile($file_path);
                });
            }
        }
    }
}
