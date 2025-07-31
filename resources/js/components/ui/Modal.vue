<script setup>
import { onMounted, onUnmounted } from 'vue'

const props = defineProps({
	isOpen: {
		type: Boolean,
		required: true
	},
	title: {
		type: String,
		default: ''
	},
	width: {
		type: String,
		default: 'default',
		validator: (value) => ['default', 'wide', 'wider', 'full'].includes(value)
	}
})

const emit = defineEmits(['close'])

const closeModal = () => {
	emit('close')
}

const handleEscape = (e) => {
	if (e.key === 'Escape' && props.isOpen) {
		closeModal()
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
	<div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
		<!-- Backdrop overlay -->
		<div class="fixed inset-0 bg-neutral-300/50 transition-opacity" @click="closeModal"></div>

		<div class="flex justify-center pt-16 px-4 text-center">
			<div
				class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all p-6 relative z-10"
				:class="{
					'sm:max-w-lg sm:w-full': props.width === 'default',
					'sm:max-w-2xl sm:w-full': props.width === 'wide',
					'sm:max-w-3xl sm:w-full': props.width === 'wider',
					'sm:max-w-4xl sm:w-full': props.width === 'full'
				}"
				@click.stop
			>
				<div class="bg-white pb-6">
					<div class="sm:flex sm:items-start">
						<div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
							<h3 class="text-lg leading-6 font-medium text-neutral-900" v-if="title">
								{{ title }}
							</h3>
							<div class="mt-2">
								<slot></slot>
							</div>
						</div>
					</div>
				</div>
				<div class="sm:flex sm:flex-row-reverse gap-2">
					<slot name="footer"></slot>
				</div>
			</div>
		</div>
	</div>
</template>
