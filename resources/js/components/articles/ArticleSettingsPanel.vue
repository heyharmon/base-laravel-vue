<template>
	<div class="bg-neutral-50 p-4 mx-8 rounded-md border border-neutral-200 mb-2">
		<h2 class="text-lg font-medium mb-4">Article Settings</h2>

		<div class="flex flex-col gap-4 mb-3">
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

		<div class="flex gap-2">
			<Button @click="saveArticle" variant="primary" size="sm" :disabled="articleStore.isLoading">
				{{ articleStore.isLoading ? 'Saving...' : 'Save Changes' }}
			</Button>
			<Button @click="closePanel" variant="outline" size="sm"> Cancel </Button>
		</div>
	</div>
</template>

<script setup>
import Button from '@/components/ui/Button.vue'
import { useArticleStore } from '@/stores/articleStore'

const articleStore = useArticleStore()

const emit = defineEmits(['close'])

const saveArticle = async () => {
	if (!articleStore.article?.id) return

	try {
		await articleStore.updateArticle(articleStore.article.id, {
			title: articleStore.article.title,
			meta_title: articleStore.article.meta_title,
			meta_description: articleStore.article.meta_description,
			schema: articleStore.article.schema
		})

		console.log('Article metadata saved successfully')

		// Close the panel after successful save
		emit('close')
	} catch (error) {
		console.error('Failed to save article:', error)
	}
}

const closePanel = () => {
	emit('close')
}
</script>
