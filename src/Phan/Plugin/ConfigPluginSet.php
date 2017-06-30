<?php declare(strict_types=1);
namespace Phan\Plugin;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Plugin;
use Phan\PluginV2\LegacyPreAnalyzeNodeCapability;
use Phan\PluginV2\AnalyzeClassCapability;
use Phan\PluginV2\AnalyzeFunctionCapability;
use Phan\PluginV2\AnalyzeMethodCapability;
use Phan\PluginV2\LegacyAnalyzeNodeCapability;
use ast\Node;

/**
 * The root plugin that calls out each hook
 * on any plugins defined in the configuration.
 *
 * (Note: This is called almost once per each AST node being analyzed.
 * Speed is preferred over using Phan\Memoize.)
 */
final class ConfigPluginSet extends Plugin {
    /** @var Plugin[]|null - Cached plugin set for this instance. Lazily generated. */
    private $pluginSet;

    /** @var LegacyPreAnalyzeNodeCapability[]|null */
    private $preAnalyzeNodePluginSet;

    /** @var LegacyAnalyzeNodeCapability[]|null */
    private $analyzeNodePluginSet;

    /** @var AnalyzeClassCapability[]|null */
    private $analyzeClassPluginSet;

    /** @var AnalyzeFunctionCapability[]|null */
    private $analyzeFunctionPluginSet;

    /** @var AnalyzeMethodCapability[]|null */
    private $analyzeMethodPluginSet;

    /**
     * Call `ConfigPluginSet::instance()` instead.
     */
    private function __construct() {}

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
        $this->ensurePluginsExist();
        foreach ($this->preAnalyzeNodePluginSet as $plugin) {
            $plugin->preAnalyzeNode(
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
     */
    public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    ) {
        $this->ensurePluginsExist();
        foreach ($this->analyzeNodePluginSet as $plugin) {
            $plugin->analyzeNode(
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
     */
    public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    ) {
        $this->ensurePluginsExist();
        foreach ($this->analyzeClassPluginSet as $plugin) {
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
        $this->ensurePluginsExist();
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
     */
    public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    ) {
        $this->ensurePluginsExist();
        foreach ($this->analyzeFunctionPluginSet as $plugin) {
            $plugin->analyzeFunction(
                $code_base,
                $function
            );
        }
    }

    // Micro-optimization in tight loops: check for plugins before calling config plugin set
    public function hasPlugins() : bool {
        return \count($this->getPlugins()) > 0;
    }

    /** @return void */
    private function ensurePluginsExist()
    {
        if (!\is_null($this->pluginSet)) {
            return;
        }
        $plugin_set = array_map(
            function (string $plugin_file_name) : Plugin {
                $plugin_instance =
                    require($plugin_file_name);

                \assert(!empty($plugin_instance),
                    "Plugins must return an instance of the plugin. The plugin at $plugin_file_name does not.");

                \assert($plugin_instance instanceof Plugin,
                    "Plugins must extend \Phan\Plugin. The plugin at $plugin_file_name does not.");

                return $plugin_instance;
            },
            Config::getValue('plugins')
        );
        $this->pluginSet = $plugin_set;

        $this->preAnalyzeNodePluginSet      = self::filterByClass($plugin_set, LegacyPreAnalyzeNodeCapability::class);
        $this->analyzeNodePluginSet         = self::filterByClass($plugin_set, LegacyAnalyzeNodeCapability::class);
        $this->analyzeMethodPluginSet       = self::filterByClass($plugin_set, AnalyzeMethodCapability::class);
        $this->analyzeFunctionPluginSet     = self::filterByClass($plugin_set, AnalyzeFunctionCapability::class);
        $this->analyzeClassPluginSet        = self::filterByClass($plugin_set, AnalyzeClassCapability::class);
    }

    private static function filterByClass(array $plugin_set, string $interface_name) : array {
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
        if (\is_null($this->pluginSet)) {
            $this->ensurePluginsExist();
        }
        return $this->pluginSet;
    }

}
