<script setup>
import { ref, onMounted, computed, watch, defineAsyncComponent, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import { useDebounceFn } from '@vueuse/core'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import { getHTMLFromFragment } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
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
const isUpdatingEditorFromStore = ref(false) // Renamed and simplified

// Tooltip state
const showTooltip = ref(false)
const tooltipPosition = ref({ top: 0, left: 0 })
const currentSelection = ref(null)

// Dynamically import components
const ArticleVersionsPanel = defineAsyncComponent(() => import('@/components/articles/ArticleVersionsPanel.vue'))
const ArticleSettingsPanel = defineAsyncComponent(() => import('@/components/articles/ArticleSettingsPanel.vue'))
const ArticleDeepResearchResponseModal = defineAsyncComponent(() => import('@/components/articles/ArticleDeepResearchResponseModal.vue'))
const PromptDetailSheet = defineAsyncComponent(() => import('@/components/prompts/PromptDetailSheet.vue'))

const context = computed(() => {
	return {
		viewing_article_id: articleStore.article?.id || null,
		viewing_article_title: articleStore.article?.title || null,
		selected_content: selectedContent.value || null,
		id_of_prompt_belonging_to_article: articleStore.article?.prompt_id || null
	}
})

const editor = useEditor({
	content: '',
	extensions: [
		StarterKit,
		Link.configure({
			openOnClick: false,
			linkOnPaste: true
		})
	],
	onUpdate: ({ editor }) => {
		// Only update store if this is a user-initiated change
		if (articleStore.article && !isUpdatingEditorFromStore.value) {
			articleStore.article.content = editor.getHTML()
		}
	},
	onSelectionUpdate: ({ editor }) => {
		// Get selected HTML when user selects text in editor
		const { from, to } = editor.state.selection
		if (from !== to) {
			// Get the selected content as a fragment
			const fragment = editor.state.doc.slice(from, to).content
			const selectedHTML = getHTMLFromFragment(fragment, editor.schema)

			if (selectedHTML && selectedHTML.trim()) {
				// Store the HTML selection
				currentSelection.value = selectedHTML
				showSelectionTooltip()
			} else {
				hideTooltip()
			}
		} else {
			hideTooltip()
		}
	},
	// Handle paste events specifically
	onTransaction: ({ editor, transaction }) => {
		// Check if this transaction includes pasted content
		if (transaction.docChanged && !isUpdatingEditorFromStore.value) {
			// Small delay to ensure the content is fully updated
			nextTick(() => {
				if (articleStore.article) {
					articleStore.article.content = editor.getHTML()
				}
			})
		}
	}
})

// Show tooltip at the selection position
const showSelectionTooltip = () => {
	if (!editor.value) return

	// Get the selection coordinates
	const { from, to } = editor.value.state.selection
	const start = editor.value.view.coordsAtPos(from)
	const end = editor.value.view.coordsAtPos(to)

	// Calculate position (centered above the selection)
	const left = (start.left + end.left) / 2
	const top = start.top - 10 // 10px above the selection

	tooltipPosition.value = {
		top: top + window.scrollY,
		left: left
	}

	showTooltip.value = true
}

// Hide tooltip
const hideTooltip = () => {
	showTooltip.value = false
	currentSelection.value = null
}

// Add selected text to chat
const addToChat = () => {
	if (currentSelection.value) {
		selectedContent.value = currentSelection.value
		hideTooltip()
	}
}

// Handle clicks outside the editor and tooltip
onMounted(() => {
	const handleClickOutside = (event) => {
		const editorElement = document.querySelector('.ProseMirror')
		const tooltipElement = document.querySelector('.selection-tooltip')

		if (editorElement && !editorElement.contains(event.target) && tooltipElement && !tooltipElement.contains(event.target)) {
			hideTooltip()
		}
	}

	document.addEventListener('click', handleClickOutside)

	// Cleanup
	return () => {
		document.removeEventListener('click', handleClickOutside)
	}
})

// Auto-save content changes with debounce (only for user changes)
const debouncedAutoSaveContent = useDebounceFn(async (content) => {
	if (articleStore.article?.id && content) {
		try {
			console.log('Auto-saving user changes...')
			await articleStore.autoSaveContent(articleStore.article.id, content)
		} catch (error) {
			console.error('Auto-save failed:', error)
		}
	}
}, 2000)

// Watcher to handle both articlestore updates and user changes
watch(
	() => articleStore.article?.content,
	(newContent, oldContent) => {
		// FIRST: Handle store updates that need to update the editor
		if (editor.value && newContent && editor.value.getHTML() !== newContent) {
			console.log('Updating editor content from store')

			// Set flag to prevent auto-save during editor update
			isUpdatingEditorFromStore.value = true
			editor.value.commands.setContent(newContent)

			// Reset flag after editor has been updated
			nextTick(() => {
				setTimeout(() => {
					isUpdatingEditorFromStore.value = false
				}, 100)
			})

			// Exit early since this was a store update, not a user change
			return
		}

		// SECOND: Handle user-initiated changes for auto-save
		if (newContent !== oldContent && !isUpdatingEditorFromStore.value) {
			console.log('User content changed, checking for auto-save')

			// Allow auto-save even for new articles (without ID) if they have content
			if (newContent && newContent.trim() !== '' && newContent !== '<p></p>') {
				debouncedAutoSaveContent(newContent)
			}
		}
	}
)

// Listen for article updates
useEcho(`article.${route.params.id}`, 'ArticleUpdated', async (e) => {
	console.log('Echo: Received article update for ID:', e.id)

	if (e.id === articleStore.article?.id) {
		console.log('Refreshing article data without affecting chat...')

		try {
			// Use the new method that doesn't clear chat state
			await articleStore.refreshArticleData(e.id)
			console.log('Article data refreshed, editor should update automatically')
		} catch (error) {
			console.error('Error refreshing article data:', error)
		}
	}
})

// Listen for deep research updates
useEcho(`article.${route.params.id}`, 'ArticleDeepResearchUpdated', (e) => {
	console.log('Deep research completed for article ID:', e.article_id)

	// Refresh the article data when deep research is completed
	if (e.id === articleStore.article?.id) {
		articleStore.refreshArticleData(e.article_id) // Use new method here too
		isArticleDeepResearchResponseModalOpen.value = true
	}
})

// Function to load article data (for initial load and route changes)
const loadArticle = async (articleId) => {
	try {
		if (!articleId) return

		isLoading.value = true

		// Load article (this will reset chat state for new articles)
		await articleStore.fetchArticle(articleId)

		// Initialize editor content
		if (editor.value && articleStore.article.content) {
			isUpdatingEditorFromStore.value = true
			editor.value.commands.setContent(articleStore.article.content)

			nextTick(() => {
				setTimeout(() => {
					isUpdatingEditorFromStore.value = false
				}, 100)
			})
		}

		isLoading.value = false
	} catch (error) {
		console.error('Error loading article:', error)
		isLoading.value = false
	}
}

// Watch for route changes to load new article
watch(
	() => route.params.id,
	(newId) => {
		if (newId) {
			loadArticle(newId)
		}
	},
	{ immediate: true }
)

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
				<div class="flex justify-between items-start gap-10 mb-4 pr-6 ml-8">
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
				<div v-if="isLoading" class="flex justify-center py-8 mx-8">
					<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
				</div>

				<div class="flex flex-col gap-6">
					<!-- Versions panel - dynamically loaded -->
					<ArticleVersionsPanel v-if="isVersionsOpen" />

					<!-- Settings panel -->
					<ArticleSettingsPanel v-if="isSettingsOpen" @close="isSettingsOpen = false" />

					<!-- Rich text editor -->
					<div class="flex flex-col overflow-hidden relative">
						<!-- Editor menu -->
						<EditorMenu v-if="editor" :editor="editor" class="ml-8" />

						<!-- Editor content -->
						<div class="pl-8 pr-6 pt-4 min-h-[400px] max-h-[calc(100vh-200px)] overflow-y-auto custom-scrollbar">
							<EditorContent v-if="editor" :editor="editor" />
						</div>
					</div>
				</div>
			</div>
		</template>
	</TwoColumnLayout>

	<!-- Selection Tooltip -->
	<Teleport to="body">
		<div
			v-if="showTooltip"
			class="selection-tooltip fixed z-50 bg-black text-white rounded-md shadow-lg transform -translate-x-1/2 -translate-y-full"
			:style="{
				top: `${tooltipPosition.top}px`,
				left: `${tooltipPosition.left}px`
			}"
		>
			<button @click="addToChat" class="px-3 py-2 text-sm font-medium hover:text-gray-200 transition-colors cursor-pointer">Add to chat</button>
			<!-- Arrow pointing down -->
			<div class="absolute left-1/2 transform -translate-x-1/2 top-full">
				<div class="w-0 h-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[6px] border-t-black"></div>
			</div>
		</div>
	</Teleport>

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

<style>
/* Add styles for the tooltip animation */
.selection-tooltip {
	animation: tooltipFadeIn 0.2s ease-out;
}

@keyframes tooltipFadeIn {
	from {
		opacity: 0;
		transform: translate(-50%, -90%);
	}
	to {
		opacity: 1;
		transform: translate(-50%, -100%);
	}
}
</style>
