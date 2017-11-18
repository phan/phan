<?php declare(strict_types=1);
namespace Phan\Plugin;

use Phan\AST\Visitor\Element;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Plugin;
use Phan\Plugin\Internal\ArrayReturnTypeOverridePlugin;
use Phan\Plugin\Internal\CallableParamPlugin;
use Phan\Plugin\Internal\CompactPlugin;
use Phan\Plugin\Internal\ClosureReturnTypeOverridePlugin;
use Phan\Plugin\Internal\StringFunctionPlugin;
use Phan\Plugin\PluginImplementation;
use Phan\PluginV2;
use Phan\PluginV2\AnalyzeNodeCapability;
use Phan\PluginV2\PreAnalyzeNodeCapability;
use Phan\PluginV2\AnalyzeClassCapability;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzeFunctionCallCapability;
use Phan\PluginV2\AnalyzePropertyCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
use Phan\PluginV2\FinalizeProcessCapability;
use Phan\PluginV2\LegacyAnalyzeNodeCapability;
use Phan\PluginV2\LegacyPreAnalyzeNodeCapability;
use Phan\PluginV2\PluginAwareAnalysisVisitor;
use Phan\PluginV2\PluginAwarePreAnalysisVisitor;
use Phan\PluginV2\ReturnTypeOverrideCapability;
use Phan\PluginV2\FunctionHookCapability;
use ast\Node;

/**
 * The root plugin that calls out each hook
 * on any plugins defined in the configuration.
 *
 * (Note: This is called almost once per each AST node being analyzed.
 * Speed is preferred over using Phan\Memoize.)
 */
final class ConfigPluginSet extends PluginV2 implements
    AnalyzeClassCapability,
    AnalyzeFunctionCapability,
    AnalyzeFunctionCallCapability,
    AnalyzeMethodCapability,
    AnalyzePropertyCapability,
    FinalizeProcessCapability,
    LegacyAnalyzeNodeCapability,
    LegacyPreAnalyzeNodeCapability,
    ReturnTypeOverrideCapability
{

    /** @var Plugin[]|null - Cached plugin set for this instance. Lazily generated. */
    private $pluginSet;

    /** @var \Closure[]|null - plugins to analyze nodes in pre order. */
    private $preAnalyzeNodePluginSet;

    /** @var \Closure[]|null - plugins to analyze nodes in post order. */
    private $analyzeNodePluginSet;

    /** @var AnalyzeClassCapability[]|null - plugins to analyze class declarations. */
    private $analyzeClassPluginSet;

    /** @var AnalyzeFunctionCallCapability[]|null - plugins to analyze invocations of subsets of functions and methods. */
    private $analyzeFunctionCallPluginSet;

    /** @var AnalyzeFunctionCapability[]|null - plugins to analyze function declarations. */
    private $analyzeFunctionPluginSet;

    /** @var AnalyzePropertyCapability[]|null - plugins to analyze property declarations. */
    private $analyzePropertyPluginSet;

    /** @var AnalyzeMethodCapability[]|null - plugins to analyze method declarations.*/
    private $analyzeMethodPluginSet;

    /** @var FinalizeProcessCapability[]|null - plugins to call finalize() on after analysis is finished. */
    private $finalizeProcessPluginSet;

    /** @var ReturnTypeOverrideCapability[]|null - plugins which generate return UnionTypes of functions based on arguments. */
    private $returnTypeOverridePluginSet;

    /**
     * Call `ConfigPluginSet::instance()` instead.
     */
    private function __construct()
    {
    }

    /**
     * @return ConfigPluginSet
     * A shared single instance of this plugin
     * @suppress PhanDeprecatedInterface
     */
    public static function instance() : ConfigPluginSet
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self;
            $instance->ensurePluginsExist();
        }
        return $instance;
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context
     * The context in which the node exits. This is
     * the context inside the given node rather than
     * the context outside of the given node
     *
     * @param Node $node
     * The php-ast Node being analyzed.
     *
     * @return void
     * @override
     */
    public function preAnalyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node
    ) {
        $plugin_callback = $this->preAnalyzeNodePluginSet[$node->kind] ?? null;
        if ($plugin_callback !== null) {
            $plugin_callback(
                $code_base,
                $context,
                $node
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context
     * The context in which the node exits. This is
     * the context inside the given node rather than
     * the context outside of the given node
     *
     * @param Node $node
     * The php-ast Node being analyzed.
     *
     * @param Node $node
     * The parent node of the given node (if one exists).
     *
     * @return void
     * @override
     */
    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
        $plugin_callback = $this->analyzeNodePluginSet[$node->kind] ?? null;
        if ($plugin_callback !== null) {
            $plugin_callback(
                $code_base,
                $context,
                $node,
                $parent_node
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     * @override
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        foreach ($this->analyzeClassPluginSet as $plugin) {
            $plugin->analyzeClass(
                $code_base,
                $class
            );
        }
        if ($this->hasAnalyzePropertyPlugins()) {
            foreach ($class->getPropertyList($code_base) as $property) {
                $this->analyzeProperty($code_base, $property);
            }
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     * @override
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        foreach ($this->analyzeMethodPluginSet as $plugin) {
            $plugin->analyzeMethod(
                $code_base,
                $method
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     * @override
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        foreach ($this->analyzeFunctionPluginSet as $plugin) {
            $plugin->analyzeFunction(
                $code_base,
                $function
            );
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the property exists
     *
     * @param Property $property
     * A property being analyzed
     *
     * (Called by analyzeClass())
     *
     * @return void
     * @override
     */
    public function analyzeProperty(
        CodeBase $code_base,
        Property $property
    ) {
        foreach ($this->analyzePropertyPluginSet as $plugin) {
            try {
                $plugin->analyzeProperty(
                    $code_base,
                    $property
                );
            } catch (IssueException $exception) {
                // e.g. getUnionType() can throw, PropertyTypesAnalyzer is probably emitting duplicate issues
                Issue::maybeEmitInstance(
                    $code_base,
                    $property->getContext(),
                    $exception->getIssueInstance()
                );
                continue;
            }
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base used for previous analysis steps
     *
     * @return void
     * @override
     */
    public function finalizeProcess(
        CodeBase $code_base
    ) {
        foreach ($this->finalizeProcessPluginSet as $plugin) {
            $plugin->finalizeProcess($code_base);
        }
    }

    // Micro-optimization in tight loops: check for plugins before calling config plugin set
    public function hasPlugins() : bool
    {
        \assert(!\is_null($this->pluginSet));
        return \count($this->pluginSet) > 0;
    }

    /**
     * Returns true if analyzeFunction() will execute any plugins.
     */
    public function hasAnalyzeFunctionPlugins() : bool
    {
        \assert(!\is_null($this->pluginSet));
        return \count($this->analyzeFunctionPluginSet) > 0;
    }

    /**
     * Returns true if analyzeMethod() will execute any plugins.
     */
    public function hasAnalyzeMethodPlugins() : bool
    {
        \assert(!\is_null($this->pluginSet));
        return \count($this->analyzeMethodPluginSet) > 0;
    }

    /**
     * @return \Closure[] maps FQSEN string to closure
     */
    public function getAnalyzeFunctionCallClosures(CodeBase $code_base) : array
    {
        $result = [];
        \assert(!\is_null($this->pluginSet));
        foreach ($this->analyzeFunctionCallPluginSet as $plugin) {
            // TODO: Make this case insensitive.
            foreach ($plugin->getAnalyzeFunctionCallClosures($code_base) as $fqsen_name => $closure) {
                $other_closure = $result[$fqsen_name] ?? null;
                if ($other_closure !== null) {
                    $old_closure = $closure;
                    $closure = static function (CodeBase $code_base, Context $context, FunctionInterface $func, array $args) use ($old_closure, $other_closure) {
                        $other_closure($code_base, $context, $func, $args);
                        $old_closure($code_base, $context, $func, $args);
                    };
                }
                $result[$fqsen_name] = $closure;
            }
        }
        return $result;
    }

    /**
     * @return \Closure[] maps FQSEN string to closure
     */
    public function getReturnTypeOverrides(CodeBase $code_base) : array
    {
        $result = [];
        \assert(!\is_null($this->pluginSet));
        foreach ($this->returnTypeOverridePluginSet as $plugin) {
            $result += $plugin->getReturnTypeOverrides($code_base);
        }
        return $result;
    }

    /**
     * Returns true if analyzeProperty() will execute any plugins.
     */
    private function hasAnalyzePropertyPlugins() : bool
    {
        \assert(!\is_null($this->pluginSet));
        return \count($this->analyzePropertyPluginSet) > 0;
    }

    /** @return void */
    private function ensurePluginsExist()
    {
        if (!\is_null($this->pluginSet)) {
            return;
        }
        // Add user-defined plugins.
        $plugin_set = array_map(
            function (string $plugin_file_name) : PluginV2 {
                // Allow any word/UTF-8 identifier as a php file name.
                // E.g. 'AlwaysReturnPlugin' becomes /path/to/phan/.phan/plugins/AlwaysReturnPlugin.php
                // (Useful when using phan.phar, etc.)
                if (\preg_match('@^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$@', $plugin_file_name) > 0) {
                    $plugin_file_name = __DIR__ . '/../../../.phan/plugins/' . $plugin_file_name . '.php';
                }

                $plugin_instance =
                    require($plugin_file_name);

                \assert(
                    !empty($plugin_instance),
                    "Plugins must return an instance of the plugin. The plugin at $plugin_file_name does not."
                );

                \assert(
                    $plugin_instance instanceof PluginV2,
                    "Plugins must extend \Phan\PluginV2. The plugin at $plugin_file_name does not."
                );

                return $plugin_instance;
            },
            Config::getValue('plugins')
        );
        // Add internal plugins. Can be disabled by disable_internal_return_type_plugins.
        if (Config::getValue('enable_internal_return_type_plugins')) {
            $internal_return_type_plugins = [
                new ArrayReturnTypeOverridePlugin(),
                new CallableParamPlugin(),
                new CompactPlugin(),
                new ClosureReturnTypeOverridePlugin(),
                new StringFunctionPlugin(),
            ];
            $plugin_set = array_merge($internal_return_type_plugins, $plugin_set);
        }

        // Register the entire set.
        $this->pluginSet = $plugin_set;

        $this->preAnalyzeNodePluginSet      = self::filterPreAnalysisPlugins($plugin_set);
        $this->analyzeNodePluginSet         = self::filterAnalysisPlugins($plugin_set);
        $this->analyzeMethodPluginSet       = self::filterOutEmptyMethodBodies(self::filterByClass($plugin_set, AnalyzeMethodCapability::class), 'analyzeMethod');
        $this->analyzeFunctionPluginSet     = self::filterOutEmptyMethodBodies(self::filterByClass($plugin_set, AnalyzeFunctionCapability::class), 'analyzeFunction');
        $this->analyzePropertyPluginSet     = self::filterOutEmptyMethodBodies(self::filterByClass($plugin_set, AnalyzePropertyCapability::class), 'analyzeProperty');
        $this->analyzeClassPluginSet        = self::filterOutEmptyMethodBodies(self::filterByClass($plugin_set, AnalyzeClassCapability::class), 'analyzeClass');
        $this->finalizeProcessPluginSet     = self::filterOutEmptyMethodBodies(self::filterByClass($plugin_set, FinalizeProcessCapability::class), 'finalizeProcess');
        $this->returnTypeOverridePluginSet  = self::filterByClass($plugin_set, ReturnTypeOverrideCapability::class);
        $this->analyzeFunctionCallPluginSet = self::filterByClass($plugin_set, AnalyzeFunctionCallCapability::class);
    }

    /**
     * @return array
     */
    private static function filterOutEmptyMethodBodies(array $plugin_set, string $method_name) : array
    {
        return \array_values(\array_filter($plugin_set, function (PluginV2 $plugin) use ($method_name) : bool {
            if ($plugin instanceof PluginImplementation) {
                if (!$plugin->isDefinedInSubclass($method_name)) {
                    // PluginImplementation defines empty method bodies for each of the plugin $method_names
                    // Don't execute $method_name for a plugin during analysis if the subclass didn't override the implementation for $method_name.
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * @return \Closure[] - [function(CodeBase $code_base, Context $context, Node $node, Node $parent_node = null): void]
     */
    private static function filterPreAnalysisPlugins(array $plugin_set) : array
    {
        $closures_for_kind = new ClosuresForKind();
        foreach ($plugin_set as $plugin) {
            if ($plugin instanceof LegacyPreAnalyzeNodeCapability) {
                if ($plugin instanceof PreAnalyzeNodeCapability) {
                    throw new \TypeError(sprintf("plugin %s should implement only one of LegacyPreAnalyzeNodeCapability and PreAnalyzeNodeCapability, not both", get_class($plugin)));
                }
                if ($plugin instanceof PluginImplementation) {
                    if (!$plugin->isDefinedInSubclass('preAnalyzeNode')) {
                        continue;
                    }
                }
                $closure = (new \ReflectionMethod($plugin, 'preAnalyzeNode'))->getClosure($plugin);
                $closures_for_kind->recordForAllKinds($closure);
            } elseif ($plugin instanceof PreAnalyzeNodeCapability) {
                $plugin_analysis_class = $plugin->getPreAnalyzeNodeVisitorClassName();
                if (!\is_subclass_of($plugin_analysis_class, PluginAwarePreAnalysisVisitor::class)) {
                    throw new \TypeError(
                        sprintf(
                            "Result of %s::getAnalyzeNodeVisitorClassName must be the name of a subclass of '%s', but '%s' is not",
                            get_class($plugin),
                            PluginAwarePreAnalysisVisitor::class,
                            $plugin_analysis_class
                        )
                    );
                }
                /**
                 * Create an instance of $plugin_analysis_class and run the visit*() method corresponding to $node->kind.
                 *
                 * @phan-closure-scope PluginAwarePreAnalysisVisitor
                 */
                $closure = (static function (CodeBase $code_base, Context $context, Node $node) {
                    $fn_name = Element::VISIT_LOOKUP_TABLE[$node->kind];
                    return (new static($code_base, $context))->{$fn_name}($node);
                })->bindTo(null, $plugin_analysis_class);
                $handled_node_kinds = $plugin_analysis_class::getHandledNodeKinds();
                if (\count($handled_node_kinds) === 0) {
                    fprintf(
                        STDERR,
                        "Plugin %s has an analyzeNode visitor %s (subclass of %s) which doesn't override any known visit<Suffix>() methods, but expected at least one method to be overridden\n",
                        get_class($plugin),
                        $plugin_analysis_class,
                        PluginAwarePreAnalysisVisitor::class
                    );
                }
                $closures_for_kind->recordForKinds($handled_node_kinds, $closure);
            }
        }
        return $closures_for_kind->getFlattenedClosures(static function (array $closure_list) : \Closure {
            return static function (CodeBase $code_base, Context $context, Node $node) use ($closure_list) {
                foreach ($closure_list as $closure) {
                    $closure($code_base, $context, $node);
                }
            };
        });
    }

    /**
     * @return \Closure[] - [function(CodeBase $code_base, Context $context, Node $node, Node $parent_node = null): void]
     * @var \Closure[][] $closures_for_kind
     */
    private static function filterAnalysisPlugins(array $plugin_set) : array
    {
        $closures_for_kind = new ClosuresForKind();
        foreach ($plugin_set as $plugin) {
            if ($plugin instanceof LegacyAnalyzeNodeCapability) {
                if ($plugin instanceof AnalyzeNodeCapability) {
                    throw new \TypeError(
                        sprintf(
                            "plugin %s should implement only one of LegacyAnalyzeNodeCapability and AnalyzeNodeCapability, not both",
                            get_class($plugin)
                        )
                    );
                }
                if ($plugin instanceof PluginImplementation) {
                    if (!$plugin->isDefinedInSubclass('analyzeNode')) {
                        continue;
                    }
                }
                $closure = (new \ReflectionMethod($plugin, 'analyzeNode'))->getClosure($plugin);
                $closures_for_kind->recordForAllKinds($closure);
            } elseif ($plugin instanceof AnalyzeNodeCapability) {
                $plugin_analysis_class = $plugin->getAnalyzeNodeVisitorClassName();
                if (!\is_subclass_of($plugin_analysis_class, PluginAwareAnalysisVisitor::class)) {
                    throw new \TypeError(
                        sprintf(
                            "Result of %s::getAnalyzeNodeVisitorClassName must be the name of a subclass of '%s', but '%s' is not",
                            get_class($plugin),
                            PluginAwareAnalysisVisitor::class,
                            $plugin_analysis_class
                        )
                    );
                }
                /**
                 * Create an instance of $plugin_analysis_class and run the visit*() method corresponding to $node->kind.
                 *
                 * @suppress PhanParamTooMany
                 * @suppress PhanUndeclaredProperty
                 * @suppress PhanDeprecatedInterface (TODO: Fix bugs in PhanClosureScope)
                 */
                $closure = (static function (CodeBase $code_base, Context $context, Node $node, Node $parent_node = null) {
                    $visitor = new static($code_base, $context);
                    $visitor->parent_node = $parent_node;
                    $fn_name = Element::VISIT_LOOKUP_TABLE[$node->kind];
                    $visitor->{$fn_name}($node);
                })->bindTo(null, $plugin_analysis_class);

                $handled_node_kinds = $plugin_analysis_class::getHandledNodeKinds();
                if (\count($handled_node_kinds) === 0) {
                    fprintf(
                        STDERR,
                        "Plugin %s has an analyzeNode visitor %s (subclass of %s) which doesn't override any known visit<Suffix>() methods, but expected at least one method to be overridden\n",
                        get_class($plugin),
                        $plugin_analysis_class,
                        PluginAwareAnalysisVisitor::class
                    );
                }
                $closures_for_kind->recordForKinds($handled_node_kinds, $closure);
            }
        }
        return $closures_for_kind->getFlattenedClosures(static function (array $closure_list) : \Closure {
            return static function (CodeBase $code_base, Context $context, Node $node, Node $parent_node = null) use ($closure_list) {
                foreach ($closure_list as $closure) {
                    $closure($code_base, $context, $node, $parent_node);
                }
            };
        });
    }

    private static function filterByClass(array $plugin_set, string $interface_name) : array
    {
        $result = [];
        foreach ($plugin_set as $plugin) {
            if ($plugin instanceof $interface_name) {
                $result[] = $plugin;
            }
        }
        return $result;
    }

    /**
     * @return Plugin[]
     */
    private function getPlugins() : array
    {
        return $this->pluginSet;
    }
}
