@if(count($results) > 0)
# Search Results

@foreach($results as $result)
## {{ $result['title'] }}
@if($result['snippet'])
{{ $result['snippet'] }}
@endif
[{{ $result['link'] }}]({{ $result['link'] }})
@endforeach

@else
No search results found.
@endif
