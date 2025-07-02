<script setup>
import { ref, onMounted, computed, watch, defineAsyncComponent } from 'vue'
import { useRoute } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import { useDebounceFn } from '@vueuse/core'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TwoColumnLayout from '@/layouts/TwoColumnLayout.vue'
import Button from '@/components/ui/Button.vue'
import ChatInterface from '@/components/chat/ChatInterface.vue'
import EditorMenu from '@/components/editor/EditorMenu.vue'
import { useEcho } from '@laravel/echo-vue'

const route = useRoute()
const articleStore = useArticleStore()

const isLoading = ref(true)
const isSettingsOpen = ref(false)
const isVersionsOpen = ref(false)
const isPromptDetailSheetOpen = ref(false)
const isArticleDeepResearchResponseModalOpen = ref(false)

const selectedContent = ref(null)
const isCopied = ref(false)
const isUpdatingFromAutoSave = ref(false)

// Dynamically import components
const ArticleVersionsPanel = defineAsyncComponent(() => import('@/components/articles/ArticleVersionsPanel.vue'))
const ArticleSettingsPanel = defineAsyncComponent(() => import('@/components/articles/ArticleSettingsPanel.vue'))
const ArticleDeepResearchResponseModal = defineAsyncComponent(() => import('@/components/articles/ArticleDeepResearchResponseModal.vue'))
const PromptDetailSheet = defineAsyncComponent(() => import('@/components/prompts/PromptDetailSheet.vue'))

const context = computed(() => {
	return {
		viewing_article_id: articleStore.article?.id || null,
		viewing_article_title: articleStore.article?.title || null,
		id_of_prompt_belonging_to_article: articleStore.article?.prompt_id || null,
		selected_content: selectedContent.value || null
	}
})

const editor = useEditor({
	content: '',
	extensions: [StarterKit],
	onUpdate: ({ editor }) => {
		if (articleStore.article && !isUpdatingFromAutoSave.value) {
			articleStore.article.content = editor.getHTML()
		}
	},
	onSelectionUpdate: ({ editor }) => {
		// Get selected text when user selects text in editor
		const { from, to } = editor.state.selection
		if (from !== to) {
			const selectedText = editor.state.doc.textBetween(from, to)
			if (selectedText.trim()) {
				selectedContent.value = selectedText.trim()
			}
		}
	}
})

// Auto-save content changes with debounce
const debouncedAutoSaveContent = useDebounceFn(async (content) => {
	if (articleStore.article?.id && content) {
		try {
			isUpdatingFromAutoSave.value = true
			await articleStore.autoSaveContent(articleStore.article.id, content)
		} catch (error) {
			console.error('Auto-save failed:', error)
		} finally {
			// Reset the flag after a short delay to ensure the watcher doesn't trigger
			setTimeout(() => {
				isUpdatingFromAutoSave.value = false
			}, 100)
		}
	}
}, 2000)

// Watch for content changes and auto-save
watch(
	() => articleStore.article?.content,
	(newContent, oldContent) => {
		// Skip if this is the initial load or if content hasn't actually changed
		if (!oldContent || newContent === oldContent) return

		// Only auto-save if we have a valid article ID and content
		if (articleStore.article?.id && newContent) {
			console.log('Content changed, triggering auto-save')
			debouncedAutoSaveContent(newContent)
		}
	}
)

// Watch for changes in the article content from the store and update the editor
// Only update if the content actually differs AND it's not from our own auto-save
watch(
	() => articleStore.article?.content,
	(newContent) => {
		if (editor.value && newContent && editor.value.getHTML() !== newContent && !isUpdatingFromAutoSave.value) {
			editor.value.commands.setContent(newContent)
		}
	}
)

// Listen for article updates
useEcho(`article.${route.params.id}`, 'ArticleUpdated', (e) => {
	console.log('Received update for article ID:', e.id)

	// Update the article content
	if (e.id === articleStore.article.id) {
		isUpdatingFromAutoSave.value = true
		articleStore.article = e

		// Update the editor content if it's different
		if (editor.value.getHTML() !== e.content) {
			editor.value.commands.setContent(e.content)
		}

		setTimeout(() => {
			isUpdatingFromAutoSave.value = false
		}, 100)
	}
})

// Listen for deep research updates
useEcho(`article.${route.params.id}`, 'ArticleDeepResearchUpdated', (e) => {
	console.log('Deep research completed for article ID:', e.article_id)

	// Refresh the article data when deep research is completed
	if (e.id === articleStore.article.id) {
		articleStore.fetchArticle(e.article_id)
		isArticleDeepResearchResponseModalOpen.value = true
	}
})

onMounted(async () => {
	try {
		const articleId = route.params.id
		if (!articleId) return

		// Load article
		await articleStore.fetchArticle(articleId)

		// Initialize editor content
		if (editor.value && articleStore.article.content) {
			editor.value.commands.setContent(articleStore.article.content)
		}

		isLoading.value = false
	} catch (error) {
		console.error('Error loading article:', error)
		isLoading.value = false
	}
})

// Open the prompt detail sheet
const showPromptDetails = () => {
	if (articleStore.article?.prompt_id) {
		isPromptDetailSheetOpen.value = true
	}
}

const copyContentToClipboard = async () => {
	try {
		if (articleStore.article?.content) {
			await navigator.clipboard.writeText(articleStore.article.content)
			isCopied.value = true
			setTimeout(() => {
				isCopied.value = false
			}, 2000)
		}
	} catch (error) {
		console.error('Failed to copy content:', error)
	}
}

// Clear selected content
const clearSelectedContent = () => {
	selectedContent.value = null
}
</script>

<template>
	<TwoColumnLayout>
		<template #left-column>
			<!-- Chat Interface Component -->
			<ChatInterface :context="context" @clear-selected-content="clearSelectedContent" />
		</template>

		<template #right-column>
			<!-- Deep research statuses -->
			<div
				v-if="articleStore.article?.perplexity_status === 'CREATED' || articleStore.article?.perplexity_status === 'IN_PROGRESS'"
				class="p-4 my-4 bg-green-50 border border-green-200 text-green-800 rounded-lg mr-6"
			>
				<div class="flex items-center gap-2 mb-2">
					<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
					Deep research is in progress...
				</div>
				<div class="w-full bg-green-200 rounded-full h-2.5 mt-2">
					<div
						class="bg-green-600 h-2.5 rounded-full transition-all duration-500 ease-in-out"
						:style="{ width: `${Math.min(((articleStore.article?.perplexity_checks || 0) / 60) * 100, 100)}%` }"
					></div>
				</div>
				<div class="text-xs text-green-800 mt-1 text-right">
					{{ Math.min(Math.round(((articleStore.article?.perplexity_checks || 0) / 60) * 100), 100) }}% completed
				</div>
			</div>
			<div
				v-if="articleStore.article?.perplexity_status === 'COMPLETED' && articleStore.article?.perplexity_request_id"
				class="p-4 my-4 bg-green-50 border border-green-200 text-green-800 rounded-lg mr-6"
			>
				<div class="flex items-center justify-between">
					<div class="flex items-center gap-2">✅ Deep research completed</div>
					<Button @click="isArticleDeepResearchResponseModalOpen = true" variant="success" size="sm"> View Deep Research </Button>
				</div>
			</div>

			<div
				v-if="articleStore.article?.perplexity_status === 'FAILED'"
				class="p-4 my-4 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center gap-2 mr-6"
			>
				Deep research failed
			</div>

			<div class="pt-4">
				<!-- Top bar -->
				<div class="flex justify-between items-start gap-10 mb-4 pr-6">
					<h1 class="text-xl font-bold">{{ articleStore.article?.title || 'Edit Article' }}</h1>

					<div class="flex items-center justify-end gap-2">
						<div class="flex gap-2">
							<Button @click="isSettingsOpen = !isSettingsOpen" variant="outline" size="sm">
								{{ isSettingsOpen ? 'Hide Settings' : 'Settings' }}
							</Button>

							<Button @click="isVersionsOpen = !isVersionsOpen" variant="outline" size="sm">
								{{ isVersionsOpen ? 'Hide Versions' : 'Versions' }}
							</Button>

							<Button @click="copyContentToClipboard" variant="outline" size="sm" :disabled="isCopied">
								{{ isCopied ? 'Copied!' : 'Copy HTML' }}
							</Button>

							<Button v-if="articleStore.article && articleStore.article.prompt_id" @click="showPromptDetails" variant="outline" size="sm"
								>Prompt</Button
							>
						</div>
					</div>
				</div>

				<!-- Loading state -->
				<div v-if="isLoading" class="flex justify-center py-8">
					<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
				</div>

				<div v-else class="flex flex-col gap-6">
					<!-- Versions panel - dynamically loaded -->
					<ArticleVersionsPanel v-if="isVersionsOpen" />

					<!-- Settings panel -->
					<ArticleSettingsPanel v-if="isSettingsOpen" @close="isSettingsOpen = false" />

					<!-- Rich text editor -->
					<div class="flex flex-col overflow-hidden">
						<!-- Editor menu -->
						<EditorMenu :editor="editor" />

						<!-- Editor content -->
						<div class="pl-2 pr-6 min-h-[400px] max-h-[calc(100vh-200px)] overflow-y-auto custom-scrollbar">
							<EditorContent :editor="editor" />
						</div>
					</div>
				</div>
			</div>
		</template>
	</TwoColumnLayout>

	<!-- Prompt Detail Sheet - now async loaded -->
	<PromptDetailSheet
		v-if="articleStore.article?.prompt_id && isPromptDetailSheetOpen"
		:is-open="isPromptDetailSheetOpen"
		:prompt-id="articleStore.article.prompt_id"
		@close="isPromptDetailSheetOpen = false"
	/>

	<!-- Perplexity Response Modal - now async loaded -->
	<ArticleDeepResearchResponseModal
		v-if="articleStore.article?.perplexity_request_id && isArticleDeepResearchResponseModalOpen"
		:is-open="isArticleDeepResearchResponseModalOpen"
		:article-id="articleStore.article?.id"
		@close="isArticleDeepResearchResponseModalOpen = false"
	/>
</template>
