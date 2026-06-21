<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - SDN Cibitung Kulon 02</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <div class="flex h-screen">
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col overflow-hidden min-w-0">
                <header class="bg-white border-b border-gray-200 shrink-0">
                    <div class="px-8 py-4 flex items-center justify-between">
                        <h1 class="text-xl font-bold text-gray-900">
                            {{ $pageTitle }}
                        </h1>

                        <div class="flex items-center gap-4">
                            <div class="h-8 w-px bg-gray-200"></div>
                            <span class="text-sm font-medium text-blue-600">
                                {{ config('navigation.role_labels.' . auth()->user()->role, auth()->user()->role) }}
                            </span>
                            <div class="w-9 h-9 bg-gray-200 rounded-full shrink-0"></div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-auto bg-gray-50">
                    <div class="px-8 py-6">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
