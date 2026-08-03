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
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[10000] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-blue-700 focus:shadow-lg">
            Lewati ke konten utama
        </a>

        <div id="page-loading" class="fixed inset-x-0 top-0 z-[9999] hidden print:hidden" role="status" aria-live="polite" aria-hidden="true">
            <div class="h-1 overflow-hidden bg-blue-100">
                <div class="page-loading-bar h-full w-1/2 bg-blue-600 shadow-[0_0_12px_rgba(37,99,235,0.55)]"></div>
            </div>
            <div class="pointer-events-none fixed right-4 top-4 rounded-md border border-blue-100 bg-white/95 px-4 py-2 text-sm font-medium text-blue-700 shadow-lg">
                Memuat data...
            </div>
        </div>

        <div class="flex h-screen overflow-hidden print:block print:h-auto print:overflow-visible">
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col overflow-hidden min-w-0">
                
                <header class="bg-white border-b border-gray-200 shrink-0 print:hidden">
                    <div class="ps-16 pe-6 md:px-8 py-4 flex items-center justify-between">
                        <h1 class="text-lg md:text-xl font-bold text-gray-900 truncate">
                            {{ $pageTitle }}
                        </h1>

                        <div class="flex items-center gap-2 md:gap-4">
                            <div class="h-6 w-px bg-gray-200 hidden sm:block"></div>
                            <a
                                href="{{ route('profile.edit') }}"
                                aria-label="Buka profil {{ auth()->user()->nama }}"
                                title="Profil"
                                class="text-xs md:text-sm font-medium text-blue-600 uppercase bg-blue-50 px-2.5 py-1 rounded-md hover:bg-blue-100 transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 md:bg-transparent md:p-0 md:hover:bg-transparent"
                            >
                                {{ config('navigation.role_labels.' . auth()->user()->role, auth()->user()->role) }}
                            </a>
                        </div>
                    </div>
                </header>

                <main id="main-content" class="flex-1 overflow-auto bg-gray-50 print:overflow-visible print:bg-white" tabindex="-1">
                    <div class="px-4 py-6 md:px-8 print:p-0">
                        {{ $slot }}
                    </div>
                </main>

            </div>
        </div>

        <x-confirm-modal />
    </body>
</html>
