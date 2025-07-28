<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <meta name="description" content="@yield('meta_description')">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50">
    @yield('content')
</body>
</html>
