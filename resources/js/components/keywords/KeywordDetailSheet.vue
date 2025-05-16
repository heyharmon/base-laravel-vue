<script setup>
import { computed, watch, onMounted } from 'vue';
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

const closeSheet = () => {
  emit('close');
};

// Fetch keyword details when component mounts or keywordId changes
const fetchDetails = async () => {
  if (props.keywordId) {
    await keywordStore.showKeyword(props.keywordId);
  }
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
    <div class="w-[800px]">
      <div v-if="keywordStore.isLoadingDetails" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
      </div>
      <div v-else-if="keywordDetails" class="space-y-6">
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
