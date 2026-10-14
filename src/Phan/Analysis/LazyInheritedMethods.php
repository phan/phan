<?php

declare(strict_types=1);

namespace Phan\Analysis;

use AssertionError;
use Closure;
use Phan\Analysis;
use Phan\CLI;
use Phan\CodeBase;
use Phan\CodeBase\ClassMap;
use Phan\CodeBase\InheritedMethodLink;
use Phan\Config;
use Phan\Daemon\Request;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\TypedElement;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\UnionType;
use Phan\Library\PhaseTimer;
use Phan\Library\Set;
use Phan\Output\Collector\BufferingCollector;
use Phan\Phan;
use Phan\Plugin\ConfigPluginSet;
use Phan\PluginV3;
use SplMinHeap;

/**
 * Creates the copies of inherited methods lazily (config `lazy_inherited_methods`).
 *
 * Without this, when a class is hydrated, Clazz::importAncestorClass() adds a copy (a clone) of every method of the
 * parent class to the class map of the class. A class extending a large base class declared in a file excluded
 * from analysis (e.g. a test framework) then holds a copy of every method of that base class, and most of them
 * are never used.
 *
 * With this, such an import adds a lazy entry (an InheritedMethodLink) instead of the copy, at the same position
 * of the class map, and the copy is created ("materialized") from a remembered clone of the parent's method
 * (a LazyInheritedMethodTemplate) the first time it is looked up by name, or when an API returns all methods.
 * Only copies whose root (the method an eager import would clone) is internal or declared in a file excluded from
 * analysis, and that pass the other checks of eligibility(), are lazy. Every other import, and every import after the
 * class phase, is eager.
 *
 * The copy is identical to the one an eager import would have created:
 * - Templates are captured while the root has the state it had at the import: each import compares the state versions
 *   of the root (TypedElement::getStateVersion()) with those recorded in its template, and captures a new
 *   template if they differ. Links resolve through the slot history of their parent's class map
 *   (ClassMap::entryAt()) to the template that was valid at their import.
 * - The changes that Clazz::addMethod() makes to each level of an eager copy are applied to the clone (applyLevel()).
 * - Each copy has the rank in the method set that its eager copy would have had (Method::getEagerOrdinal()),
 *   and Analysis::analyzeFunctions() processes the methods in that order, so the declaration analysis of a copy
 *   created in the method phase runs before that of every method that eagerly comes after it (if needed, in the
 *   middle of that method's analysis: see MethodDeclarationAnalyzer::analyzeNested()).
 * - A copy created after the method phase gets the declaration analysis (MethodDeclarationAnalyzer::analyzeMethodDeclaration())
 *   and the plugin closures of Analysis::loadMethodPlugins() (replayOps()) when it is created.
 *
 * This is disabled in modes where it was not verified or that would not benefit from it (decide()).
 *
 * Environment: PHAN_LAZY_INHERITED_METHODS=0|1 overrides the config setting, PHAN_DISABLE_LAZY_INHERIT=1 disables it,
 * and PHAN_LAZY_INHERIT_VERIFY=1 enables it (unless disabled) and verifies that the copies are identical to eager copies.
 *
 * @internal
 */
final class LazyInheritedMethods
{
    /** After this many templates of one root (its state kept changing in the class phase), its later imports are eager. */
    public const TEMPLATE_CAP = 8;

    /** The prefix of the PhaseTimer notes */
    public const NOTE_PREFIX = 'lazy_';

    /** @var CodeBase the code base that owns this registry */
    public $code_base;

    /** @var bool true while lazy entries may be created (from the start of hydration to the end of Analysis::analyzeClasses()) */
    public $creation_open = false;

    /**
     * @var bool true while copies created from lazy entries are added to the method set and processed in the method loop
     * of Analysis::analyzeFunctions() (until that loop is done)
     */
    public $registration_open = false;

    /** @var bool true with PHAN_LAZY_INHERIT_VERIFY=1 */
    public $verify = false;

    /** @var bool true once ConfigPluginSet::finalizeProcess() has started in this process */
    public $finalize_started = false;

    /** @var bool true while the method loop of Analysis::analyzeFunctions() runs */
    public $in_method_loop = false;

    /** @var int the eager ordinal of the method that the method loop is processing */
    public $current_ordinal = 0;

    /**
     * @var array{0:int,1:int,2:int,3:int} the side effects (class hydrations, scope initializations of user-defined
     * methods, methods added to the method set, loaded classes, see EmitOnlyChecks::getSideEffectCounts())
     * of the lazy-only steps: adding created copies to the method set and the declaration analysis of copies
     * run in the middle of another method's analysis or after the method phase.
     * EmitOnlyChecks::getSideEffectCounts() subtracts these, so that the checks comparing side effects
     * see the same counts as without lazy entries.
     */
    public $nested_effects = [0, 0, 0, 0];

    /** @var ?MethodDeclarationAnalyzer the analyzer of the copies processed outside of the method loop */
    public $analyzer = null;

    /** @var bool whether CloneReplay is enabled for the method loop */
    private $clone_replay = false;

    /** @var array<int,list<LazyInheritedMethodTemplate>> by spl_object_id() of the root: its templates, in capture order */
    private $templates = [];

    /** @var array<int,ClassMap> the class maps that have (or had) lazy entries, by spl_object_id() */
    private $maps_with_links = [];

    /** @var int the number of lazy entries in all class maps */
    private $lazy_entry_count = 0;

    /** @var list<Method> the copies created after the method loop, that are not in the method set */
    private $late_materialized = [];

    /** @var bool true if copies were added to the method set out of eager order since the public view last sorted it */
    private $set_is_unordered = false;

    /** @var array<string,int|float> the counters (PhaseTimer notes without the prefix) */
    private $counts = [];

    /** @var array<string,int> the callers of the materializations in the method loop (with phase timings) */
    private $loop_callers = [];

    /** @var int the number of violations found by PHAN_LAZY_INHERIT_VERIFY=1 in this process */
    private $violation_count = 0;

    /** @var int the number of violations found by PHAN_LAZY_INHERIT_VERIFY=1 in the analysis workers (in the parent) */
    private $worker_violation_count = 0;

    /** @var bool whether the first late internal class load was reported */
    private $reported_late_internal_load = false;

    // The state below is local to the analysis phase (torn down by endAnalysisPhase()).

    /**
     * @var list<array{0:string,1:string,2:Closure,3:?PluginV3}> the plugin closures of Analysis::loadMethodPlugins() that
     * were attached to the methods with a given defining FQSEN while some of their copies were still lazy:
     * [FQSEN string, 'return_type' or 'call', closure, plugin]
     */
    private $ops = [];

    /** @var array<string,true> the FQSEN strings of the methods that have their own return type override closure */
    private $own_return_type_override_keys = [];

    /** @var ?array{0:list<Closure>,1:ConfigPluginSet} the analyzer inputs of the AnalyzeCallableArgumentCapability plugins */
    private $callable_arg_state = null;

    /** @var array<int,?Closure> the callable argument analyzers by defining FQSEN (see Analysis::attachCallableArgumentAnalyzer()) */
    private $closures_by_defining_fqsen = [];

    /** @var SplMinHeap<array{0:int,1:Method}> the copies added to the method set and not processed yet, by eager ordinal */
    private $pending;

    /** @var array<int,true> the spl_object_id() of the copies in $pending that the method loop has not processed yet */
    private $pending_ids = [];

    /** @var array<int,true> the spl_object_id() of the copies whose declaration analysis ran outside of the loop body */
    private $processed = [];

    /** @var ?string the reason why the last call to activate() did not enable this, if it was requested */
    private static $last_disabled_reason = null;

    public function __construct(CodeBase $code_base)
    {
        $this->code_base = $code_base;
        $this->pending = new SplMinHeap();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Activation

    /**
     * Decide whether to create inherited method copies lazily for this analysis, and if so, create the registry
     * of $code_base and open the creation window. Called right before hydration is enabled.
     *
     * @param ?array<string,mixed> $path_filter
     */
    public static function activate(CodeBase $code_base, ?Request $request, ?array $path_filter, bool $has_incremental_manifest = false): void
    {
        [$is_requested, $reason] = self::decide($request, $path_filter, $has_incremental_manifest);
        self::$last_disabled_reason = $is_requested ? $reason : null;
        if ($reason !== null) {
            if ($is_requested) {
                PhaseTimer::note(self::NOTE_PREFIX . 'disabled_reason', $reason);
                if ($reason !== 'env_disabled') {
                    CLI::printToStderr("Phan: lazy_inherited_methods disabled: " . self::describeReason($reason) . "\n");
                }
            }
            return;
        }
        $lazy = new self($code_base);
        $lazy->verify = \getenv('PHAN_LAZY_INHERIT_VERIFY') === '1';
        $lazy->creation_open = true;
        $lazy->registration_open = true;
        $code_base->setLazyInherited($lazy);
    }

    /**
     * Returns [whether lazy inherited methods were requested, null if they are enabled or the reason why not].
     *
     * @param ?array<string,mixed> $path_filter
     * @return array{0:bool,1:?string}
     */
    public static function decide(?Request $request, ?array $path_filter, bool $has_incremental_manifest = false): array
    {
        if (\getenv('PHAN_DISABLE_LAZY_INHERIT') === '1') {
            return [self::isRequested(), 'env_disabled'];
        }
        if (!self::isRequested()) {
            return [false, 'not_requested'];
        }
        if ($request !== null) {
            return [true, 'request'];
        }
        if ($path_filter !== null) {
            return [true, 'path_filter'];
        }
        if (Config::get_track_references()) {
            return [true, 'track_references'];
        }
        if (Config::getValue('__analyze_twice')) {
            return [true, 'analyze_twice'];
        }
        if (Config::getValue('__analyze_until_convergence')) {
            return [true, 'analyze_until_convergence'];
        }
        if (Config::getValue('dump_matching_functions')) {
            return [true, 'dump_matching_functions'];
        }
        if ($has_incremental_manifest) {
            return [true, 'incremental'];
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $unmarked = ConfigPluginSet::instance()->getAnalyzeMethodPluginClassesWithoutLazyMarker();
        if ($unmarked) {
            return [true, 'unmarked_plugin:' . \implode(',', $unmarked)];
        }
        return [true, null];
    }

    /**
     * Whether lazy inherited methods were requested (config, PHAN_LAZY_INHERITED_METHODS or PHAN_LAZY_INHERIT_VERIFY)
     */
    public static function isRequested(): bool
    {
        if (\getenv('PHAN_LAZY_INHERIT_VERIFY') === '1') {
            return true;
        }
        $env = \getenv('PHAN_LAZY_INHERITED_METHODS');
        if ($env === '0' || $env === '1') {
            return $env === '1';
        }
        return (bool)Config::getValue('lazy_inherited_methods');
    }

    /**
     * Returns the reason why the last activation did not enable lazy inherited methods although they were requested.
     */
    public static function getLastDisabledReason(): ?string
    {
        return self::$last_disabled_reason;
    }

    private static function describeReason(string $reason): string
    {
        if (\str_starts_with($reason, 'unmarked_plugin:')) {
            $plugins = \str_replace(',', ', ', \substr($reason, \strlen('unmarked_plugin:')));
            return "AnalyzeMethodCapability plugin(s) $plugins do not implement AnalyzeMethodSkipsInheritedCopiesCapability";
        }
        $descriptions = [
            'request' => 'daemon mode and the language server analyze some files only',
            'path_filter' => 'only some files are analyzed',
            'track_references' => 'dead code detection or reference tracking is enabled',
            'analyze_twice' => '--analyze-twice is used',
            'analyze_until_convergence' => '--analyze-until-convergence is used',
            'dump_matching_functions' => 'dump_matching_functions is used',
            'incremental' => 'incremental analysis is used',
        ];
        return $descriptions[$reason] ?? $reason;
    }

    /**
     * Called when Analysis::analyzeClasses() is done: later imports are eager.
     */
    public function closeCreationWindow(): void
    {
        $this->creation_open = false;
        $this->count('links_at_class_phase_end', $this->lazy_entry_count, true);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Eligibility and creation (called by Clazz::importAncestorClass() for parent imports)

    /**
     * The per-import part of the eligibility: may the methods that $class imports from its parent class $parent be lazy?
     * (steps 1-5 of the eligibility, see LazyInheritedMethodsEligibility)
     *
     * @param int $entry_count the number of entries of the parent's class map (for the counters)
     */
    public function canCreateLinksFor(Clazz $class, Clazz $parent, int $entry_count): bool
    {
        $reason = $this->creation_open ? LazyInheritedMethodsEligibility::getReasonForImportInWindow($this->code_base, $class, $parent) : 'window';
        if ($reason === null) {
            return true;
        }
        if ($entry_count > 0 && $reason !== 'window') {
            $this->count("ineligible_$reason", $entry_count);
        }
        return false;
    }

    /**
     * The per-entry part of the eligibility (steps 6-12): may the copy of the parent's entry $entry under the class map key
     * $lowercase_name be lazy? If it may, the template of its root was checked or captured for the import $seq.
     *
     * @param Method|InheritedMethodLink $entry the entry of the parent's class map
     * @return ?string null if the copy may be lazy, otherwise the reason why not
     * @suppress PhanAccessMethodInternal
     */
    public function eligibility(ClassMap $class_map, ClassMap $parent_map, string $lowercase_name, Method|InheritedMethodLink $entry, int $seq): ?string
    {
        if ($lowercase_name === '__construct') {
            return $this->ineligible('construct');
        }
        if (\str_contains($lowercase_name, ',') || $parent_map->entry("$lowercase_name,1") !== null) {
            return $this->ineligible('alternate');
        }
        if ($class_map->entry($lowercase_name) !== null) {
            // A declared method, (at)method, or a copy from an interface, a trait or a trait alias.
            return $this->ineligible('multi_provider');
        }
        if ($entry instanceof InheritedMethodLink) {
            // The parent inherited it lazily: the root and its template were checked when the parent's link was created,
            // and nothing can have changed the parent's copy since (any access to it would have created it).
            return null;
        }
        $reason = LazyInheritedMethodsEligibility::getReasonForRoot($this->code_base, $entry);
        if ($reason !== null) {
            return $this->ineligible($reason);
        }
        if (!$this->templateForImport($entry, $seq)) {
            return $this->ineligible('template_cap');
        }
        return null;
    }

    private function ineligible(string $reason): string
    {
        $this->count("ineligible_$reason");
        return $reason;
    }

    /**
     * Create the link for the lazy entries of the import $seq of the parent class $parent by $class.
     */
    public function newLink(Clazz $class, ClassMap $class_map, ClassMap $parent_map, Clazz $parent, int $seq): InheritedMethodLink
    {
        $link = new InheritedMethodLink($this, $class, $class_map, $parent_map, $parent, $seq);
        // @phan-suppress-next-line PhanAccessMethodInternal
        $parent_map->addDependentLink($link);
        $this->count('link_objects');
        return $link;
    }

    /**
     * Add a lazy entry of $link under $lowercase_name to the class map of its class, where Clazz::addMethod()
     * would have added the copy of $entry (the parent's entry).
     */
    public function addLazySlot(InheritedMethodLink $link, string $lowercase_name, Method|InheritedMethodLink $entry): void
    {
        $code_base = $this->code_base;
        // @phan-suppress-next-line PhanAccessMethodInternal
        $link->ordinals[$lowercase_name] = $code_base->nextEagerOrdinal();
        $link->unresolved++;
        $class_map = $link->class_map;
        // @phan-suppress-next-line PhanAccessMethodInternal
        $class_map->addLazyEntry($lowercase_name, $link);
        $this->maps_with_links[\spl_object_id($class_map)] = $class_map;
        $this->lazy_entry_count++;
        $this->count('links');
        if ($this->verify || $entry instanceof Method) {
            [$template] = $this->resolveChain($link, $lowercase_name);
            if ($template->link_target !== null && $entry instanceof Method) {
                $this->count('links_relaxed_return_link');
            }
            if ($this->verify) {
                $this->verifyCreatedSlot($link, $lowercase_name, $entry);
            }
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Templates

    /**
     * Returns the template of $root for the import $seq: the current template if $root still has the state it had
     * when that was captured, otherwise a new one (or null after TEMPLATE_CAP templates).
     */
    private function templateForImport(Method $root, int $seq): ?LazyInheritedMethodTemplate
    {
        $id = \spl_object_id($root);
        $list = $this->templates[$id] ?? null;
        if ($list) {
            $last = $list[\count($list) - 1];
            if ($last->superseded_seq === \PHP_INT_MAX) {
                if (self::matchesTemplate($root, $last)) {
                    if ($this->verify) {
                        $this->verifyTemplateSnapshot($root, $last);
                    }
                    return $last;
                }
                $last->superseded_seq = $seq;
                $this->count('template_recaptures');
            }
            if (\count($list) >= self::TEMPLATE_CAP) {
                return null;
            }
        }
        $template = clone($root);
        // (What Clazz::addMethod() does to the parent's method, through Method::cloneWithTemplateParameterTypeMap([]))
        // @phan-suppress-next-line PhanAccessMethodInternal
        $template->cloneParameterList();
        // @phan-suppress-next-line PhanAccessMethodInternal
        $t = new LazyInheritedMethodTemplate($root, $template, $root->getReturnTypeLinkOfClone(), $seq, LazyInheritedMethodsEligibility::getStateVersionsOfRoot($root));
        if ($this->verify) {
            $t->snapshot = self::snapshotRoot($root);
        }
        $this->templates[$id][] = $t;
        $this->count('templates');
        return $t;
    }

    private static function matchesTemplate(Method $root, LazyInheritedMethodTemplate $t): bool
    {
        // The link decision also reads the return type of the link target, which may be another method (Method::getReturnTypeLinkOfClone())
        // @phan-suppress-next-line PhanAccessMethodInternal
        return LazyInheritedMethodsEligibility::getStateVersionsOfRoot($root) === $t->versions && $root->getReturnTypeLinkOfClone() === $t->link_target;
    }

    /**
     * Returns the template of $root that was valid at the import $seq.
     */
    private function templateFor(Method $root, int $seq): LazyInheritedMethodTemplate
    {
        foreach ($this->templates[\spl_object_id($root)] ?? [] as $t) {
            if ($t->captured_seq <= $seq && $seq < $t->superseded_seq && $t->root === $root) {
                return $t;
            }
        }
        throw new AssertionError("No template of {$root->getFQSEN()} for the import $seq");
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Materialization

    /**
     * Returns the levels of the chain of links that the slot $lowercase_name of $link's class resolves through
     * (from the import of the root's class down to $link) and the template of the root for the top level.
     *
     * @return array{0:LazyInheritedMethodTemplate,1:non-empty-list<InheritedMethodLink>}
     */
    private function resolveChain(InheritedMethodLink $link, string $lowercase_name): array
    {
        $levels = [$link];
        // @phan-suppress-next-line PhanAccessMethodInternal
        $value = $link->parent_map->entryAt($lowercase_name, $link->seq);
        while ($value instanceof InheritedMethodLink) {
            $levels[] = $value;
            // @phan-suppress-next-line PhanAccessMethodInternal
            $value = $value->parent_map->entryAt($lowercase_name, $value->seq);
        }
        if (!$value instanceof Method) {
            throw new AssertionError("The lazy entry $lowercase_name of {$link->class->getFQSEN()} resolves to nothing");
        }
        $top = $levels[\count($levels) - 1];
        return [$this->templateFor($value, $top->seq), \array_reverse($levels)];
    }

    /**
     * Returns information about the lazy entry $lowercase_name of $link for read-only questions that a copy answers
     * like its template (name, visibility, flags, defining FQSENs, node, parameters, real signature, file):
     * [the root, the template (do not modify it, and do not use its FQSEN), the class of the slot].
     *
     * @return array{0:Method,1:Method,2:Clazz}
     */
    public function view(InheritedMethodLink $link, string $lowercase_name): array
    {
        [$t] = $this->resolveChain($link, $lowercase_name);
        return [$t->root, $t->template, $link->class];
    }

    /**
     * Returns the template of the lazy entry $lowercase_name of $link (see view()).
     */
    public function templateOf(InheritedMethodLink $link, string $lowercase_name): Method
    {
        return $this->resolveChain($link, $lowercase_name)[0]->template;
    }

    /**
     * Create the copy for the lazy entry $lowercase_name of $map (whose value is $link), put it in the slot and register it.
     *
     * @param string $trigger what caused the materialization (for counters)
     */
    public function materialize(ClassMap $map, string $lowercase_name, InheritedMethodLink $link, string $trigger): Method
    {
        [$t, $levels] = $this->resolveChain($link, $lowercase_name);
        $method = $this->flatten($t, $levels);
        $this->register($map, $lowercase_name, $link, $method, $trigger);
        return $method;
    }

    /**
     * Returns a new copy for the chain $levels, equal to the copy that the eager imports of that chain would have created.
     *
     * @param non-empty-list<InheritedMethodLink> $levels
     */
    private function flatten(LazyInheritedMethodTemplate $t, array $levels): Method
    {
        $method = clone($t->template);
        // (Method::cloneWithTemplateParameterTypeMap([]))
        // @phan-suppress-next-line PhanAccessMethodInternal
        $method->cloneParameterList();
        foreach ($levels as $i => $level) {
            $this->applyLevel($method, $level->class, $i === 0 ? $t : null);
        }
        return $method;
    }

    /**
     * Apply the changes that Clazz::addMethod() makes to the copy of a method that $level_class inherits from its parent
     * (for a parent import of a class that does not have the method yet: no override, not an interface).
     */
    private function applyLevel(Method $method, Clazz $level_class, ?LazyInheritedMethodTemplate $top): void
    {
        $method->setFQSEN(FullyQualifiedMethodName::make($level_class->getFQSEN(), $method->getName(), 0));
        $method->setIsOverride(false);
        $method->setIsOverriddenByAnother(false);
        if ($top !== null) {
            // Only the first level can take the return type link (Method::ensureClonesReturnType()), and the decision
            // was taken when the template was captured, from the state of the link target then.
            // @phan-suppress-next-line PhanAccessMethodInternal
            $method->setReturnTypeLinkForLazyCopy($top->link_target);
            // @phan-suppress-next-line PhanAccessMethodInternal
            $method->setCloneSource($top->root);
        }
        if ($method->hasYield()) {
            $new_type = UnionType::fromFullyQualifiedRealString('\\Generator');
            if (!$new_type->canCastToUnionType($method->getUnionType(), $this->code_base)) {
                $method->setUnionType($new_type);
            }
        }
        if ($level_class->hasNoNamedArguments()) {
            $method->setHasNoNamedArguments();
        }
    }

    /**
     * Put the copy $method in its slot and register it like an eagerly created copy.
     */
    private function register(ClassMap $map, string $lowercase_name, InheritedMethodLink $link, Method $method, string $trigger): void
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        $map->replaceEntry($lowercase_name, $method);
        $ordinal = $link->ordinals[$lowercase_name];
        unset($link->ordinals[$lowercase_name]);
        $link->unresolved--;
        $this->lazy_entry_count--;
        // @phan-suppress-next-line PhanAccessMethodInternal
        $method->setEagerOrdinal($ordinal);
        self::releaseLinkIfDone($link);
        $code_base = $this->code_base;
        if ($this->registration_open) {
            $phase = $this->creation_open ? 'class' : 'loop';
            $this->count("materialized_{$phase}_$trigger");
            if ($phase === 'loop' && PhaseTimer::$enabled) {
                $this->recordLoopCaller();
            }
            // @phan-suppress-next-line PhanAccessMethodInternal
            $code_base->addMethodToMethodSet($method);
            $this->nested_effects[2]++;
            $this->set_is_unordered = true;
            if ($this->in_method_loop && $ordinal < $this->current_ordinal) {
                // An eager loop would already have processed this copy: do it now.
                MethodDeclarationAnalyzer::analyzeNested($code_base, $method);
            } else {
                $this->pending->insert([$ordinal, $method]);
                $this->pending_ids[\spl_object_id($method)] = true;
            }
            return;
        }
        $this->count("materialized_late_$trigger");
        $this->late_materialized[] = $method;
        MethodDeclarationAnalyzer::analyzeMethodDeclaration($code_base, $method);
        $this->replayOps($method);
    }

    /**
     * Forget the lazy entry $lowercase_name of $link, which was replaced by another method without being created.
     * (Phan looks a method up before replacing it, which creates the copy, so this is only a safeguard.)
     */
    public function discardLazySlot(InheritedMethodLink $link, string $lowercase_name): void
    {
        if (!isset($link->ordinals[$lowercase_name])) {
            return;
        }
        unset($link->ordinals[$lowercase_name]);
        $link->unresolved--;
        $this->lazy_entry_count--;
        $this->count('discarded_links');
        self::releaseLinkIfDone($link);
    }

    /**
     * Release $link from its parent map's dependents once neither a lazy slot nor a slot history record needs it.
     */
    private static function releaseLinkIfDone(InheritedMethodLink $link): void
    {
        $work = [$link];
        while ($work) {
            $current = \array_pop($work);
            if ($current->unresolved > 0 || $current->held_by_history > 0) {
                continue;
            }
            // @phan-suppress-next-line PhanAccessMethodInternal
            foreach ($current->parent_map->removeDependentLink($current) as $released) {
                $work[] = $released;
            }
        }
    }

    /**
     * Returns the next sequence number for a change of a class map slot (see ClassMap::entryAt())
     */
    public function nextSlotChangeSeq(): int
    {
        $this->count('history_records');
        // @phan-suppress-next-line PhanAccessMethodInternal
        return $this->code_base->nextLazySeq();
    }

    /**
     * Count a slot history record that was dropped because no link needs it any more.
     */
    public function countHistoryPruned(): void
    {
        $this->count('history_pruned');
    }

    /**
     * Materialize the lazy entries of $map in ascending eager ordinal.
     */
    public function materializeAllOf(ClassMap $map, string $trigger): void
    {
        $this->materializeEntries(self::collectLazyEntries([$map]), $trigger);
    }

    /**
     * Materialize the lazy entries of the class $fqsen (if any).
     */
    public function materializeAllOfClass(FullyQualifiedClassName $fqsen, string $trigger): void
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        $map = $this->code_base->getClassMapForLazyInheritance($fqsen);
        // @phan-suppress-next-line PhanAccessMethodInternal
        if ($map->hasLazyEntries()) {
            $this->materializeAllOf($map, $trigger);
        }
    }

    /**
     * Materialize every lazy entry, in ascending eager ordinal.
     */
    public function materializeAll(string $trigger): void
    {
        if ($this->lazy_entry_count <= 0) {
            return;
        }
        $this->count("materialize_all_$trigger");
        $entries = self::collectLazyEntries($this->maps_with_links);
        if ($this->verify) {
            CLI::printToStderr(\sprintf("PHAN_LAZY_INHERIT_VERIFY: creating the %d remaining lazily inherited methods (%s)\n", \count($entries), $trigger));
        }
        $this->materializeEntries($entries, $trigger);
    }

    /**
     * @param iterable<ClassMap> $maps
     * @return list<array{0:int,1:ClassMap,2:string}> [eager ordinal, map, key] of the lazy entries of $maps, ascending
     */
    private static function collectLazyEntries(iterable $maps): array
    {
        $entries = [];
        foreach ($maps as $map) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            if (!$map->hasLazyEntries()) {
                continue;
            }
            // @phan-suppress-next-line PhanAccessMethodInternal
            foreach ($map->getMethodEntryMap() as $lowercase_name => $entry) {
                if ($entry instanceof InheritedMethodLink) {
                    $lowercase_name = (string)$lowercase_name;
                    $entries[] = [$entry->ordinals[$lowercase_name], $map, $lowercase_name];
                }
            }
        }
        \usort($entries, /**
         * @param array{0:int,1:ClassMap,2:string} $a
         * @param array{0:int,1:ClassMap,2:string} $b
         */ static function (array $a, array $b): int {
            return $a[0] <=> $b[0];
        });
        return $entries;
    }

    /**
     * @param list<array{0:int,1:ClassMap,2:string}> $entries
     */
    private function materializeEntries(array $entries, string $trigger): void
    {
        foreach ($entries as [, $map, $lowercase_name]) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $entry = $map->entry($lowercase_name);
            if ($entry instanceof InheritedMethodLink) {
                $this->materialize($map, $lowercase_name, $entry, $trigger);
            }
        }
    }

    /**
     * Returns the copies created after the method loop that are not in the method set yet (see getMethodSetInEagerOrder()).
     *
     * @return list<Method>
     */
    public function getLateMaterializedMethods(): array
    {
        return $this->late_materialized;
    }

    /**
     * Returns the number of lazy entries in all class maps.
     */
    public function getLazyEntryCount(): int
    {
        return $this->lazy_entry_count;
    }

    /**
     * The method set for the public APIs (CodeBase::getMethodSet() etc.): materializes every lazy entry, then returns
     * the methods in eager order (by Method::getEagerOrdinal(), as they were or would have been added).
     * Outside of the method loop, the sorted set replaces the method set (through $replace_method_set).
     *
     * @param Set<Method> $method_set
     * @param Closure(Set<Method>):void $replace_method_set
     * @return Set<Method>
     */
    public function getMethodSetInEagerOrder(Set $method_set, string $caller, Closure $replace_method_set): Set
    {
        $this->materializeAll($caller);
        if (!$this->set_is_unordered && !$this->late_materialized) {
            return $method_set;
        }
        $methods = \iterator_to_array($method_set, false);
        foreach ($this->late_materialized as $method) {
            $methods[] = $method;
        }
        \usort($methods, static function (Method $a, Method $b): int {
            // @phan-suppress-next-line PhanAccessMethodInternal
            return $a->getEagerOrdinal() <=> $b->getEagerOrdinal();
        });
        $sorted = new Set();
        foreach ($methods as $method) {
            $sorted->offsetSet($method);
        }
        if (!$this->in_method_loop) {
            $replace_method_set($sorted);
            $this->late_materialized = [];
            $this->set_is_unordered = false;
        }
        return $sorted;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The method loop (Analysis::analyzeFunctions())

    /**
     * Called before the method loop of Analysis::analyzeFunctions().
     */
    public function beginMethodLoop(MethodDeclarationAnalyzer $analyzer, bool $clone_replay): void
    {
        $this->analyzer = $analyzer;
        $this->clone_replay = $clone_replay;
        $this->in_method_loop = true;
        $this->current_ordinal = 0;
    }

    /**
     * Called by the method loop before it processes $method: processes the pending copies that come before it
     * in eager order. Returns true if $method was processed already (the loop must skip it).
     */
    public function beginLoopMethod(Method $method): bool
    {
        $id = \spl_object_id($method);
        if (isset($this->processed[$id])) {
            return true;
        }
        // @phan-suppress-next-line PhanAccessMethodInternal
        $ordinal = $method->getEagerOrdinal();
        $this->drainPendingBefore($ordinal);
        unset($this->pending_ids[$id]);
        $this->current_ordinal = $ordinal;
        return false;
    }

    /**
     * Called after the method loop: processes the remaining pending copies and closes the registration window.
     */
    public function endMethodLoop(): void
    {
        $this->drainPendingBefore(\PHP_INT_MAX);
        $this->in_method_loop = false;
        $this->registration_open = false;
        $this->count('remaining_links_after_method_loop', $this->lazy_entry_count, true);
    }

    /**
     * Process the pending copies whose eager ordinal is below $ordinal, in ascending order.
     */
    private function drainPendingBefore(int $ordinal): void
    {
        $pending = $this->pending;
        while (!$pending->isEmpty()) {
            [$pending_ordinal, $method] = $pending->top();
            if ($pending_ordinal >= $ordinal) {
                return;
            }
            $pending->extract();
            $id = \spl_object_id($method);
            if (!isset($this->pending_ids[$id])) {
                continue;
            }
            unset($this->pending_ids[$id]);
            $this->count('pending_drained');
            $previous_ordinal = $this->current_ordinal;
            $this->current_ordinal = $pending_ordinal;
            try {
                $this->processCopy($method);
            } finally {
                $this->current_ordinal = $previous_ordinal;
            }
        }
    }

    /**
     * Run the declaration analysis of the copy $method like the body of the method loop does.
     * (Used for the pending copies processed between two methods, and nested in the analysis of another method.)
     */
    public function processCopy(Method $method): void
    {
        $this->processed[\spl_object_id($method)] = true;
        if ($method->isPHPInternal()) {
            return;
        }
        $code_base = $this->code_base;
        $analyzer = $this->analyzer ??= MethodDeclarationAnalyzer::forLateUse($code_base);
        EmitOnlyChecks::beginAnalyzingMethod($code_base, $method);
        try {
            $analyzer->analyze($method, $this->clone_replay ? CloneReplay::beginAnalyzingMethod($code_base, $method) : null);
        } finally {
            EmitOnlyChecks::endAnalyzingMethod();
            if (CloneReplay::$has_pending_method) {
                CloneReplay::endAnalyzingMethod();
            }
        }
    }

    /**
     * Record that a nested or late pipeline had the side effects $delta (see $nested_effects)
     *
     * @param array{0:int,1:int,2:int,3:int} $delta
     */
    public function addNestedEffects(array $delta): void
    {
        foreach ($delta as $i => $d) {
            $this->nested_effects[$i] += $d;
        }
    }

    /**
     * Count the internal classes loaded by the declaration analysis of a copy created after the method loop
     * (the classes it looks up were already looked up by its root, so this should be 0).
     */
    public function countLateInternalLoads(Method $method, int $count): void
    {
        $this->count('late_internal_loads', $count);
        if (!$this->reported_late_internal_load) {
            $this->reported_late_internal_load = true;
            CLI::printToStderr("Phan: lazy_inherited_methods: the declaration analysis of {$method->getFQSEN()} (created late) loaded $count class(es)\n");
        }
        if ($this->verify) {
            $this->recordViolation($method, 'late pipeline', "loaded $count class(es)");
        }
    }

    private function recordLoopCaller(): void
    {
        $frames = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        $caller = '?';
        foreach ($frames as $frame) {
            $class = $frame['class'] ?? '';
            if ($class === self::class || $class === ClassMap::class || $class === CodeBase::class) {
                continue;
            }
            $caller = ($class !== '' ? \substr((string)\strrchr('\\' . $class, '\\'), 1) . '::' : '') . $frame['function'];
            break;
        }
        if (isset($this->loop_callers[$caller]) || \count($this->loop_callers) < 100) {
            $this->loop_callers[$caller] = ($this->loop_callers[$caller] ?? 0) + 1;
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Plugin closures (Analysis::loadMethodPlugins())

    /**
     * Called at the start of Analysis::loadMethodPlugins()
     *
     * @param array<string,true> $own_return_type_override_keys the FQSEN strings of the methods with their own return type override
     */
    public function beginLoadMethodPlugins(array $own_return_type_override_keys): void
    {
        $this->own_return_type_override_keys = $own_return_type_override_keys;
    }

    /**
     * Record that a plugin closure was attached to the methods defined by $fqsen while some of their copies were still
     * lazy: those get it when they are created (replayOps()).
     *
     * @param string $kind 'return_type' or 'call'
     */
    public function recordOp(FullyQualifiedMethodName $fqsen, string $kind, Closure $closure, ?PluginV3 $plugin): void
    {
        $this->ops[] = [$fqsen->__toString(), $kind, $closure, $plugin];
        $this->count('ops_recorded');
    }

    /**
     * Called after Analysis::loadMethodPlugins() attached the analyzers of the AnalyzeCallableArgumentCapability plugins
     * to the method set: attach them to the copies created after the method loop, and to those created later.
     *
     * @param list<Closure> $plugin_closures
     * @param array<int,?Closure> $closures_by_defining_fqsen
     */
    public function setCallableArgumentState(array $plugin_closures, array $closures_by_defining_fqsen, ConfigPluginSet $plugin_set): void
    {
        $this->callable_arg_state = [$plugin_closures, $plugin_set];
        $this->closures_by_defining_fqsen = $closures_by_defining_fqsen;
        foreach ($this->late_materialized as $method) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            Analysis::attachCallableArgumentAnalyzer($this->code_base, $method, $plugin_closures, $this->closures_by_defining_fqsen, $plugin_set);
        }
    }

    /**
     * Give the copy $method, created after the method loop, the plugin closures that Analysis::loadMethodPlugins()
     * gave the methods with its defining FQSEN, in the same order.
     */
    public function replayOps(Method $method): void
    {
        if ($this->ops) {
            $fqsen_string = $method->getFQSEN()->__toString();
            $defining_fqsen_string = $method->getDefiningFQSEN()->__toString();
            $real_defining_fqsen_string = $method->getRealDefiningFQSEN()->__toString();
            foreach ($this->ops as [$op_fqsen_string, $kind, $closure, $plugin]) {
                if ($op_fqsen_string !== $defining_fqsen_string && $op_fqsen_string !== $real_defining_fqsen_string) {
                    continue;
                }
                if ($kind === 'return_type') {
                    if (isset($this->own_return_type_override_keys[$fqsen_string])) {
                        // An override closure targeting SubClass::foo takes precedence over BaseClass::foo
                        continue;
                    }
                    $method->setDependentReturnTypeClosure($closure);
                } else {
                    $method->addFunctionCallAnalyzer($closure, $plugin);
                }
                $this->count('ops_replayed');
            }
        }
        if ($this->callable_arg_state !== null) {
            [$plugin_closures, $plugin_set] = $this->callable_arg_state;
            // @phan-suppress-next-line PhanAccessMethodInternal
            Analysis::attachCallableArgumentAnalyzer($this->code_base, $method, $plugin_closures, $this->closures_by_defining_fqsen, $plugin_set);
        }
    }

    /**
     * Called at the end of Analysis::loadMethodPlugins(). With PHAN_LAZY_INHERIT_VERIFY=1, create every remaining copy
     * now, through the same steps as a copy created later (which checks that they have no side effects),
     * and check that the methods each copy overrides are those of its root.
     */
    public function endLoadMethodPlugins(): void
    {
        if (!$this->verify) {
            return;
        }
        $roots = [];
        $entries = self::collectLazyEntries($this->maps_with_links);
        foreach ($entries as [, $map, $lowercase_name]) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $entry = $map->entry($lowercase_name);
            if ($entry instanceof InheritedMethodLink) {
                $roots[] = [$this->resolveChain($entry, $lowercase_name)[0]->root, $map, $lowercase_name];
            }
        }
        $this->materializeAll('verify');
        $this->count('verify_materialized_all', \count($roots), true);
        foreach ($roots as [$root, $map, $lowercase_name]) {
            // @phan-suppress-next-line PhanAccessMethodInternal
            $copy = $map->entry($lowercase_name);
            if (!$copy instanceof Method) {
                continue;
            }
            if ($root->isPHPInternal()) {
                // The method loop never analyzes copies of internal methods, so eager mode never computes their overridden
                // methods; computing them here would initialize the scopes of the methods they override (a side effect
                // that eager mode does not have, visible in the extended state digest).
                continue;
            }
            try {
                $expected = self::describeOverriddenMethods($root->getOverriddenMethods($this->code_base));
                $actual = self::describeOverriddenMethods($copy->getOverriddenMethods($this->code_base));
            } catch (\Exception $e) {
                $this->recordViolation($copy, 'overridden methods', 'threw ' . \get_class($e) . ': ' . $e->getMessage());
                continue;
            }
            if ($expected !== $actual) {
                $this->recordViolation($copy, 'overridden methods', "differ from those of its root {$root->getFQSEN()}: $actual, expected $expected");
            }
        }
    }

    /**
     * @param list<Method>|array<int,Method> $methods
     */
    private static function describeOverriddenMethods(array $methods): string
    {
        return \implode(',', \array_map(static function (Method $method): string {
            return $method->getDefiningFQSEN()->__toString();
        }, $methods));
    }

    /**
     * Called right before ConfigPluginSet::finalizeProcess() in each process.
     */
    public function beginFinalize(): void
    {
        $this->finalize_started = true;
    }

    /**
     * Called after ConfigPluginSet::finalizeProcess() in each process: tear down the state of the analysis phase.
     * Lookups still materialize copies (with their declaration analysis, without plugins and plugin closures).
     */
    public function endAnalysisPhase(): void
    {
        $this->ops = [];
        $this->own_return_type_override_keys = [];
        $this->callable_arg_state = null;
        $this->closures_by_defining_fqsen = [];
        $this->pending = new SplMinHeap();
        $this->pending_ids = [];
        $this->processed = [];
        $this->analyzer = null;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Counters

    /**
     * @param bool $set true to set the counter to $amount instead of adding $amount
     */
    private function count(string $name, int|float $amount = 1, bool $set = false): void
    {
        $this->counts[$name] = $set ? $amount : (($this->counts[$name] ?? 0) + $amount);
    }

    /**
     * Count something that happened outside of this class (e.g. a late pipeline, from MethodDeclarationAnalyzer)
     */
    public function addToCounter(string $name, int|float $amount = 1): void
    {
        $this->count($name, $amount);
    }

    /**
     * @return array<string,int|float> the counters, by PhaseTimer note name
     */
    public function getCounts(): array
    {
        $result = [];
        foreach ($this->counts as $name => $value) {
            $result[self::NOTE_PREFIX . $name] = $value;
        }
        $result[self::NOTE_PREFIX . 'remaining_links'] = $this->lazy_entry_count;
        if ($this->verify) {
            $result[self::NOTE_PREFIX . 'verify_violations'] = $this->violation_count;
        }
        return $result;
    }

    /**
     * Write the counters as PhaseTimer notes (called before the results are displayed, and at the end of each worker).
     */
    public function recordNotes(): void
    {
        if (!PhaseTimer::$enabled) {
            return;
        }
        foreach ($this->getCounts() as $name => $value) {
            PhaseTimer::note($name, \is_float($value) ? \round($value, 6) : $value);
        }
        \arsort($this->loop_callers);
        foreach ($this->loop_callers as $caller => $count) {
            PhaseTimer::note(self::NOTE_PREFIX . "loop_caller_$caller", $count);
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Verify mode (PHAN_LAZY_INHERIT_VERIFY=1)

    /**
     * Record a violation found by PHAN_LAZY_INHERIT_VERIFY=1.
     */
    public function recordViolation(FunctionInterface $element, string $label, string $details): void
    {
        $this->violation_count++;
        EmitOnlyChecks::recordViolation($element, $label, $details, 'PHAN_LAZY_INHERIT_VERIFY');
    }

    /**
     * Returns the number of violations found by PHAN_LAZY_INHERIT_VERIFY=1 so far.
     */
    public function getViolationCount(): int
    {
        return $this->violation_count;
    }

    /**
     * Record the number of violations that the analysis workers found (called in the parent, with --processes N).
     */
    public function addWorkerViolations(int $count): void
    {
        $this->worker_violation_count += $count;
    }

    /**
     * With PHAN_LAZY_INHERIT_VERIFY=1, print the summary and return true if violations were found in this process
     * or in an analysis worker (the caller exits with an error).
     */
    public function reportVerifyResults(): bool
    {
        if (!$this->verify) {
            return false;
        }
        $violation_count = $this->violation_count + $this->worker_violation_count;
        CLI::printToStderr(\sprintf(
            "PHAN_LAZY_INHERIT_VERIFY: %d lazy entries created, %d checked at creation, %d template guards checked exhaustively, %d created by the final check: %d violation(s)%s\n",
            $this->counts['links'] ?? 0,
            $this->counts['verify_created_checked'] ?? 0,
            $this->counts['verify_exhaustive_checked'] ?? 0,
            $this->counts['verify_materialized_all'] ?? 0,
            $violation_count,
            $this->worker_violation_count > 0 ? " ({$this->worker_violation_count} in analysis workers)" : ''
        ));
        return $violation_count > 0;
    }

    /**
     * Verify check 1: the copy that the lazy entry just created will produce equals the copy that the eager import would
     * have created now (both built detached, without registering them).
     *
     * @param Method|InheritedMethodLink $entry the parent's entry
     */
    private function verifyCreatedSlot(InheritedMethodLink $link, string $lowercase_name, Method|InheritedMethodLink $entry): void
    {
        $this->count('verify_created_checked');
        try {
            if ($entry instanceof InheritedMethodLink) {
                [$parent_template, $parent_levels] = $this->resolveChain($entry, $lowercase_name);
                $original = $this->flatten($parent_template, $parent_levels);
            } else {
                $original = $entry;
            }
            $eager = $this->buildEagerCopy($original, $link->class);
            [$t, $levels] = $this->resolveChain($link, $lowercase_name);
            $lazy = $this->flatten($t, $levels);
        } catch (\Throwable $e) {
            if (!$entry instanceof Method) {
                throw $e;
            }
            $this->recordViolation($entry, 'created lazy entry', 'threw ' . \get_class($e) . ': ' . $e->getMessage());
            return;
        }
        $difference = self::compareObjects($eager, $lazy, ['state_version', 'eager_ordinal', 'clone_source'], true);
        if ($difference !== null) {
            $this->recordViolation($eager, 'created lazy entry', "differs from the eager copy: $difference");
        }
    }

    /**
     * Returns the copy of $original that Clazz::addMethod() would add to $class (for a parent import, no override,
     * a class without template parameters) without adding it.
     */
    private function buildEagerCopy(Method $original, Clazz $class): Method
    {
        $method = $original->cloneWithTemplateParameterTypeMap([]);
        $method->setFQSEN(FullyQualifiedMethodName::make($class->getFQSEN(), $original->getName(), $original->getFQSEN()->getAlternateId()));
        $method->setIsOverride(false);
        $method->setIsOverriddenByAnother(false);
        $method->ensureClonesReturnType($original);
        // @phan-suppress-next-line PhanAccessMethodInternal
        $method->setCloneSource($original);
        if ($method->hasYield()) {
            $new_type = UnionType::fromFullyQualifiedRealString('\\Generator');
            if (!$new_type->canCastToUnionType($method->getUnionType(), $this->code_base)) {
                $method->setUnionType($new_type);
            }
        }
        if ($class->hasNoNamedArguments()) {
            $method->setHasNoNamedArguments();
        }
        return $method;
    }

    /**
     * Compares the properties of two objects of the same class: objects by identity, except the internal scope and
     * the parameters (compared the same way) if $recurse; other values with ===.
     *
     * @param list<string> $ignored_properties
     * @return ?string a description of the first difference, or null if there is none
     */
    private static function compareObjects(object $a, object $b, array $ignored_properties, bool $recurse): ?string
    {
        if (\get_class($a) !== \get_class($b)) {
            return 'class ' . \get_class($a) . ' !== ' . \get_class($b);
        }
        $a_props = (array)$a;
        $b_props = (array)$b;
        foreach ($a_props as $key => $value) {
            $name = self::propertyName((string)$key);
            if (\in_array($name, $ignored_properties, true)) {
                continue;
            }
            if (!\array_key_exists($key, $b_props)) {
                return "property $name is missing";
            }
            $other = $b_props[$key];
            if ($recurse && \is_object($value) && \is_object($other) && $name === 'internal_scope') {
                $difference = self::compareObjects($value, $other, ['state_version'], false);
                if ($difference !== null) {
                    return "internal_scope: $difference";
                }
                continue;
            }
            if ($recurse && \is_array($value) && \is_array($other) && $name === 'parameter_list') {
                if (\array_keys($value) !== \array_keys($other)) {
                    return 'parameter_list keys differ';
                }
                foreach ($value as $i => $parameter) {
                    if (!\is_object($parameter) || !\is_object($other[$i])) {
                        return "parameter $i is not an object";
                    }
                    $difference = self::compareObjects($parameter, $other[$i], ['state_version'], false);
                    if ($difference !== null) {
                        return "parameter $i: $difference";
                    }
                }
                continue;
            }
            if ($value !== $other) {
                return "property $name: " . self::describeValue($value) . ' !== ' . self::describeValue($other);
            }
        }
        return null;
    }

    private static function propertyName(string $key): string
    {
        $pos = \strrpos($key, "\0");
        return $pos === false ? $key : \substr($key, $pos + 1);
    }

    private static function describeValue(mixed $value): string
    {
        if (\is_object($value)) {
            return \get_class($value) . '#' . \spl_object_id($value) . ($value instanceof \Stringable ? "($value)" : '');
        }
        if (\is_array($value)) {
            return 'array(' . \count($value) . ')';
        }
        return \var_export($value, true);
    }

    /**
     * The snapshot of the state of $root for the exhaustive template check (verify mode)
     *
     * @return array<string,mixed>
     */
    private static function snapshotRoot(Method $root): array
    {
        $scope = $root->getInternalScope();
        $variables = [];
        foreach ($scope->getVariableMap() as $name => $variable) {
            $variables[$name] = [$variable, (array)$variable];
        }
        return [
            'root' => (array)$root,
            'scope' => (array)$scope,
            'variables' => $variables,
            'parameters' => \array_map(self::snapshotParameter(...), $root->getParameterList()),
            // @phan-suppress-next-line PhanAccessMethodInternal
            'real_parameters' => \array_map(self::snapshotParameter(...), $root->getRealParameterListWithoutCloning()),
        ];
    }

    /**
     * @return array{0:Parameter,1:array<string,mixed>}
     */
    private static function snapshotParameter(Parameter $parameter): array
    {
        return [$parameter, (array)$parameter];
    }

    /**
     * Verify that nothing in the state of $root changed without a state version change since the template $t was captured
     * (the guard passed: the versions are equal). A difference means that a setter does not increment the state version.
     */
    private function verifyTemplateSnapshot(Method $root, LazyInheritedMethodTemplate $t): void
    {
        $snapshot = $t->snapshot;
        if ($snapshot === null) {
            return;
        }
        $this->count('verify_exhaustive_checked');
        $current = self::snapshotRoot($root);
        $difference = self::compareSnapshot($snapshot, $current);
        if ($difference !== null) {
            $this->recordViolation($root, 'template guard', "changed without a state version change: $difference");
        }
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     */
    private static function compareSnapshot(array $old, array $new): ?string
    {
        $ignored = ['state_version', 'suppress_issue_list', 'overridden_methods_cache', 'clone_source', 'eager_ordinal'];
        $difference = self::compareArrays($old['root'], $new['root'], $ignored);
        if ($difference !== null) {
            return "method: $difference";
        }
        $difference = self::compareArrays($old['scope'], $new['scope'], ['state_version']);
        if ($difference !== null) {
            return "scope: $difference";
        }
        foreach (['variables', 'parameters', 'real_parameters'] as $kind) {
            if (\array_keys($old[$kind]) !== \array_keys($new[$kind])) {
                return "$kind: the keys differ";
            }
            foreach ($old[$kind] as $i => [$object, $properties]) {
                [$new_object, $new_properties] = $new[$kind][$i];
                if ($object !== $new_object) {
                    return "$kind $i: a different object";
                }
                $difference = self::compareArrays($properties, $new_properties, ['state_version', 'suppress_issue_list']);
                if ($difference !== null) {
                    return "$kind $i: $difference";
                }
            }
        }
        return null;
    }

    /**
     * @param array<mixed,mixed> $old
     * @param array<mixed,mixed> $new
     * @param list<string> $ignored_properties
     */
    private static function compareArrays(array $old, array $new, array $ignored_properties): ?string
    {
        foreach ($old as $key => $value) {
            $name = self::propertyName((string)$key);
            if (\in_array($name, $ignored_properties, true)) {
                continue;
            }
            $other = $new[$key] ?? null;
            if ($name === 'phan_flags' && \is_int($value) && \is_int($other)) {
                // @phan-suppress-next-line PhanAccessClassConstantInternal
                if ((($value ^ $other) & ~TypedElement::UNVERSIONED_PHAN_FLAGS) !== 0) {
                    return "property phan_flags: $value !== $other";
                }
                continue;
            }
            if ($value !== $other) {
                return "property $name: " . self::describeValue($value) . ' !== ' . self::describeValue($other);
            }
        }
        if (\count($old) !== \count($new)) {
            return 'the properties differ';
        }
        return null;
    }

    /**
     * Start the side effect check of a late or nested declaration analysis of a copy (verify mode, see endSideEffectCheck())
     *
     * @return array{0:\Phan\Output\IssueCollectorInterface,1:BufferingCollector,2:array{0:int,1:int,2:int,3:int},3:?list<TypedElement>}
     */
    public function beginSideEffectCheck(): array
    {
        $issue_collector = Phan::getIssueCollector();
        $capturing_collector = new BufferingCollector();
        Phan::setIssueCollector($capturing_collector);
        // @phan-suppress-next-line PhanAccessPropertyInternal
        $previous_recorder = TypedElement::$suppression_count_recorder;
        // @phan-suppress-next-line PhanAccessPropertyInternal
        TypedElement::$suppression_count_recorder = [];
        return [$issue_collector, $capturing_collector, EmitOnlyChecks::getSideEffectCounts($this->code_base), $previous_recorder];
    }

    /**
     * Verify that the late or nested declaration analysis of the copy $method collected no issue, changed no suppression count
     * of an element declared in an analyzed file, hydrated no class, loaded no class, added no method and initialized at most
     * the scope of $method (side effects of the analysis of other copies it created are excluded).
     *
     * @param array{0:\Phan\Output\IssueCollectorInterface,1:BufferingCollector,2:array{0:int,1:int,2:int,3:int},3:?list<TypedElement>} $state
     */
    public function endSideEffectCheck(Method $method, array $state, string $label): void
    {
        [$issue_collector, $capturing_collector, $before, $previous_recorder] = $state;
        Phan::setIssueCollector($issue_collector);
        // @phan-suppress-next-line PhanAccessPropertyInternal
        $suppressing_elements = TypedElement::$suppression_count_recorder ?? [];
        // (The suppressions counted by the analysis of copies that this one created were checked by their own check)
        // @phan-suppress-next-line PhanAccessPropertyInternal
        TypedElement::$suppression_count_recorder = $previous_recorder;
        foreach ($capturing_collector->getCollectedIssues() as $issue) {
            $this->recordViolation($method, $label, "emitted an issue that is reported: $issue");
            $issue_collector->collectIssue($issue);
        }
        $reported = [];
        foreach ($suppressing_elements as $element) {
            $file = $element->getFileRef()->getFile();
            if (isset($reported[\spl_object_id($element)]) || Phan::isExcludedAnalysisFile($file)) {
                continue;
            }
            $reported[\spl_object_id($element)] = true;
            $name = $element instanceof AddressableElementInterface ? (string)$element->getFQSEN() : $element->getName();
            $this->recordViolation($method, $label, "changed a suppression count of $name, declared in the analyzed file $file");
        }
        $after = EmitOnlyChecks::getSideEffectCounts($this->code_base);
        if ($after[0] !== $before[0] || $after[2] !== $before[2] || $after[3] !== $before[3] || $after[1] > $before[1] + 1) {
            $this->recordViolation($method, $label, 'had side effects (hydrations, scope initializations, methods, classes): ' . \json_encode($before) . ' -> ' . \json_encode($after));
        }
    }
}
