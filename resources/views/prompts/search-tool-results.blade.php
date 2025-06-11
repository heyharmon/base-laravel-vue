@if(count($results) > 0)
# Search Results

@foreach($results as $result)
## {{ $result['title'] ?? 'No Title' }}
{{ $result['snippet'] ?? 'No Description Available' }}
@if(isset($result['link']) && !empty($result['link']))
[{{ $result['link'] }}]({{ $result['link'] }})
@endif
@endforeach

@else
No search results found.
@endif
