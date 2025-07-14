<script setup>
import { useArticleStore } from '@/stores/articleStore'
import moment from 'moment'
import Button from '@/components/ui/Button.vue'

const articleStore = useArticleStore()
// Format the version date for display
const formatVersionDate = (dateString) => {
	if (!dateString) return ''
	return moment(dateString).fromNow()
}

// Handle reverting to a previous version
const revertToVersion = async (versionId) => {
	if (!versionId) return

	try {
		await articleStore.revertToVersion(articleStore.article.id, versionId)
	} catch (err) {
		console.error('Failed to revert to version:', err)
	}
}
</script>

<template>
	<div class="bg-neutral-50 p-4 mx-8 rounded-md border border-neutral-200 mb-2">
		<h2 class="text-lg font-medium mb-4">Article Versions</h2>
		<p class="text-sm text-neutral-500 mb-3">Select a version to revert the article to that state.</p>

		<div v-if="articleStore.article.versions.length > 0" class="max-h-60 overflow-y-auto custom-scrollbar">
			<div
				v-for="version in articleStore.article.versions"
				:key="version.id"
				:class="[
					'flex justify-between items-center p-3 rounded-md border mb-2 last:mb-0',
					version.version_number === articleStore.article.current_version ? 'bg-neutral-100 border-neutral-300' : 'bg-white border-neutral-200'
				]"
			>
				<div>
					<div class="text-sm font-medium">
						Version {{ version.version_number }}
						{{ version.version_number === articleStore.article.current_version ? '(Current version)' : '' }}
					</div>
					<div class="text-xs text-neutral-500">{{ formatVersionDate(version.created_at) }}</div>
				</div>
				<Button @click="revertToVersion(version.id)" variant="outline" size="xs"> Revert </Button>
			</div>
		</div>

		<div v-else class="text-sm text-neutral-500 p-2">No versions available yet. Versions are created when you edit the article content.</div>
	</div>
</template>
