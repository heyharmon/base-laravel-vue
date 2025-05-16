<script setup>
import { onMounted, ref } from 'vue';
import { useKeywordStore } from '@/stores/keywordStore';
import { usePromptStore } from '@/stores/promptStore';
import KeywordDetailSheet from '@/components/keywords/KeywordDetailSheet.vue';
import KeywordCreateModal from '@/components/keywords/KeywordCreateModal.vue';
import PromptCreateModal from '@/components/prompts/PromptCreateModal.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const keywordStore = useKeywordStore();
const promptStore = usePromptStore();

const isKeywordModalOpen = ref(false);
const isPromptModalOpen = ref(false);
const isKeywordDetailSheetOpen = ref(false);
const selectedKeyword = ref(null);

onMounted(async () => {
  await keywordStore.fetchKeywords();
  await promptStore.fetchPrompts();
});

const runPrompt = async (id) => {
  await promptStore.runPrompt(id);
};

const showKeywordDetails = async (keyword) => {
  selectedKeyword.value = keyword;
  isKeywordDetailSheetOpen.value = true;
  
  // Fetch the keyword with its prompts relationship
  if (keyword) {
    await keywordStore.fetchKeywordDetails(keyword.id);
  }
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
            class="px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
          >
            Add keyword
          </button>
        </div>
        
        <div v-if="keywordStore.isLoading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
        </div>
        
        <div v-else class="space-y-3">
          <div 
            v-for="keyword in keywordStore.keywords" 
            :key="keyword.id" 
            class="p-3 bg-white border border-neutral-300 hover:border-neutral-400 hover:bg-neutral-50 rounded-lg cursor-pointer"
            @click="showKeywordDetails(keyword)"
          >
            <div class="flex justify-between items-center">
              <div>
                <span class="text-lg font-medium text-neutral-800">{{ keyword.name }}</span>
                <div v-if="keyword.prompts_count >= 0" class="text-sm text-neutral-500 mt-1">Found in {{ keyword.prompts_count }} {{ keyword.prompts_count === 1 ? 'prompt' : 'prompts' }}</div>
                <div v-else class="text-sm text-neutral-500 mt-1">New keyword</div>
              </div>
              <button 
                @click.stop="keywordStore.deleteKeyword(keyword.id)" 
                class="text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer"
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
              class="px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
            >
              Add prompt
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
            class="flex items-start justify-between p-4 bg-white border border-neutral-300 hover:border-neutral-400 hover:bg-neutral-50 rounded-lg cursor-pointer"
          >
            <div>
                <!-- <h3 class="font-semibold text-lg text-neutral-800">{{ prompt.name }}</h3> -->
                <p class="text-neutral-800 text-lg">{{ prompt.content }}</p>
                <div class="text-sm text-neutral-500 mt-1">{{ prompt.keywords_count }} keyword {{ prompt.keywords_count === 1 ? 'occurrence' : 'occurrences' }}</div>
            </div>
            <div class="flex justify-end space-x-2">
              <button 
                @click.stop="runPrompt(prompt.id)" 
                class="px-3 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer"
              >
                Run
              </button>
              <button 
                @click.stop="promptStore.deletePrompt(prompt.id)" 
                class="p-1.5 text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer"
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
  <KeywordCreateModal
    :is-open="isKeywordModalOpen"
    @close="isKeywordModalOpen = false"
  />

  <!-- Prompt Modal -->
  <PromptCreateModal
    :is-open="isPromptModalOpen"
    @close="isPromptModalOpen = false"
  />

  <!-- Keyword Detail Sheet -->
  <KeywordDetailSheet
    :is-open="isKeywordDetailSheetOpen"
    :keyword="selectedKeyword"
    :keyword-id="selectedKeyword?.id"
    @close="isKeywordDetailSheetOpen = false"
  />
</template>
