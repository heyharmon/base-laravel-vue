<script setup>
import { ref, nextTick, watch } from 'vue'
import { useTermStore } from '@/stores/termStore'
import { useRoute } from 'vue-router'
import Modal from '@/components/ui/Modal.vue'
import Button from '@/components/ui/Button.vue'

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
	<Modal :is-open="isOpen" title="Add term" @close="closeModal">
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
			<Button @click="addTerm" :disabled="termStore.isLoading" variant="dark">Add</Button>
			<Button @click="closeModal" variant="neutral"> Cancel </Button>
		</template>
	</Modal>
</template>
