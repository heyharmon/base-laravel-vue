<script setup>
const props = defineProps({
  keyword: { type: Object, required: true }
})
const emit = defineEmits(['accept', 'deny'])
</script>

<template>
  <div class="p-4 border border-neutral-200 rounded-lg">
    <div class="flex justify-between items-center">
      <div>
        <span class="text-lg font-medium text-neutral-800">{{ keyword.name }}</span>
        <div v-if="keyword.description" class="text-sm text-neutral-500 mt-1">
          {{ keyword.description }}
        </div>
      </div>
      <div class="flex gap-2">
        <button
          @click="$emit('accept', keyword)"
          class="text-green-600 hover:text-green-800 transition-colors cursor-pointer"
          title="Accept this keyword"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path
              fill-rule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
        <button
          @click="$emit('deny', keyword)"
          class="text-red-600 hover:text-red-800 transition-colors cursor-pointer"
          title="Deny this keyword"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path
              fill-rule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clip-rule="evenodd"
            />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>
