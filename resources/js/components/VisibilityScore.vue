<script setup>
import { computed } from 'vue'

const props = defineProps({
  organization: {
    type: Object,
    required: true
  },
  width: {
    type: String,
    default: 'w-full'
  }
})

const orgName = computed(() => props.organization?.name || 'Your Organization')
const visibility = computed(() => props.organization?.visibility || 0)
</script>

<template>
  <div :class="[width, 'bg-white rounded-lg p-6 border border-neutral-200 shadow-sm']">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img
          :src="`https://cdn.brandfetch.io/${organization?.website}/w/400/h/400?c=1idaplhOcH8x9kYGESa`"
          :alt="organization?.name + ' logo'"
          class="size-12 object-contain bg-white rounded-lg border border-neutral-200"
        />
        <div>
          <h1 class="text-lg font-bold">Visibility score</h1>
          <p class="text-neutral-500">{{ orgName }}</p>
        </div>
      </div>
      <div class="text-6xl font-medium text-green-600 flex items-start gap-1">
        {{ visibility }}
        <span class="text-2xl">%</span>
      </div>
    </div>
  </div>
</template>
