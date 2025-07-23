<script setup>
import { computed, watch, onMounted, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePromptStore } from '@/stores/promptStore'
import { useArticleStore } from '@/stores/articleStore'
import api from '@/services/api.js'
import Sheet from '@/components/ui/Sheet.vue'
import Button from '@/components/ui/Button.vue'
import CopyIcon from '@/components/icons/CopyIcon.vue'
import SparkleIcon from '@/components/icons/SparkleIcon.vue'
import GoogleIcon from '@/components/icons/GoogleIcon.vue'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	},
	promptId: {
		type: [Number, String],
		default: null
	}
})

const router = useRouter()
const route = useRoute()
const teamId = route.params.teamId

const emit = defineEmits(['close'])

const promptStore = usePromptStore()
const articleStore = useArticleStore()
const isCopied = ref(false)

const promptDetails = computed(() => {
	return promptStore.selectedPromptDetails
})

// Get the basic prompt data from the store
const prompt = computed(() => {
	return promptStore.prompts.find((p) => p.id === Number(props.promptId)) || null
})

// Get mentions percentage from either the prompt details or the prompt list
const mentionsPercentage = computed(() => {
	if (promptDetails.value?.mentions_percentage !== undefined) {
		return promptDetails.value.mentions_percentage
	} else if (prompt.value?.mentions_percentage !== undefined) {
		return prompt.value.mentions_percentage
	}
	return 0
})

// Watch isOpen and fetch details when the sheet opens
watch(
	() => props.isOpen,
	(isOpen) => {
		if (isOpen && props.promptId) {
			fetchDetails()
		}
	}
)

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

const exportPrompt = async () => {
	if (!props.promptId) return

	try {
		const response = await api.get(`prompts/${props.promptId}/export`)
		await navigator.clipboard.writeText(JSON.stringify(response, null, 2))
		isCopied.value = true
		setTimeout(() => {
			isCopied.value = false
		}, 2000)
	} catch (error) {
		console.error('Error exporting prompt:', error)
	}
}

const createArticle = async () => {
        const newArticle = await articleStore.createArticle(teamId, {
                title: 'Untitled article',
                prompt_id: props.promptId
        })
	router.push({ name: 'articles.edit', params: { id: newArticle.id } })
}

onMounted(fetchDetails)

watch(() => props.promptId, fetchDetails)
</script>

<template>
	<Sheet :is-open="isOpen" @close="closeSheet" position="right" title="Prompt">
		<div class="w-full xl:w-[800px] md:p-4">
			<!-- Loader for sheet -->
			<div v-if="promptStore.isLoadingDetails" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
			</div>

			<!-- Sheet content -->
			<div v-else-if="promptDetails" class="space-y-6">
				<!-- Prompt header -->
				<div class="bg-neutral-50 p-4 rounded-lg">
					<div class="mb-4">
						<div class="flex justify-between items-start">
							<span class="text-neutral-500 text-sm">Content:</span>
							<Button @click="exportPrompt" variant="link" size="sm">
								<CopyIcon />
								{{ isCopied ? 'Copied!' : 'Export' }}
							</Button>
						</div>
						<p class="text-neutral-800 text-2xl/7 font-medium mt-1">{{ promptDetails.content }}</p>
					</div>
					<div class="mb-1 text-sm">
						<span class="text-neutral-500">Mentioned:</span>
						<span class="text-neutral-800 ml-2">{{ mentionsPercentage }}% of the time</span>
					</div>
					<div class="mb-2 text-sm">
						<span class="text-neutral-500">Term occurrences:</span>
						<span class="text-neutral-800 ml-2">
							{{ promptDetails.terms?.length || 0 }} {{ promptDetails.terms?.length === 1 ? 'term' : 'terms' }}
						</span>
					</div>
				</div>

				<!-- Articles section -->
				<div class="mt-6">
					<div class="flex items-center justify-between gap-6 mb-6">
						<div>
							<h3 class="text-lg font-medium text-neutral-800 mb-1">Articles</h3>
							<p class="text-neutral-600">Generate an article to optimize visibility for this prompt</p>
						</div>
						<Button @click.stop="createArticle" class="flex items-center gap-2 mr-2" variant="outline" size="sm">
							<SparkleIcon />
							Create article
						</Button>
					</div>

					<div v-if="promptDetails.articles && promptDetails.articles.length > 0" class="space-y-4">
						<RouterLink
							v-for="article in promptDetails.articles"
							:key="article.id"
							:to="`/articles/${article.id}/edit`"
							class="block bg-white border border-neutral-200 p-4 rounded-lg hover:bg-neutral-50 transition-colors"
						>
							<div class="flex justify-between items-center mb-3">
								<h4 class="font-medium text-neutral-800">{{ article.title }}</h4>
								<span class="text-xs text-neutral-500">{{ new Date(article.created_at).toLocaleDateString() }}</span>
							</div>

							<div class="text-sm text-neutral-600 mb-3 line-clamp-3">
								{{ article.content ? article.content.substring(0, 200) + '...' : 'No content available' }}
							</div>
						</RouterLink>
					</div>
					<div v-else class="text-neutral-500 italic">No articles have been generated for this prompt yet.</div>
				</div>

				<!-- Responses section -->
				<div class="mt-6">
					<div v-if="promptStore.isLoadingPromptResponses" class="mt-6 flex justify-center py-4">
						<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
					</div>

					<div v-else-if="promptDetails && promptStore.selectedPromptResponses.length > 0">
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
											<GoogleIcon />
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

				<!-- Terms section -->
				<div class="mt-6">
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
				</div>
			</div>
		</div>
	</Sheet>
</template>
