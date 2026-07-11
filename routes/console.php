<?php

use App\Models\WhatsappNotification;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => [WhatsappNotification::class],
])->dailyAt('02:00')->withoutOverlapping();
