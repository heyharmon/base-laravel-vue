<script setup>
const props = defineProps({
  isOpen: { type: Boolean, required: true },
  prompt: { type: Object, default: null }
})
const emit = defineEmits(['confirm', 'cancel'])
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 bg-neutral-300/50 flex items-center justify-center z-50" @click="$emit('cancel')">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4" @click.stop>
      <h3 class="text-lg font-medium text-neutral-900 mb-4">Delete Prompt</h3>
      <p class="text-sm text-neutral-600 mb-6">Are you sure you want to delete this prompt? This action cannot be undone.</p>
      <div class="flex justify-end space-x-3">
        <button @click="$emit('cancel')" class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer">Cancel</button>
        <button @click="$emit('confirm')" class="ml-3 inline-flex justify-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md cursor-pointer">Delete</button>
      </div>
    </div>
  </div>
</template>
