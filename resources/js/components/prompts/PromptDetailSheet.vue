<script setup>
import { computed, watch, onMounted, ref } from 'vue'
import { usePromptStore } from '@/stores/promptStore'
import { useArticleStore } from '@/stores/articleStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import Sheet from '@/components/ui/Sheet.vue'
import Button from '@/components/ui/Button.vue'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	},
	promptId: {
		type: [Number, String],
		default: null
	},
	prompt: {
		type: Object,
		default: null
	}
})

const emit = defineEmits(['close'])

const promptStore = usePromptStore()
const articleStore = useArticleStore()
const jobStatusStore = useJobStatusStore()

const isGeneratingArticle = ref(false)

const promptDetails = computed(() => {
	return promptStore.selectedPromptDetails
})

// Fetch prompt details when component mounts or promptId changes
const fetchDetails = async () => {
	if (props.promptId) {
		await promptStore.showPrompt(props.promptId)
		await promptStore.getPromptResponses(props.promptId)
	}
}

// Method to highlight terms in response content
const highlightTerms = (content) => {
	if (!promptDetails.value?.terms || !content) return content

	let highlightedContent = content

	// Apply highlighting for each term
	promptDetails.value.terms.forEach((term) => {
		const regex = new RegExp(term.name, 'gi')
		highlightedContent = highlightedContent.replace(regex, (match) => `<span class="bg-yellow-200">${match}</span>`)
	})

	return highlightedContent
}

const closeSheet = () => {
	emit('close')
}

// Generate an article for the current prompt
const generateArticle = async () => {
	if (!props.promptId) return

	isGeneratingArticle.value = true
	try {
		await articleStore.generateArticle(props.promptId)
		jobStatusStore.pollTeamJobs()
	} catch (error) {
		console.error('Error generating article:', error)
	} finally {
		isGeneratingArticle.value = false
	}
}

onMounted(fetchDetails)

watch(() => props.promptId, fetchDetails)
</script>

<template>
	<Sheet :is-open="isOpen" @close="closeSheet" position="right" title="Prompt">
		<!-- Call to action for article generation -->
		<div class="bg-white border-b border-neutral-200 p-6 -mx-6 -mt-6 mb-4">
			<h3 class="text-xl font-medium text-neutral-800 mb-2">Optimize for this prompt</h3>
			<p class="text-neutral-600 mb-4">Generate an article that can be published on your website and increase visibility for this prompt</p>
			<div class="space-y-4">
				<div class="flex items-center gap-2">
					<Button @click="generateArticle" class="flex items-center gap-2" :disabled="isGeneratingArticle">
						<svg
							v-if="!isGeneratingArticle"
							xmlns="http://www.w3.org/2000/svg"
							width="16"
							height="16"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round"
							class="lucide lucide-sparkles"
						>
							<path
								d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"
							/>
							<path d="M5 3v4" />
							<path d="M19 17v4" />
							<path d="M3 5h4" />
							<path d="M17 19h4" />
						</svg>
						<div v-else class="animate-spin rounded-full h-4 w-4 border-2 border-b-transparent border-white"></div>
						<span>{{ isGeneratingArticle ? 'Generating...' : 'Generate article' }}</span>
					</Button>
				</div>
			</div>
		</div>

		<div class="w-full xl:w-[800px] md:p-4">
			<div v-if="promptStore.isLoadingDetails" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
			</div>
			<div v-else-if="promptDetails" class="space-y-6">
				<!-- Prompt header -->
				<div>
					<div class="bg-neutral-50 p-4 rounded-lg">
						<div class="mb-4">
							<span class="text-neutral-500 text-sm">Content:</span>
							<p class="text-neutral-800 text-2xl/7 font-medium mt-1">{{ promptDetails.content }}</p>
						</div>
						<div class="mb-1 text-sm">
							<span class="text-neutral-500">Mentioned:</span>
							<span class="text-neutral-800 ml-2">{{ prompt.mentions_percentage }}% of the time</span>
						</div>
						<div class="mb-2 text-sm">
							<span class="text-neutral-500">Term occurrences:</span>
							<span class="text-neutral-800 ml-2"
								>{{ promptDetails.terms?.length || 0 }} {{ promptDetails.terms?.length === 1 ? 'term' : 'terms' }}</span
							>
						</div>
					</div>
				</div>

				<div v-if="promptDetails.terms && promptDetails.terms.length > 0">
					<h3 class="text-lg font-medium text-neutral-800 mb-2">Terms Found</h3>
					<div class="space-y-3">
						<div v-for="term in promptDetails.terms" :key="term.id" class="bg-white border border-neutral-300 p-3 rounded-lg">
							<p class="text-neutral-800 font-medium">{{ term.name }}</p>
							<div class="mt-2 text-sm text-neutral-500 flex justify-between">
								<span
									>Occurrences: <span class="font-medium">{{ term.pivot.count }}</span></span
								>
								<span>Last found: {{ new Date(term.pivot.last_found_at).toLocaleDateString() }}</span>
							</div>
						</div>
					</div>
				</div>
				<div v-else class="text-neutral-500 italic">No terms have been found in this prompt yet.</div>

				<div v-if="promptStore.isLoadingPromptResponses" class="mt-6 flex justify-center py-4">
					<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
				</div>

				<div v-else-if="promptDetails && promptStore.selectedPromptResponses.length > 0" class="mt-6">
					<h3 class="text-lg font-medium text-neutral-800 mb-2">Responses</h3>
					<div class="space-y-4">
						<div
							v-for="response in promptStore.selectedPromptResponses"
							:key="response.id"
							class="bg-white border border-neutral-200 p-4 rounded-lg"
						>
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
								v-html="highlightTerms(response.content)"
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
										<svg class="inline-block w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24">
											<path
												d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
												fill="#4285F4"
											/>
											<path
												d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
												fill="#34A853"
											/>
											<path
												d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
												fill="#FBBC05"
											/>
											<path
												d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
												fill="#EA4335"
											/>
											<path d="M1 1h22v22H1z" fill="none" />
										</svg>
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

				<div v-else-if="promptDetails && promptStore.selectedPromptResponses.length === 0" class="mt-6 text-neutral-500 italic">
					No responses found for this prompt.
				</div>
			</div>
		</div>
	</Sheet>
</template>
