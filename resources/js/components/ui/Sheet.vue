<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    default: ''
  },
  position: {
    type: String,
    default: 'right',
    validator: (value) => ['right', 'left'].includes(value)
  }
});

const emit = defineEmits(['close']);

const closeSheet = () => {
  emit('close');
};

const handleEscape = (e) => {
  if (e.key === 'Escape' && props.isOpen) {
    closeSheet();
  }
};

onMounted(() => {
  document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-hidden">
    <!-- Backdrop overlay -->
    <div class="fixed inset-0 bg-neutral-300/50 transition-opacity" @click="closeSheet"></div>
    
    <div class="absolute inset-y-0" :class="position === 'right' ? 'right-0' : 'left-0'">
      <div 
        class="h-full bg-white shadow-xl transform transition-transform ease-in-out duration-300 flex flex-col"
        :class="isOpen ? 'translate-x-0' : position === 'right' ? 'translate-x-full' : '-translate-x-full'"
        @click.stop
      >
        <div class="p-6 border-b border-neutral-200 flex justify-between items-center">
          <h3 class="text-lg font-medium text-neutral-900" v-if="title">
            {{ title }}
          </h3>
          <button 
            @click="closeSheet" 
            class="text-neutral-400 hover:text-neutral-600 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
        <div class="flex-1 p-6 overflow-y-auto">
          <slot></slot>
        </div>
        <div v-if="$slots.footer" class="border-t border-neutral-200 p-4">
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </div>
</template>
