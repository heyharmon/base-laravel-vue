<script setup>
import { onMounted, ref, computed } from 'vue';
import { useKeywordStore } from '@/stores/keywordStore';
import { usePromptStore } from '@/stores/promptStore';
import Modal from '@/components/ui/Modal.vue';
import Sheet from '@/components/ui/Sheet.vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const keywordStore = useKeywordStore();
const promptStore = usePromptStore();

const newKeyword = ref('');
const newPrompt = ref({ name: '', content: '' });
const isKeywordModalOpen = ref(false);
const isPromptModalOpen = ref(false);
const isKeywordDetailSheetOpen = ref(false);
const selectedKeyword = ref(null);

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

const showKeywordDetails = async (keyword) => {
  selectedKeyword.value = keyword;
  isKeywordDetailSheetOpen.value = true;
  
  // Fetch the keyword with its prompts relationship
  if (keyword) {
    await keywordStore.fetchKeywordDetails(keyword.id);
  }
};

const keywordDetails = computed(() => {
  return keywordStore.selectedKeywordDetails;
});
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
                <div class="text-sm text-neutral-500 mt-1">Found in {{ keyword.prompts_count }} {{ keyword.prompts_count === 1 ? 'prompt' : 'prompts' }}</div>
              </div>
              <button 
                @click="keywordStore.deleteKeyword(keyword.id)" 
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
                @click="runPrompt(prompt.id)" 
                class="px-3 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer"
              >
                Run
              </button>
              <button 
                @click="promptStore.deletePrompt(prompt.id)" 
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
      <!-- <input 
        v-model="newPrompt.name" 
        type="text" 
        placeholder="Prompt title" 
        class="w-full px-3 py-2 border border-neutral-300 rounded-md"
      /> -->
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

  <!-- Keyword Detail Sheet -->
  <Sheet 
    :is-open="isKeywordDetailSheetOpen" 
    @close="isKeywordDetailSheetOpen = false"
    position="right"
    :title="selectedKeyword?.name || 'Keyword Details'"
  >
    <div class="w-[800px]">
        <div v-if="keywordStore.isLoadingDetails" class="flex justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
        </div>
        <div v-else-if="keywordDetails" class="space-y-6">
            <div>
                <h3 class="text-lg font-medium text-neutral-800 mb-2">Keyword Information</h3>
                <div class="bg-neutral-50 p-4 rounded-lg">
                    <div class="mb-2">
                        <span class="text-neutral-500 text-sm">Name:</span>
                        <span class="text-neutral-800 ml-2 font-medium">{{ keywordDetails.name }}</span>
                    </div>
                    <div v-if="keywordDetails.description" class="mb-2">
                        <span class="text-neutral-500 text-sm">Description:</span>
                        <span class="text-neutral-800 ml-2">{{ keywordDetails.description }}</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-neutral-500 text-sm">Found in:</span>
                        <span class="text-neutral-800 ml-2">{{ keywordDetails.prompts?.length || 0 }} {{ keywordDetails.prompts?.length === 1 ? 'prompt' : 'prompts' }}</span>
                    </div>
                </div>
            </div>

            <div v-if="keywordDetails.prompts && keywordDetails.prompts.length > 0">
                <h3 class="text-lg font-medium text-neutral-800 mb-2">Found in Prompts</h3>
                <div class="space-y-3">
                    <div 
                        v-for="prompt in keywordDetails.prompts" 
                        :key="prompt.id"
                        class="bg-white border border-neutral-300 p-3 rounded-lg"
                    >
                        <p class="text-neutral-800">{{ prompt.content }}</p>
                        <div class="mt-2 text-sm text-neutral-500 flex justify-between">
                            <span>Occurrences: <span class="font-medium">{{ prompt.pivot.count }}</span></span>
                            <span>Last found: {{ new Date(prompt.pivot.last_found_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-neutral-500 italic">
                This keyword hasn't been found in any prompts yet.
            </div>
        </div>
    </div>
  </Sheet>
</template>
