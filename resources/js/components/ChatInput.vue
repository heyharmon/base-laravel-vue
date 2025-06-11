<script setup>
import { ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

const emit = defineEmits(['send'])
const message = ref('')

// add some props
const props = defineProps({
	placeholder: {
		type: String,
		default: 'Type your message here...'
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
					<button type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
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
							class="lucide lucide-image"
						>
							<rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
							<circle cx="9" cy="9" r="2" />
							<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
						</svg>
					</button>

					<!-- Search button -->
					<button type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
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
							class="lucide lucide-search"
						>
							<circle cx="11" cy="11" r="8" />
							<path d="m21 21-4.3-4.3" />
						</svg>
					</button>

					<!-- Deep research button -->
					<button type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
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
							class="lucide lucide-book-open"
						>
							<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
							<path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
						</svg>
					</button>

					<!-- Internal knowledge button -->
					<button type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
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
							class="lucide lucide-database"
						>
							<ellipse cx="12" cy="5" rx="9" ry="3" />
							<path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
							<path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
						</svg>
					</button>

					<!-- More options button -->
					<button type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
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
							class="lucide lucide-more-horizontal"
						>
							<circle cx="12" cy="12" r="1" />
							<circle cx="19" cy="12" r="1" />
							<circle cx="5" cy="12" r="1" />
						</svg>
					</button>
				</div>

				<div>
					<!-- Microphone button -->
					<button type="button" class="p-2 text-neutral-500 hover:text-neutral-700">
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
							class="lucide lucide-mic"
						>
							<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z" />
							<path d="M19 10v2a7 7 0 0 1-14 0v-2" />
							<line x1="12" x2="12" y1="19" y2="22" />
						</svg>
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
