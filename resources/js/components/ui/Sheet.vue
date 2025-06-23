<script setup>
import { onMounted, onUnmounted } from 'vue'
import CloseIcon from '../icons/CloseIcon.vue'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	},
	title: {
		type: String,
		default: ''
	},
	position: {
		type: String,
		default: 'right',
		validator: (value) => ['right', 'left'].includes(value)
	}
})

const emit = defineEmits(['close'])

const closeSheet = () => {
	emit('close')
}

const handleEscape = (e) => {
	if (e.key === 'Escape' && props.isOpen) {
		closeSheet()
	}
}

onMounted(() => {
	document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
	document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
	<div v-if="isOpen" class="fixed inset-0 z-50 overflow-hidden">
		<!-- Backdrop overlay -->
		<div class="fixed inset-0 bg-neutral-300/30 transition-opacity" @click="closeSheet"></div>

		<div class="absolute inset-y-0" :class="position === 'right' ? 'right-0' : 'left-0'">
			<div
				class="h-full bg-white shadow-xl transform transition-transform ease-in-out duration-300 flex flex-col"
				:class="isOpen ? 'translate-x-0' : position === 'right' ? 'translate-x-full' : '-translate-x-full'"
				@click.stop
			>
				<div class="px-6 py-3 border-b border-neutral-200 flex justify-between items-center gap-2">
					<h3 class="text-lg font-medium text-neutral-900" v-if="title">
						{{ title }}
					</h3>
					<div class="flex items-center gap-2">
						<slot name="header-actions"></slot>
						<button @click="closeSheet" class="text-neutral-400 hover:text-neutral-600 transition-colors">
							<CloseIcon class="h-6 w-6" />
						</button>
					</div>
				</div>
				<div class="flex-1 p-6 overflow-y-auto">
					<slot></slot>
				</div>
				<div v-if="$slots.footer" class="border-t border-neutral-200 p-4">
					<slot name="footer"></slot>
				</div>
			</div>
		</div>
	</div>
</template>
