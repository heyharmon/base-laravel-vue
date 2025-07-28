@extends('admin.layout')

@section('header', 'Create Page')

@section('content')
<form action="{{ route('admin.pages.store') }}" method="POST" class="space-y-6">
    @csrf
    <div>
        <label class="block mb-1 font-medium" for="title">Title</label>
        <input type="text" name="title" id="title" class="w-full border-gray-300 rounded" required>
    </div>
    <div>
        <label class="block mb-1 font-medium" for="slug">Slug</label>
        <input type="text" name="slug" id="slug" class="w-full border-gray-300 rounded">
    </div>
    <div>
        <label class="block mb-1 font-medium" for="intro">Intro</label>
        <textarea name="intro" id="intro" class="w-full border-gray-300 rounded" rows="3"></textarea>
    </div>
    <div>
        <label class="block mb-1 font-medium" for="meta_title">Meta Title</label>
        <input type="text" name="meta_title" id="meta_title" class="w-full border-gray-300 rounded">
    </div>
    <div>
        <label class="block mb-1 font-medium" for="meta_description">Meta Description</label>
        <textarea name="meta_description" id="meta_description" class="w-full border-gray-300 rounded" rows="2"></textarea>
    </div>
    <div>
        <label class="block mb-1 font-medium" for="published_at">Published At</label>
        <input type="datetime-local" name="published_at" id="published_at" class="w-full border-gray-300 rounded">
    </div>
    <div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
    </div>
</form>
@endsection
