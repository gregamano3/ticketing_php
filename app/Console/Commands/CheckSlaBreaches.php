<?php

namespace App\Console\Commands;

use App\Services\SlaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('helpdesk:check-sla')]
#[Description('Flag tickets that breached their SLA and escalate them')]
class CheckSlaBreaches extends Command
{
    public function handle(SlaService $sla): int
    {
        $stats = $sla->checkBreaches();

        $this->info(sprintf(
            'Response breaches: %d, resolution breaches: %d, triage overdue: %d, escalations: %d',
            $stats['response'], $stats['resolution'], $stats['triage'], $stats['escalated']
        ));

        return self::SUCCESS;
    }
}
