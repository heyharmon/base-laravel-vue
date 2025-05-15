<script setup>
import { onMounted, ref } from 'vue';
import { useKeywordStore } from '@/stores/keywordStore';
import { usePromptStore } from '@/stores/promptStore';
import Modal from '@/components/ui/Modal.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const keywordStore = useKeywordStore();
const promptStore = usePromptStore();

const newKeyword = ref('');
const newPrompt = ref({ name: '', content: '' });
const isKeywordModalOpen = ref(false);
const isPromptModalOpen = ref(false);

onMounted(async () => {
  await keywordStore.fetchKeywords();
  await promptStore.fetchPrompts();
});

const addKeyword = async () => {
  if (newKeyword.value.trim()) {
    await keywordStore.createKeyword({ name: newKeyword.value.trim() });
    newKeyword.value = '';
    isKeywordModalOpen.value = false;
  }
};

const addPrompt = async () => {
    await promptStore.createPrompt(newPrompt.value);
    newPrompt.value = { name: '', content: '' };
    isPromptModalOpen.value = false;
};

const runPrompt = async (id) => {
  await promptStore.runPrompt(id);
};
</script>

<template>
  <DefaultLayout>
    <div class="flex h-[calc(100vh-4rem)]">
      <!-- Left column - Keywords -->
      <div class="w-1/3 pr-4 py-4 border-r border-neutral-200 h-full overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-2xl font-semibold">Keywords</h2>
          <button 
            @click="isKeywordModalOpen = true" 
            class="w-8 h-8 flex items-center justify-center bg-neutral-800 text-white rounded-full hover:bg-neutral-700"
            aria-label="Add Keyword"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
        
        <div v-if="keywordStore.isLoading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
        </div>
        
        <div v-else class="space-y-3">
          <div 
            v-for="keyword in keywordStore.keywords" 
            :key="keyword.id" 
            class="p-3 bg-neutral-50 border border-neutral-100 rounded-lg shadow-sm hover:shadow transition-all duration-200"
          >
            <div class="flex justify-between items-center">
              <span class="font-medium text-neutral-700">{{ keyword.name }}</span>
              <button 
                @click="keywordStore.deleteKeyword(keyword.id)" 
                class="text-neutral-400 hover:text-neutral-600 transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Right column - Prompts -->
      <div class="w-2/3 pl-4 py-4 h-full overflow-y-auto">
        <div class="mb-4">
          <div class="flex justify-between items-center">
            <h2 class="text-2xl font-semibold">Prompts</h2>
            <button 
              @click="isPromptModalOpen = true" 
              class="w-8 h-8 flex items-center justify-center bg-neutral-800 text-white rounded-full hover:bg-neutral-700"
              aria-label="Add Prompt"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
        
        <div v-if="promptStore.isLoading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
        </div>
        
        <div v-else class="space-y-4">
          <div 
            v-for="prompt in promptStore.prompts" 
            :key="prompt.id" 
            class="flex items-start justify-between p-4 bg-neutral-50 border border-neutral-100 rounded-lg shadow-sm hover:shadow transition-all duration-200"
          >
            <div>
                <h3 class="font-semibold text-lg text-neutral-800">{{ prompt.name }}</h3>
                <p class="mt-2 text-neutral-600">{{ prompt.content }}</p>
            </div>
            <div class="flex justify-end space-x-2 mt-4">
              <button 
                @click="runPrompt(prompt.id)" 
                class="px-3 py-1.5 bg-neutral-700 text-white rounded-md text-xs font-medium hover:bg-neutral-800 transition-colors"
              >
                Run
              </button>
              <button 
                @click="promptStore.deletePrompt(prompt.id)" 
                class="p-1.5 text-neutral-400 hover:text-neutral-600 transition-colors"
                aria-label="Delete prompt"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>

  <!-- Keyword Modal -->
  <Modal :is-open="isKeywordModalOpen" title="Add Keyword" @close="isKeywordModalOpen = false">
    <div class="space-y-4">
      <input 
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
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 text-white rounded-md"
        :disabled="keywordStore.isLoading"
      >
        Add
      </button>
      <button 
        @click="isKeywordModalOpen = false" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 text-neutral-800 rounded-md"
      >
        Cancel
      </button>
    </template>
  </Modal>

  <!-- Prompt Modal -->
  <Modal :is-open="isPromptModalOpen" title="Add Prompt" @close="isPromptModalOpen = false">
    <div class="space-y-4">
      <input 
        v-model="newPrompt.name" 
        type="text" 
        placeholder="Prompt title" 
        class="w-full px-3 py-2 border border-neutral-300 rounded-md"
      />
      <textarea 
        v-model="newPrompt.content" 
        placeholder="Prompt content" 
        class="w-full px-3 py-2 border border-neutral-300 rounded-md h-24"
      ></textarea>
    </div>
    <template #footer>
      <button 
        @click="addPrompt" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 text-white rounded-md"
        :disabled="promptStore.isLoading"
      >
        Add
      </button>
      <button 
        @click="isPromptModalOpen = false" 
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 text-neutral-800 rounded-md"
      >
        Cancel
      </button>
    </template>
  </Modal>
</template>
