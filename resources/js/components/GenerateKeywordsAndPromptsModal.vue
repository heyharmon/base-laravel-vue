<script setup>
import { ref, nextTick, watch, onMounted } from 'vue';
import Modal from '@/components/ui/Modal.vue';
import api from '@/services/api';
import { useKeywordStore } from '@/stores/keywordStore';
import { usePromptStore } from '@/stores/promptStore';
import { useOrganizationStore } from '@/stores/organizationStore';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  }
});

const emit = defineEmits(['close']);

const domain = ref('');
const domainInput = ref(null);
const isLoadingKeywords = ref(false);
const isLoadingPrompts = ref(false);
const generatedKeywords = ref([]);
const generatedPrompts = ref([]);
const error = ref(null);
const activeTab = ref('keywords');
const keywordStore = useKeywordStore();
const promptStore = usePromptStore();
const organizationStore = useOrganizationStore();
const selectedOrganizationId = ref(null);
const organizations = ref([]);

onMounted(async () => {
  await fetchOrganizations();
});

watch(() => props.isOpen, async (isOpen) => {
  if (isOpen) {
    await nextTick();
    if (domainInput.value) {
      domainInput.value.focus();
    }

    if (organizations.value.length > 0 && !selectedOrganizationId.value) {
      // Set default to the first owned organization
      const ownedOrg = organizations.value.find(org => !org.is_competitor);
      if (ownedOrg) {
        selectedOrganizationId.value = ownedOrg.id;
      }
    }
  }
}, { immediate: true });

// Watch for organization selection changes and pre-populate domain
watch(selectedOrganizationId, (newOrgId) => {
  if (newOrgId) {
    const selectedOrg = organizations.value.find(org => org.id === newOrgId);
    if (selectedOrg && selectedOrg.website) {
      domain.value = selectedOrg.website;
    }
  }
});

const fetchOrganizations = async () => {
  try {
    await organizationStore.fetchOrganizations();
    organizations.value = organizationStore.organizations;
  } catch (err) {
    console.error('Error fetching organizations:', err);
    error.value = 'Failed to fetch organizations. Please try again.';
  }
};

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

  isLoadingKeywords.value = true;
  isLoadingPrompts.value = true;
  error.value = null;
  generatedKeywords.value = [];
  generatedPrompts.value = [];

  // Handle keywords promise separately
  api.post('/generate-keywords', { domain: domain.value.trim() })
    .then(keywordsResponse => {
      generatedKeywords.value = keywordsResponse || [];
    })
    .catch(err => {
      console.error('Error generating keywords:', err);
      if (!error.value) {
        error.value = 'Failed to generate keywords. ';
      } else {
        error.value += 'Failed to generate keywords. ';
      }
    })
    .finally(() => {
      isLoadingKeywords.value = false;
    });

  // Handle prompts promise separately
  api.post('/generate-prompts', { domain: domain.value.trim() })
    .then(promptsResponse => {
      generatedPrompts.value = promptsResponse || [];
    })
    .catch(err => {
      console.error('Error generating prompts:', err);
      if (!error.value) {
        error.value = 'Failed to generate prompts. ';
      } else {
        error.value += 'Failed to generate prompts. ';
      }
    })
    .finally(() => {
      isLoadingPrompts.value = false;
    });
};

const createKeywords = async () => {
  if (!generatedKeywords.value.length || !selectedOrganizationId.value) return;

  try {
    const promises = generatedKeywords.value.map(keyword =>
      keywordStore.createKeyword(selectedOrganizationId.value, { name: keyword })
    );

    await Promise.all(promises);
    closeModal();
  } catch (err) {
    console.error('Error creating keywords:', err);
    error.value = 'Failed to create keywords. Please try again.';
  }
};

const createPrompts = async () => {
  if (!generatedPrompts.value.length || !selectedOrganizationId.value) return;

  try {
    const promises = generatedPrompts.value.map(prompt =>
      promptStore.createPrompt({ content: prompt, organization_id: selectedOrganizationId.value })
    );

    await Promise.all(promises);
    closeModal();
  } catch (err) {
    console.error('Error creating prompts:', err);
    error.value = 'Failed to create prompts. Please try again.';
  }
};

const createAll = async () => {
  if (!selectedOrganizationId.value) return;

  try {
    const keywordPromises = generatedKeywords.value.map(keyword =>
      keywordStore.createKeyword(selectedOrganizationId.value, { name: keyword })
    );

    const promptPromises = generatedPrompts.value.map(prompt =>
      promptStore.createPrompt({ content: prompt, organization_id: selectedOrganizationId.value })
    );

    await Promise.all([...keywordPromises, ...promptPromises]);
    closeModal();
  } catch (err) {
    console.error('Error creating content:', err);
    error.value = 'Failed to create content. Please try again.';
  }
};
</script>

<template>
  <Modal :is-open="isOpen" title="Generate Keywords & Prompts" width="wider" @close="closeModal">
    <div class="space-y-4">
      <!-- Organization Selector -->
      <div class="mb-4">
        <label for="organization" class="block text-sm font-medium text-neutral-700 mb-1">Organization</label>
        <select
          id="organization"
          v-model="selectedOrganizationId"
          class="w-full px-3 py-2 border border-neutral-300 rounded-md"
          :disabled="isLoadingKeywords || isLoadingPrompts || organizations.length === 0"
        >
          <option v-if="organizations.length === 0" value="" disabled>No organizations available</option>
          <optgroup label="Owned Organizations">
            <option
              v-for="org in organizations.filter(o => !o.is_competitor)"
              :key="org.id"
              :value="org.id"
            >
              {{ org.name }}
            </option>
          </optgroup>
          <optgroup label="Competitor Organizations">
            <option
              v-for="org in organizations.filter(o => o.is_competitor)"
              :key="org.id"
              :value="org.id"
            >
              {{ org.name }}
            </option>
          </optgroup>
        </select>
      </div>

      <input
        ref="domainInput"
        v-model="domain"
        type="text"
        placeholder="Enter website domain (e.g. acme.com)"
        class="w-full px-3 py-2 border border-neutral-300 rounded-md"
        @keyup.enter="generateKeywordsAndPrompts"
        :disabled="isLoadingKeywords || isLoadingPrompts"
      />

      <div v-if="error" class="text-red-500 text-sm">
        {{ error }}
      </div>

      <div class="mt-4 h-[calc(100vh-30rem)]">
        <!-- Tabs -->
        <div class="border-b border-neutral-200 mb-4">
          <nav class="flex -mb-px">
            <button
              @click="activeTab = 'keywords'"
              :class="[
                'py-2 px-4 text-sm font-medium flex items-center',
                activeTab === 'keywords'
                  ? 'border-b-2 border-neutral-800 text-neutral-800'
                  : 'text-neutral-500 hover:text-neutral-700'
              ]"
            >
              Keywords ({{ generatedKeywords.length }})
              <div v-if="isLoadingKeywords" class="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-neutral-800 ml-2"></div>
            </button>
            <button
              @click="activeTab = 'prompts'"
              :class="[
                'py-2 px-4 text-sm font-medium flex items-center',
                activeTab === 'prompts'
                  ? 'border-b-2 border-neutral-800 text-neutral-800'
                  : 'text-neutral-500 hover:text-neutral-700'
              ]"
            >
              Prompts ({{ generatedPrompts.length }})
              <div v-if="isLoadingPrompts" class="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-neutral-800 ml-2"></div>
            </button>
          </nav>
        </div>

        <!-- Keywords Tab -->
        <div v-if="activeTab === 'keywords'" class="max-h-[calc(100vh-30rem)] overflow-y-auto">
          <div v-if="isLoadingKeywords" class="flex flex-col items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-800 mb-2"></div>
            <p class="text-neutral-600 text-sm">Generating keywords...</p>
          </div>
          <div v-else-if="generatedKeywords.length > 0">
            <h3 class="font-medium mb-2">Generated Keywords:</h3>
            <ul class="space-y-1">
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

        <!-- Prompts Tab -->
        <div v-if="activeTab === 'prompts'" class="max-h-[calc(100vh-30rem)] overflow-y-auto">
          <div v-if="isLoadingPrompts" class="flex flex-col items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-800 mb-2"></div>
            <p class="text-neutral-600 text-sm">Generating prompts...</p>
          </div>
          <div v-else-if="generatedPrompts.length > 0">
            <h3 class="font-medium mb-2">Generated Prompts:</h3>
            <ul class="space-y-1">
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
    </div>

    <template #footer>
      <button
        v-if="generatedKeywords.length === 0 && generatedPrompts.length === 0"
        @click="generateKeywordsAndPrompts"
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
        :disabled="isLoadingKeywords || isLoadingPrompts || !domain.trim()"
      >
        Generate
      </button>

      <template v-if="generatedKeywords.length > 0 || generatedPrompts.length > 0">
        <button
          v-if="activeTab === 'keywords' && generatedKeywords.length > 0"
          @click="createKeywords"
          class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
          :disabled="isLoadingKeywords || !selectedOrganizationId"
        >
          Create Keywords
        </button>

        <button
          v-if="activeTab === 'prompts' && generatedPrompts.length > 0"
          @click="createPrompts"
          class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
          :disabled="isLoadingPrompts || !selectedOrganizationId"
        >
          Create Prompts
        </button>

        <button
          @click="createAll"
          class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
          :disabled="isLoadingKeywords || isLoadingPrompts || !selectedOrganizationId"
        >
          Create All
        </button>
      </template>

      <button
        @click="closeModal"
        class="ml-3 inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
        :disabled="isLoadingKeywords || isLoadingPrompts"
      >
        Cancel
      </button>
    </template>
  </Modal>
</template>
