<script setup>
import { ref, nextTick, watch } from 'vue';
import Modal from '@/components/ui/Modal.vue';
import { usePromptStore } from '@/stores/promptStore';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  }
});

const emit = defineEmits(['close']);

const promptStore = usePromptStore();
const newPrompt = ref({ name: '', content: '' });
const promptTextarea = ref(null);

watch(() => props.isOpen, async (isOpen) => {
  if (isOpen) {
    await nextTick();
    if (promptTextarea.value) {
      promptTextarea.value.focus();
    }
  }
}, { immediate: true });

const closeModal = () => {
  newPrompt.value = { name: '', content: '' };
  emit('close');
};

const addPrompt = async () => {
  if (newPrompt.value.content.trim()) {
    await promptStore.createPrompt(newPrompt.value);
    closeModal();
  }
};
</script>

<template>
  <Modal :is-open="isOpen" title="Add Prompt" @close="closeModal">
    <div class="space-y-4">
      <textarea 
        ref="promptTextarea"
        v-model="newPrompt.content" 
        placeholder="Prompt content" 
        class="w-full px-3 py-2 border border-neutral-300 rounded-md h-24"
      ></textarea>
    </div>
    <template #footer>
      <button 
        @click="addPrompt" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
        :disabled="promptStore.isLoading"
      >
        Add
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
