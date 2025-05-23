<script setup>
import { ref, nextTick, watch } from 'vue';
import Modal from '@/components/ui/Modal.vue';
import { useKeywordStore } from '@/stores/keywordStore';
import { useRoute } from 'vue-router';

const route = useRoute(); 
const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  }
});

const emit = defineEmits(['close', 'create']);

const keywordStore = useKeywordStore();
const newKeyword = ref('');
const keywordInput = ref(null);

watch(() => props.isOpen, async (isOpen) => {
  if (isOpen) {
    await nextTick();
    if (keywordInput.value) {
      keywordInput.value.focus();
    }
  }
}, { immediate: true });

const closeModal = () => {
  newKeyword.value = '';
  emit('close');
};

const addKeyword = async () => {
  if (newKeyword.value.trim()) {
    const keywordData = { name: newKeyword.value.trim() };
    const processedData = emit('create', keywordData) || keywordData;
    await keywordStore.createKeyword(route.params.id, processedData);
    closeModal();
  }
};
</script>

<template>
  <Modal :is-open="isOpen" title="Add Keyword" @close="closeModal">
    <div class="space-y-4">
      <input 
        ref="keywordInput"
        v-model="newKeyword" 
        type="text" 
        placeholder="New keyword" 
        class="w-full px-3 py-2 border border-neutral-300 rounded-md"
        @keyup.enter="addKeyword"
      />
    </div>
    <template #footer>
      <button 
        @click="addKeyword" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
        :disabled="keywordStore.isLoading"
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
