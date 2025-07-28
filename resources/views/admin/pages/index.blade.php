@extends('admin.layout')

@section('header', 'Pages')

@section('content')
<a href="{{ route('admin.pages.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded mb-4 inline-block">Create Page</a>

<table class="min-w-full bg-white shadow rounded-lg overflow-hidden">
    <thead class="bg-gray-100">
        <tr>
            <th class="px-4 py-2 text-left">Title</th>
            <th class="px-4 py-2 text-left">Slug</th>
            <th class="px-4 py-2 text-left">Published</th>
        </tr>
    </thead>
    <tbody>
        @foreach($pages as $page)
            <tr class="border-b">
                <td class="px-4 py-2">{{ $page->title }}</td>
                <td class="px-4 py-2">{{ $page->slug }}</td>
                <td class="px-4 py-2">{{ optional($page->published_at)->format('Y-m-d') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endsection
