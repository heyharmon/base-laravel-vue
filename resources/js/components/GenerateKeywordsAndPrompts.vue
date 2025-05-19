<script setup>
import { ref } from 'vue';
import Modal from '@/components/ui/Modal.vue';
import api from '@/services/api';
import { useKeywordStore } from '@/stores/keywordStore';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  }
});

const emit = defineEmits(['close']);

const domain = ref('');
const isLoading = ref(false);
const generatedKeywords = ref([]);
const error = ref(null);
const keywordStore = useKeywordStore();

const closeModal = () => {
  domain.value = '';
  generatedKeywords.value = [];
  error.value = null;
  emit('close');
};

const removeKeyword = (index) => {
  generatedKeywords.value.splice(index, 1);
};

const generateKeywordsAndPrompts = async () => {
  if (!domain.value.trim()) return;
  
  isLoading.value = true;
  error.value = null;
  generatedKeywords.value = [];
  
  try {
    const response = await api.post('/generate-keywords', { domain: domain.value.trim() });
    generatedKeywords.value = response || [];
  } catch (err) {
    console.error('Error generating keywords:', err);
    error.value = 'Failed to generate keywords. Please try again.';
  } finally {
    isLoading.value = false;
  }
};

const createKeywords = async () => {
  if (!generatedKeywords.value.length) return;
  
  isLoading.value = true;
  
  try {
    const promises = generatedKeywords.value.map(keyword => 
      keywordStore.createKeyword({ name: keyword })
    );
    
    await Promise.all(promises);
    closeModal();
  } catch (err) {
    console.error('Error creating keywords:', err);
    error.value = 'Failed to create keywords. Please try again.';
  } finally {
    isLoading.value = false;
  }
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
        :disabled="isLoading"
      />
      
      <div v-if="isLoading" class="flex justify-center py-4">
        <div class="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-neutral-800"></div>
      </div>
      
      <div v-if="error" class="text-red-500 text-sm">
        {{ error }}
      </div>
      
      <div v-if="generatedKeywords.length > 0" class="mt-4">
        <h3 class="font-medium mb-2">Generated Keywords:</h3>
        <ul class="space-y-1 max-h-96 overflow-y-auto">
          <li v-for="(keyword, index) in generatedKeywords" :key="index" class="flex items-center justify-between bg-neutral-100 px-2 py-1.5 rounded mb-1">
            <span class="text-sm">{{ keyword }}</span>
            <button 
              @click="removeKeyword(index)" 
              class="text-neutral-500 hover:text-red-500 ml-2 p-1 cursor-pointer rounded-lg hover:bg-red-100"
              type="button"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4">
                <path d="M18 6 6 18"/>
                <path d="m6 6 12 12"/>
              </svg>
            </button>
          </li>
        </ul>
      </div>
    </div>
    
    <template #footer>
      <button 
        v-if="generatedKeywords.length === 0"
        @click="generateKeywordsAndPrompts" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
        :disabled="isLoading || !domain.trim()"
      >
        Generate
      </button>
      
      <button 
        v-if="generatedKeywords.length > 0"
        @click="createKeywords" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
        :disabled="isLoading"
      >
        Create Keywords
      </button>
      
      <button 
        @click="closeModal" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
        :disabled="isLoading"
      >
        Cancel
      </button>
    </template>
  </Modal>
</template>
