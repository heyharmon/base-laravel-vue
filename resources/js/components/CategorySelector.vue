<script setup>
import { ref, computed, watch } from 'vue';
import { useCategoryStore } from '@/stores/categoryStore';

const props = defineProps({
  modelValue: [Number, String],
  placeholder: {
    type: String,
    default: 'Search or create category...'
  }
});

const emit = defineEmits(['update:modelValue']);

const categoryStore = useCategoryStore();
const searchTerm = ref('');
const isOpen = ref(false);
const isCreating = ref(false);

const filteredCategories = computed(() => {
  if (!searchTerm.value) {
    return categoryStore.categories;
  }
  return categoryStore.searchedCategories;
});

const selectedCategory = computed(() => {
  return categoryStore.categories.find(c => c.id === props.modelValue);
});

const canCreateNew = computed(() => {
  return searchTerm.value &&
         !filteredCategories.value.some(c =>
           c.name.toLowerCase() === searchTerm.value.toLowerCase()
         );
});

watch(searchTerm, async (newValue) => {
  if (newValue) {
    await categoryStore.searchCategories(newValue);
  }
});

const selectCategory = (category) => {
  emit('update:modelValue', category.id);
  searchTerm.value = category.name;
  isOpen.value = false;
};

const createAndSelectCategory = async () => {
  if (!searchTerm.value || isCreating.value) return;

  isCreating.value = true;
  try {
    const newCategory = await categoryStore.createCategory({
      name: searchTerm.value
    });
    selectCategory(newCategory);
  } catch (error) {
    console.error('Error creating category:', error);
  } finally {
    isCreating.value = false;
  }
};

const clearSelection = () => {
  emit('update:modelValue', null);
  searchTerm.value = '';
  isOpen.value = false;
};

if (selectedCategory.value) {
  searchTerm.value = selectedCategory.value.name;
}
</script>

<template>
  <div class="relative">
    <div class="relative">
      <input
        v-model="searchTerm"
        type="text"
        :placeholder="placeholder"
        class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        @focus="isOpen = true"
        @blur="setTimeout(() => isOpen = false, 200)"
      />
      <button
        v-if="selectedCategory"
        @click="clearSelection"
        class="absolute right-2 top-2 text-neutral-400 hover:text-neutral-600"
      >
        ×
      </button>
    </div>

    <div
      v-if="isOpen"
      class="absolute z-10 w-full mt-1 bg-white border border-neutral-300 rounded-md shadow-lg max-h-60 overflow-y-auto"
    >
      <div
        v-for="category in filteredCategories"
        :key="category.id"
        @click="selectCategory(category)"
        class="px-3 py-2 hover:bg-neutral-100 cursor-pointer flex justify-between items-center"
      >
        <span>{{ category.name }}</span>
        <span class="text-sm text-neutral-500">{{ category.transactions_count || 0 }}</span>
      </div>

      <div
        v-if="canCreateNew"
        @click="createAndSelectCategory"
        class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-t border-neutral-200 text-blue-600"
      >
        <span v-if="isCreating">Creating...</span>
        <span v-else>+ Create "{{ searchTerm }}"</span>
      </div>

      <div
        v-if="filteredCategories.length === 0 && !canCreateNew"
        class="px-3 py-2 text-neutral-500 text-sm"
      >
        No categories found
      </div>
    </div>
  </div>
</template>
