@extends('layouts.frontend')

@section('title', $page->meta_title ?: $page->title)
@section('meta_description', $page->meta_description)

@section('content')
<article class="max-w-4xl mx-auto px-4 py-12">
    <header class="mb-12">
        <h1 class="text-5xl font-bold text-gray-900 mb-4">{{ $page->title }}</h1>
        @if($page->intro)
            <p class="text-xl text-gray-600 leading-relaxed">{{ $page->intro }}</p>
        @endif
    </header>

    <div class="prose prose-lg max-w-none">
        @foreach($page->contentBlocks as $block)
            @include('content-blocks.' . $block->block_type, ['block' => $block])
        @endforeach
    </div>
</article>
@endsection
