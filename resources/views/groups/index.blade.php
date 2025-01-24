<html>
    <body>
        <h1>Groups</h1>
        <ul>
            @foreach ($groups as $group)
                <li>
                    <a href="{{ route('groups.show', $group) }}">{{ $group->title }}</a>
                </li>
            @endforeach
        </ul>
    </body>
</html>