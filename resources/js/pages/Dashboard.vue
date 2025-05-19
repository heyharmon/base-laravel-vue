<script setup>
import { onMounted, ref } from 'vue';
import { useKeywordStore } from '@/stores/keywordStore';
import { usePromptStore } from '@/stores/promptStore';
import KeywordDetailSheet from '@/components/keywords/KeywordDetailSheet.vue';
import PromptDetailSheet from '@/components/prompts/PromptDetailSheet.vue';
import KeywordCreateModal from '@/components/keywords/KeywordCreateModal.vue';
import PromptCreateModal from '@/components/prompts/PromptCreateModal.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const keywordStore = useKeywordStore();
const promptStore = usePromptStore();

const isKeywordCreateModalOpen = ref(false);
const isPromptCreateModalOpen = ref(false);
const isKeywordDetailSheetOpen = ref(false);
const isPromptDetailSheetOpen = ref(false);

const selectedKeyword = ref(null);
const selectedKeywordId = ref(null);
const selectedPrompt = ref(null);
const selectedPromptId = ref(null);
const activeTab = ref('keywords'); // Default tab for mobile view

onMounted(async () => {
  await keywordStore.fetchKeywords();
  await promptStore.fetchPrompts();
});

const runPrompt = async (id) => {
  await promptStore.runPrompt(id);
};

const runAllPrompts = async () => {
  const allPrompts = promptStore.prompts;
  for (const prompt of allPrompts) {
    promptStore.runPrompt(prompt.id);
    await new Promise(resolve => setTimeout(resolve, 800));
  }
};

const showKeywordDetails = async (keyword) => {
  selectedKeyword.value = keyword;
  selectedKeywordId.value = keyword.id;
  isKeywordDetailSheetOpen.value = true;
};

const showPromptDetails = async (prompt) => {
  selectedPrompt.value = prompt;
  selectedPromptId.value = prompt.id;
  isPromptDetailSheetOpen.value = true;
};
</script>

<template>
  <DefaultLayout>
    <div class="flex flex-col md:flex-row h-[calc(100vh-4rem)] overflow-hidden">
      <!-- Mobile tabs -->
      <div class="flex md:hidden border-b border-neutral-200 sticky top-0 bg-white z-10 shadow-sm">
        <button 
          @click="activeTab = 'keywords'" 
          class="w-1/2 py-2 text-center font-medium"
          :class="activeTab === 'keywords' ? 'text-neutral-800 border-b-2 border-neutral-800' : 'text-neutral-500'"
        >
          Keywords
        </button>
        <button 
          @click="activeTab = 'prompts'" 
          class="w-1/2 py-2 text-center font-medium"
          :class="activeTab === 'prompts' ? 'text-neutral-800 border-b-2 border-neutral-800' : 'text-neutral-500'"
        >
          Prompts
        </button>
      </div>

      <!-- Left column - Keywords -->
      <div class="w-full md:w-1/3 md:pr-4 md:px-4 py-4 md:border-r border-neutral-200 overflow-y-auto" :class="{'block': activeTab === 'keywords', 'hidden': activeTab !== 'keywords', 'md:block': true}">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl md:text-2xl font-semibold">Keywords</h2>
          <button 
            @click="isKeywordCreateModalOpen = true" 
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
            class="p-4 border border-neutral-300 hover:border-neutral-400 hover:bg-neutral-50 rounded-lg cursor-pointer"
            :class="{ 'border-2 border-neutral-400 bg-neutral-50': selectedKeywordId === keyword.id }"
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
      <div class="w-full md:w-2/3 md:pl-4 md:px-4 py-4 overflow-y-auto" :class="{'block': activeTab === 'prompts', 'hidden': activeTab !== 'prompts', 'md:block': true}">
        <div class="mb-4">
          <div class="flex justify-between items-center">
            <h2 class="text-xl md:text-2xl font-semibold">Prompts</h2>
            <div class="flex space-x-2">
              <button 
                @click="runAllPrompts" 
                class="px-3 py-1.5 bg-white text-neutral-700 rounded-md text-xs font-medium hover:bg-neutral-100 border transition-colors cursor-pointer"
                :disabled="promptStore.isLoading || promptStore.loadingPromptIds.length > 0"
              >
                Run all prompts
              </button>
              <button 
                @click="isPromptCreateModalOpen = true" 
                class="px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
              >
                Add prompt
              </button>
            </div>
          </div>
        </div>
        
        <div v-if="promptStore.isLoading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
        </div>
        
        <div v-else class="space-y-4">
          <div 
            v-for="prompt in promptStore.prompts" 
            :key="prompt.id" 
            class="flex items-start justify-between p-4 border border-neutral-300 hover:border-neutral-400 hover:bg-neutral-50 rounded-lg cursor-pointer"
            :class="{ 'border-2 border-neutral-400 bg-neutral-50': selectedPromptId === prompt.id }"
            @click="showPromptDetails(prompt)"
          >
            <div>
                <p class="text-neutral-800 text-lg">{{ prompt.content }}</p>
                <div v-if="prompt.keywords_count >= 0" class="flex items-center gap-2 text-sm text-neutral-500 mt-1">
                    <p v-if="prompt.mentions_percentage !== undefined" class="min-w-[200px]">Mentioned {{ prompt.mentions_percentage }}% of the time</p>
                    <p class="">{{ prompt.keywords_count }} keyword {{ prompt.keywords_count === 1 ? 'occurrence' : 'occurrences' }}</p>
                </div>
                <div v-else class="text-sm text-neutral-500 mt-1">New prompt</div>
            </div>
            <div class="flex justify-end space-x-2">
              <button 
                @click.stop="runPrompt(prompt.id)" 
                class="px-3 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer flex items-center justify-center min-w-[40px]"
                :disabled="promptStore.loadingPromptIds.includes(prompt.id)"
              >
                <div v-if="promptStore.loadingPromptIds.includes(prompt.id)" class="animate-spin h-3 w-3 border-b-2 border-neutral-800 rounded-full"></div>
                <span v-else>Run</span>
              </button>
              <button 
                @click.stop="promptStore.deletePrompt(prompt.id)" 
                class="-mr-2 p-1.5 text-neutral-400 hover:text-neutral-600 transition-colors cursor-pointer"
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
    :is-open="isKeywordCreateModalOpen"
    @close="isKeywordCreateModalOpen = false"
  />

  <!-- Prompt Modal -->
  <PromptCreateModal
    :is-open="isPromptCreateModalOpen"
    @close="isPromptCreateModalOpen = false"
  />

  <!-- Keyword Detail Sheet -->
  <KeywordDetailSheet
    :is-open="isKeywordDetailSheetOpen"
    :keyword="selectedKeyword"
    :keyword-id="selectedKeyword?.id"
    @close="() => {
      isKeywordDetailSheetOpen = false;
      selectedKeywordId = null;
    }"
  />

  <!-- Prompt Detail Sheet -->
  <PromptDetailSheet
    :is-open="isPromptDetailSheetOpen"
    :prompt="selectedPrompt"
    :prompt-id="selectedPrompt?.id"
    @close="() => {
      isPromptDetailSheetOpen = false;
      selectedPromptId = null;
    }"
  />
</template>
