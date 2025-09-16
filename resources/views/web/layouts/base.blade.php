<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GameTicketHub')</title>
    @include('web.includes.css-plugins')
    @stack('custom-css')
    @livewireStyles
    <meta name="csrf-token" content="{{ csrf_token() }}">

</head>

<body>

    <!-- Header -->
    @include('web.includes.header')

    @yield('contents')

    @include('web.includes.js-plugins')
    @stack('custom-js')
    @livewireScripts

</body>

</html>
