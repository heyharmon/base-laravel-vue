<script setup>
import AppNav from '@/components/AppNav.vue'
</script>

<template>
	<div class="h-screen bg-white">
		<AppNav />

		<main class="flex flex-col md:flex-row h-[calc(100vh-52px)]">
			<!-- Left column (fixed width) -->
			<div class="w-full md:w-1/3 h-full border-r border-gray-200 flex flex-col">
				<slot name="left-column" />
			</div>

			<!-- Right column (flexible width) -->
			<div class="w-full md:w-2/3 h-full">
				<slot name="right-column" />
			</div>
		</main>
	</div>
</template>
