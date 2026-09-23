@php
    $appearance = $appearance ?? [
        'mode' => 'system',
        'theme' => 'default',
        'accent' => '#3b82f6',
        'accent_foreground' => 'oklch(0.985 0 0)',
        'contrast' => 'normal',
    ];

    $accent = $appearance['accent'];
    $accentForeground = $appearance['accent_foreground'];
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class(['dark' => $appearance['mode'] === 'dark'])
    data-mode="{{ $appearance['mode'] }}"
    data-theme="{{ $appearance['theme'] }}"
    data-accent="{{ $accent }}"
    data-contrast="{{ $appearance['contrast'] }}"
    style="--primary: {{ $accent }}; --primary-foreground: {{ $accentForeground }}; --ring: {{ $accent }}; --sidebar-primary: {{ $accent }}; --sidebar-primary-foreground: {{ $accentForeground }}; --sidebar-ring: {{ $accent }}; --chart-1: {{ $accent }};"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const mode = '{{ $appearance['mode'] }}';

                if (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color before the stylesheet loads.
             Keep these values in sync with the theme blocks in resources/css/app.css. --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            html[data-theme='dracula'] {
                background-color: #282a36;
            }

            html[data-theme='khaki'] {
                background-color: #f4efe0;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
