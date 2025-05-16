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

const closeSheet = () => {
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
  await keywordStore.getPromptResponses(props.keywordId, prompt.id);
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
    <div class="w-[1200px] flex">
      <div v-if="keywordStore.isLoadingDetails" class="flex-1 flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
      </div>
      <div v-else-if="keywordDetails" class="flex-1 space-y-6 pr-4 border-r border-neutral-200">
        <div>
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
              class="bg-white border border-neutral-300 p-3 rounded-lg cursor-pointer hover:bg-neutral-50"
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
      <div class="flex-1 pl-4">
        <div v-if="selectedPromptId && keywordStore.isLoadingResponses" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
        </div>
        <div v-else-if="selectedPromptId && selectedPrompt" class="space-y-4">
          <div class="bg-neutral-50 p-4 rounded-lg mb-4">
            <div class="mb-2">
              <span class="text-neutral-500 text-sm">Prompt:</span>
              <div class="text-neutral-800 mt-1 p-2 bg-white border border-neutral-200 rounded">
                {{ selectedPrompt?.content }}
              </div>
            </div>
          </div>

          <h3 class="text-lg font-medium text-neutral-800 mb-2">Responses</h3>
          <div v-if="keywordStore.selectedPromptResponses.length === 0" class="text-neutral-500 italic">
            No responses found containing this keyword.
          </div>
          <div v-else class="space-y-4 overflow-y-auto max-h-[600px] pr-2">
            <div 
              v-for="response in keywordStore.selectedPromptResponses" 
              :key="response.id"
              class="bg-white border border-neutral-200 p-4 rounded-lg"
            >
              <div class="mb-2 flex justify-between">
                <span class="text-neutral-500 text-sm">Provider: <span class="font-medium">{{ response.provider }}</span></span>
                <span class="text-neutral-500 text-sm">Model: <span class="font-medium">{{ response.model }}</span></span>
              </div>
              <div class="p-3 bg-neutral-50 rounded border border-neutral-200 whitespace-pre-wrap text-sm">
                {{ response.content }}
              </div>
              <div class="mt-2 text-xs text-neutral-500 flex justify-between">
                <span>Run Date: {{ new Date(response.run.run_date).toLocaleString() }}</span>
                <span>Tokens: {{ response.tokens }}</span>
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
