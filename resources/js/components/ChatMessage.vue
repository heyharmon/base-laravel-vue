<script setup>
const props = defineProps({
    message: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <div :class="['flex', props.message.role === 'user' ? 'justify-end' : 'justify-start']">
        <div :class="['max-w-[80%] rounded-lg p-3', 
                    props.message.role === 'user' 
                    ? 'bg-neutral-200 dark:bg-neutral-800' 
                    : 'bg-neutral-300 dark:bg-neutral-700']">
            {{ props.message.content }}
        </div>
    </div>
</template>
