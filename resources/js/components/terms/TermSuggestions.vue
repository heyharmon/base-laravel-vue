<script setup>
import { ref, watch, onMounted } from 'vue'
import api from '@/services/api'
import { useTermStore } from '@/stores/termStore'
import CloseIcon from '../icons/CloseIcon.vue'

const props = defineProps({
	domain: {
		type: String,
		required: true
	},
        organizationId: {
                type: String,
                default: null
        },
        teamId: {
                type: [String, Number],
                required: true
        }
})

const emit = defineEmits(['update:terms', 'create-terms'])

const isLoadingTerms = ref(false)
const generatedTerms = ref([])
const error = ref(null)
const termStore = useTermStore()

// Generate terms on mount if domain is available
onMounted(() => {
	if (props.domain) {
		GenerateOrganizationKeywords()
	}
})

const GenerateOrganizationKeywords = async () => {
	if (!props.domain) {
		error.value = 'No website domain available.'
		return
	}

	isLoadingTerms.value = true
	error.value = null
	generatedTerms.value = []

	try {
		const response = await api.post('/generate-terms', { domain: props.domain })
		generatedTerms.value = response || []
		emit('update:terms', generatedTerms.value)
	} catch (err) {
		console.error('Error generating terms:', err)
		error.value = 'Failed to generate terms. Please try again.'
	} finally {
		isLoadingTerms.value = false
	}
}

const removeTerm = (index) => {
	generatedTerms.value.splice(index, 1)
	emit('update:terms', generatedTerms.value)
}

const createTerms = async () => {
        if (!generatedTerms.value.length || !props.organizationId) return

	try {
                const promises = generatedTerms.value.map((term) => termStore.createTerm(props.teamId, props.organizationId, { name: term }))

		await Promise.all(promises)
		emit('create-terms')
	} catch (err) {
		console.error('Error creating terms:', err)
		error.value = 'Failed to create terms. Please try again.'
	}
}

// Expose methods to parent components
defineExpose({ GenerateOrganizationKeywords })
</script>

<template>
	<div class="space-y-4">
		<div v-if="error" class="text-red-500 text-sm">
			{{ error }}
		</div>

		<div class="max-h-[calc(100vh-30rem)] overflow-y-auto">
			<div v-if="isLoadingTerms" class="space-x-4">
				<h3 class="text-sm font-medium text-neutral-700 mb-2">Generating terms...</h3>
				<div class="flex-1 space-y-3 animate-pulse">
					<div class="h-4 rounded bg-neutral-200"></div>
					<div class="h-4 w-11/12 rounded bg-neutral-200"></div>
					<div class="h-4 w-10/12 rounded bg-neutral-200"></div>
					<div class="h-4 rounded bg-neutral-200"></div>
				</div>
			</div>
			<div v-else-if="generatedTerms.length > 0">
				<h3 class="text-sm font-medium text-neutral-700 mb-2">Term suggestions:</h3>
				<ul>
					<li
						v-for="(term, index) in generatedTerms"
						:key="index"
						class="flex items-center justify-between bg-neutral-100 px-2 py-1.5 rounded mb-1.5"
					>
						<span class="text-sm">{{ term }}</span>
						<button
							@click="removeTerm(index)"
							class="text-neutral-500 hover:text-red-500 ml-2 p-1 cursor-pointer rounded-lg hover:bg-red-100"
							type="button"
						>
							<CloseIcon class="h-4 w-4" />
						</button>
					</li>
				</ul>
			</div>
		</div>

		<div class="flex space-x-2 mt-4">
			<button
				v-if="generatedTerms.length > 0 && organizationId"
				@click="createTerms"
				class="inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
				:disabled="isLoadingTerms"
			>
				Create Terms
			</button>

			<button
				v-if="generatedTerms.length > 0"
				@click="GenerateOrganizationKeywords"
				class="text-neutral-600 text-sm rounded-md cursor-pointer hover:text-neutral-900"
				:disabled="isLoadingTerms || !domain"
			>
				Regenerate terms
			</button>
		</div>
	</div>
</template>
