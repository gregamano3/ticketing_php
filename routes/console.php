<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('helpdesk:check-sla')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('activitylog:clean')->daily();
