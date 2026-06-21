<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(
        public ?string $title = null,
    ) {}

    public function render(): View
    {
        $pageTitle = $this->title
            ?? config('navigation.dashboard_titles.' . auth()->user()->role, 'Dashboard');

        return view('layouts.app', [
            'pageTitle' => $pageTitle,
        ]);
    }
}
