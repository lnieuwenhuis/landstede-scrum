<html>
    <body>
        <h1>Boards</h1>
        <ul>
            @foreach ($boards as $board)
                <li>
                    <a href="{{ route('boards.show', $board) }}">{{ $board->title }}</a>
                </li>
            @endforeach
        </ul>
    </body>
</html>