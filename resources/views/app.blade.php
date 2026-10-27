<!DOCTYPE html>
{{-- PlanewardThemeSync sets data-theme on <html> from the host after the handshake. --}}
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="A starter app that runs inside a Planeward community: member view, staff dashboard, API and webhooks.">
        <title>{{ $title }} · Planeward Community App</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body class="bg-background text-foreground min-h-dvh antialiased">
        <div id="app" data-page="{{ $page }}" data-props="{{ json_encode((object) $props) }}"></div>
    </body>
</html>
