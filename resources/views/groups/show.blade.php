<html>
    <head>
        <title>Groups</title>
    </head>
    <body>
        <h1>Group {{ $group->name }}</h1>
        <ul>
            @foreach ($group->users as $user)
                <li>{{ $user->name }}</li>
            @endforeach
        </ul>
    </body>
</html>