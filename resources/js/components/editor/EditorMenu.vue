<template>
	<div class="flex items-center gap-2 p-2 mr-6 rounded-xl bg-neutral-100">
		<button @click="$emit('command', 'bold')" :class="{ 'bg-neutral-200': activeCommands?.bold }" class="p-1 rounded hover:bg-neutral-200" title="Bold">
			<svg
				fill="none"
				height="16"
				stroke="currentColor"
				stroke-linecap="round"
				stroke-linejoin="round"
				stroke-width="2"
				viewBox="0 0 24 24"
				width="16"
				xmlns="http://www.w3.org/2000/svg"
			>
				<path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
				<path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
			</svg>
		</button>

		<button
			@click="$emit('command', 'italic')"
			:class="{ 'bg-neutral-200': activeCommands?.italic }"
			class="p-1 rounded hover:bg-neutral-200"
			title="Italic"
		>
			<svg
				fill="none"
				height="16"
				stroke="currentColor"
				stroke-linecap="round"
				stroke-linejoin="round"
				stroke-width="2"
				viewBox="0 0 24 24"
				width="16"
				xmlns="http://www.w3.org/2000/svg"
			>
				<line x1="19" x2="10" y1="4" y2="4"></line>
				<line x1="14" x2="5" y1="20" y2="20"></line>
				<line x1="15" x2="9" y1="4" y2="20"></line>
			</svg>
		</button>

		<template v-for="level in [1, 2, 3, 4]" :key="`h${level}`">
			<button
				@click="$emit('command', 'heading', { level })"
				:class="{ 'bg-neutral-200': activeCommands?.heading?.level === level }"
				class="p-1 rounded hover:bg-neutral-200"
				:title="`Heading ${level}`"
			>
				H{{ level }}
			</button>
		</template>

		<button
			@click="$emit('command', 'bulletList')"
			:class="{ 'bg-neutral-200': activeCommands?.bulletList }"
			class="p-1 rounded hover:bg-neutral-200"
			title="Bullet List"
		>
			<svg
				fill="none"
				height="16"
				stroke="currentColor"
				stroke-linecap="round"
				stroke-linejoin="round"
				stroke-width="2"
				viewBox="0 0 24 24"
				width="16"
				xmlns="http://www.w3.org/2000/svg"
			>
				<line x1="8" x2="21" y1="6" y2="6"></line>
				<line x1="8" x2="21" y1="12" y2="12"></line>
				<line x1="8" x2="21" y1="18" y2="18"></line>
				<line x1="3" x2="3.01" y1="6" y2="6"></line>
				<line x1="3" x2="3.01" y1="12" y2="12"></line>
				<line x1="3" x2="3.01" y1="18" y2="18"></line>
			</svg>
		</button>

		<button
			@click="$emit('command', 'orderedList')"
			:class="{ 'bg-neutral-200': activeCommands?.orderedList }"
			class="p-1 rounded hover:bg-neutral-200"
			title="Numbered List"
		>
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				width="16"
				height="16"
				fill="none"
				stroke="currentColor"
				stroke-width="2"
				stroke-linecap="round"
				stroke-linejoin="round"
			>
				<line x1="10" y1="6" x2="21" y2="6"></line>
				<line x1="10" y1="12" x2="21" y2="12"></line>
				<line x1="10" y1="18" x2="21" y2="18"></line>
				<path d="M4 6h1v4"></path>
				<path d="M4 10h2"></path>
				<path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path>
			</svg>
		</button>

		<button
			@click="$emit('command', 'blockquote')"
			:class="{ 'bg-neutral-200': activeCommands?.blockquote }"
			class="p-1 rounded hover:bg-neutral-200"
			title="Blockquote"
		>
			<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24">
				<path
					fill="currentColor"
					d="m6.585 17.308l2.396-4.174q-.173.097-.404.135t-.461.039q-1.4 0-2.354-.972q-.954-.971-.954-2.336q0-1.4.954-2.354t2.354-.954q1.364 0 2.336.954t.971 2.347q0 .486-.118.906t-.336.793l-3.238 5.616zm8.769 0l2.396-4.173q-.173.096-.404.134t-.461.039q-1.4 0-2.354-.972q-.954-.971-.954-2.336q0-1.42.954-2.363t2.354-.945q1.364 0 2.336.954t.971 2.347q0 .486-.118.906t-.335.793L16.5 17.308zm-7.238-5.116q.913 0 1.552-.639q.64-.64.64-1.553t-.64-1.553t-1.552-.64q-.914 0-1.553.64q-.64.64-.64 1.553t.64 1.553t1.553.64m8.769 0q.913 0 1.553-.64T19.077 10t-.64-1.553t-1.552-.64t-1.553.64t-.64 1.553t.64 1.553t1.553.64M8.115 10"
				/>
			</svg>
		</button>

		<div class="h-5 w-px bg-neutral-300 mx-1"></div>

		<button @click="$emit('command', 'undo')" class="p-1 rounded hover:bg-neutral-200" title="Undo">
			<svg
				fill="none"
				height="16"
				stroke="currentColor"
				stroke-linecap="round"
				stroke-linejoin="round"
				stroke-width="2"
				viewBox="0 0 24 24"
				width="16"
				xmlns="http://www.w3.org/2000/svg"
			>
				<path d="M3 7v6h6"></path>
				<path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path>
			</svg>
		</button>

		<button @click="$emit('command', 'redo')" class="p-1 rounded hover:bg-neutral-200" title="Redo">
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				width="16"
				height="16"
				fill="none"
				stroke="currentColor"
				stroke-width="2"
				stroke-linecap="round"
				stroke-linejoin="round"
			>
				<path d="M21 7v6h-6"></path>
				<path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"></path>
			</svg>
		</button>
	</div>
</template>

<script setup>
defineProps({
	activeCommands: {
		type: Object,
		default: () => ({})
	}
})

defineEmits(['command'])
</script>
