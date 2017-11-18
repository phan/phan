<?php declare(strict_types=1);
namespace Phan;

use Phan\CodeBase\ClassMap;
use Phan\CodeBase\UndoTracker;
use Phan\Language\Context;
use Phan\Language\Element\ClassAliasRecord;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionFactory;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassElement;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\UnionType;
use Phan\Library\Map;
use Phan\Library\Set;

use Generator;
use ReflectionClass;

/**
 * A CodeBase represents the known state of a code base
 * we're analyzing.
 *
 * In order to understand internal classes, interfaces,
 * traits and functions, a CodeBase needs to be
 * initialized with the list of those elements begotten
 * before any classes are loaded.
 *
 * # Example
 * ```
 * // Grab these before we define our own classes
 * $internal_class_name_list = get_declared_classes();
 * $internal_interface_name_list = get_declared_interfaces();
 * $internal_trait_name_list = get_declared_traits();
 * $internal_function_name_list = get_defined_functions()['internal'];
 *
 * // Load any required code ...
 *
 * $code_base = new CodeBase(
 *     $internal_class_name_list,
 *     $internal_interface_name_list,
 *     $internal_trait_name_list,
 *     CodeBase::getPHPInternalConstantNameList(),
 *     $internal_function_name_list
 *  );
 *
 *  // Do stuff ...
 * ```
 *
 * This supports undoing some operations in the parse phase,
 * for a background daemon analyzing single files. (Phan\CodeBase\UndoTracker)
 */
class CodeBase
{
    /**
     * @var Map
     * A map from FQSEN to an internal or user defined class
     */
    private $fqsen_class_map;

    /**
     * @var Map
     * A map from FQSEN to a user defined class
     */
    private $fqsen_class_map_user_defined;

    /**
     * @var Map
     * A map from FQSEN to an internal class
     */
    private $fqsen_class_map_internal;

    /**
     * @var Map
     * A map from FQSEN to a ReflectionClass
     */
    private $fqsen_class_map_reflection;

    /**
     * @var Map
     * A map from FQSEN to set of ClassAliasRecord objects
     */
    private $fqsen_alias_map;

    /**
     * @var Map
     * A map from FQSEN to a global constant
     */
    private $fqsen_global_constant_map;

    /**
     * @var Map
     * A map from FQSEN to function
     */
    private $fqsen_func_map;

    /**
     * @var Set
     * A set of internal function FQSENs to lazily initialize.
     * Entries are removed as new entries get added to fqsen_func_map.
     */
    private $internal_function_fqsen_set;

    /**
     * @var Set
     * The set of all methods
     */
    private $method_set;

    /**
     * @var Map
     * A map from FullyQualifiedClassName to a ClassMap,
     * an object that holds properties, methods and class
     * constants.
     */
    private $class_fqsen_class_map_map;

    /**
     * @var Set[]
     * A map from a string method name to a Set of
     * Methods
     */
    private $name_method_map = [];

    /**
     * @var bool
     * If true, elements will be ensured to be hydrated
     * on demand as they are requested.
     */
    private $should_hydrate_requested_elements = false;

    /**
     * @var UndoTracker|null - undoes the addition of global constants, classes, functions, and methods.
     */
    private $undo_tracker;

    /**
     * @var bool
     */
    private $has_enabled_undo_tracker = false;

    /**
     * Initialize a new CodeBase
     * TODO: Remove internal_function_name_list completely?
     * @param string[] $internal_class_name_list
     * @param string[] $internal_interface_name_list
     * @param string[] $internal_trait_name_list
     * @param string[] $internal_constant_name_list
     * @param string[] $internal_function_name_list
     */
    public function __construct(
        array $internal_class_name_list,
        array $internal_interface_name_list,
        array $internal_trait_name_list,
        array $internal_constant_name_list,
        array $internal_function_name_list
    ) {
        $this->fqsen_class_map = new Map;
        $this->fqsen_class_map_internal = new Map;
        $this->fqsen_class_map_reflection = new Map;
        $this->fqsen_class_map_user_defined = new Map;
        $this->fqsen_alias_map = new Map;
        $this->fqsen_global_constant_map = new Map;
        $this->fqsen_func_map = new Map;
        $this->class_fqsen_class_map_map = new Map;
        $this->method_set = new Set;
        $this->internal_function_fqsen_set = new Set;

        // Add any pre-defined internal classes, interfaces,
        // constants, traits and functions
        $this->addClassesByNames($internal_class_name_list);
        $this->addClassesByNames($internal_interface_name_list);
        $this->addClassesByNames($internal_trait_name_list);
        $this->addGlobalConstantsByNames($internal_constant_name_list);
        // We initialize the FQSENs early on so that they show up
        // in the proper casing.
        $this->addInternalFunctionsByNames($internal_function_name_list);
    }

    /**
     * @return void
     */
    public function enableUndoTracking()
    {
        if ($this->has_enabled_undo_tracker) {
            throw new \RuntimeException("Undo tracking already enabled");
        }
        $this->has_enabled_undo_tracker = true;
        $this->undo_tracker = new UndoTracker();
    }

    /**
     * @return void
     */
    public function disableUndoTracking()
    {
        if (!$this->has_enabled_undo_tracker) {
            throw new \RuntimeException("Undo tracking was never enabled");
        }
        $this->undo_tracker = null;
    }

    public function isUndoTrackingEnabled() : bool
    {
        return $this->undo_tracker !== null;
    }

    /**
     * @return void
     */
    public function setShouldHydrateRequestedElements(
        bool $should_hydrate_requested_elements
    ) {
        $this->should_hydrate_requested_elements =
            $should_hydrate_requested_elements;
    }

    /**
     * @return string[] - The list of files which are successfully parsed.
     * This changes whenever the file list is reloaded from disk.
     * This also includes files which don't declare classes or functions or globals,
     * because those files use classes/functions/constants.
     *
     * (This is the list prior to any analysis exclusion or whitelisting steps)
     */
    public function getParsedFilePathList() : array
    {
        if ($this->undo_tracker) {
            return $this->undo_tracker->getParsedFilePathList();
        }
        throw new \RuntimeException("Calling getParsedFilePathList without an undo tracker");
    }

    /**
     * @return int The size of $this->getParsedFilePathList()
     */
    public function getParsedFilePathCount() : int
    {
        if ($this->undo_tracker) {
            return $this->undo_tracker->getParsedFilePathCount();
        }
        throw new \RuntimeException("Calling getParsedFilePathCount without an undo tracker");
    }

    /**
     * @param string|null $current_parsed_file
     * @return void
     */
    public function setCurrentParsedFile($current_parsed_file)
    {
        if ($this->undo_tracker) {
            $this->undo_tracker->setCurrentParsedFile($current_parsed_file);
        }
    }

    /**
     * Called when a file is unparseable.
     * Removes the classes and functions, etc. from an older version of the file, if one exists.
     * @return void
     */
    public function recordUnparseableFile(string $current_parsed_file)
    {
        if ($this->undo_tracker) {
            $this->undo_tracker->recordUnparseableFile($this, $current_parsed_file);
        }
    }

    /**
     * @param string[] $class_name_list
     * A list of class names to load type information for
     *
     * @return void
     */
    private function addClassesByNames(array $class_name_list)
    {
        foreach ($class_name_list as $class_name) {
            $reflection_class = new \ReflectionClass($class_name);
            if (!$reflection_class->isUserDefined()) {
                // include internal classes, but not external classes such as composer
                $this->addReflectionClass($reflection_class);
            }
        }
    }

    /**
     * @param string[] $const_name_list
     * A list of global constant names to load type information for
     *
     * @return void
     */
    private function addGlobalConstantsByNames(array $const_name_list)
    {
        foreach ($const_name_list as $const_name) {
            if (!$const_name) {
                // #1015 workaround for empty constant names ('' and '0').
                fprintf(STDERR, "Saw constant with empty name of %s. There may be a bug in a PECL extension you are using (php -m will list those)\n", var_export($const_name, true));
                continue;
            }
            try {
                $const_obj = GlobalConstant::fromGlobalConstantName($this, $const_name);
                $this->addGlobalConstant($const_obj);
            } catch (\InvalidArgumentException $e) {
                // Workaround for windows bug in #1011
                if (\strncmp($const_name, "\0__COMPILER_HALT_OFFSET__\0", 26) === 0) {
                    continue;
                }
                fprintf(STDERR, "Failed to load global constant value for %s, continuing: %s\n", var_export($const_name, true), $e->getMessage());
            }
        }
    }

    /**
     * @param string[] $new_file_list
     * @param string[] $file_mapping_contents maps relative path to absolute paths
     * @return string[] - Subset of $new_file_list which changed on disk and has to be parsed again. Automatically unparses the old versions of files which were modified.
     */
    public function updateFileList(array $new_file_list, array $file_mapping_contents = [])
    {
        if ($this->undo_tracker) {
            return $this->undo_tracker->updateFileList($this, $new_file_list, $file_mapping_contents);
        }
        throw new \RuntimeException("Calling updateFileList without undo tracker");
    }

    /**
     * @param string $file_name
     * @return bool - true if caller should replace contents
     */
    public function beforeReplaceFileContents(string $file_name)
    {
        if ($this->undo_tracker) {
            return $this->undo_tracker->beforeReplaceFileContents($this, $file_name);
        }
        throw new \RuntimeException("Calling replaceFileContents without undo tracker");
    }

    public function eagerlyLoadAllSignatures()
    {
        $this->getInternalClassMap();  // Force initialization of remaining internal php classes to reduce latency of future analysis requests.
        $this->forceLoadingInternalFunctions();  // Force initialization of internal functions to reduce latency of future analysis requests.
    }

    /**
     * @return void
     */
    public function forceLoadingInternalFunctions()
    {
        $internal_function_fqsen_set = $this->internal_function_fqsen_set;
        $this->internal_function_fqsen_set = new Set;  // Don't need to track these any more.
        foreach ($internal_function_fqsen_set as $function_fqsen) {
            // hasFunctionWithFQSEN will automatically load $function_name, **unless** we don't have a signature for that function.
            if (!$this->hasFunctionWithFQSEN($function_fqsen)) {
                // Force loading these even if automatic loading failed.
                // (Shouldn't happen, the function list is fetched from reflection by callers.
                $function_alternate_generator = FunctionFactory::functionListFromReflectionFunction(
                    $this,
                    $function_fqsen,
                    new \ReflectionFunction($function_fqsen->getNamespacedName())
                );
                foreach ($function_alternate_generator as $function) {
                    $this->addFunction($function);
                }
            }
        }
    }

    /**
     * @param string[] $internal_function_name_list
     * @return void
     */
    private function addInternalFunctionsByNames(array $internal_function_name_list)
    {
        foreach ($internal_function_name_list as $function_name) {
            $this->internal_function_fqsen_set->attach(FullyQualifiedFunctionName::makeFromExtractedNamespaceAndName($function_name));
        }
    }

    /**
     * Clone dependent objects when cloning this object.
     */
    public function __clone()
    {
        $this->fqsen_class_map =
            $this->fqsen_class_map->deepCopy();

        $this->fqsen_class_map_user_defined =
            new Map;

        $this->fqsen_class_map_internal =
            new Map;

        foreach ($this->fqsen_class_map as $fqsen => $clazz) {
            if ($clazz->isPHPInternal()) {
                $this->fqsen_class_map_internal[$fqsen] = $clazz;
            } else {
                $this->fqsen_class_map_user_defined[$fqsen] = $clazz;
            }
        }

        $this->fqsen_class_map_reflection =
            clone($this->fqsen_class_map_reflection);

        $this->fqsen_alias_map =
            $this->fqsen_alias_map->deepCopy();

        $this->fqsen_global_constant_map =
            $this->fqsen_global_constant_map->deepCopy();

        $this->fqsen_func_map =
            $this->fqsen_func_map->deepCopy();

        $this->method_set =
            $this->method_set->deepCopy();

        $this->class_fqsen_class_map_map =
            $this->class_fqsen_class_map_map->deepCopy();

        $name_method_map = $this->name_method_map;
        $this->name_method_map = [];
        foreach ($name_method_map as $name => $method_map) {
            $this->name_method_map[$name] = $method_map->deepCopy();
        }
    }

    /**
     * @return CodeBase
     * A new code base is returned which is a shallow clone
     * of this one, which is to say that the sets and maps
     * of elements themselves are cloned, but the keys and
     * values within those sets and maps are not cloned.
     *
     * Updates to elements will bleed through code bases
     * with only shallow clones. See
     * https://github.com/phan/phan/issues/257
     */
    public function shallowClone() : CodeBase
    {
        $code_base = new CodeBase([], [], [], [], []);
        $code_base->fqsen_class_map =
            clone($this->fqsen_class_map);
        $code_base->fqsen_class_map_user_defined =
            clone($this->fqsen_class_map_user_defined);
        $code_base->fqsen_class_map_internal =
            clone($this->fqsen_class_map_internal);
        $code_base->fqsen_class_map_reflection =
            clone($this->fqsen_class_map_reflection);
        $code_base->fqsen_alias_map =
            clone($this->fqsen_alias_map);

        $code_base->fqsen_global_constant_map =
            clone($this->fqsen_global_constant_map);
        $code_base->fqsen_func_map =
            clone($this->fqsen_func_map);
        $code_base->internal_function_fqsen_set =
            clone($this->internal_function_fqsen_set);
        $code_base->class_fqsen_class_map_map =
            clone($this->class_fqsen_class_map_map);
        $code_base->method_set =
            clone($this->method_set);
        return $code_base;
    }

    /**
     * @param Clazz $class
     * A class to add.
     *
     * @return void
     */
    public function addClass(Clazz $class)
    {
        // Map the FQSEN to the class
        $fqsen = $class->getFQSEN();
        $this->fqsen_class_map[$fqsen] = $class;
        $this->fqsen_class_map_user_defined[$fqsen] = $class;
        if ($this->undo_tracker) {
            $this->undo_tracker->recordUndo(function (CodeBase $inner) use ($fqsen) {
                Daemon::debugf("Undoing addClass %s\n", $fqsen);
                unset($inner->fqsen_class_map[$fqsen]);
                unset($inner->fqsen_class_map_user_defined[$fqsen]);
                // unset($inner->fqsen_class_map_reflection[$fqsen]);  // should not be necessary
                unset($inner->class_fqsen_class_map_map[$fqsen]);
            });
        }
    }

    /**
     * @param ReflectionClass $class
     * A class to add, lazily.
     *
     * @return void
     */
    public function addReflectionClass(ReflectionClass $class)
    {
        // Map the FQSEN to the class
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class->getName());
        $this->fqsen_class_map_reflection[$class_fqsen] = $class;
    }

    /**
     * Call this to record the existence of a class_alias in the global scope.
     * After parse phase is complete (And daemonize has split off a new process),
     * call resolveClassAliases() to create FQSEN entries.
     *
     * @param FullyQualifiedClassName $original
     *  an existing class to alias to
     *
     * @param FullyQualifiedClassName $alias
     *  a name to alias $original to
     *
     *
     * @return void
     */
    public function addClassAlias(
        FullyQualifiedClassName $original,
        FullyQualifiedClassName $alias,
        Context $context,
        int $lineno
    ) {
        if (!isset($this->fqsen_alias_map[$original])) {
            $this->fqsen_alias_map[$original] = new Set();
        }
        $alias_record = new ClassAliasRecord($alias, $context, $lineno);
        $this->fqsen_alias_map[$original]->attach($alias_record);

        if ($this->undo_tracker) {
            // TODO: Track a count of aliases instead? This doesn't work in daemon mode if multiple files add the same alias to the same class.
            // TODO: Allow .phan/config.php to specify aliases or precedences for aliases?
            $this->undo_tracker->recordUndo(function (CodeBase $inner) use ($original, $alias_record) {
                $fqsen_alias_map = $inner->fqsen_alias_map[$original] ?? null;
                if ($fqsen_alias_map) {
                    $fqsen_alias_map->detach($alias_record);
                    if ($fqsen_alias_map->count() == 0) {
                        unset($inner->fqsen_alias_map[$original]);
                    }
                }
            });
        }
    }

    /**
     * @return void
     */
    public function resolveClassAliases()
    {
        \assert(!$this->undo_tracker, 'should only call this after daemon mode is finished');
        // loop through fqsen_alias_map and add entries to fqsen_class_map.
        foreach ($this->fqsen_alias_map as $original_fqsen => $alias_set) {
            $this->resolveClassAliasesForAliasSet($original_fqsen, $alias_set);
        }
    }

    /**
     * @return void
     */
    private function resolveClassAliasesForAliasSet(FullyQualifiedClassName $original_fqsen, Set $alias_set)
    {
        if (!$this->hasClassWithFQSEN($original_fqsen)) {
            // The original class does not exist.
            // Emit issues at the point of every single class_alias call with that original class.
            foreach ($alias_set as $alias_record) {
                \assert($alias_record instanceof ClassAliasRecord);
                Issue::maybeEmit(
                    $this,
                    $alias_record->context,
                    Issue::UndeclaredClassAliasOriginal,
                    $alias_record->lineno,
                    $original_fqsen,
                    $alias_record->alias_fqsen
                );
            }
            return;
        }
        // The original class exists. Attempt to create aliases of the original class.
        $class = $this->getClassByFQSEN($original_fqsen);
        foreach ($alias_set as $alias_record) {
            \assert($alias_record instanceof ClassAliasRecord);
            $alias_fqsen = $alias_record->alias_fqsen;
            // Don't do anything if there is a real class, or if an earlier class_alias created an alias.
            if ($this->hasClassWithFQSEN($alias_fqsen)) {
                // Emit a different issue type to make filtering out false positives easier.
                $clazz = $this->getClassByFQSEN($alias_fqsen);
                Issue::maybeEmit(
                    $this,
                    $alias_record->context,
                    Issue::RedefineClassAlias,
                    $alias_record->lineno,
                    $alias_fqsen,
                    $alias_record->context->getFile(),
                    $alias_record->lineno,
                    $clazz->getFQSEN(),
                    $clazz->getFileRef()->getFile(),
                    $clazz->getFileRef()->getLineNumberStart()
                );
            } else {
                $this->fqsen_class_map[$alias_fqsen] = $class;
            }
        }
    }

    /**
     * @return bool
     * True if a Clazz with the given FQSEN exists
     */
    public function hasClassWithFQSEN(
        FullyQualifiedClassName $fqsen
    ) : bool {
        if ($this->fqsen_class_map->offsetExists($fqsen)) {
            return true;
        }
        return $this->lazyLoadPHPInternalClassWithFQSEN($fqsen);
    }

    /**
     * @return bool
     * True if a Clazz with the given FQSEN was created
     */
    private function lazyLoadPHPInternalClassWithFQSEN(
        FullyQualifiedClassName $fqsen
    ) : bool {
        $reflection_class = $this->fqsen_class_map_reflection[$fqsen] ?? null;
        if ($reflection_class !== null) {
            $this->loadPHPInternalClassWithFQSEN($fqsen, $reflection_class);
            return true;
        }
        return false;
    }

    /** @return void */
    private function loadPHPInternalClassWithFQSEN(
        FullyQualifiedClassName $fqsen,
        ReflectionClass $reflection_class
    ) {
        $class = Clazz::fromReflectionClass($this, $reflection_class);
        $this->fqsen_class_map[$fqsen] = $class;
        $this->fqsen_class_map_internal[$fqsen] = $class;
        unset($this->fqsen_class_map_reflection[$fqsen]);
    }
    /**
     * @param FullyQualifiedClassName $fqsen
     * The FQSEN of a class to get
     *
     * @return Clazz
     * A class with the given FQSEN
     */
    public function getClassByFQSEN(
        FullyQualifiedClassName $fqsen
    ) : Clazz {
        $clazz = $this->fqsen_class_map[$fqsen];

        // This is an optimization that saves us a few minutes
        // on very large code bases.
        //
        // Instead of 'hydrating' all classes (expanding their
        // types and importing parent methods, properties, etc.)
        // all in one go, we just do it on the fly as they're
        // requested. When running as multiple processes this
        // lets us avoid a significant amount of hydration per
        // process.
        if ($this->should_hydrate_requested_elements) {
            $clazz->hydrate($this);
        }

        return $clazz;
    }

    /**
     * @param FullyQualifiedClassName $original
     * The FQSEN of class to get aliases of
     *
     * @return ClassAliasRecord[]
     * A list of all aliases of $original (and their definitions)
     */
    public function getClassAliasesByFQSEN(
        FullyQualifiedClassName $original
    ) : array {
        if (isset($this->fqsen_alias_map[$original])) {
            return $this->fqsen_alias_map[$original]->toArray();
        }

        return [];
    }


    /**
     * @return Map
     * A list of all classes
     *
     * @deprecated - use hasClassWithFQSEN and getClassByFQSEN or getUserDefinedClassMap instead
     */
    public function getClassMap() : Map
    {
        $this->getInternalClassMap(); // Force initialization of remaining internal php classes
        return $this->fqsen_class_map;
    }

    /**
     * @return Map
     * A map from FQSENs to classes which are internal.
     */
    public function getUserDefinedClassMap() : Map
    {
        return $this->fqsen_class_map_user_defined;
    }

    /**
     * @return Map
     * A list of all classes which are internal.
     */
    public function getInternalClassMap() : Map
    {
        if (\count($this->fqsen_class_map_reflection) > 0) {
            $fqsen_class_map_reflection = $this->fqsen_class_map_reflection;
            // Free up memory used by old class map. Prevent it from being freed before we can load it manually.
            $this->fqsen_class_map_reflection = new Map;
            foreach ($fqsen_class_map_reflection as $fqsen => $reflection_class) {
                $this->loadPHPInternalClassWithFQSEN($fqsen, $reflection_class);
            }
        }
        // TODO: Resolve internal classes and optimize the implementation.
        return $this->fqsen_class_map_internal;
    }

    /**
     * @param Method $method
     * A method to add to the code base
     *
     * @return void
     */
    public function addMethod(Method $method)
    {

        // Add the method to the map
        $this->getClassMapByFQSEN(
            $method->getFQSEN()
        )->addMethod($method);

        $this->method_set->attach($method);

        // If we're doing dead code detection(or something else) and this is a
        // method, map the name to the FQSEN so we can do hail-
        // mary references.
        if (Config::get_track_references()) {
            if (empty($this->name_method_map[$method->getFQSEN()->getNameWithAlternateId()])) {
                $this->name_method_map[$method->getFQSEN()->getNameWithAlternateId()] = new Set;
            }
            $this->name_method_map[$method->getFQSEN()->getNameWithAlternateId()]->attach($method);
        }
        if ($this->undo_tracker) {
            // The addClass's recordUndo should remove the class map. Only need to remove it from method_set
            $this->undo_tracker->recordUndo(function (CodeBase $inner) use ($method) {
                $inner->method_set->detach($method);
            });
        }
    }

    /**
     * @return bool
     * True if an element with the given FQSEN exists
     */
    public function hasMethodWithFQSEN(
        FullyQualifiedMethodName $fqsen
    ) : bool {
        return $this->getClassMapByFQSEN($fqsen)->hasMethodWithName(
            $fqsen->getNameWithAlternateId()
        );
    }

    /**
     * @param FullyQualifiedMethodName $fqsen
     * The FQSEN of a method to get
     *
     * @return Method
     * A method with the given FQSEN
     */
    public function getMethodByFQSEN(
        FullyQualifiedMethodName $fqsen
    ) : Method {
        return $this->getClassMapByFQSEN($fqsen)->getMethodByName(
            $fqsen->getNameWithAlternateId()
        );
    }

    /**
     * @return Method[]
     * The set of methods associated with the given class
     */
    public function getMethodMapByFullyQualifiedClassName(
        FullyQualifiedClassName $fqsen
    ) : array {
        return $this->getClassMapByFullyQualifiedClassName(
            $fqsen
        )->getMethodMap();
    }

    /**
     * @return Set
     * A set of all known methods with the given name
     */
    public function getMethodSetByName(string $name) : Set
    {
        \assert(
            Config::get_track_references(),
            __METHOD__ . ' can only be called when dead code '
            . ' detection (or force_tracking_references) is enabled.'
        );

        return $this->name_method_map[$name] ?? new Set;
    }

    /**
     * @return Set
     * The set of all methods and functions
     *
     * @deprecated - Use getFunctionMap and getMethodSet instead, this is slow and may be removed in a future release.
     */
    public function getFunctionAndMethodSet() : Set
    {
        $set = clone($this->method_set);
        foreach ($this->fqsen_func_map as $value) {
            $set->attach($value);
        }
        return $set;
    }

    /**
     * @return Set
     * The set of all methods that Phan is tracking.
     */
    public function getMethodSet() : Set
    {
        return $this->method_set;
    }

    /**
     * @return string[][] -
     * A human readable encoding of $this->func_and_method_set [string $function_or_method_name => [int|string $pos => string $spec]]
     * Excludes internal functions and methods.
     *
     * This can be used for debugging Phan's inference
     * @suppress PhanDeprecatedFunction
     */
    public function exportFunctionAndMethodSet() : array
    {
        $result = [];
        foreach ($this->getFunctionAndMethodSet() as $function_or_method) {
            if ($function_or_method->isPHPInternal()) {
                continue;
            }
            $fqsen = $function_or_method->getFQSEN();
            $function_or_method_name = (string)$fqsen;
            $signature = [(string)$function_or_method->getUnionType()];
            foreach ($function_or_method->getParameterList() as $param) {
                $name = $param->getName();
                $paramType = (string)$param->getUnionType();
                if ($param->isVariadic()) {
                    $name = '...' . $name;
                }
                if ($param->isPassByReference()) {
                    $name = '&' . $name;
                }
                if ($param->isOptional()) {
                    $name = $name . '=';
                }
                $signature[$name] = $paramType;
            }
            $result[$function_or_method_name] = $signature;
        }
        \ksort($result);
        return $result;
    }

    /**
     * @param Func $function
     * A function to add to the code base
     *
     * @return void
     */
    public function addFunction(Func $function)
    {
        // Add it to the map of functions
        $this->fqsen_func_map[$function->getFQSEN()] = $function;

        if ($this->undo_tracker) {
            $this->undo_tracker->recordUndo(function (CodeBase $inner) use ($function) {
                Daemon::debugf("Undoing addFunction on %s\n", $function->getFQSEN());
                unset($inner->fqsen_func_map[$function->getFQSEN()]);
            });
        }
    }

    /**
     * @return bool
     * True if an element with the given FQSEN exists
     */
    public function hasFunctionWithFQSEN(
        FullyQualifiedFunctionName $fqsen
    ) : bool {
        $has_function = $this->fqsen_func_map->contains($fqsen);

        if ($has_function) {
            return true;
        }

        // Make the following checks:
        //
        // 1. this is an internal function that hasn't been loaded yet.
        // 2. Unless 'ignore_undeclared_functions_with_known_signatures' is true, require that the current php binary or it's extensions define this function before that.
        return $this->hasInternalFunctionWithFQSEN($fqsen);
    }

    /**
     * @param FullyQualifiedFunctionName $fqsen
     * The FQSEN of a function to get
     *
     * @return Func
     * A function with the given FQSEN
     */
    public function getFunctionByFQSEN(
        FullyQualifiedFunctionName $fqsen
    ) : Func {
        return $this->fqsen_func_map[$fqsen];
    }

    /**
     * @return Map
     */
    public function getFunctionMap() : Map
    {
        return $this->fqsen_func_map;
    }

    /**
     * @param ClassConstant $class_constant
     * A class constant to add to the code base
     *
     * @return void
     */
    public function addClassConstant(ClassConstant $class_constant)
    {
        return $this->getClassMapByFQSEN(
            $class_constant->getFQSEN()
        )->addClassConstant($class_constant);
    }

    /**
     * @return bool
     * True if an element with the given FQSEN exists
     */
    public function hasClassConstantWithFQSEN(
        FullyQualifiedClassConstantName $fqsen
    ) : bool {
        return $this->getClassMapByFQSEN(
            $fqsen
        )->hasClassConstantWithName($fqsen->getNameWithAlternateId());
    }

    /**
     * @param FullyQualifiedClassConstantName $fqsen
     * The FQSEN of a class constant to get
     *
     * @return ClassConstant
     * A class constant with the given FQSEN
     */
    public function getClassConstantByFQSEN(
        FullyQualifiedClassConstantName $fqsen
    ) : ClassConstant {
        return $this->getClassMapByFQSEN(
            $fqsen
        )->getClassConstantByName($fqsen->getNameWithAlternateId());
    }

    /**
     * @return ClassConstant[]
     * The set of class constants associated with the given class
     */
    public function getClassConstantMapByFullyQualifiedClassName(
        FullyQualifiedClassName $fqsen
    ) : array {
        return $this->getClassMapByFullyQualifiedClassName(
            $fqsen
        )->getClassConstantMap();
    }

    /**
     * @param GlobalConstant $global_constant
     * A global constant to add to the code base
     *
     * @return void
     */
    public function addGlobalConstant(GlobalConstant $global_constant)
    {
        $this->fqsen_global_constant_map[
            $global_constant->getFQSEN()
        ] = $global_constant;
        if ($this->undo_tracker) {
            $this->undo_tracker->recordUndo(function (CodeBase $inner) use ($global_constant) {
                Daemon::debugf("Undoing addGlobalConstant on %s\n", $global_constant->getFQSEN());
                unset($inner->fqsen_global_constant_map[$global_constant->getFQSEN()]);
            });
        }
    }

    /**
     * @return bool
     * True if an element with the given FQSEN exists
     */
    public function hasGlobalConstantWithFQSEN(
        FullyQualifiedGlobalConstantName $fqsen
    ) : bool {
        return $this->fqsen_global_constant_map->offsetExists($fqsen);
    }

    /**
     * @param FullyQualifiedGlobalConstantName $fqsen
     * The FQSEN of a global constant to get
     *
     * @return GlobalConstant
     * A global constant with the given FQSEN
     */
    public function getGlobalConstantByFQSEN(
        FullyQualifiedGlobalConstantName $fqsen
    ) : GlobalConstant {
        return $this->fqsen_global_constant_map[$fqsen];
    }

    /**
     * @return Map
     */
    public function getGlobalConstantMap() : Map
    {
        return $this->fqsen_global_constant_map;
    }

    /**
     * @param Property $property
     * A property to add to the code base
     *
     * @return void
     */
    public function addProperty(Property $property)
    {
        return $this->getClassMapByFQSEN(
            $property->getFQSEN()
        )->addProperty($property);
    }

    /**
     * @return bool
     * True if an element with the given FQSEN exists
     */
    public function hasPropertyWithFQSEN(
        FullyQualifiedPropertyName $fqsen
    ) : bool {
        return $this->getClassMapByFQSEN(
            $fqsen
        )->hasPropertyWithName($fqsen->getNameWithAlternateId());
    }

    /**
     * @param FullyQualifiedPropertyName $fqsen
     * The FQSEN of a property to get
     *
     * @return Property
     * A property with the given FQSEN
     */
    public function getPropertyByFQSEN(
        FullyQualifiedPropertyName $fqsen
    ) : Property {
        return $this->getClassMapByFQSEN(
            $fqsen
        )->getPropertyByName($fqsen->getNameWithAlternateId());
    }

    /**
     * @return Property[]
     * The set of properties associated with the given class
     */
    public function getPropertyMapByFullyQualifiedClassName(
        FullyQualifiedClassName $fqsen
    ) : array {
        return $this->getClassMapByFullyQualifiedClassName(
            $fqsen
        )->getPropertyMap();
    }

    /**
     * @param FullyQualifiedClassElement $fqsen
     * The FQSEN of a class element
     *
     * @return ClassMap
     * Get the class map for an FQSEN representing
     * a class element
     */
    private function getClassMapByFQSEN(
        FullyQualifiedClassElement $fqsen
    ) : ClassMap {
        return $this->getClassMapByFullyQualifiedClassName(
            $fqsen->getFullyQualifiedClassName()
        );
    }

    /**
     * @param FullyQualifiedClassName $fqsen
     * The FQSEN of a class
     *
     * @return ClassMap
     * Get the class map for an FQSEN representing
     * a class element
     */
    private function getClassMapByFullyQualifiedClassName(
        FullyQualifiedClassName $fqsen
    ) : ClassMap {
        if (!$this->class_fqsen_class_map_map->offsetExists($fqsen)) {
            $this->class_fqsen_class_map_map[$fqsen] = new ClassMap;
        }

        return $this->class_fqsen_class_map_map[$fqsen];
    }

    /**
     * @return Map
     */
    public function getClassMapMap() : Map
    {
        return $this->class_fqsen_class_map_map;
    }

    /**
     * @param FullyQualifiedFunctionName
     * The FQSEN of a function we'd like to look up
     *
     * @return bool
     * If the FQSEN represents an internal function that
     * hasn't been loaded yet, true is returned.
     */
    private function hasInternalFunctionWithFQSEN(
        FullyQualifiedFunctionName $fqsen
    ) : bool {
        $canonical_fqsen = $fqsen->withAlternateId(0);
        $found = isset($this->internal_function_fqsen_set[$canonical_fqsen]);
        if (!$found) {
            // Act as though functions don't exist if they aren't loaded into the php binary
            // running phan (or that binary's extensions), even if the signature map contains them.
            // (All of the functions were loaded during initialization)
            //
            // Also, skip over user-defined global functions defined **by Phan** and its dependencies for analysis
            if (!Config::get()->ignore_undeclared_functions_with_known_signatures) {
                return false;
            }
            // If we already created the alternates, do nothing.
            // TODO: This assumes we call hasFunctionWithFQSEN before adding.
            if (isset($this->fqsen_func_map[$canonical_fqsen])) {
                return false;
            }
        }

        $name = $canonical_fqsen->getName();
        if ($canonical_fqsen->getNamespace() !== '\\') {
            $name = \ltrim($canonical_fqsen->getNamespace(), '\\') . '\\' . $name;
        }

        // For elements in the root namespace, check to see if
        // there's a static method signature for something that
        // hasn't been loaded into memory yet and create a
        // method out of it as it's requested

        $function_signature_map =
            UnionType::internalFunctionSignatureMap();

        // Don't need to track this any more
        unset($this->internal_function_fqsen_set[$canonical_fqsen]);

        if (!empty($function_signature_map[$name])) {
            $signature = $function_signature_map[$name];

            // Add each method returned for the signature
            foreach (FunctionFactory::functionListFromSignature(
                $this,
                $canonical_fqsen,
                $signature
            ) as $function) {
                $this->addFunction($function);
            }

            return true;
        } elseif ($found) {
            // Phan doesn't have extended information for the signature for this function, but the function exists.
            foreach (FunctionFactory::functionListFromReflectionFunction(
                $this,
                $canonical_fqsen,
                new \ReflectionFunction($name)
            ) as $function) {
                $this->addFunction($function);
            }

            return true;
        }
        return false;
    }

    /**
     * @return int
     * The total number of elements of all types in the
     * code base.
     */
    public function totalElementCount() : int
    {
        $sum = (
            \count($this->getFunctionMap())
            + \count($this->getGlobalConstantMap())
            + \count($this->getUserDefinedClassMap())
            + \count($this->fqsen_class_map_internal)  // initialized internal classes
            + \count($this->fqsen_class_map_reflection)  // uninitialized internal classes
        );

        foreach ($this->getClassMapMap() as $class_map) {
            $sum += (
                \count($class_map->getClassConstantMap())
                + \count($class_map->getPropertyMap())
                + \count($class_map->getMethodMap())
            );
        }

        return $sum;
    }

    /**
     * @return void
     */
    public function flushDependenciesForFile(string $file_path)
    {
        // TODO: ...
    }

    /**
     * @return void
     */
    public function store()
    {
        // TODO: ...
    }

    /**
     * @return string[]
     * The list of files that depend on the code in the given
     * file path
     */
    public function dependencyListForFile(string $file_path) : array
    {
        // TODO: ...
        return [];
    }

    /**
     * @return string[] every constant name except user-defined constants.
     */
    public static function getPHPInternalConstantNameList() : array
    {
        // Unit tests call this on every test case. Cache the **internal** constants in a static variable for efficiency; those won't change.
        static $constant_name_list = null;
        if ($constant_name_list === null) {
            // 'true', 'false', and 'null' aren't actually defined constants, they're keywords? Add them so that analysis won't break.
            $constant_name_list = \array_keys(\array_merge(['true' => true, 'false' => false, 'null' => null], ...\array_values(
                \array_diff_key(\get_defined_constants(true), ['user' => []])
            )));
        }
        return $constant_name_list;
    }
}
