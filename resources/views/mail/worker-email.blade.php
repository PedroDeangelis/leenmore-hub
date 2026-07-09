{{-- The legacy storage-service email body: the admin's message (newlines kept),
     then a "Links:" list when resource links were selected. --}}
<p>{!! nl2br(e($body)) !!}</p>

@if ($links !== [])
    <hr>
    <h4>Links:</h4>
    <ul>
        @foreach ($links as $link)
            <li><a href="{{ $link['url'] }}" target="_blank">{{ $link['title'] !== '' ? $link['title'] : $link['url'] }}</a></li>
        @endforeach
    </ul>
@endif
