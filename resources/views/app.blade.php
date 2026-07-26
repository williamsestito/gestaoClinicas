<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{--
            Metadados de SEO renderizados no HTML inicial (sem depender de
            JavaScript ou de SSR do Inertia). Cada página que precisa de
            metadados próprios envia uma prop `seo` (ver App\Support\Seo\
            SeoMetaBuilder); páginas sem essa prop caem no título padrão
            abaixo e nunca ficam indexáveis (robots noindex por padrão).
        --}}
        @php($seo = $page['props']['seo'] ?? null)
        <title>{{ $seo['title'] ?? config('app.name', 'Gestão de Clínicas') }}</title>
        <meta name="robots" content="{{ $seo['robots'] ?? 'noindex, nofollow' }}">
        @if(! empty($seo['description']))
            <meta name="description" content="{{ $seo['description'] }}">
        @endif
        @if(! empty($seo['canonical']))
            <link rel="canonical" href="{{ $seo['canonical'] }}">
        @endif
        @if(! empty($seo['og']))
            <meta property="og:type" content="{{ $seo['og']['type'] ?? 'website' }}">
            <meta property="og:site_name" content="{{ $seo['og']['site_name'] ?? config('app.name') }}">
            <meta property="og:locale" content="{{ $seo['og']['locale'] ?? 'pt_BR' }}">
            @if(! empty($seo['og']['title']))
                <meta property="og:title" content="{{ $seo['og']['title'] }}">
            @endif
            @if(! empty($seo['og']['description']))
                <meta property="og:description" content="{{ $seo['og']['description'] }}">
            @endif
            @if(! empty($seo['og']['url']))
                <meta property="og:url" content="{{ $seo['og']['url'] }}">
            @endif
            @if(! empty($seo['og']['image']))
                <meta property="og:image" content="{{ $seo['og']['image'] }}">
                @if(! empty($seo['og']['image_alt']))
                    <meta property="og:image:alt" content="{{ $seo['og']['image_alt'] }}">
                @endif
            @endif
        @endif
        @if(! empty($seo['twitter']))
            <meta name="twitter:card" content="{{ $seo['twitter']['card'] ?? 'summary' }}">
            @if(! empty($seo['twitter']['title']))
                <meta name="twitter:title" content="{{ $seo['twitter']['title'] }}">
            @endif
            @if(! empty($seo['twitter']['description']))
                <meta name="twitter:description" content="{{ $seo['twitter']['description'] }}">
            @endif
            @if(! empty($seo['twitter']['image']))
                <meta name="twitter:image" content="{{ $seo['twitter']['image'] }}">
                @if(! empty($seo['twitter']['image_alt']))
                    <meta name="twitter:image:alt" content="{{ $seo['twitter']['image_alt'] }}">
                @endif
            @endif
        @endif
        @if(! empty($seo['json_ld']))
            <script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endif

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        {{--
            Favicon gerado a partir da imagem enviada pelo administrador em
            "Site da clínica" (ver App\Support\Site\FaviconGenerator e
            App\Http\Middleware\HandleInertiaRequests). Enquanto nenhum
            favicon foi enviado, cai nos arquivos estáticos padrão —
            nunca renderiza uma tag com href vazio.
        --}}
        @php($favicon = $page['props']['favicon'] ?? [])
        @if(! empty($favicon))
            @if(! empty($favicon['32']))
                <link rel="icon" type="image/png" sizes="32x32" href="{{ $favicon['32'] }}">
            @endif
            @if(! empty($favicon['16']))
                <link rel="icon" type="image/png" sizes="16x16" href="{{ $favicon['16'] }}">
            @endif
            @if(! empty($favicon['192']))
                <link rel="icon" type="image/png" sizes="192x192" href="{{ $favicon['192'] }}">
            @endif
            @if(! empty($favicon['180']))
                <link rel="apple-touch-icon" sizes="180x180" href="{{ $favicon['180'] }}">
            @endif
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        {{-- O <title> já é renderizado acima (dado real, sem depender de JS) — não duplicar aqui. --}}
        <x-inertia::head />
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
