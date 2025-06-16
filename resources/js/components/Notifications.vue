<template>
  <div class="toast-container">
    <div
      v-for="notif in notifications"
      :key="notif.id"
      class="toast"
      :class="notif.type"
    >
      <span>{{ notif.message }}</span>
      <button class="close-btn" @click="dismiss(notif.id)">✕</button>
    </div>
  </div>
</template>

<script setup>
import { useNotificationStore } from '@/stores/notificationStore'
import { storeToRefs } from 'pinia'

const notificationStore = useNotificationStore()
const { notifications } = storeToRefs(notificationStore)

function dismiss(id) {
  notificationStore.removeNotification(id)
}
</script>

<style scoped>
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
}
.toast {
  display: flex;
  align-items: center;
  min-width: 250px;
  margin-bottom: 8px;
  padding: 12px 16px;
  border-radius: 4px;
  color: #fff;
  font-weight: 500;
}
.toast.error { background: #f56565; }
.toast.success { background: #48bb78; }
.toast.info { background: #4299e1; }
.close-btn {
  background: none;
  border: none;
  color: #fff;
  font-weight: bold;
  margin-left: auto;
  cursor: pointer;
}
</style>
