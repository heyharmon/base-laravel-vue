<script setup>
// Simple loading indicator component
</script>

<template>
    <div class="flex justify-start">
        <div class="bg-neutral-300 dark:bg-neutral-700 rounded-lg p-3 flex items-center space-x-2">
            <div class="w-2 h-2 rounded-full bg-neutral-500 animate-pulse"></div>
            <div class="w-2 h-2 rounded-full bg-neutral-500 animate-pulse" style="animation-delay: 0.2s"></div>
            <div class="w-2 h-2 rounded-full bg-neutral-500 animate-pulse" style="animation-delay: 0.4s"></div>
        </div>
    </div>
</template>
