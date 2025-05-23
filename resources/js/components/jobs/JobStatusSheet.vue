<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useJobStatusStore } from '@/stores/jobStatusStore';
import Sheet from '@/components/ui/Sheet.vue';
import JobStatus from '@/components/jobs/JobStatus.vue';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  }
});

const emit = defineEmits(['close']);

const jobStatusStore = useJobStatusStore();

const closeSheet = () => {
  emit('close');
};

onMounted(() => {
  // Initial fetch of jobs
  jobStatusStore.fetchTeamJobs();
});

onBeforeUnmount(() => {
  // Clean up any auto-refresh when sheet is closed
  jobStatusStore.stopAutoRefresh();
});
</script>

<template>
  <Sheet
    :is-open="isOpen"
    @close="closeSheet"
    position="right"
    title="Runs"
  >
    <div class="w-full xl:w-[800px] md:p-4">
      <JobStatus
        :autoRefresh="true"
        :refreshInterval="1000"
      />
    </div>
  </Sheet>
</template>
