<script setup>
import { computed, watch, onMounted } from 'vue';
import { usePromptStore } from '@/stores/promptStore';
import Sheet from '@/components/ui/Sheet.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  promptId: {
    type: [Number, String],
    default: null
  },
  prompt: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['close']);

const promptStore = usePromptStore();

const promptDetails = computed(() => {
  return promptStore.selectedPromptDetails;
});

const closeSheet = () => {
  emit('close');
};

// Fetch prompt details when component mounts or promptId changes
const fetchDetails = async () => {
  if (props.promptId) {
    await promptStore.showPrompt(props.promptId);
  }
};

onMounted(fetchDetails);

watch(() => props.promptId, fetchDetails);
</script>

<template>
  <Sheet 
    :is-open="isOpen" 
    @close="closeSheet"
    position="right"
    title="Prompt"
  >
    <div class="w-[800px]">
      <div v-if="promptStore.isLoadingDetails" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
      </div>
      <div v-else-if="promptDetails" class="space-y-6">
        <div>
          <div class="bg-neutral-50 p-4 rounded-lg">
            <div class="mb-2">
              <span class="text-neutral-500 text-sm">Content:</span>
              <p class="text-neutral-800 mt-1">{{ promptDetails.content }}</p>
            </div>
            <div class="mb-2">
              <span class="text-neutral-500 text-sm">Keyword occurrences:</span>
              <span class="text-neutral-800 ml-2">{{ promptDetails.keywords?.length || 0 }} {{ promptDetails.keywords?.length === 1 ? 'keyword' : 'keywords' }}</span>
            </div>
          </div>
        </div>

        <div v-if="promptDetails.keywords && promptDetails.keywords.length > 0">
          <h3 class="text-lg font-medium text-neutral-800 mb-2">Keywords Found</h3>
          <div class="space-y-3">
            <div 
              v-for="keyword in promptDetails.keywords" 
              :key="keyword.id"
              class="bg-white border border-neutral-300 p-3 rounded-lg"
            >
              <p class="text-neutral-800 font-medium">{{ keyword.name }}</p>
              <div class="mt-2 text-sm text-neutral-500 flex justify-between">
                <span>Occurrences: <span class="font-medium">{{ keyword.pivot.count }}</span></span>
                <span>Last found: {{ new Date(keyword.pivot.last_found_at).toLocaleDateString() }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-neutral-500 italic">
          No keywords have been found in this prompt yet.
        </div>
      </div>
    </div>
  </Sheet>
</template>
