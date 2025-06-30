<script setup>
import { computed } from 'vue'
import { useArticleStore } from '@/stores/articleStore'
import BoldIcon from '@/components/icons/BoldIcon.vue'
import ItalicIcon from '@/components/icons/ItalicIcon.vue'
import BulletListIcon from '@/components/icons/BulletListIcon.vue'
import NumberedListIcon from '@/components/icons/NumberedListIcon.vue'
import BlockquoteIcon from '@/components/icons/BlockquoteIcon.vue'
import BackIcon from '@/components/icons/BackIcon.vue'
import ForwardIcon from '@/components/icons/ForwardIcon.vue'

const articleStore = useArticleStore()

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
	blockquote: props.editor?.isActive('blockquote')
}))

const handleCommand = (command, options = {}) => {
	const commandMap = {
		bold: () => props.editor.chain().focus().toggleBold().run(),
		italic: () => props.editor.chain().focus().toggleItalic().run(),
		heading: ({ level }) => props.editor.chain().focus().toggleHeading({ level }).run(),
		bulletList: () => props.editor.chain().focus().toggleBulletList().run(),
		orderedList: () => props.editor.chain().focus().toggleOrderedList().run(),
		blockquote: () => props.editor.chain().focus().toggleBlockquote().run()
	}

	if (commandMap[command]) {
		commandMap[command](options)
	}
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
	<div class="flex items-center gap-2 p-2 mr-6 rounded-xl bg-neutral-100">
		<button @click="handleCommand('bold')" :class="{ 'bg-neutral-200': activeCommands?.bold }" class="p-1 rounded hover:bg-neutral-200" title="Bold">
			<BoldIcon />
		</button>

		<button @click="handleCommand('italic')" :class="{ 'bg-neutral-200': activeCommands?.italic }" class="p-1 rounded hover:bg-neutral-200" title="Italic">
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
</template>
