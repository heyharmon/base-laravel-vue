<script setup>
import { computed, ref } from 'vue'
import { useArticleStore } from '@/stores/articleStore'
import BoldIcon from '@/components/icons/BoldIcon.vue'
import ItalicIcon from '@/components/icons/ItalicIcon.vue'
import BulletListIcon from '@/components/icons/BulletListIcon.vue'
import NumberedListIcon from '@/components/icons/NumberedListIcon.vue'
import BlockquoteIcon from '@/components/icons/BlockquoteIcon.vue'
import LinkIcon from '@/components/icons/LinkIcon.vue'
import BackIcon from '@/components/icons/BackIcon.vue'
import ForwardIcon from '@/components/icons/ForwardIcon.vue'

const articleStore = useArticleStore()

// Link dialogue state
const showLinkDialog = ref(false)
const linkUrl = ref('')

defineEmits(['command'])

const props = defineProps({
	editor: {
		type: Object,
		default: () => ({})
	}
})

const activeCommands = computed(() => ({
	bold: props.editor?.isActive('bold'),
	italic: props.editor?.isActive('italic'),
	heading: {
		level: [1, 2, 3, 4].find((level) => props.editor?.isActive('heading', { level }))
	},
	bulletList: props.editor?.isActive('bulletList'),
	orderedList: props.editor?.isActive('orderedList'),
	blockquote: props.editor?.isActive('blockquote'),
	link: props.editor?.isActive('link')
}))

const handleCommand = (command, options = {}) => {
	const commandMap = {
		bold: () => props.editor.chain().focus().toggleBold().run(),
		italic: () => props.editor.chain().focus().toggleItalic().run(),
		heading: ({ level }) => props.editor.chain().focus().toggleHeading({ level }).run(),
		bulletList: () => props.editor.chain().focus().toggleBulletList().run(),
		orderedList: () => props.editor.chain().focus().toggleOrderedList().run(),
		blockquote: () => props.editor.chain().focus().toggleBlockquote().run(),
		link: () => setLink()
	}

	if (commandMap[command]) {
		commandMap[command](options)
	}
}

const handleLinkClick = () => {
	const { from, to } = props.editor.state.selection

	// If no text is selected, try to select the current word or extend the link range
	if (from === to) {
		if (activeCommands.value.link) {
			// Cursor is within a link, extend to the full link range
			props.editor.chain().focus().extendMarkRange('link').run()
		} else {
			// Cursor is within a word, select the word
			const { state } = props.editor
			const { doc } = state
			const pos = state.selection.from

			// Find word boundaries
			let start = pos
			let end = pos

			// Move start backwards to find word start
			while (start > 0) {
				const char = doc.textBetween(start - 1, start)
				if (/\s/.test(char)) break
				start--
			}

			// Move end forwards to find word end
			while (end < doc.content.size) {
				const char = doc.textBetween(end, end + 1)
				if (/\s/.test(char)) break
				end++
			}

			// Select the word if we found boundaries
			if (start < end) {
				props.editor.chain().focus().setTextSelection({ from: start, to: end }).run()
			} else {
				// No word found, don't show dialog
				return
			}
		}
	}

	if (activeCommands.value.link) {
		// Get current link URL and show dialog to edit
		const currentUrl = props.editor.getAttributes('link').href || ''
		linkUrl.value = currentUrl
	} else {
		// Show dialog to add new link
		linkUrl.value = ''
	}

	showLinkDialog.value = true
}

const applyLink = () => {
	if (linkUrl.value) {
		props.editor.chain().focus().extendMarkRange('link').setLink({ href: linkUrl.value }).run()
	}
	showLinkDialog.value = false
	linkUrl.value = ''
}

const removeLink = () => {
	props.editor.chain().focus().extendMarkRange('link').unsetLink().run()
	showLinkDialog.value = false
	linkUrl.value = ''
}

const cancelLink = () => {
	showLinkDialog.value = false
	linkUrl.value = ''
}

const canRevertBack = () => {
	return articleStore.article.versions && articleStore.article.versions.length > 0 && articleStore.article.current_version > 1
}

const canRevertForward = () => {
	return articleStore.article.versions && articleStore.article.current_version < articleStore.article.versions.length
}

const revertToPreviousVersion = async () => {
	if (!canRevertBack()) return
	const targetVersion = articleStore.article.versions.find((v) => v.version_number === articleStore.article.current_version - 1)
	if (targetVersion) {
		await articleStore.revertToVersion(articleStore.article.id, targetVersion.id)
	}
}

const revertToNextVersion = async () => {
	if (!canRevertForward()) return
	const targetVersion = articleStore.article.versions.find((v) => v.version_number === articleStore.article.current_version + 1)
	if (targetVersion) {
		await articleStore.revertToVersion(articleStore.article.id, targetVersion.id)
	}
}
</script>

<template>
	<div class="flex items-center justify-between p-2 mr-6 rounded-xl bg-neutral-100">
		<div class="flex items-center gap-2">
			<button @click="handleCommand('bold')" :class="{ 'bg-neutral-200': activeCommands?.bold }" class="p-1 rounded hover:bg-neutral-200" title="Bold">
				<BoldIcon />
			</button>

			<button
				@click="handleCommand('italic')"
				:class="{ 'bg-neutral-200': activeCommands?.italic }"
				class="p-1 rounded hover:bg-neutral-200"
				title="Italic"
			>
				<ItalicIcon />
			</button>

			<template v-for="level in [1, 2, 3, 4]" :key="`h${level}`">
				<button
					@click="handleCommand('heading', { level })"
					:class="{ 'bg-neutral-200': activeCommands?.heading?.level === level }"
					class="p-1 rounded hover:bg-neutral-200"
					:title="`Heading ${level}`"
				>
					H{{ level }}
				</button>
			</template>

			<button
				@click="handleCommand('bulletList')"
				:class="{ 'bg-neutral-200': activeCommands?.bulletList }"
				class="p-1 rounded hover:bg-neutral-200"
				title="Bullet List"
			>
				<BulletListIcon />
			</button>

			<button
				@click="handleCommand('orderedList')"
				:class="{ 'bg-neutral-200': activeCommands?.orderedList }"
				class="p-1 rounded hover:bg-neutral-200"
				title="Numbered List"
			>
				<NumberedListIcon />
			</button>

			<button
				@click="handleCommand('blockquote')"
				:class="{ 'bg-neutral-200': activeCommands?.blockquote }"
				class="p-1 rounded hover:bg-neutral-200"
				title="Blockquote"
			>
				<BlockquoteIcon />
			</button>

			<div class="relative">
				<button @click="handleLinkClick" :class="{ 'bg-neutral-200': activeCommands?.link }" class="p-1 rounded hover:bg-neutral-200" title="Link">
					<LinkIcon />
				</button>

				<!-- Link Dialog -->
				<div v-if="showLinkDialog" class="absolute top-full left-0 mt-2 p-3 bg-white border border-neutral-200 rounded-lg shadow-lg z-50 min-w-64">
					<div class="mb-2">
						<input
							v-model="linkUrl"
							type="url"
							placeholder="https://example.com"
							class="w-full px-2 py-1 border border-neutral-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
							@keyup.enter="applyLink"
							@keyup.escape="cancelLink"
							autofocus
						/>
					</div>
					<div class="flex gap-2">
						<button
							@click="applyLink"
							class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
						>
							Apply
						</button>
						<button
							v-if="activeCommands?.link"
							@click="removeLink"
							class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
						>
							Remove
						</button>
						<button
							@click="cancelLink"
							class="px-3 py-1 bg-neutral-200 text-neutral-700 text-sm rounded hover:bg-neutral-300 focus:outline-none focus:ring-2 focus:ring-neutral-500"
						>
							Cancel
						</button>
					</div>
				</div>
			</div>

			<div class="h-5 w-px bg-neutral-300 mx-1"></div>

			<div v-if="articleStore.article" class="flex items-center gap-2">
				<button v-if="canRevertBack()" @click="revertToPreviousVersion()" class="p-1 rounded hover:bg-neutral-200" title="Undo">
					<BackIcon />
				</button>

				<button v-if="canRevertForward()" @click="revertToNextVersion()" class="p-1 rounded hover:bg-neutral-200" title="Redo">
					<ForwardIcon />
				</button>

				<span class="text-sm">{{ `Version ${articleStore.article.current_version}/${articleStore.article.versions.length}` }}</span>
			</div>
		</div>

		<span v-if="articleStore.isSaving" class="flex items-center">
			<span class="animate-spin h-4 w-4 mr-2 border-t-2 border-b-2 border-neutral-600 rounded-full"></span>
		</span>
	</div>
</template>
