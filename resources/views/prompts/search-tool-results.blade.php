@if(count($results) > 0)
# Search Results

@foreach($results as $result)
## {{ $result['title'] }}
{{ $result['snippet'] }}
[{{ $result['link'] }}]({{ $result['link'] }})

@endforeach
@else
No search results found.
@endif
