<script setup>
const props = defineProps({
	chat: {
		type: Object,
		required: true
	}
})
</script>

<template>
	<div :class="['flex', props.chat.role === 'user' ? 'justify-end' : 'justify-start']">
		<div :class="['max-w-[80%] rounded-lg p-3', props.chat.role === 'user' ? 'bg-neutral-200' : 'bg-neutral-300']">
			<!-- Chat content -->
			<div v-html="chat.content"></div>

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
