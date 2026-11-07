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
            @foreach($user as $key=>$u)
                @if($key=="property_id")
                    @php $property=App\Models\Property::find($u); @endphp
                    <td>{{ $property->name ?? '' }}</td>
            @else
              <td>@php  print_r($u); @endphp</td>
            @endif
            
            
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>