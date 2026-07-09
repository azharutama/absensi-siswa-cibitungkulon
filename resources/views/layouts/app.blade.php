<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }} - SDN Cibitung Kulon 02</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <style>
            @keyframes page-loading-slide {
                0% {
                    transform: translateX(-100%);
                }
                50% {
                    transform: translateX(15%);
                }
                100% {
                    transform: translateX(110%);
                }
            }

            .page-loading-bar {
                animation: page-loading-slide 1.1s ease-in-out infinite;
            }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <div id="page-loading" class="fixed inset-x-0 top-0 z-[9999] hidden" aria-live="polite" aria-busy="true">
            <div class="h-1 overflow-hidden bg-blue-100">
                <div class="page-loading-bar h-full w-1/2 bg-blue-600 shadow-[0_0_12px_rgba(37,99,235,0.55)]"></div>
            </div>
            <div class="pointer-events-none fixed right-4 top-4 rounded-md border border-blue-100 bg-white/95 px-4 py-2 text-sm font-medium text-blue-700 shadow-lg">
                Memuat data...
            </div>
        </div>

        <div class="flex h-screen overflow-hidden">
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col overflow-hidden min-w-0">
                
                <header class="bg-white border-b border-gray-200 shrink-0">
                    <div class="ps-16 pe-6 md:px-8 py-4 flex items-center justify-between">
                        <h1 class="text-lg md:text-xl font-bold text-gray-900 truncate">
                            {{ $pageTitle }}
                        </h1>

                        <div class="flex items-center gap-2 md:gap-4">
                            <div class="h-6 w-px bg-gray-200 hidden sm:block"></div>
                            <span class="text-xs md:text-sm font-medium text-blue-600 uppercase bg-blue-50 px-2.5 py-1 rounded-md md:bg-transparent md:p-0">
                                {{ config('navigation.role_labels.' . auth()->user()->role, auth()->user()->role) }}
                            </span>
                            <div class="w-8 h-8 md:w-9 md:h-9 bg-gray-200 rounded-full shrink-0"></div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-auto bg-gray-50">
                    <div class="px-4 py-6 md:px-8">
                        {{ $slot }}
                    </div>
                </main>

            </div>
        </div>
    </body>
</html>
