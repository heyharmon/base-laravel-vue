<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useOrganizationStore } from '@/stores/organizationStore';
import { useKeywordStore } from '@/stores/keywordStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import KeywordDetailSheet from '@/components/keywords/KeywordDetailSheet.vue';
import KeywordCreateModal from '@/components/keywords/KeywordCreateModal.vue';
import GenerateKeywordsModal from '@/components/GenerateKeywordsModal.vue';

const route = useRoute();
const router = useRouter();
const organizationStore = useOrganizationStore();
const keywordStore = useKeywordStore();
const organization = ref({
  name: '',
  website: '',
  founded: '',
  employee_count: '',
  is_competitor: false
});
const originalOrganization = ref({
  name: '',
  website: '',
  founded: '',
  employee_count: '',
  is_competitor: false
});
const isSubmitting = ref(false);
const isLoading = ref(true);
const isKeywordCreateModalOpen = ref(false);
const isGenerateKeywordsModalOpen = ref(false);
const isKeywordDetailSheetOpen = ref(false);
const selectedKeyword = ref(null);
const selectedKeywordId = ref(null);

onMounted(async () => {
  try {
    const data = await organizationStore.fetchOrganization(route.params.id);
    organization.value = { ...data };
    originalOrganization.value = { ...data };
    await keywordStore.fetchKeywords(route.params.id);
  } catch (error) {
    console.error('Error fetching organization:', error);
  } finally {
    isLoading.value = false;
  }
});

const hasChanges = computed(() => {
  return organization.value.name !== originalOrganization.value.name ||
         organization.value.website !== originalOrganization.value.website ||
         organization.value.founded !== originalOrganization.value.founded ||
         organization.value.employee_count !== originalOrganization.value.employee_count ||
         organization.value.is_competitor !== originalOrganization.value.is_competitor;
});

const showKeywordDetails = (keyword) => {
  selectedKeyword.value = keyword;
  selectedKeywordId.value = keyword.id;
  isKeywordDetailSheetOpen.value = true;
};

const addKeyword = (keyword) => {
  keyword.organization_id = route.params.id;
  return keyword;
};

const updateOrganization = async () => {
  isSubmitting.value = true;
  try {
    await organizationStore.updateOrganization(route.params.id, organization.value);
    router.push({ name: 'organizations.index' });
  } catch (error) {
    console.error('Error updating organization:', error);
  } finally {
    isSubmitting.value = false;
  }
};

const cancelEdit = () => {
  router.push({ name: 'organizations.index' });
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8">
      <div class="flex justify-between items-center mb-8">
        <div class="flex items-center gap-3">
			<h1 class="text-2xl font-bold">{{ organization.name }}</h1>
			<span v-if="organization.is_competitor" class="bg-neutral-200 text-neutral-800 text-xs px-2 py-1 rounded">Competitor</span>
		</div>
        <Button @click="cancelEdit" variant="neutral">
          Back
        </Button>
      </div>

      <!-- Loading state -->
      <div v-if="isLoading" class="flex justify-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
      </div>

      <!-- Error state -->
      <div v-else-if="organizationStore.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        {{ organizationStore.error }}
      </div>

      <div v-else class="flex flex-col md:flex-row gap-12">
        <!-- Left column - Keywords section -->
        <div class="w-full md:w-2/3">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Keywords</h2>

            <div class="flex gap-2">
              <button
                @click="isGenerateKeywordsModalOpen = true"
                class="flex items-center gap-2 px-3 py-1.5 bg-neutral-800 text-white rounded-md text-xs font-medium hover:bg-neutral-700 transition-colors cursor-pointer"
              >
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
					<path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
				</svg>
                <span>Generate keywords</span>
              </button>
              <button
                @click="isKeywordCreateModalOpen = true"
                class="px-3 py-1.5 bg-white text-neutral-800 border border-neutral-400 rounded-md text-xs font-medium hover:bg-neutral-100 transition-colors cursor-pointer"
              >
                Add a keyword
              </button>
            </div>
          </div>

          <div v-if="keywordStore.isLoading" class="flex justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-neutral-800"></div>
          </div>

          <div v-else-if="keywordStore.keywords.length === 0" class="text-center py-12 border border-neutral-200 rounded-xl">
            <div class="text-neutral-400 text-sm">No keywords yet</div>
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
                  @click.stop="keywordStore.deleteKeyword(route.params.id, keyword.id)"
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

        <!-- Right column - Organization details -->
        <div class="w-full md:w-1/3">
		  <h2 class="text-xl font-semibold mb-2">Edit {{ organization.is_competitor ? 'competitor' : 'your organization' }}</h2>
          <form @submit.prevent="updateOrganization" class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Name</label>
            <input
              v-model="organization.name"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter organization name"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Website</label>
            <input
              v-model="organization.website"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter website URL"
            />
          </div>

          <!-- <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Founded</label>
            <input
              v-model="organization.founded"
              type="text"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter founding year"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Employee Count</label>
            <select
              v-model="organization.employee_count"
              class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Select employee count</option>
              <option value="1-10">1-10</option>
              <option value="11-50">11-50</option>
              <option value="51-200">51-200</option>
              <option value="201-500">201-500</option>
              <option value="501-1000">501-1000</option>
              <option value="1000+">1000+</option>
            </select>
          </div> -->

          <div class="pt-4">
            <Button
              v-if="hasChanges"
              type="submit"
              :disabled="isSubmitting"
              variant="dark"
            >
              {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
            </Button>
          </div>
          </form>
        </div>

      </div>
    </div>
  </DefaultLayout>

  <!-- Keyword Modal -->
  <KeywordCreateModal
    :is-open="isKeywordCreateModalOpen"
    @close="isKeywordCreateModalOpen = false"
    @create="addKeyword"
  />

  <!-- Generate Keywords Modal -->
  <GenerateKeywordsModal
    :is-open="isGenerateKeywordsModalOpen"
    @close="isGenerateKeywordsModalOpen = false"
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
</template>
