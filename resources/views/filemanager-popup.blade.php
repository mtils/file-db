<!doctype html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        @section('head')
            <title>Prodis- 2.0 &gt; {{ Menu::current()->title }}</title>
            <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
            <!-- bootstrap 3.0.2 -->
            <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
            <!-- Font Awesome Icons -->
            <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
            <!--[if lt IE 9]>
            <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
            <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
            <![endif]-->
            <style>
                @include('file-db::partials.style')
            </style>
        @show
    </head>
    <body>
        <!-- Main content -->
        <section class="content" style="padding: 10px;">
        @include('file-db::partials.filemanager')
        </section>
        @section('js')
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.0.2/jquery.min.js" type="text/javascript"></script>
        <!-- Bootstrap -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
        <script>
            @include('file-db::partials.javascript')
        </script>
        @show
    </body>
</html>