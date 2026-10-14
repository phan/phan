<?php

declare(strict_types=1);

namespace Phan\PluginV3;

/**
 * A plugin implementing AnalyzeMethodCapability implements this to declare that its analyzeMethod() does not depend on
 * being called for every inherited copy of a method (a Method with getFQSEN() !== getDefiningFQSEN()).
 *
 * With lazy_inherited_methods, Phan creates an inherited copy only when it is first needed. analyzeMethod() is then
 * called for that copy when it is created, which can be after the method analysis phase, after beforeAnalyzePhase(),
 * and in a forked analysis worker; it is never called for copies that are never needed. A copy first needed after
 * finalizeProcess() has started in that process gets no analyzeMethod() call at all.
 *
 * A plugin implementing this MUST satisfy, for inherited copies:
 * 1. Visiting a copy emits no issue and changes no suppression count merely because the copy was visited
 *    (returning early for copies satisfies this).
 * 2. State recorded for a copy is keyed by that copy and only read when that copy itself is used later
 *    (called, referenced, looked up), never aggregated over copies.
 * 3. No global counts, ordering, or "every method was seen" assumptions over copies.
 * 4. No state computed at finalizeProcess() or beforeAnalyzePhase() depends on copies that may never be created.
 *
 * Without this marker on every loaded AnalyzeMethodCapability plugin, lazy_inherited_methods is disabled.
 */
interface AnalyzeMethodSkipsInheritedCopiesCapability
{
}
