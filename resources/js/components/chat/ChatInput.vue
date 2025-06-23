<script setup>
import { ref, onMounted, nextTick } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import BookIcon from '@/components/icons/BookIcon.vue'
import DatabaseIcon from '@/components/icons/DatabaseIcon.vue'
import EllipsesHorizontalIcon from '@/components/icons/EllipsesHorizontalIcon.vue'
import ImageIcon from '@/components/icons/ImageIcon.vue'
import MicrophoneIcon from '@/components/icons/MicrophoneIcon.vue'
import SearchIcon from '@/components/icons/SearchIcon.vue'

const emit = defineEmits(['send'])
const message = ref('')
const textareaRef = ref(null)

// add some props
const props = defineProps({
	placeholder: {
		type: String,
		default: 'Type your message here...'
	},
	showOptions: {
		type: Boolean,
		default: false
	}
})

function resizeTextarea() {
	if (!textareaRef.value) return
	textareaRef.value.style.height = 'auto'
	textareaRef.value.style.height = textareaRef.value.scrollHeight + 'px'
}

function sendMessage() {
	if (!message.value.trim()) return
	emit('send', message.value)
	message.value = ''
	// Reset textarea height after sending message
	nextTick(() => {
		resizeTextarea()
	})
}

// Watch for changes in the message and resize the textarea
function handleInput() {
	resizeTextarea()
}

// Handle keyboard events - submit on Enter but not on Shift+Enter
function handleKeydown(event) {
	if (event.key === 'Enter' && !event.shiftKey) {
		event.preventDefault()
		sendMessage()
	}
}

onMounted(() => {
	resizeTextarea()
})
</script>

<template>
	<div class="relative max-w-full mx-auto">
		<form @submit.prevent="sendMessage" class="border border-gray-300 bg-white rounded-3xl">
			<textarea
				v-model="message"
				:placeholder="placeholder"
				rows="1"
				autofocus
				ref="textareaRef"
				@input="handleInput"
				@keydown="handleKeydown"
				class="w-full pt-3 px-4 resize-none focus:outline-none"
				style="min-height: 44px; max-height: 200px"
			/>

			<div class="flex items-center justify-between px-2 pb-2">
				<div>
					<!-- Prompt w/ image button -->
					<button v-if="showOptions" type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
						<ImageIcon />
					</button>

					<!-- Search button -->
					<button v-if="showOptions" type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
						<SearchIcon />
					</button>

					<!-- Deep research button -->
					<button v-if="showOptions" type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
						<BookIcon />
					</button>

					<!-- Internal knowledge button -->
					<button v-if="showOptions" type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
						<DatabaseIcon />
					</button>

					<!-- More options button -->
					<button v-if="showOptions" type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
						<EllipsesHorizontalIcon />
					</button>
				</div>

				<div class="flex items-center gap-1">
					<Button
						as="a"
						variant="link"
						size="sm"
						href="https://sites.google.com/bloomcu.com/paraloom-instruction-templates"
						target="_blank"
						class="underline text-neutral-500 hover:text-neutral-700"
					>
						Instruction templates
					</Button>

					<!-- Microphone button -->
					<button v-if="showOptions" type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
						<MicrophoneIcon />
					</button>

					<!-- Send button -->
					<button
						@click="sendMessage"
						type="submit"
						class="p-2 bg-black text-white rounded-full cursor-pointer hover:bg-black/80 disabled:cursor-not-allowed disabled:opacity-50"
						:disabled="!message.trim()"
					>
						<svg
							xmlns="http://www.w3.org/2000/svg"
							width="20"
							height="20"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							stroke-width="2"
							stroke-linecap="round"
							stroke-linejoin="round"
							class="lucide lucide-arrow-up"
						>
							<line x1="12" y1="19" x2="12" y2="5" />
							<polyline points="5 12 12 5 19 12" />
						</svg>
					</button>
				</div>
			</div>
		</form>
	</div>
</template>
