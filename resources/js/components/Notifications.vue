<template>
	<div class="fixed bottom-5 right-5 z-50">
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
import { useNotificationStore } from '@/stores/notificationStore'

const notificationStore = useNotificationStore()

function dismiss(id) {
	notificationStore.removeNotification(id)
}
</script>
