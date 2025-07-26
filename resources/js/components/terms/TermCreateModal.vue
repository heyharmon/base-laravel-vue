<script setup>
import { ref, nextTick, watch } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import { useTermStore } from '@/stores/termStore'
import { useRoute } from 'vue-router'

const route = useRoute()
const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	},
	teamId: {
		type: [Number, String],
		required: true
	}
})

const emit = defineEmits(['close', 'create'])

const termStore = useTermStore()
const newTerm = ref('')
const termInput = ref(null)

watch(
	() => props.isOpen,
	async (isOpen) => {
		if (isOpen) {
			await nextTick()
			if (termInput.value) {
				termInput.value.focus()
			}
		}
	},
	{ immediate: true }
)

const closeModal = () => {
	newTerm.value = ''
	emit('close')
}

const addTerm = async () => {
	if (newTerm.value.trim()) {
		const termData = { name: newTerm.value.trim() }
		const processedData = emit('create', termData) || termData
		await termStore.createTerm(props.teamId, route.params.organizationId, processedData)
		closeModal()
	}
}
</script>

<template>
	<Modal :is-open="isOpen" title="Add Term" @close="closeModal">
		<div class="space-y-4">
			<input
				ref="termInput"
				v-model="newTerm"
				type="text"
				placeholder="New term"
				class="w-full px-3 py-2 border border-neutral-300 rounded-md"
				@keyup.enter="addTerm"
			/>
		</div>
		<template #footer>
			<button
				@click="addTerm"
				class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
				:disabled="termStore.isLoading"
			>
				Add
			</button>
			<button
				@click="closeModal"
				class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
			>
				Cancel
			</button>
		</template>
	</Modal>
</template>
