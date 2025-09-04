<template>
	<div class="fixed bottom-5 right-5 z-[100]">
		<div
			v-for="notification in notificationStore.notifications"
			:key="notification.id"
			class="flex items-center min-w-[250px] max-w-[460px] mb-2 py-3 px-4 gap-5 rounded text-white font-medium"
			:class="{
				'bg-red-500': notification.type === 'error',
				'bg-green-500': notification.type === 'success',
				'bg-blue-500': notification.type === 'info'
			}"
		>
			<span>{{ notification.message }}</span>
			<button class="bg-transparent border-0 text-white font-bold ml-auto cursor-pointer" @click="dismiss(notification.id)">✕</button>
		</div>
	</div>
</template>

<script setup>
import { watch, onBeforeUnmount } from 'vue'
import { useNotificationStore } from '@/stores/notificationStore'

const notificationStore = useNotificationStore()

// Track timers for auto-dismiss per notification
const timers = new Map()

function dismiss(id) {
	// Clear any existing timer for this id
	if (timers.has(id)) {
		clearTimeout(timers.get(id))
		timers.delete(id)
	}
	notificationStore.removeNotification(id)
}

// Auto-dismiss notifications after 15 seconds
watch(
	() => notificationStore.notifications.slice(),
	(notifs) => {
		// Start timers for new notifications
		notifs.forEach((n) => {
			if (!timers.has(n.id)) {
				const t = setTimeout(() => {
					// Only dismiss if still present
					const exists = notificationStore.notifications.find((x) => x.id === n.id)
					if (exists) {
						dismiss(n.id)
					}
				}, 10000)

				timers.set(n.id, t)
			}
		})

		// Clean timers for notifications that no longer exist
		for (const [id, t] of timers.entries()) {
			if (!notifs.find((n) => n.id === id)) {
				clearTimeout(t)
				timers.delete(id)
			}
		}
	},
	{ deep: false }
)

onBeforeUnmount(() => {
	for (const [, t] of timers.entries()) {
		clearTimeout(t)
	}
	timers.clear()
})
</script>
