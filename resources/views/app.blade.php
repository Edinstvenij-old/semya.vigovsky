<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#5a1010">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/crest.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
    <noscript>Для работы Family Hub нужен JavaScript.</noscript>
</body>
</html>
