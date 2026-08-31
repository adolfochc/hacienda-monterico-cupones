<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>Hacienda MonteRico · Club de Socios</title>
    <meta name="description" content="Plataforma privada de beneficios para socios de Hacienda MonteRico.">
    <meta name="author" content="Jaketec">

    <!-- Social Media Meta Tags -->
    <meta property="og:title" content="Hacienda MonteRico · Club de Socios">
    <meta property="og:description" content="Plataforma privada de beneficios para socios.">
    <meta name="twitter:card" content="summary_large_image">

    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ URL::asset('image/favicon.ico') }}">

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
