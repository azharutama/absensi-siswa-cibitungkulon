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
        $routeName = request()->route()?->getName();
        $pageTitle = $this->title
            ?? ($routeName ? config("navigation.page_titles.{$routeName}") : null)
            ?? config('navigation.dashboard_titles.' . auth()->user()->role, 'Dashboard');

        return view('layouts.app', [
            'pageTitle' => $pageTitle,
        ]);
    }
}
