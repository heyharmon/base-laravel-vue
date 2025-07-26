<script setup>
import { watch, onMounted, ref } from 'vue'
import { useTermStore } from '@/stores/termStore'
import Sheet from '@/components/ui/Sheet.vue'
import { useRoute } from 'vue-router'
import GoogleIcon from '../icons/GoogleIcon.vue'

const route = useRoute()

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	},
	termId: {
		type: [Number, String],
		default: null
	},
	term: {
		type: Object,
		default: null
	},
	teamId: {
		type: [Number, String],
		required: true
	}
})

const emit = defineEmits(['close'])
const termStore = useTermStore()
const selectedPromptId = ref(null)
const selectedPrompt = ref(null)

// Methods
const highlightTerm = (content) => {
	if (!termStore.selectedTermDetails?.name || !content) return content

	const regex = new RegExp(termStore.selectedTermDetails.name, 'gi')
	return content.replace(regex, (match) => `<span class="bg-yellow-200">${match}</span>`)
}

const showTerm = async () => {
	selectedPromptId.value = null
	selectedPrompt.value = null

	if (props.termId) {
		await termStore.showTerm(props.teamId, route.params.organizationId, props.termId)
	}
}

const getTermResponses = async (prompt) => {
	selectedPromptId.value = prompt.id
	selectedPrompt.value = prompt
	await termStore.getTermResponses(props.termId, prompt.id)
}

// Lifecycle hooks
onMounted(showTerm)
watch(() => props.termId, showTerm)

// Watch for term details to load, then select the latest prompt
watch(
	() => termStore.selectedTermDetails,
	(newDetails, oldDetails) => {
		if (newDetails?.prompts?.length > 0) {
			const latestPrompt = newDetails.prompts[0]
			getTermResponses(latestPrompt)
		}
	},
	{ deep: true }
)

// Watch for sheet open state to ensure prompt is shown when sheet is opened
watch(
	() => props.isOpen,
	(isOpen) => {
		if (isOpen && termStore.selectedTermDetails?.prompts?.length > 0) {
			const latestPrompt = termStore.selectedTermDetails.prompts[0]
			getTermResponses(latestPrompt)
		}
	}
)
</script>

<template>
	<Sheet :is-open="isOpen" @close="emit('close')" position="right" title="Term">
		<div class="flex flex-col md:flex-row xl:w-[1300px] w-full h-full">
			<!-- Term Details Column -->
			<section class="w-full md:w-1/3 h-full overflow-y-auto">
				<!-- Loading state -->
				<div v-if="termStore.isLoadingDetails" class="flex justify-center py-8">
					<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
				</div>

				<!-- Term details content -->
				<div v-else-if="termStore.selectedTermDetails" class="space-y-6 md:p-4 md:pr-4 md:border-r border-b md:border-b-0 border-neutral-200">
					<!-- Term info card -->
					<div class="bg-neutral-50 p-4 rounded-lg">
						<div>
							<div class="text-neutral-500 text-sm mb-1">Term:</div>
							<span class="text-neutral-800 text-2xl/7 font-medium">{{ termStore.selectedTermDetails.name }}</span>
						</div>
						<div v-if="termStore.selectedTermDetails.description" class="mt-3 text-lg">
							<span class="text-neutral-500">Description:</span>
							<span class="text-neutral-800 ml-2">{{ termStore.selectedTermDetails.description }}</span>
						</div>
					</div>

					<!-- Prompts list -->
					<div v-if="termStore.selectedTermDetails?.prompts?.length > 0">
						<h3 class="text-lg font-medium text-neutral-800 mb-2">
							Found in {{ termStore.selectedTermDetails?.prompts?.length || 0 }}
							{{ (termStore.selectedTermDetails?.prompts?.length || 0) === 1 ? 'prompt' : 'prompts' }}
						</h3>
						<div class="space-y-3">
							<div
								v-for="prompt in termStore.selectedTermDetails.prompts"
								:key="prompt.id"
								class="border border-neutral-300 hover:border-neutral-400 p-3 rounded-lg cursor-pointer hover:bg-neutral-50"
								:class="{ 'border-2 border-neutral-400 bg-neutral-50': selectedPromptId === prompt.id }"
								@click="getTermResponses(prompt)"
							>
								<p class="text-neutral-800">{{ prompt.content }}</p>
								<div class="mt-2 text-sm text-neutral-500 flex justify-between">
									<span
										>Occurrences: <span class="font-medium">{{ prompt.pivot.count }}</span></span
									>
									<span>Last found: {{ new Date(prompt.pivot.last_found_at).toLocaleDateString() }}</span>
								</div>
							</div>
						</div>
					</div>

					<!-- No prompts message -->
					<div v-else class="text-neutral-500 italic">This term hasn't been found in any prompts yet.</div>
				</div>
			</section>

			<!-- Responses Column -->
			<section class="w-full md:w-2/3 h-full p-4 md:pl-4 overflow-y-auto">
				<!-- Loading state -->
				<div v-if="selectedPromptId && termStore.isLoadingTermResponses" class="flex justify-center py-8">
					<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
				</div>

				<!-- Response content -->
				<div v-else-if="selectedPromptId && selectedPrompt" class="space-y-4">
					<!-- Selected prompt info -->
					<div class="bg-neutral-50 p-4 rounded-lg mb-4">
						<div class="mb-2">
							<span class="text-neutral-500 text-sm">Prompt:</span>
							<div class="text-neutral-800 mt-2 text-2xl/7 font-medium">
								{{ selectedPrompt.content }}
							</div>
						</div>
					</div>

					<h3 class="text-lg font-medium text-neutral-800 mb-2">Responses</h3>

					<!-- No responses message -->
					<div v-if="termStore.selectedTermResponses.length === 0" class="text-neutral-500 italic">No responses found containing this term.</div>

					<!-- Response list -->
					<div v-else class="space-y-4">
						<div v-for="response in termStore.selectedTermResponses" :key="response.id" class="bg-white border border-neutral-200 p-4 rounded-lg">
							<!-- Response provider and model -->
							<div class="mb-3 flex justify-between">
								<span class="text-neutral-500 text-sm"
									>Provider: <span class="font-medium">{{ response.provider }}</span></span
								>
								<span class="text-neutral-500 text-sm"
									>Model: <span class="font-medium">{{ response.model }}</span></span
								>
							</div>

							<!-- Response content -->
							<div
								class="p-3 bg-neutral-50 rounded border border-neutral-200 whitespace-pre-wrap text-base/7 mb-4"
								v-html="highlightTerm(response.content)"
							></div>

							<!-- Response search queries -->
							<div v-if="response.search?.queries?.length > 0" class="p-2 rounded border border-neutral-200">
								<div class="text-sm text-neutral-500 mb-2">Google searches performed by the agent</div>
								<div class="flex flex-wrap gap-2 mb-2">
									<a
										v-for="(query, index) in response.search.queries"
										:key="index"
										:href="`https://www.google.com/search?q=${query}`"
										target="_blank"
										class="cursor-pointer text-sm bg-white px-2 py-1 rounded border border-neutral-200 hover:bg-neutral-100"
									>
										<GoogleIcon class="inline-block w-4 h-4 mr-1" />
										{{ query }}
									</a>
								</div>
								<div class="text-xs text-neutral-500">Agent may perform Google searches at its discretion to provide accurate answers.</div>
							</div>

							<!-- Response metadata -->
							<div class="mt-3 text-xs text-neutral-500 flex justify-between">
								<span>Date: {{ new Date(response.created_at).toLocaleString() }}</span>
								<span>Tokens: {{ response.metadata.usage.promptTokens + response.metadata.usage.completionTokens }}</span>
							</div>
						</div>
					</div>
				</div>

				<!-- No prompt selected message -->
				<div v-else class="flex items-center justify-center h-full text-neutral-500">
					<p>Select a prompt to view responses containing this term</p>
				</div>
			</section>
		</div>
	</Sheet>
</template>
