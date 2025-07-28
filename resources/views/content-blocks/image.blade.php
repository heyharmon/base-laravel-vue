<figure class="mb-8">
    <img src="{{ $block->block_data['url'] }}" alt="{{ $block->block_data['alt'] ?? '' }}" class="w-full rounded-lg shadow-md">
    @if(!empty($block->block_data['caption']))
        <figcaption class="text-sm text-gray-600 mt-3 text-center">
            {{ $block->block_data['caption'] }}
        </figcaption>
    @endif
</figure>
