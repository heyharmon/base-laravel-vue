<script setup>
import DefaultLayout from "@/layouts/DefaultLayout.vue";
</script>

<template>
    <DefaultLayout>
        <div class="flex h-[calc(100vh-4rem)]">
            <!-- Left column -->
            <div class="w-1/4 pr-4 py-4 border-r border-neutral-200 h-full">
                Left columns
            </div>

            <!-- Right column -->
            <div class="w-3/4 pl-4 py-4 flex flex-col">Right column</div>
        </div>
    </DefaultLayout>
</template>
