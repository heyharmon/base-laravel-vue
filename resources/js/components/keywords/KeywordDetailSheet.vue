<script setup>
import { computed, watch, onMounted, ref } from 'vue';
import { useKeywordStore } from '@/stores/keywordStore';
import Sheet from '@/components/ui/Sheet.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  keywordId: {
    type: [Number, String],
    default: null
  },
  keyword: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['close']);

const keywordStore = useKeywordStore();

const keywordDetails = computed(() => {
  return keywordStore.selectedKeywordDetails;
});

const selectedPromptId = ref(null);
const selectedPrompt = ref(null);

const highlightKeyword = (content) => {
  if (!keywordDetails.value?.name || !content) return content;
  
  const regex = new RegExp(keywordDetails.value.name, 'gi');
  return content.replace(regex, match => `<span class="bg-yellow-200">${match}</span>`);
};

const closeSheet = () => {
  selectedPromptId.value = null;
  selectedPrompt.value = null;
  emit('close');
};

// Fetch keyword details when component mounts or keywordId changes
const fetchDetails = async () => {
  if (props.keywordId) {
    await keywordStore.showKeyword(props.keywordId);
  }
};

const showPromptResponses = async (prompt) => {
  selectedPromptId.value = prompt.id;
  selectedPrompt.value = prompt;
  await keywordStore.getKeywordResponses(props.keywordId, prompt.id);
};

onMounted(fetchDetails);

watch(() => props.keywordId, fetchDetails);
</script>

<template>
  <Sheet 
    :is-open="isOpen" 
    @close="closeSheet"
    position="right"
    title="Keyword"
  >
    <div class="flex w-[1300px] h-full">
      <div v-if="keywordStore.isLoadingDetails" class="flex-[1] h-full flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
      </div>
      <div v-else-if="keywordDetails" class="flex-[1] h-full space-y-6 pr-4 border-r border-neutral-200">
        <div>
          <div class="bg-neutral-50 p-4 rounded-lg">
            <div class="mb-3">
              <div class="text-neutral-500 text-sm mb-1">Keyword:</div>
              <span class="text-neutral-800 text-2xl/7 font-medium">{{ keywordDetails.name }}</span>
            </div>
            <div v-if="keywordDetails.description" class="mb-2 text-lg">
              <span class="text-neutral-500">Description:</span>
              <span class="text-neutral-800 ml-2">{{ keywordDetails.description }}</span>
            </div>
            <div class="mb-2 text-sm">
              <span class="text-neutral-500">Found in {{ keywordDetails.prompts?.length || 0 }} {{ keywordDetails.prompts?.length === 1 ? 'prompt' : 'prompts' }}</span>
            </div>
          </div>
        </div>

        <div v-if="keywordDetails.prompts && keywordDetails.prompts.length > 0">
          <h3 class="text-lg font-medium text-neutral-800 mb-2">Found in Prompts</h3>
          <div class="space-y-3">
            <div 
              v-for="prompt in keywordDetails.prompts" 
              :key="prompt.id"
              class="border border-neutral-300 hover:border-neutral-400 p-3 rounded-lg cursor-pointer hover:bg-neutral-50"
              :class="{ 'border-2 border-neutral-400 bg-neutral-50': selectedPromptId === prompt.id }"
              @click="showPromptResponses(prompt)"
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
      
      <!-- Responses Column -->
      <div class="flex-[2] h-full pl-4">
        <div v-if="selectedPromptId && keywordStore.isLoadingKeywordResponses" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
        </div>
        <div v-else-if="selectedPromptId && selectedPrompt" class="space-y-4">
          <div class="bg-neutral-50 p-4 rounded-lg mb-4">
            <div class="mb-2">
              <span class="text-neutral-500 text-sm">Prompt:</span>
              <div class="text-neutral-800 mt-2 text-lg">
                {{ selectedPrompt?.content }}
              </div>
            </div>
          </div>

          <h3 class="text-lg font-medium text-neutral-800 mb-2">Responses</h3>
          <div v-if="keywordStore.selectedKeywordResponses.length === 0" class="text-neutral-500 italic">
            No responses found containing this keyword.
          </div>
          <div v-else class="space-y-4 pr-2">
            <div 
              v-for="response in keywordStore.selectedKeywordResponses" 
              :key="response.id"
              class="bg-white border border-neutral-200 p-4 rounded-lg"
            >
              <!-- Response provider and model -->
              <div class="mb-3 flex justify-between">
                <span class="text-neutral-500 text-sm">Provider: <span class="font-medium">{{ response.provider }}</span></span>
                <span class="text-neutral-500 text-sm">Model: <span class="font-medium">{{ response.model }}</span></span>
              </div>

              <!-- Response content -->
              <div class="p-3 bg-neutral-50 rounded border border-neutral-200 whitespace-pre-wrap text-base/7 mb-4" v-html="highlightKeyword(response.content)"></div>

              <!-- Response search queries -->
              <div v-if="response.search && response.search.queries && response.search.queries.length > 0" class="p-2 rounded border border-neutral-200">
                <div class="text-sm text-neutral-500 mb-2">Google searches performed by the agent</div>
                <div class="flex flex-wrap gap-2 mb-2">
                  <span v-for="(query, index) in response.search.queries" :key="index" class="text-sm bg-white px-2 py-1 rounded border border-neutral-200">
                    <svg class="inline-block w-4 h-4 mr-1" xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/><path d="M1 1h22v22H1z" fill="none"/></svg>
                    {{ query }}
                  </span>
                </div>
                <div class="text-xs text-neutral-500">Agent may perform Google searches at its discretion to provide accurate answers.</div>
              </div>

              <!-- Response metadata -->
              <div class="mt-3 text-xs text-neutral-500 flex justify-between">
                <span>Run Date: {{ new Date(response.run.run_date).toLocaleString() }}</span>
                <span>Tokens: {{ response.metadata.usage.promptTokens + response.metadata.usage.completionTokens }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="flex items-center justify-center h-full text-neutral-500">
          <p>Select a prompt to view responses containing this keyword</p>
        </div>
      </div>
    </div>
  </Sheet>
</template>
