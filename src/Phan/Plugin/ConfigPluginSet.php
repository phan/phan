<?php declare(strict_types=1);
namespace Phan\Plugin;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\Plugin;
use ast\Node;

/**
 * The root plugin that calls out each hook
 * on any plugins defined in the configuration.
 *
 * (Note: This is called almost once per each AST node being analyzed.
 * Speed is preferred over using Phan\Memoize.)
 */
class ConfigPluginSet extends Plugin {
    /** @var Plugin[]|null - Cached plugin set for this instance. Lazily generated. */
    private $pluginSet;

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
        foreach ($this->getPlugins() as $plugin) {
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
        foreach ($this->getPlugins() as $plugin) {
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
        foreach ($this->getPlugins() as $plugin) {
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
        foreach ($this->getPlugins() as $plugin) {
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
        foreach ($this->getPlugins() as $plugin) {
            $plugin->analyzeFunction(
                $code_base,
                $function
            );
        }
    }

    // Micro-optimization in tight loops: check for plugins before calling config plugin set
    public function hasPlugins() : bool {
        return count($this->getPlugins()) > 0;
    }

    /**
     * @return Plugin[]
     */
    private function getPlugins() : array
    {
        if (is_null($this->pluginSet)) {
            $this->pluginSet = array_map(
                function (string $plugin_file_name) : Plugin {
                    $plugin_instance =
                        require($plugin_file_name);

                    assert(!empty($plugin_instance),
                        "Plugins must return an instance of the plugin. The plugin at $plugin_file_name does not.");

                    assert($plugin_instance instanceof Plugin,
                        "Plugins must extend \Phan\Plugin. The plugin at $plugin_file_name does not.");

                    return $plugin_instance;
                },
                Config::get()->plugins
            );
        }
        return $this->pluginSet;
    }

}
