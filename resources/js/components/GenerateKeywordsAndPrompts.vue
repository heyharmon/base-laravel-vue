<script setup>
import { ref } from 'vue';
import Modal from '@/components/ui/Modal.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  }
});

const emit = defineEmits(['close']);

const domain = ref('');

const closeModal = () => {
  domain.value = '';
  emit('close');
};

const generateKeywordsAndPrompts = () => {
  // This will be implemented later
  console.log('Generate keywords and prompts for:', domain.value);
};
</script>

<template>
  <Modal :is-open="isOpen" title="Generate Keywords & Prompts" @close="closeModal">
    <div class="space-y-4">
      <input 
        v-model="domain" 
        type="text" 
        placeholder="Enter website domain (e.g. acme.com)" 
        class="w-full px-3 py-2 border border-neutral-300 rounded-md"
        @keyup.enter="generateKeywordsAndPrompts"
      />
    </div>
    <template #footer>
      <button 
        @click="generateKeywordsAndPrompts" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
      >
        Generate
      </button>
      <button 
        @click="closeModal" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
      >
        Cancel
      </button>
    </template>
  </Modal>
</template>
