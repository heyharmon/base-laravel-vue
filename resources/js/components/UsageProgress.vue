<template>
  <div v-if="limit !== null" class="mb-4">
    <div class="flex justify-between text-sm mb-1">
      <span>{{ label }}</span>
      <span>{{ used }} / {{ limit }}</span>
    </div>
    <div class="w-full bg-neutral-200 h-2 rounded">
      <div class="bg-blue-500 h-2 rounded" :style="{ width: percent + '%' }"></div>
    </div>
  </div>
  <div v-else class="mb-4 text-sm">{{ label }}: {{ used }}</div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  used: { type: Number, required: true },
  limit: { type: Number, default: null },
  label: { type: String, default: '' }
})

const percent = computed(() => {
  if (props.limit === null || props.limit === 0) return 0
  return Math.min(100, Math.round((props.used / props.limit) * 100))
})
</script>
