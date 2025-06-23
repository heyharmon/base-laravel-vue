<script setup>
import { ref } from 'vue'
import { usePromptStore } from '@/stores/promptStore'
import ChevronDownIcon from '../icons/ChevronDownIcon.vue'
import LightningIcon from '../icons/LightningIcon.vue'

const promptStore = usePromptStore()

const props = defineProps({
	sortOption: { type: String, default: 'default' }
})

const emit = defineEmits(['update:sortOption', 'run-all', 'add', 'generate'])

const isRunAllMenuOpen = ref(false)

const toggleRunAllMenu = () => {
	isRunAllMenuOpen.value = !isRunAllMenuOpen.value
}
const closeRunAllMenu = () => {
	isRunAllMenuOpen.value = false
}
const runAll = (count) => {
	emit('run-all', count)
	closeRunAllMenu()
}
</script>

<template>
	<div class="flex space-x-2">
		<div class="relative inline-block">
			<select
				:value="sortOption"
				@change="$emit('update:sortOption', $event.target.value)"
				class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium appearance-none pr-8 cursor-pointer"
			>
				<option value="default">Default order</option>
				<option value="mentions-desc">Mentions (high to low)</option>
				<option value="mentions-asc">Mentions (low to high)</option>
			</select>
			<div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-neutral-700">
				<ChevronDownIcon />
			</div>
		</div>

		<div class="relative">
			<button
				@click.stop="toggleRunAllMenu"
				class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer flex items-center justify-center"
				:disabled="promptStore.isLoading || promptStore.loadingPromptIds.length > 0 || promptStore.isRunningAll"
			>
				<div v-if="promptStore.isRunningAll" class="animate-spin h-3 w-3 border-b-2 border-neutral-800 rounded-full mr-1"></div>
				<span>Run all prompts</span>
			</button>
			<div
				v-if="isRunAllMenuOpen"
				class="absolute right-0 mt-1 w-36 bg-white border border-neutral-300 rounded-md shadow-lg z-10 overflow-hidden"
				@click.stop
			>
				<button @click.stop="runAll(1)" class="w-full px-3 py-2.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
					Run all prompts 1x
				</button>
				<button @click.stop="runAll(3)" class="w-full px-3 py-2.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
					Run all prompts 3x
				</button>
				<button @click.stop="runAll(5)" class="w-full px-3 py-2.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">
					Run all prompts 5x
				</button>
			</div>
		</div>

		<button
			@click="$emit('add')"
			class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer"
		>
			Add a prompt
		</button>

		<button
			@click="$emit('generate')"
			class="flex items-center space-x-1 px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
		>
			<LightningIcon />
			<span>Generate prompts</span>
		</button>
	</div>
</template>
