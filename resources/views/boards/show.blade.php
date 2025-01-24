<html>
    <head>
        <title>Board {{ $board->title }}</title>
    </head>
    <body>
        <h1>Board {{ $board->title }}</h1>
        <p>{{ $board->description }}</p>
        <h2>Users</h2>
        <ul>
            @foreach ($board->group->users as $user)
                <li>{{ $user->name }}</li>
            @endforeach
        </ul>
        <h2>Columns</h2>
        <ul>
            @foreach ($board->columns as $column)
                <li>
                    <h2>{{ $column->title }}</h2>
                    <ul>
                        @foreach ($column->cards as $card)
                            <li>{{ $card->title }}</li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </body>
</html>