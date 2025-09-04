<template>
	<div class="fixed bottom-5 right-5 z-[100]">
		<div
			v-for="notification in notificationStore.notifications"
			:key="notification.id"
			class="notification relative overflow-hidden flex items-center min-w-[250px] max-w-[460px] mb-2 py-3 px-4 gap-5 rounded text-gray-800 font-medium shadow-lg bg-white border border-gray-200"
		>
			<span>{{ notification.message }}</span>
			<button class="bg-transparent border-0 text-gray-600 hover:text-gray-800 font-bold ml-auto cursor-pointer" @click="dismiss(notification.id)">
				✕
			</button>

			<!-- Progress bar with CSS animation -->
			<div class="pointer-events-none absolute left-0 bottom-0 w-full h-[3px] bg-gray-200">
				<div
					class="progress-bar h-full"
					:class="{
						'bg-red-500': notification.type === 'error',
						'bg-green-500': notification.type === 'success',
						'bg-blue-500': notification.type === 'info'
					}"
					@animationend="dismiss(notification.id)"
				></div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { useNotificationStore } from '@/stores/notificationStore'

const notificationStore = useNotificationStore()
const timers = ref(new Map())

function dismiss(id) {
	// Clear any existing timer
	if (timers.value.has(id)) {
		clearTimeout(timers.value.get(id))
		timers.value.delete(id)
	}
	notificationStore.removeNotification(id)
}

// Auto-dismiss after 8 seconds as fallback (CSS animation is primary)
function addNotification(notification) {
	const timer = setTimeout(() => dismiss(notification.id), 8000)
	timers.value.set(notification.id, timer)
}

// Watch for new notifications
const stopWatcher = notificationStore.$subscribe((mutation, state) => {
	// Handle new notifications
	state.notifications.forEach((notification) => {
		if (!timers.value.has(notification.id)) {
			addNotification(notification)
		}
	})

	// Clean up timers for removed notifications
	for (const [id] of timers.value) {
		if (!state.notifications.find((n) => n.id === id)) {
			timers.value.delete(id)
		}
	}
})

onBeforeUnmount(() => {
	stopWatcher()
	// Clear all timers
	for (const [, timer] of timers.value) {
		clearTimeout(timer)
	}
	timers.value.clear()
})
</script>

<style scoped>
.notification {
	animation: slideIn 0.3s ease-out;
}

.progress-bar {
	width: 0;
	animation: fillProgress 8s linear;
}

@keyframes slideIn {
	from {
		transform: translateX(100%);
		opacity: 0;
	}
	to {
		transform: translateX(0);
		opacity: 1;
	}
}

@keyframes fillProgress {
	from {
		width: 0%;
	}
	to {
		width: 100%;
	}
}
</style>
