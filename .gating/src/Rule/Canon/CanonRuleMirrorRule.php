<?php

declare(strict_types=1);

namespace Gating\Gate\Rule\Canon;

use Gating\Gate\Contract\RuleContext;
use Gating\Gate\Contract\RuleResult;

/**
 * Provides the canon rule mirror rule implementation used by the Gating runtime and rule execution flow.
 */
final class CanonRuleMirrorRule extends AbstractCanonRule
{
    /**
     * Returns the stable identifier used to register and report this Gating rule.
     */
    public function id(): string
    {
        return 'canon.mirror_contract';
    }

    /**
     * Evaluates the target repository against this rule and returns the structured Gating result.
     */
    public function check(RuleContext $context): RuleResult
    {
        $canonRoot = dirname($context->targetPath).'/Canonization/.canonization/Governance/Architecture/Rule';
        $gatingRoot = $context->targetPath.'/src/Rule/Canon';
        if (!is_dir($canonRoot) || !is_dir($gatingRoot)) {
            return $this->result('skipped', 'Canonization/Gating sibling mirror roots are not both available.');
        }
        $hits = [];
        $count = 0;
        foreach (glob($canonRoot.'/Canon*Rule.md') ?: [] as $document) {
            ++$count;
            $base = basename($document, '.md');
            if (1 !== preg_match('/^Canon\d{3}[A-Z][A-Za-z0-9]*Rule$/', $base)) {
                $hits[] = basename($document).' violates CanonNNN<SemanticName>Rule naming.';
                continue;
            }
            if (!is_file($gatingRoot.'/'.$base.'.php')) {
                $hits[] = $base.'.md has no mirrored '.$base.'.php';
            }
        }
        if ([] !== $hits) {
            return $this->result('failed', 'Canonization/Gating mirror contract is incomplete.', $hits);
        }

        return 0 === $count ? $this->result('skipped', 'No CanonNNN rule documents found.') : $this->result('passed', sprintf('%d Canonization rule(s) have mirrored Gating PHP rules.', $count));
    }
}
