<script setup>
import { computed } from 'vue'

const props = defineProps({
	chat: {
		type: Object,
		required: true
	}
})

// Check if the chat has annotations
const hasAnnotations = computed(() => {
	return props.chat.annotations && props.chat.annotations.length > 0
})

// Function to format chat content with citations
const formattedContent = computed(() => {
	if (!hasAnnotations.value) {
		return props.chat.content
	}

	// Process content to add inline citation links
	let content = props.chat.content

	// Look for citation patterns like ([source](url)) or similar markdown links
	props.chat.annotations.forEach((annotation, index) => {
		// Look for the URL or title in the content
		const urlRegex = new RegExp(`\\[([^\\]]+)\\]\\(${escapeRegExp(annotation.url)}\\)`, 'g')
		const titleRegex = new RegExp(`\\[([^\\]]+)\\]\\(${escapeRegExp(annotation.title || '')}\\)`, 'g')

		// Replace markdown links with HTML links that have citation numbers
		content = content
			.replace(urlRegex, `<a href="${annotation.url}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">[${index + 1}]</a>`)
			.replace(
				titleRegex,
				`<a href="${annotation.url}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">[${index + 1}]</a>`
			)

		// Also look for plain text citations like (source.com) or (title)
		const plainUrlRegex = new RegExp(`\\(${escapeRegExp(getHostname(annotation.url))}\\)`, 'g')
		const plainTitleRegex = new RegExp(`\\(${escapeRegExp(annotation.title || '')}\\)`, 'g')

		content = content
			.replace(
				plainUrlRegex,
				`<a href="${annotation.url}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">[${index + 1}]</a>`
			)
			.replace(
				plainTitleRegex,
				`<a href="${annotation.url}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">[${index + 1}]</a>`
			)
	})

	return content
})

// Helper function to escape special characters in regex
function escapeRegExp(string) {
	return string ? string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') : ''
}

// Helper function to get hostname from URL
function getHostname(url) {
	try {
		return new URL(url).hostname
	} catch (e) {
		return url
	}
}
</script>

<template>
	<div :class="['flex', props.chat.role === 'user' ? 'justify-end' : 'justify-start']">
		<div :class="['max-w-[80%] rounded-lg p-3', props.chat.role === 'user' ? 'bg-neutral-200' : 'bg-neutral-300']">
			<!-- Chat content -->
			{{ chat.content }}
			<!-- <div v-html="formattedContent"></div> -->

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
