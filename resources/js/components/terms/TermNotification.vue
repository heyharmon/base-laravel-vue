<script setup>
const props = defineProps({
	message: { type: String, default: null }
})
</script>

<template>
	<div v-if="message" class="p-4 mb-3 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2">
		{{ message }}
	</div>
</template>
