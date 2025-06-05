<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useArticleStore } from '@/stores/articleStore'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import Button from '@/components/ui/Button.vue'

const route = useRoute()
const router = useRouter()
const articleStore = useArticleStore()

const article = ref({
	title: '',
	outline: '',
	content: '',
	organization_id: null,
	prompt_id: null,
	conversation_id: null
})

const originalArticle = ref({
	title: '',
	outline: '',
	content: '',
	organization_id: null,
	prompt_id: null,
	conversation_id: null
})

const isSubmitting = ref(false)
const isLoading = ref(true)

const editor = useEditor({
	content: '',
	extensions: [StarterKit],
	onUpdate: ({ editor }) => {
		article.value.content = editor.getHTML()
	}
})

onMounted(async () => {
	try {
		if (route.params.id) {
			const data = await articleStore.fetchArticle(route.params.id)
			article.value = { ...data }
			originalArticle.value = { ...data }

			// Set editor content
			if (editor.value && article.value.content) {
				editor.value.commands.setContent(article.value.content)
			}
		}
	} catch (error) {
		console.error('Error fetching article:', error)
	} finally {
		isLoading.value = false
	}
})

const hasChanges = computed(() => {
	return (
		article.value.title !== originalArticle.value.title ||
		article.value.outline !== originalArticle.value.outline ||
		article.value.content !== originalArticle.value.content ||
		article.value.organization_id !== originalArticle.value.organization_id ||
		article.value.prompt_id !== originalArticle.value.prompt_id
	)
})

const updateArticle = async () => {
	isSubmitting.value = true
	try {
		await articleStore.updateArticle(route.params.id, article.value)
		window.location.reload()
	} catch (error) {
		console.error('Error updating article:', error)
	} finally {
		isSubmitting.value = false
	}
}

const cancelEdit = () => {
	router.push({ name: 'articles.index' })
}
</script>

<template>
	<DefaultLayout>
		<div class="container mx-auto py-8">
			<!-- Top bar -->
			<div class="flex justify-between items-start mb-8">
				<div class="flex items-center gap-3">
					<h1 class="text-2xl font-bold">{{ article.title || 'Edit Article' }}</h1>
				</div>
				<div class="flex items-center justify-end gap-2 w-2/6">
					<Button v-if="hasChanges" @click="updateArticle" :disabled="isSubmitting" :loading="isSubmitting"> Save </Button>
					<Button @click="cancelEdit" variant="neutral"> Cancel </Button>
				</div>
			</div>

			<!-- Loading state -->
			<div v-if="isLoading" class="flex justify-center py-8">
				<div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
			</div>

			<!-- Error state -->
			<div v-else-if="articleStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
				{{ articleStore.error }}
			</div>

			<div v-else class="flex flex-col gap-6">
				<!-- Title input -->
				<div>
					<label for="title" class="block text-sm font-medium text-neutral-700 mb-1">Title</label>
					<input
						id="title"
						v-model="article.title"
						type="text"
						class="w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
						placeholder="Article title"
					/>
				</div>

				<!-- Outline input -->
				<!-- <div>
          <label for="outline" class="block text-sm font-medium text-neutral-700 mb-1">Outline</label>
          <textarea
            id="outline"
            v-model="article.outline"
            rows="4"
            class="w-full px-4 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-neutral-500 focus:border-neutral-500"
            placeholder="Article outline"
          ></textarea>
        </div> -->

				<!-- Rich text editor -->
				<div>
					<label class="block text-sm font-medium text-neutral-700 mb-1">Content</label>
					<div class="border border-neutral-300 rounded-md shadow-sm overflow-hidden">
						<!-- Editor menu -->
						<div class="flex items-center gap-2 p-2 border-b border-neutral-300 bg-neutral-50">
							<button
								@click="editor.chain().focus().toggleBold().run()"
								:class="{ 'bg-neutral-200': editor.isActive('bold') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path><path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path></svg>
							</button>
							<button
								@click="editor.chain().focus().toggleItalic().run()"
								:class="{ 'bg-neutral-200': editor.isActive('italic') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><line x1=19 x2=10 y1=4 y2=4></line><line x1=14 x2=5 y1=20 y2=20></line><line x1=15 x2=9 y1=4 y2=20></line></svg>
							</button>
							<button
								@click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
								:class="{ 'bg-neutral-200': editor.isActive('heading', { level: 1 }) }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								H1
							</button>
							<button
								@click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
								:class="{ 'bg-neutral-200': editor.isActive('heading', { level: 2 }) }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								H2
							</button>
							<button
								@click="editor.chain().focus().toggleBulletList().run()"
								:class="{ 'bg-neutral-200': editor.isActive('bulletList') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><line x1=8 x2=21 y1=6 y2=6></line><line x1=8 x2=21 y1=12 y2=12></line><line x1=8 x2=21 y1=18 y2=18></line><line x1=3 x2=3.01 y1=6 y2=6></line><line x1=3 x2=3.01 y1=12 y2=12></line><line x1=3 x2=3.01 y1=18 y2=18></line></svg>
							</button>
							<button
								@click="editor.chain().focus().toggleOrderedList().run()"
								:class="{ 'bg-neutral-200': editor.isActive('orderedList') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="10" y1="6" x2="21" y2="6"></line><line x1="10" y1="12" x2="21" y2="12"></line><line x1="10" y1="18" x2="21" y2="18"></line><path d="M4 6h1v4"></path><path d="M4 10h2"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>
							</button>
							<button
								@click="editor.chain().focus().toggleBlockquote().run()"
								:class="{ 'bg-neutral-200': editor.isActive('blockquote') }"
								class="p-1 rounded hover:bg-neutral-200"
							>
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><path d="M14 9.5L21 9.5"></path><path d="M14 14.5L21 14.5"></path><path d="M3 9.5L10 9.5"></path><path d="M3 14.5L10 14.5"></path><path d="M10 19.5L3 19.5L3 4.5L10 4.5"></path><path d="M21 19.5L14 19.5L14 4.5L21 4.5"></path></svg>
							</button>
							<button @click="editor.chain().focus().undo().run()" class="p-1 rounded hover:bg-neutral-200">
								<!-- prettier-ignore -->
								<svg fill=none height=16 stroke=currentColor stroke-linecap=round stroke-linejoin=round stroke-width=2 viewBox="0 0 24 24" width=16 xmlns=http://www.w3.org/2000/svg><path d="M3 7v6h6"></path><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path></svg>
							</button>
							<button @click="editor.chain().focus().redo().run()" class="p-1 rounded hover:bg-neutral-200">
								<!-- prettier-ignore -->
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"></path><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"></path></svg>
							</button>
						</div>

						<!-- Editor content -->
						<div class="p-4 min-h-[400px]">
							<EditorContent :editor="editor" />
						</div>
					</div>
				</div>
			</div>
		</div>
	</DefaultLayout>
</template>

<style>
.ProseMirror {
	min-height: 300px;
	outline: none;
}

.ProseMirror p {
	margin-bottom: 0.75em;
}

.ProseMirror h1 {
	font-size: 1.5em;
	font-weight: bold;
	margin-bottom: 0.5em;
}

.ProseMirror h2 {
	font-size: 1.25em;
	font-weight: bold;
	margin-bottom: 0.5em;
}

.ProseMirror ul,
.ProseMirror ol {
	padding-left: 1.5em;
	margin-bottom: 0.75em;
}

.ProseMirror blockquote {
	border-left: 3px solid #ddd;
	padding-left: 1em;
	margin-left: 0;
	margin-right: 0;
	font-style: italic;
}
</style>
