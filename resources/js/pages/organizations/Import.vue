<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useApifyImportStore } from '@/stores/apifyImportStore';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

const router = useRouter();
const apifyImportStore = useApifyImportStore();

const form = ref({
  search_term: '',
  location: '',
  max_places: 100,
  min_rating: 3,
  skip_closed: true,
});

const isSubmitting = ref(false);

onMounted(async () => {
  await apifyImportStore.fetchImports();
});

const startImport = async () => {
  if (!form.value.search_term || !form.value.location) {
    alert('Please fill in both search term and location');
    return;
  }
  isSubmitting.value = true;
  try {
    await apifyImportStore.startImport(form.value);
    alert('Import started successfully! You can monitor the progress below.');
    form.value = {
      search_term: '',
      location: '',
      max_places: 100,
      min_rating: 3,
      skip_closed: true,
    };
    await apifyImportStore.fetchImports();
  } catch (error) {
    alert('Failed to start import: ' + (error.message || 'Unknown error'));
  } finally {
    isSubmitting.value = false;
  }
};

const getStatusBadgeClass = (status) => {
  switch (status) {
    case 'SUCCEEDED':
      return 'bg-green-100 text-green-800';
    case 'FAILED':
    case 'ABORTED':
      return 'bg-red-100 text-red-800';
    case 'RUNNING':
      return 'bg-blue-100 text-blue-800';
    default:
      return 'bg-neutral-100 text-neutral-800';
  }
};

const refreshImports = async () => {
  await apifyImportStore.fetchImports();
};
</script>

<template>
  <DefaultLayout>
    <div class="container mx-auto py-8 px-4 max-w-4xl">
      <div class="mb-8">
        <router-link to="/organizations" class="text-blue-600 hover:text-blue-800 text-sm mb-2 inline-block">
          ← Back to Organizations
        </router-link>
        <h1 class="text-2xl font-bold">Import Organizations from Google Maps</h1>
        <p class="text-neutral-600 mt-2">
          Use this tool to scrape organizations from Google Maps and automatically import them into your CRM.
        </p>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Start New Import</h2>
        
        <form @submit.prevent="startImport" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Search Term *</label>
              <Input 
                v-model="form.search_term" 
                required 
                placeholder="e.g., insurance broker" 
              />
              <p class="text-xs text-neutral-500 mt-1">What type of business to search for</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Location *</label>
              <Input 
                v-model="form.location" 
                required 
                placeholder="e.g., Utah" 
              />
              <p class="text-xs text-neutral-500 mt-1">City, state, or region to search in</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Max Places</label>
              <Input 
                v-model="form.max_places" 
                type="number" 
                min="1" 
                max="1000" 
                placeholder="100" 
              />
              <p class="text-xs text-neutral-500 mt-1">Maximum number of organizations to import</p>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-neutral-700 mb-1">Min Rating</label>
              <Input 
                v-model="form.min_rating" 
                type="number" 
                step="0.1" 
                min="0" 
                max="5" 
                placeholder="3" 
              />
              <p class="text-xs text-neutral-500 mt-1">Minimum star rating (0-5)</p>
            </div>
          </div>
          
          <div>
            <label class="flex items-center">
              <input 
                v-model="form.skip_closed" 
                type="checkbox" 
                class="rounded border-neutral-300 text-blue-600 focus:ring-blue-500"
              >
              <span class="ml-2 text-sm text-neutral-700">Skip closed places</span>
            </label>
          </div>
          
          <div class="flex justify-end">
            <Button 
              type="submit" 
              :disabled="isSubmitting"
              class="bg-green-600 hover:bg-green-700 text-white"
            >
              {{ isSubmitting ? 'Starting Import...' : 'Start Import' }}
            </Button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-lg shadow-sm border border-neutral-200">
        <div class="px-6 py-4 border-b border-neutral-200 flex justify-between items-center">
          <h2 class="text-xl font-semibold">Import History</h2>
          <Button @click="refreshImports" variant="outline" size="sm">
            Refresh
          </Button>
        </div>
        
        <div v-if="apifyImportStore.isLoading" class="flex justify-center py-8">
          <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-neutral-900"></div>
        </div>
        
        <div v-else-if="apifyImportStore.imports.length === 0" class="p-6 text-center text-neutral-500">
          No imports yet. Start your first import above!
        </div>
        
        <div v-else class="divide-y divide-neutral-200">
          <div 
            v-for="importRun in apifyImportStore.imports" 
            :key="importRun.id"
            class="p-6"
          >
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <div class="flex items-center space-x-2 mb-2">
                  <span 
                    :class="getStatusBadgeClass(importRun.status)"
                    class="px-2 py-1 rounded-full text-xs font-medium"
                  >
                    {{ importRun.status }}
                  </span>
                  <span class="text-sm text-neutral-500">
                    {{ new Date(importRun.created_at).toLocaleString() }}
                  </span>
                </div>
                
                <div class="text-sm text-neutral-900 mb-2">
                  <strong>Search:</strong> "{{ importRun.input_data.searchStringsArray[0] }}" in {{ importRun.input_data.locationQuery }}
                </div>
                
                <div class="text-sm text-neutral-600 space-x-4">
                  <span>Max: {{ importRun.input_data.maxPlaces }}</span>
                  <span>Min Rating: {{ importRun.input_data.minStars }}</span>
                  <span v-if="importRun.items_processed > 0">
                    Processed: {{ importRun.items_processed }}
                  </span>
                  <span v-if="importRun.items_imported > 0">
                    Imported: {{ importRun.items_imported }}
                  </span>
                  <span v-if="importRun.items_updated > 0">
                    Updated: {{ importRun.items_updated }}
                  </span>
                  <span v-if="importRun.items_skipped > 0">
                    Skipped: {{ importRun.items_skipped }}
                  </span>
                </div>
                
                <div v-if="importRun.error_message" class="text-sm text-red-600 mt-2">
                  Error: {{ importRun.error_message }}
                </div>
              </div>
              
              <div v-if="importRun.status === 'RUNNING'" class="ml-4">
                <div class="w-32 bg-neutral-200 rounded-full h-2">
                  <div 
                    class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                    :style="{ width: importRun.progress_percentage + '%' }"
                  ></div>
                </div>
                <div class="text-xs text-neutral-500 mt-1 text-center">
                  {{ importRun.progress_percentage }}%
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
