<script setup>
import { ref, nextTick, watch, onMounted } from 'vue';
import Modal from '@/components/ui/Modal.vue';
import CloseIcon from '@/components/icons/CloseIcon.vue';
import api from '@/services/api';
import { usePromptStore } from '@/stores/promptStore';
import { useOrganizationStore } from '@/stores/organizationStore';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  teamId: {
    type: [Number, String],
    required: true
  },
  campaignId: {
    type: [Number, String],
    required: true
  }
});

const emit = defineEmits(['close']);

const isLoadingPrompts = ref(false);
const generatedPrompts = ref([]);
const error = ref(null);
const promptStore = usePromptStore();
const organizationStore = useOrganizationStore();
const selectedOrganizationId = ref(null);
const organizations = ref([]);

onMounted(async () => {
  await fetchOrganizations();
});

watch(() => props.isOpen, async (isOpen) => {
  if (isOpen) {
    if (organizations.value.length > 0 && !selectedOrganizationId.value) {
      // Set default to the first owned organization
      const ownedOrg = organizations.value.find(org => !org.is_competitor);
      if (ownedOrg) {
        selectedOrganizationId.value = ownedOrg.id;
      }
    }
  }
}, { immediate: true });

const fetchOrganizations = async () => {
  try {
    await organizationStore.fetchOrganizations(props.teamId, props.campaignId);
    organizations.value = organizationStore.organizations;
  } catch (err) {
    console.error('Error fetching organizations:', err);
    error.value = 'Failed to fetch organizations. Please try again.';
  }
};

const closeModal = () => {
  generatedPrompts.value = [];
  error.value = null;
  emit('close');
};

const removePrompt = (index) => {
  generatedPrompts.value.splice(index, 1);
};

const generatePrompts = async () => {
  if (!selectedOrganizationId.value) return;

  const selectedOrg = organizations.value.find(org => org.id === selectedOrganizationId.value);
  if (!selectedOrg) {
    error.value = 'Please select an organization.';
    return;
  }

  isLoadingPrompts.value = true;
  error.value = null;
  generatedPrompts.value = [];

  try {
    const response = await api.post(`/teams/${props.teamId}/campaigns/${props.campaignId}/generate-prompts`);
    generatedPrompts.value = response || [];
  } catch (err) {
    console.error('Error generating prompts:', err);
    if (err.response?.status === 422) {
      error.value = 'Campaign does not have any keywords.';
    } else {
      error.value = 'Failed to generate prompts. Please try again.';
    }
  } finally {
    isLoadingPrompts.value = false;
  }
};

const createPrompts = async () => {
  if (!generatedPrompts.value.length || !selectedOrganizationId.value) return;

  try {
    const promises = generatedPrompts.value.map(prompt =>
      promptStore.createPrompt({ 
        content: prompt
      })
    );

    await Promise.all(promises);
    closeModal();
  } catch (err) {
    console.error('Error creating prompts:', err);
    error.value = 'Failed to create prompts. Please try again.';
  }
};
</script>

<template>
  <Modal :is-open="isOpen" title="Generate Prompts" width="wider" @close="closeModal">
    <div class="space-y-4">
      <!-- Organization Selector -->
      <div class="mb-4">
        <label for="organization" class="block text-sm font-medium text-neutral-700 mb-1">Based on:</label>
        <select
          id="organization"
          v-model="selectedOrganizationId"
          class="w-full px-3 py-2 border border-neutral-300 rounded-md"
          :disabled="isLoadingPrompts"
        >
          <option value="">Select an organization</option>
          <optgroup label="Your Organizations">
            <option
              v-for="org in organizations.filter(o => !o.is_competitor)"
              :key="org.id"
              :value="org.id"
            >
              {{ org.name }}
            </option>
          </optgroup>
          <optgroup label="Competitors">
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

      <div v-if="error" class="text-red-500 text-sm">
        {{ error }}
      </div>

      <div class="mt-4 max-h-[calc(100vh-30rem)]">
        <!-- Prompts Content -->
        <div class="max-h-[calc(100vh-30rem)] overflow-y-auto">
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
                  <CloseIcon />
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
		<button
        v-if="generatedPrompts.length > 0"
        @click="createPrompts"
        class="inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
        :disabled="isLoadingPrompts || !selectedOrganizationId"
      >
        Create Prompts
      </button>

	  <button
        v-if="!isLoadingPrompts && generatedPrompts.length === 0"
        @click="generatePrompts"
        class="inline-flex justify-center px-4 py-2 bg-neutral-800 hover:bg-neutral-700 text-white rounded-md cursor-pointer"
        :disabled="isLoadingPrompts || !selectedOrganizationId"
      >
        Generate
      </button>

      <button
        v-if="!isLoadingPrompts && generatedPrompts.length > 0"
        @click="generatePrompts"
        class="inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
        :disabled="isLoadingPrompts || !selectedOrganizationId"
      >
        Regenerate
      </button>

      <button
        @click="closeModal"
        class="inline-flex justify-center px-4 py-2 bg-neutral-200 hover:bg-neutral-100 text-neutral-800 rounded-md cursor-pointer"
      >
	  Cancel
      </button>
    </template>
  </Modal>
</template>
