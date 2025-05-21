<script setup>
import { ref, nextTick, watch } from 'vue';
import Modal from '@/components/ui/Modal.vue';
import api from '@/services/api';
import { useKeywordStore } from '@/stores/keywordStore';
import { usePromptStore } from '@/stores/promptStore';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  }
});

const emit = defineEmits(['close']);

const domain = ref('');
const domainInput = ref(null);
const isLoading = ref(false);
const generatedKeywords = ref([]);
const generatedPrompts = ref([]);
const error = ref(null);
const activeTab = ref('keywords');
const keywordStore = useKeywordStore();
const promptStore = usePromptStore();

watch(() => props.isOpen, async (isOpen) => {
  if (isOpen) {
    await nextTick();
    if (domainInput.value) {
      domainInput.value.focus();
    }
  }
}, { immediate: true });

const closeModal = () => {
  domain.value = '';
  generatedKeywords.value = [];
  generatedPrompts.value = [];
  error.value = null;
  emit('close');
};

const removeKeyword = (index) => {
  generatedKeywords.value.splice(index, 1);
};

const removePrompt = (index) => {
  generatedPrompts.value.splice(index, 1);
};

const generateKeywordsAndPrompts = async () => {
  if (!domain.value.trim()) return;
  
  isLoading.value = true;
  error.value = null;
  generatedKeywords.value = [];
  generatedPrompts.value = [];
  
  try {
    // Generate keywords and prompts in parallel
    const [keywordsResponse, promptsResponse] = await Promise.all([
      api.post('/generate-keywords', { domain: domain.value.trim() }),
      api.post('/generate-prompts', { domain: domain.value.trim() })
    ]);
    
    generatedKeywords.value = keywordsResponse || [];
    generatedPrompts.value = promptsResponse || [];
  } catch (err) {
    console.error('Error generating content:', err);
    error.value = 'Failed to generate content. Please try again.';
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

const createPrompts = async () => {
  if (!generatedPrompts.value.length) return;
  
  isLoading.value = true;
  
  try {
    const promises = generatedPrompts.value.map(prompt => 
      promptStore.createPrompt({ content: prompt })
    );
    
    await Promise.all(promises);
    closeModal();
  } catch (err) {
    console.error('Error creating prompts:', err);
    error.value = 'Failed to create prompts. Please try again.';
  } finally {
    isLoading.value = false;
  }
};

const createAll = async () => {
  isLoading.value = true;
  
  try {
    const keywordPromises = generatedKeywords.value.map(keyword => 
      keywordStore.createKeyword({ name: keyword })
    );
    
    const promptPromises = generatedPrompts.value.map(prompt => 
      promptStore.createPrompt({ content: prompt })
    );
    
    await Promise.all([...keywordPromises, ...promptPromises]);
    closeModal();
  } catch (err) {
    console.error('Error creating content:', err);
    error.value = 'Failed to create content. Please try again.';
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <Modal :is-open="isOpen" title="Generate Keywords & Prompts" width="wider" @close="closeModal">
    <div class="space-y-4">
      <input 
        ref="domainInput"
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
      
      <div v-if="generatedKeywords.length > 0 || generatedPrompts.length > 0" class="mt-4 min-h-[calc(100vh-30rem)]">
        <!-- Tabs -->
        <div class="border-b border-neutral-200 mb-4">
          <nav class="flex -mb-px">
            <button
              @click="activeTab = 'keywords'"
              :class="[
                'py-2 px-4 text-sm font-medium',
                activeTab === 'keywords'
                  ? 'border-b-2 border-neutral-800 text-neutral-800'
                  : 'text-neutral-500 hover:text-neutral-700'
              ]"
            >
              Keywords ({{ generatedKeywords.length }})
            </button>
            <button
              @click="activeTab = 'prompts'"
              :class="[
                'py-2 px-4 text-sm font-medium',
                activeTab === 'prompts'
                  ? 'border-b-2 border-neutral-800 text-neutral-800'
                  : 'text-neutral-500 hover:text-neutral-700'
              ]"
            >
              Prompts ({{ generatedPrompts.length }})
            </button>
          </nav>
        </div>
        
        <!-- Keywords Tab -->
        <div v-if="activeTab === 'keywords' && generatedKeywords.length > 0">
          <h3 class="font-medium mb-2">Generated Keywords:</h3>
          <ul class="space-y-1 max-h-[calc(100vh-20rem)] overflow-y-auto">
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
        
        <!-- Prompts Tab -->
        <div v-if="activeTab === 'prompts' && generatedPrompts.length > 0">
          <h3 class="font-medium mb-2">Generated Prompts:</h3>
          <ul class="space-y-1 max-h-[calc(100vh-20rem)] overflow-y-auto">
            <li v-for="(prompt, index) in generatedPrompts" :key="index" class="flex items-center justify-between bg-neutral-100 px-2 py-1.5 rounded mb-1">
              <span class="text-sm">{{ prompt }}</span>
              <button 
                @click="removePrompt(index)" 
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
    </div>
    
    <template #footer>
      <button 
        v-if="generatedKeywords.length === 0 && generatedPrompts.length === 0"
        @click="generateKeywordsAndPrompts" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
        :disabled="isLoading || !domain.trim()"
      >
        Generate
      </button>
      
      <template v-if="generatedKeywords.length > 0 || generatedPrompts.length > 0">
        <button 
          v-if="activeTab === 'keywords' && generatedKeywords.length > 0"
          @click="createKeywords" 
          class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
          :disabled="isLoading"
        >
          Create Keywords
        </button>
        
        <button 
          v-if="activeTab === 'prompts' && generatedPrompts.length > 0"
          @click="createPrompts" 
          class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
          :disabled="isLoading"
        >
          Create Prompts
        </button>
        
        <button 
          @click="createAll" 
          class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
          :disabled="isLoading"
        >
          Create All
        </button>
      </template>
      
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
