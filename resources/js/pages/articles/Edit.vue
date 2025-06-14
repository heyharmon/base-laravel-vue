<script setup>
import { ref, onMounted, computed, onUnmounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import { useJobStatusStore } from '@/stores/jobStatusStore'
import moment from 'moment'
import { usePromptStore } from '@/stores/promptStore'
import PromptDetailSheet from '@/components/prompts/PromptDetailSheet.vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import CopyIcon from '@/components/icons/CopyIcon.vue'
import SettingsIcon from '@/components/icons/SettingsIcon.vue'
import StarterKit from '@tiptap/starter-kit'
import TwoColumnLayout from '@/layouts/TwoColumnLayout.vue'
import Button from '@/components/ui/Button.vue'
import ChatMessage from '@/components/ChatMessage.vue'
import ChatInput from '@/components/ChatInput.vue'
import ArticleConversationDropdown from '@/components/conversations/ArticleConversationDropdown.vue'
import ChatLoadingIndicator from '@/components/ChatLoadingIndicator.vue'
import EditorMenu from '@/components/editor/EditorMenu.vue'
import { useEcho } from '@laravel/echo-vue'

const route = useRoute()
const articleStore = useArticleStore()
const jobStatusStore = useJobStatusStore()

const isLoading = ref(true)
const showSettings = ref(false)
const showVersions = ref(false)
const messagesContainer = ref(null)

// Get active jobs related to this article
const activeArticleJobs = computed(() => {
	if (!articleStore.article) return []
	return jobStatusStore.jobs.filter(
		(job) =>
			job.trackable_type === 'App\\Models\\Article' &&
			job.trackable_id === articleStore.article.id &&
			(job.status === 'pending' || job.status === 'processing')
	)
})

// Format the version date for display
const formatVersionDate = (dateString) => {
	if (!dateString) return ''
	return moment(dateString).fromNow()
}

// Handle reverting to a previous version
const revertToVersion = async (versionId) => {
	if (!articleStore.article?.id || !versionId) return

	if (confirm('Are you sure you want to revert to this version? Current changes will be lost.')) {
		try {
			await articleStore.revertToVersion(articleStore.article.id, versionId)
			// Update editor content
			if (editor.value && articleStore.article.content) {
				editor.value.commands.setContent(articleStore.article.content)
			}
		} catch (err) {
			console.error('Failed to revert to version:', err)
		}
	}
}

const editor = useEditor({
	content: '',
	extensions: [StarterKit],
	onUpdate: ({ editor }) => {
		articleStore.article.content = editor.getHTML()
	}
})

const handleEditorCommand = (command, options = {}) => {
	if (command === 'undo') return editor.value.chain().focus().undo().run()
	if (command === 'redo') return editor.value.chain().focus().redo().run()

	const commandMap = {
		bold: () => editor.value.chain().focus().toggleBold().run(),
		italic: () => editor.value.chain().focus().toggleItalic().run(),
		heading: ({ level }) => editor.value.chain().focus().toggleHeading({ level }).run(),
		bulletList: () => editor.value.chain().focus().toggleBulletList().run(),
		orderedList: () => editor.value.chain().focus().toggleOrderedList().run(),
		blockquote: () => editor.value.chain().focus().toggleBlockquote().run()
	}

	if (commandMap[command]) {
		commandMap[command](options)
	}
}

const activeEditorCommands = computed(() => ({
	bold: editor.value?.isActive('bold'),
	italic: editor.value?.isActive('italic'),
	heading: {
		level: [1, 2, 3, 4].find((level) => editor.value?.isActive('heading', { level }))
	},
	bulletList: editor.value?.isActive('bulletList'),
	orderedList: editor.value?.isActive('orderedList'),
	blockquote: editor.value?.isActive('blockquote')
}))

const fetchArticle = async () => {
	console.log('Fetching article...')
	const data = await articleStore.fetchArticle(route.params.id)

	editor.value.commands.setContent(articleStore.article.content) // Set editor content
}

// Echo channel subscription handlers
const { leaveChannel, listen } = useEcho(`article.${route.params.id}`, 'ArticleUpdated', (e) => {
	console.log('Received update for article ID:', e.id)

	// Update the article content
	if (e.id === articleStore.article.id) {
		articleStore.article.content = e.content

		// Update the editor content if it's different
		if (editor.value && editor.value.getHTML() !== e.content) {
			editor.value.commands.setContent(e.content)
		}
	}
})

// Function to scroll to the bottom of the messages container
const scrollToBottom = () => {
	nextTick(() => {
		if (messagesContainer.value) {
			messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
		}
	})
}

// Watch for changes in the chats array to scroll to bottom when new messages are added
watch(
	() => articleStore.chats.length,
	(newLength, oldLength) => {
		if (newLength > oldLength) {
			scrollToBottom()
		}
	}
)

onMounted(async () => {
	try {
		if (route.params.id) {
			fetchArticle()

			// Fetch chats for this article
			await articleStore.fetchChats(route.params.id)

			// Scroll to bottom after chats are loaded
			scrollToBottom()

			// Subscribe to real-time updates
			listen()
		}
	} catch (error) {
		console.error('Error fetching article:', error)
	} finally {
		isLoading.value = false
	}
})

onUnmounted(() => {
	// Leave the Echo channel when component is unmounted
	leaveChannel()
})

const isCopied = ref(false)
const isPromptDetailSheetOpen = ref(false)
const promptStore = usePromptStore()

// Preset prompts for empty chat
const presetPrompts = [
	'🧠 Summarize this article for me',
	'🔗 List sources mentioned in prompt responses',
	'✨ Suggest improvements for this article',
	'🌐 Search the web for information related to this article'
]

// Handle conversation selection from dropdown
const handleConversationChanged = async (conversationId) => {
	if (conversationId) {
		articleStore.setConversationId(conversationId)
		await articleStore.fetchChats()
	}
}

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
</script>

<template>
	<TwoColumnLayout>
		<template #left-column>
			<!-- Chat column -->
			<div class="flex-1 overflow-hidden flex flex-col h-full">
				<!-- Messages top bar -->
				<div class="py-2 px-4">
					<ArticleConversationDropdown :article-id="articleStore.article?.id" @conversation-changed="handleConversationChanged" />
				</div>

				<!-- Messages area (scrollable) -->
				<div ref="messagesContainer" class="flex-1 overflow-y-auto scrollbar-thin p-4 pb-8 space-y-6 custom-scrollbar">
					<div v-if="articleStore.chats.length === 0 && !articleStore.isLoadingChats" class="flex flex-col gap-3 p-2">
						<p class="text-neutral-600 font-medium">Start a conversation with one of these prompts:</p>
						<button
							v-for="(prompt, index) in presetPrompts"
							:key="index"
							@click="articleStore.sendMessage(prompt)"
							class="cursor-pointer text-left p-3 bg-neutral-100 hover:bg-neutral-200 rounded-lg transition-colors"
						>
							{{ prompt }}
						</button>
					</div>
					<ChatMessage v-for="(chat, index) in articleStore.chats" :key="index" :chat="chat" />
					<ChatLoadingIndicator v-if="articleStore.isLoadingChats" />
				</div>

				<!-- Input area (fixed at bottom) -->
				<div class="px-4 pb-4 bg-transparent -mt-4">
					<ChatInput v-model="articleStore.newMessage" @send="articleStore.sendMessage" :disabled="articleStore.isLoadingChats" />
				</div>
			</div>
		</template>

		<template #right-column>
			<!-- Active jobs message -->
			<div v-if="activeArticleJobs.length > 0" class="p-4 mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center gap-2">
				<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-green-700 rounded-full"></span>
				<span>
					{{ activeArticleJobs.length }}
					{{ activeArticleJobs.length === 1 ? 'job is running for this article' : 'jobs are running for this article' }}
				</span>
			</div>

			<div class="pt-4">
				<!-- Top bar -->
				<div class="flex justify-between items-start gap-10 mb-4 pr-6">
					<h1 class="text-xl font-bold">{{ articleStore.article?.title || 'Edit Article' }}</h1>

					<div class="flex items-center justify-end gap-2">
						<!-- Auto-save indicator -->
						<div class="flex items-center mr-2 text-sm text-neutral-600">
							<span v-if="articleStore.isSaving" class="flex items-center">
								<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-neutral-600 rounded-full"></span>
								Saving...
							</span>
							<span v-else-if="articleStore.error" class="text-red-600">{{ articleStore.error }}</span>
						</div>

						<div class="flex gap-2">
							<Button @click="showSettings = !showSettings" variant="outline" size="sm">
								{{ showSettings ? 'Hide Settings' : 'Settings' }}
							</Button>

							<Button @click="showVersions = !showVersions" variant="outline" size="sm">
								{{ showVersions ? 'Hide Versions' : 'Versions' }}
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

				<!-- Error state -->
				<div v-else-if="articleStore.error" class="bg-red-100 border border-red-400 text-red-700 pl-4 pr-6 py-3 rounded mb-4">
					{{ articleStore.error }}
				</div>

				<div v-else class="flex flex-col gap-6">
					<!-- Versions panel -->
					<div v-if="showVersions && articleStore.article?.versions?.length > 0" class="bg-neutral-50 p-4 rounded-md border border-neutral-200 mb-2">
						<h2 class="text-lg font-medium mb-4">Article Versions</h2>
						<p class="text-sm text-neutral-500 mb-3">Select a version to revert the article to that state.</p>

						<div class="max-h-60 overflow-y-auto custom-scrollbar">
							<div
								v-for="version in articleStore.article.versions"
								:key="version.id"
								:class="[
									'flex justify-between items-center p-3 rounded-md border mb-2 last:mb-0',
									version.version_number === articleStore.article.current_version
										? 'bg-neutral-100 border-neutral-300'
										: 'bg-white border-neutral-200'
								]"
							>
								<div>
									<div class="text-sm font-medium">
										Version {{ version.version_number }}
										{{ version.version_number === articleStore.article.current_version ? '(Current version)' : '' }}
									</div>
									<div class="text-xs text-neutral-500">{{ formatVersionDate(version.created_at) }}</div>
								</div>
								<Button @click="revertToVersion(version.id)" variant="outline" size="xs" :disabled="articleStore.isRevertingVersion">
									{{ articleStore.isRevertingVersion ? 'Reverting...' : 'Revert' }}
								</Button>
							</div>
						</div>

						<div v-if="articleStore.article?.versions?.length === 0" class="text-sm text-neutral-500 p-2">
							No versions available yet. Versions are created when you edit the article content.
						</div>
					</div>

					<!-- Settings panel -->
					<div v-if="showSettings" class="bg-neutral-50 p-4 rounded-md border border-neutral-200 mb-2">
						<h2 class="text-lg font-medium mb-4">Article Settings</h2>

						<div class="flex flex-col gap-4">
							<!-- Title input -->
							<div>
								<label for="title" class="block text-sm font-medium text-neutral-700 mb-1">Title</label>
								<input
									id="title"
									v-model="articleStore.article.title"
									type="text"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
									placeholder="Article title"
								/>
							</div>

							<!-- Meta Title input -->
							<div>
								<label for="meta_title" class="block text-sm font-medium text-neutral-700 mb-1">Meta Title</label>
								<input
									id="meta_title"
									v-model="articleStore.article.meta_title"
									type="text"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
									placeholder="Meta title for SEO"
								/>
							</div>

							<!-- Meta Description input -->
							<div>
								<label for="meta_description" class="block text-sm font-medium text-neutral-700 mb-1">Meta Description</label>
								<textarea
									id="meta_description"
									v-model="articleStore.article.meta_description"
									rows="3"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
									placeholder="Meta description for SEO"
								></textarea>
							</div>

							<!-- Schema input -->
							<div>
								<label for="schema" class="block text-sm font-medium text-neutral-700 mb-1">Schema</label>
								<textarea
									id="schema"
									v-model="articleStore.article.schema"
									rows="5"
									class="bg-white w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500 font-mono text-sm"
									placeholder="JSON-LD structured data schema"
								></textarea>
							</div>
						</div>
					</div>

					<!-- Rich text editor -->
					<div class="flex flex-col overflow-hidden">
						<!-- Editor menu -->
						<EditorMenu @command="handleEditorCommand" :active-commands="activeEditorCommands" />

						<!-- Editor content -->
						<div class="pl-2 pr-6 min-h-[400px] max-h-[calc(100vh-200px)] overflow-y-auto custom-scrollbar">
							<EditorContent :editor="editor" />
						</div>
					</div>
				</div>
			</div>
		</template>
	</TwoColumnLayout>

	<!-- Prompt Detail Sheet -->
	<PromptDetailSheet
		v-if="articleStore.article?.prompt_id"
		:is-open="isPromptDetailSheetOpen"
		:prompt-id="articleStore.article.prompt_id"
		@close="isPromptDetailSheetOpen = false"
	/>
</template>
