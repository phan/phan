<?php declare(strict_types=1);
namespace Phan\Plugin;

use Phan\Analysis\ClassAnalyzer;
use Phan\Analysis\ClassInheritanceAnalyzer;
use Phan\Analysis\CompositionAnalyzer;
use Phan\Analysis\DuplicateClassAnalyzer;
use Phan\Analysis\DuplicateFunctionAnalyzer;
use Phan\Analysis\FunctionAnalyzer;
use Phan\Analysis\MethodAnalyzer;
use Phan\Analysis\OverrideSignatureAnalyzer;
use Phan\Analysis\ParameterTypesAnalyzer;
use Phan\Analysis\ParentConstructorCalledAnalyzer;
use Phan\Analysis\PostOrderAnalyzer;
use Phan\Analysis\PreOrderAnalyzer;
use Phan\Analysis\PropertyTypesAnalyzer;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Plugin;
use ast\Node;
use ReflectionClass;

/**
 * The root plugin that calls out each hook
 * on any plugins defined in the configuration.
 *
 * (Note: This is called almost once per each AST node being analyzed.
 * Speed is preferred over using Phan\Memoize.)
 */
class ConfigPluginSet extends Plugin {
    /** @var ClassAnalyzer[] - Cached analyzer set for this instance. */
    private $classAnalyzerSet = [];

    /** @var FunctionAnalyzer[] - Cached analyzer set for this instance. */
    private $functionAnalyzerSet = [];

    /** @var MethodAnalyzer[] - Cached analyzer set for this instance. */
    private $methodAnalyzerSet = [];

    /** @var Plugin[] - Cached plugin set for this instance. */
    private $pluginSet = [];

    /** @var PostOrderAnalyzer[] - Cached analyzer set for this instance. */
    private $postOrderAnalyzerSet = [];

    /** @var PreOrderAnalyzer[] - Cached analyzer set for this instance. */
    private $preOrderAnalyzerSet = [];

    /**
     * Call `ConfigPluginSet::instance()` instead.
     */
    private function __construct() {
        foreach (Config::get()->plugins as $plugin_file_name) {
            $plugin_instance = require($plugin_file_name);

            assert(!empty($plugin_instance),
                "Plugins must return an instance of the plugin. The plugin at $plugin_file_name does not.");

            if (is_string($plugin_instance)) {
                $reflection_class = new ReflectionClass($plugin_instance);

                if ($reflection_class->implementsInterface(PostOrderAnalyzer::class)) {
                    // could assert AnalysisVisitor parent or whatever else
                    $this->postOrderAnalyzerSet[] = $reflection_class;
                }

                if ($reflection_class->implementsInterface(PreOrderAnalyzer::class)) {
                    $this->preOrderAnalyzerSet[] = $reflection_class;
                }
            } else if ($plugin_instance instanceof Plugin) {
                // append onto these arrays so there's a single iteration below
                $this->classAnalyzerSet[] = $plugin_instance;
                $this->functionAnalyzerSet[] = $plugin_instance;
                $this->methodAnalyzerSet[] = $plugin_instance;

                // but keep a secondary list for node analysis
                // the newer visitor impl style is invoked differently
                $this->pluginSet[] = $plugin_instance;
            } else {
                if ($plugin_instance instanceof ClassAnalyzer) {
                    $this->classAnalyzerSet[] = $plugin_instance;
                }

                if ($plugin_instance instanceof FunctionAnalyzer) {
                    $this->functionAnalyzerSet[] = $plugin_instance;
                }

                if ($plugin_instance instanceof MethodAnalyzer) {
                    $this->methodAnalyzerSet[] = $plugin_instance;
                }
            }
        }

        // add internal analyzers that are structured as plugins, that should be included with all runs
        $this->classAnalyzerSet[] = new ClassInheritanceAnalyzer;
        $this->classAnalyzerSet[] = new CompositionAnalyzer;
        $this->classAnalyzerSet[] = new DuplicateClassAnalyzer;
        $this->classAnalyzerSet[] = new ParentConstructorCalledAnalyzer;
        $this->classAnalyzerSet[] = new PropertyTypesAnalyzer;

        $this->functionAnalyzerSet[] = $this->methodAnalyzerSet[] = new DuplicateFunctionAnalyzer;
        $this->functionAnalyzerSet[] = $this->methodAnalyzerSet[] = new ParameterTypesAnalyzer;
        if (Config::get()->analyze_signature_compatibility) {
            $this->methodAnalyzerSet[] = new OverrideSignatureAnalyzer;
        }
    }

    /**
     * @return ConfigPluginSet
     * A shared single instance of this plugin
     */
    public static function instance() : ConfigPluginSet
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self;
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
     */
    public function preAnalyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node
    ) {
        foreach ($this->pluginSet as $plugin) {
            $plugin->preAnalyzeNode(
                $code_base,
                $context,
                $node
            );
        }

        foreach ($this->preOrderAnalyzerSet as $analyzer) {
            $visitor = $analyzer->newInstance($code_base, $context);
            $visitor($node);
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
     */
    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
        foreach ($this->pluginSet as $plugin) {
            $plugin->analyzeNode(
                $code_base,
                $context,
                $node,
                $parent_node
            );
        }

        foreach ($this->postOrderAnalyzerSet as $analyzer) {
            $visitor = $analyzer->newInstance($code_base, $context);
            $visitor($node);
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
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        foreach ($this->classAnalyzerSet as $plugin) {
            $plugin->analyzeClass(
                $code_base,
                $class
            );
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
     */
    public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    ) {
        foreach ($this->methodAnalyzerSet as $plugin) {
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
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        foreach ($this->functionAnalyzerSet as $plugin) {
            $plugin->analyzeFunction(
                $code_base,
                $function
            );
        }
    }

}
