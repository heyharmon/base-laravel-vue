<script setup>
const props = defineProps({
  keyword: { type: Object, required: true },
  isSelected: { type: Boolean, default: false }
})
const emit = defineEmits(['select', 'delete'])
</script>

<template>
  <div
    class="p-4 border border-neutral-200 rounded-lg hover:border-neutral-300 transition-colors cursor-pointer"
    :class="{ 'border-2 border-neutral-400 bg-neutral-50': isSelected }"
    @click="$emit('select', keyword)"
  >
    <div class="flex justify-between items-center">
      <div>
        <span class="text-lg font-medium text-neutral-800">{{ keyword.name }}</span>
        <div v-if="keyword.prompts_count >= 0" class="text-sm text-neutral-500 mt-1">
          Found in {{ keyword.prompts_count }}
          {{ keyword.prompts_count === 1 ? 'prompt' : 'prompts' }}
        </div>
        <div v-else class="text-sm text-neutral-500 mt-1">New keyword</div>
      </div>
      <button
        @click.stop="$emit('delete', keyword)"
        class="text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
          <path
            fill-rule="evenodd"
            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
            clip-rule="evenodd"
          />
        </svg>
      </button>
    </div>
  </div>
</template>
