<?php

namespace App\Console\Commands;

use App\Models\IctRemediationRule;
use Illuminate\Console\Command;

class AtlasSentinelToggleRemediationRule extends Command
{
    protected $signature = 'atlas-sentinel:toggle-remediation {metric_key} {--on} {--off}';
    protected $description = 'Flip a self-healing remediation rule\'s auto_execute flag on or off';

    public function handle(): int
    {
        $rule = IctRemediationRule::where('metric_key', $this->argument('metric_key'))->first();

        if (! $rule) {
            $this->error("No remediation rule found with metric_key \"{$this->argument('metric_key')}\".");
            $this->line('Known metric_keys: ' . IctRemediationRule::pluck('metric_key')->implode(', '));
            return self::FAILURE;
        }

        if ($this->option('on') && $this->option('off')) {
            $this->error('Pass either --on or --off, not both.');
            return self::FAILURE;
        }

        if ($this->option('on')) {
            $rule->update(['auto_execute' => true]);
        } elseif ($this->option('off')) {
            $rule->update(['auto_execute' => false]);
        }

        $this->info("{$rule->metric_key} ({$rule->action}): auto_execute = " . ($rule->auto_execute ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
