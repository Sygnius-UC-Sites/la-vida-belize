<table>
    <thead>
    <tr>
        @foreach($fields as $f)
        <th>{{ $f }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($data as $user)
        <tr>
            @foreach($user as $u)
            <td>{{$u }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>