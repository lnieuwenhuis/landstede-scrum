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
        <h2>Boards</h2>
        <ul>
            @foreach ($group->boards as $board)
                <li><a href="{{ route('boards.show', $board) }}">{{ $board->title }}</a></li>
            @endforeach
        </ul>
    </body>
</html>