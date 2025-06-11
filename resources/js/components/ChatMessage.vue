<script setup>
import { marked } from 'marked'

const props = defineProps({
	chat: {
		type: Object,
		required: true
	}
})

const renderMarkdown = (content) => {
	return marked.parse(content || '')
}
</script>

<template>
	<div :class="['flex', props.chat.role === 'user' ? 'justify-end' : 'justify-start']">
		<div :class="['max-w-[80%] rounded-lg p-3', props.chat.role === 'user' ? 'bg-neutral-200' : 'bg-neutral-300']">
			<!-- Chat content -->
			<div class="markdown-content" v-html="renderMarkdown(chat.content)"></div>

			<!-- Citations section if annotations exist -->
			<div v-if="chat.annotations && chat.annotations.length > 0" class="mt-3 pt-2 border-t border-neutral-400">
				<p class="text-xs font-semibold mb-1">Sources:</p>
				<ul class="text-xs space-y-1">
					<li v-for="(annotation, index) in chat.annotations" :key="index">
						<a :href="annotation.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">
							{{ annotation.title || annotation.url }}
						</a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<style>
/* Markdown content styling */
.markdown-content h1 {
	font-size: 1.5rem;
	font-weight: 700;
	margin-top: 1rem;
	margin-bottom: 0.5rem;
}

.markdown-content h2 {
	font-size: 1.25rem;
	font-weight: 600;
	margin-top: 0.75rem;
	margin-bottom: 0.5rem;
}

.markdown-content h3 {
	font-size: 1.125rem;
	font-weight: 600;
	margin-top: 0.75rem;
	margin-bottom: 0.5rem;
}

.markdown-content p:not(:last-of-type) {
	margin-bottom: 0.75rem;
}

.markdown-content ul,
.markdown-content ol {
	margin-left: 1.5rem;
	margin-bottom: 0.75rem;
}

.markdown-content ul {
	list-style-type: disc;
}

.markdown-content ol {
	list-style-type: decimal;
}

.markdown-content li {
	margin-bottom: 0.25rem;
}

.markdown-content a {
	color: #3b82f6;
	text-decoration: underline;
}

.markdown-content blockquote {
	border-left: 4px solid #d1d5db;
	padding-left: 1rem;
	margin-left: 0;
	margin-right: 0;
	font-style: italic;
	color: #4b5563;
}

.markdown-content pre {
	background-color: #f3f4f6;
	padding: 0.75rem;
	border-radius: 0.375rem;
	overflow-x: auto;
	margin-bottom: 0.75rem;
}

.markdown-content code {
	background-color: #f3f4f6;
	padding: 0.25rem 0.375rem;
	border-radius: 0.25rem;
	font-family: ui-monospace, monospace;
	font-size: 0.875em;
}

.markdown-content pre code {
	background-color: transparent;
	padding: 0;
}

.markdown-content hr {
	margin-top: 1rem;
	margin-bottom: 1rem;
	border-top: 1px solid #e5e7eb;
}

.markdown-content table {
	border-collapse: collapse;
	width: 100%;
	margin-bottom: 0.75rem;
}

.markdown-content th,
.markdown-content td {
	border: 1px solid #d1d5db;
	padding: 0.5rem;
}

.markdown-content th {
	background-color: #f3f4f6;
	font-weight: 600;
}

.markdown-content img {
	max-width: 100%;
	height: auto;
	border-radius: 0.375rem;
}

.markdown-content strong {
	font-weight: 600;
}

.markdown-content em {
	font-style: italic;
}
</style>
