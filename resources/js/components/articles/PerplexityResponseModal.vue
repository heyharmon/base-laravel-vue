<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import api from '@/services/api'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	},
	articleId: {
		type: Number,
		required: true
	}
})

const emit = defineEmits(['close'])

const response = ref(null)
const isLoading = ref(false)
const error = ref(null)

const fetchPerplexityResponse = async () => {
	if (!props.articleId) return

	isLoading.value = true
	error.value = null

	try {
		let data = await api.get(`/articles/${props.articleId}/perplexity-response`)
		// console.log(data)
		response.value = data
	} catch (err) {
		error.value = err.response?.data?.message || 'Failed to fetch Perplexity response'
	} finally {
		isLoading.value = false
	}
}

// Fetch the response when the modal is opened
onMounted(() => {
	if (props.isOpen) {
		fetchPerplexityResponse()
	}
})

// Watch for changes in isOpen prop
watch(
	() => props.isOpen,
	(newValue) => {
		if (newValue) {
			fetchPerplexityResponse()
		}
	}
)

const closeModal = () => {
	emit('close')
}

// Format the response content for better display
const formattedContent = computed(() => {
	if (!response.value?.response?.choices?.[0]?.message?.content) {
		return ''
	}

	return response.value.response.choices[0].message.content
})

// Get the status of the Perplexity request
const status = computed(() => {
	return response.value?.status || 'UNKNOWN'
})

// Check if the response is complete
const isComplete = computed(() => {
	return status.value === 'COMPLETED'
})
</script>

<template>
	<Modal :isOpen="isOpen" title="Perplexity Deep Research Response" width="wider" @close="closeModal">
		<div class="py-4">
			<div v-if="isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<div v-else-if="error" class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4">
				{{ error }}
			</div>

			<div v-else-if="response">
				<div class="mb-4 flex items-center">
					<span class="font-medium mr-2">Status:</span>
					<span
						:class="{
							'bg-green-100 text-green-800': isComplete,
							'bg-yellow-100 text-yellow-800': !isComplete && status !== 'FAILED',
							'bg-red-100 text-red-800': status === 'FAILED'
						}"
						class="px-2 py-1 rounded text-sm"
					>
						{{ status }}
					</span>
				</div>

				<div v-if="isComplete" class="prose max-w-none">
					<div class="bg-neutral-50 p-4 rounded-lg border border-neutral-200 overflow-auto max-h-[60vh]">
						<div class="ProseMirror" v-html="formattedContent"></div>
					</div>
				</div>

				<div v-else-if="status === 'IN_PROGRESS'" class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-4">
					The deep research is still in progress. Please check back later.
				</div>

				<div v-else-if="status === 'FAILED'" class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4">The deep research request failed.</div>
			</div>

			<div v-else class="bg-neutral-50 border border-neutral-200 rounded-lg p-4">No Perplexity response data available.</div>
		</div>

		<template #footer>
			<Button @click="closeModal" variant="outline" class="mt-4"> Close </Button>
		</template>
	</Modal>
</template>
