<div class="bg-blue-50 rounded-lg p-8 text-center my-12">
    <h3 class="text-2xl font-bold text-gray-900 mb-4">
        {{ $block->block_data['title'] ?? '' }}
    </h3>
    <p class="text-gray-700 mb-6 text-lg">
        {{ $block->block_data['description'] ?? '' }}
    </p>
    <a href="{{ $block->block_data['button_url'] ?? '#' }}" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
        {{ $block->block_data['button_text'] ?? 'Learn More' }}
    </a>
</div>
