<script setup>
import { computed } from 'vue'

const props = defineProps({
  amount: { type: Number, required: true },
  limit: { type: Number, default: null },
  label: { type: String, default: 'Monthly Usage' },
  precision: { type: Number, default: 2 }
})

const isUnlimited = computed(() => props.limit === null || props.limit === undefined)

const percent = computed(() => {
  if (isUnlimited.value) return 0
  const denom = props.limit || 0
  if (!denom) return 0
  return Math.min((props.amount / denom) * 100, 100)
})
</script>

<template>
  <div>
    <div class="flex justify-between mb-2 text-sm">
      <span>{{ label }}</span>
      <span v-if="!isUnlimited">
        ${{ amount.toFixed(precision) }} / ${{ Number(limit || 0).toFixed(precision) }}
      </span>
      <span v-else>
        ${{ amount.toFixed(precision) }} / Unlimited
      </span>
    </div>
    <div class="w-full bg-neutral-200 rounded h-2">
      <div class="h-2 bg-neutral-700 rounded" :style="{ width: percent + '%' }"></div>
    </div>
  </div>
</template>

