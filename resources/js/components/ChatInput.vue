<script setup>
import { ref } from 'vue'
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

function sendMessage() {
	if (!message.value.trim()) return
	emit('send', message.value)
	message.value = ''
}
</script>

<template>
	<div class="relative px-3 pb-3 border border-neutral-300 rounded-3xl bg-white shadow-sm">
		<form @submit.prevent="sendMessage">
			<!-- Input -->
			<div class="flex items-center">
				<input
					v-model="message"
					:placeholder="placeholder"
					autofocus
					class="flex-1 py-7 px-2 flex h-9 w-full min-w-0 rounded-md outline-none bg-transparent text-base placeholder:text-muted-foreground transition-[color,box-shadow] md:text-base"
				/>
			</div>

			<div class="flex items-center justify-between">
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

				<div>
					<!-- Microphone button -->
					<button v-if="showOptions" type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
						<MicrophoneIcon />
					</button>

					<!-- Send button -->
					<button type="submit" class="p-2 bg-black text-white rounded-full ml-2 mr-1" :disabled="!message.trim()">
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
