<script setup>
// Default layout component
</script>

<template>
  <div class="min-h-screen bg-white">
    <header class="border-b border-neutral-200">
      <div class="max-w-7xl p-4 mx-auto flex justify-between items-center">
        <router-link to="/dashboard" class="text-xl font-semibold">Agent Mentions</router-link>
        <nav class="flex space-x-4">
          <router-link to="/dashboard" class="px-3 py-1.5 text-sm rounded-md transition-colors hover:bg-neutral-100">Dashboard</router-link>
          <router-link to="/analytics" class="px-3 py-1.5 text-sm rounded-md transition-colors hover:bg-neutral-100">Analytics</router-link>
        </nav>
      </div>
    </header>
    
    <main class="max-w-7xl mx-auto px-4">
      <slot />
    </main>
  </div>
</template>
