<script setup>
import { ref, watch, onMounted } from 'vue'
import api from '@/services/api'
import { useKeywordStore } from '@/stores/keywordStore'

const props = defineProps({
	domain: {
		type: String,
		required: true
	},
	organizationId: {
		type: String,
		default: null
	}
})

const emit = defineEmits(['update:keywords', 'create-keywords'])

const isLoadingKeywords = ref(false)
const generatedKeywords = ref([])
const error = ref(null)
const keywordStore = useKeywordStore()

// Auto-generate keywords when domain changes
watch(
	() => props.domain,
	(newDomain) => {
		if (newDomain) {
			generateKeywords()
		}
	}
)

// Generate keywords on mount if domain is available
onMounted(() => {
	if (props.domain) {
		generateKeywords()
	}
})

const generateKeywords = async () => {
	if (!props.domain) {
		error.value = 'No website domain available.'
		return
	}

	isLoadingKeywords.value = true
	error.value = null
	generatedKeywords.value = []

	try {
		const response = await api.post('/generate-keywords', { domain: props.domain })
		generatedKeywords.value = response || []
		emit('update:keywords', generatedKeywords.value)
	} catch (err) {
		console.error('Error generating keywords:', err)
		error.value = 'Failed to generate keywords. Please try again.'
	} finally {
		isLoadingKeywords.value = false
	}
}

const removeKeyword = (index) => {
	generatedKeywords.value.splice(index, 1)
	emit('update:keywords', generatedKeywords.value)
}

const createKeywords = async () => {
	if (!generatedKeywords.value.length || !props.organizationId) return

	try {
		const promises = generatedKeywords.value.map((keyword) => keywordStore.createKeyword(props.organizationId, { name: keyword }))

		await Promise.all(promises)
		emit('create-keywords')
	} catch (err) {
		console.error('Error creating keywords:', err)
		error.value = 'Failed to create keywords. Please try again.'
	}
}

// Expose methods to parent components
defineExpose({ generateKeywords })
</script>

<template>
	<div class="space-y-4">
		<div v-if="error" class="text-red-500 text-sm">
			{{ error }}
		</div>

		<div class="max-h-[calc(100vh-30rem)] overflow-y-auto">
			<div v-if="isLoadingKeywords" class="space-x-4">
				<h3 class="text-sm font-medium text-neutral-700 mb-2">Generating keywords...</h3>
				<div class="flex-1 space-y-3 animate-pulse">
					<div class="h-4 rounded bg-neutral-200"></div>
					<div class="h-4 w-11/12 rounded bg-neutral-200"></div>
					<div class="h-4 w-10/12 rounded bg-neutral-200"></div>
					<div class="h-4 rounded bg-neutral-200"></div>
				</div>
			</div>
			<div v-else-if="generatedKeywords.length > 0">
				<h3 class="text-sm font-medium text-neutral-700 mb-2">Keyword suggestions:</h3>
				<ul>
					<li
						v-for="(keyword, index) in generatedKeywords"
						:key="index"
						class="flex items-center justify-between bg-neutral-100 px-2 py-1.5 rounded mb-1.5"
					>
						<span class="text-sm">{{ keyword }}</span>
						<button
							@click="removeKeyword(index)"
							class="text-neutral-500 hover:text-red-500 ml-2 p-1 cursor-pointer rounded-lg hover:bg-red-100"
							type="button"
						>
							<svg
								xmlns="http://www.w3.org/2000/svg"
								width="16"
								height="16"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								stroke-width="2"
								stroke-linecap="round"
								stroke-linejoin="round"
								class="h-4 w-4"
							>
								<path d="M18 6 6 18" />
								<path d="m6 6 12 12" />
							</svg>
						</button>
					</li>
				</ul>
			</div>
		</div>

		<div class="flex space-x-2 mt-4">
			<button
				v-if="generatedKeywords.length > 0 && organizationId"
				@click="createKeywords"
				class="inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
				:disabled="isLoadingKeywords"
			>
				Create Keywords
			</button>

			<button
				v-if="generatedKeywords.length > 0"
				@click="generateKeywords"
				class="flex items-center gap-2 text-neutral-600 text-sm rounded-md cursor-pointer hover:text-neutral-900"
				:disabled="isLoadingKeywords || !domain"
			>
				<svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
					<path
						stroke-linecap="round"
						stroke-linejoin="round"
						d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
					/>
				</svg>
				Regenerate keywords
			</button>
		</div>
	</div>
</template>
