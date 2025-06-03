<script setup>
import { computed, watch, onMounted, ref } from 'vue'
import { usePromptStore } from '@/stores/promptStore'
import api from '@/services/api'
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

const promptDetails = computed(() => {
	return promptStore.selectedPromptDetails
})

// Fetch and copy prompt to clipboard
const isCopied = ref(false)
const copyPromptToClipboard = async () => {
	if (!promptDetails.value) return

	try {
		const data = await api.get(`prompts/${props.promptId}/optimize`)
		navigator.clipboard.writeText(data.text)

		isCopied.value = true
		setTimeout(() => {
			isCopied.value = false
		}, 2000)
	} catch (error) {
		console.error('Error getting prompt:', error)
	}
}

// Fetch prompt details when component mounts or promptId changes
const fetchDetails = async () => {
	if (props.promptId) {
		await promptStore.showPrompt(props.promptId)
		await promptStore.getPromptResponses(props.promptId)
	}
}

// Method to highlight keywords in response content
const highlightKeywords = (content) => {
	if (!promptDetails.value?.keywords || !content) return content

	let highlightedContent = content

	// Apply highlighting for each keyword
	promptDetails.value.keywords.forEach((keyword) => {
		const regex = new RegExp(keyword.name, 'gi')
		highlightedContent = highlightedContent.replace(regex, (match) => `<span class="bg-yellow-200">${match}</span>`)
	})

	return highlightedContent
}

const closeSheet = () => {
	emit('close')
}

onMounted(fetchDetails)

watch(() => props.promptId, fetchDetails)
</script>

<template>
	<Sheet :is-open="isOpen" @close="closeSheet" position="right" title="Prompt">
		<div class="w-full xl:w-[800px] md:p-4">
			<div v-if="promptStore.isLoadingDetails" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
			</div>
			<div v-else-if="promptDetails" class="space-y-6">
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
							<span class="text-neutral-500">Keyword occurrences:</span>
							<span class="text-neutral-800 ml-2"
								>{{ promptDetails.keywords?.length || 0 }} {{ promptDetails.keywords?.length === 1 ? 'keyword' : 'keywords' }}</span
							>
						</div>
					</div>
				</div>

				<div v-if="promptDetails.keywords && promptDetails.keywords.length > 0">
					<h3 class="text-lg font-medium text-neutral-800 mb-2">Keywords Found</h3>
					<div class="space-y-3">
						<div v-for="keyword in promptDetails.keywords" :key="keyword.id" class="bg-white border border-neutral-300 p-3 rounded-lg">
							<p class="text-neutral-800 font-medium">{{ keyword.name }}</p>
							<div class="mt-2 text-sm text-neutral-500 flex justify-between">
								<span
									>Occurrences: <span class="font-medium">{{ keyword.pivot.count }}</span></span
								>
								<span>Last found: {{ new Date(keyword.pivot.last_found_at).toLocaleDateString() }}</span>
							</div>
						</div>
					</div>
				</div>
				<div v-else class="text-neutral-500 italic">No keywords have been found in this prompt yet.</div>

				<!-- Call to action for article generation -->
				<div class="mt-6 bg-neutral-100 border border-neutral-200 p-6 rounded-lg">
					<h3 class="text-xl font-medium text-neutral-800 mb-2">Optimize for this prompt</h3>
					<p class="text-neutral-600 mb-4">Generate an article that can be published on your website and increase visibility for this prompt</p>
					<Button @click="copyPromptToClipboard" variant="primary" class="flex items-center gap-2">
						<span v-if="isCopied">Copied to clipboard!</span>
						<span v-else>Generate article</span>
						<svg
							v-if="!isCopied"
							xmlns="http://www.w3.org/2000/svg"
							width="16"
							height="16"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round"
							class="lucide lucide-clipboard"
						>
							<rect width="8" height="4" x="8" y="2" rx="1" ry="1" />
							<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
						</svg>
						<svg
							v-else
							xmlns="http://www.w3.org/2000/svg"
							width="16"
							height="16"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round"
							class="lucide lucide-check"
						>
							<path d="M20 6 9 17l-5-5" />
						</svg>
					</Button>
				</div>

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
								v-html="highlightKeywords(response.content)"
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
