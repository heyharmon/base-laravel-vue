<script setup>
import { ref } from 'vue'

const props = defineProps({
  sortOption: { type: String, default: 'default' },
  isLoading: { type: Boolean, default: false },
  isRunningAll: { type: Boolean, default: false },
  disableRunAll: { type: Boolean, default: false }
})

const emit = defineEmits(['update:sortOption', 'run-all', 'add', 'generate'])

const isRunAllMenuOpen = ref(false)

const toggleRunAllMenu = () => {
  isRunAllMenuOpen.value = !isRunAllMenuOpen.value
}
const closeRunAllMenu = () => {
  isRunAllMenuOpen.value = false
}
const runAll = (count) => {
  emit('run-all', count)
  closeRunAllMenu()
}
</script>

<template>
  <div class="flex justify-between items-center">
    <div class="flex items-center gap-3">
      <h1 class="text-2xl font-bold">Prompts</h1>
      <div v-if="isLoading" class="animate-spin rounded-full size-4 border-b-2 border-neutral-800"></div>
    </div>

    <div class="flex space-x-2">
      <div class="relative inline-block">
        <select
          :value="sortOption"
          @change="$emit('update:sortOption', $event.target.value)"
          class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium appearance-none pr-8 cursor-pointer"
        >
          <option value="default">Default order</option>
          <option value="mentions-desc">Mentions (high to low)</option>
          <option value="mentions-asc">Mentions (low to high)</option>
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-neutral-700">
          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </div>
      </div>

      <div class="relative">
        <button
          @click.stop="toggleRunAllMenu"
          class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer flex items-center justify-center"
          :disabled="disableRunAll"
        >
          <div v-if="isRunningAll" class="animate-spin h-3 w-3 border-b-2 border-neutral-800 rounded-full mr-1"></div>
          <span>Run all prompts</span>
        </button>
        <div
          v-if="isRunAllMenuOpen"
          class="absolute right-0 mt-1 w-36 bg-white border border-neutral-300 rounded-md shadow-lg z-10 overflow-hidden"
          @click.stop
        >
          <button @click.stop="runAll(1)" class="w-full px-3 py-2.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">Run all prompts 1x</button>
          <button @click.stop="runAll(3)" class="w-full px-3 py-2.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">Run all prompts 3x</button>
          <button @click.stop="runAll(5)" class="w-full px-3 py-2.5 text-left text-xs hover:bg-neutral-100 transition-colors cursor-pointer">Run all prompts 5x</button>
        </div>
      </div>

      <button
        @click="$emit('add')"
        class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer"
      >
        Add a prompt
      </button>

      <button
        @click="$emit('generate')"
        class="flex items-center space-x-1 px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
          <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
        </svg>
        <span>Generate prompts</span>
      </button>
    </div>
  </div>
</template>
