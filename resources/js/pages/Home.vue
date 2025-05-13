<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import DefaultLayout from '@/layouts/DefaultLayout.vue';

const conversation = ref(null);

onMounted(async () => {
    conversation.value = await api.get(`/conversation/1`);
});
</script>

<template>
  <DefaultLayout>
    <h2 class="text-2xl font-semibold mb-4">Conversation</h2>

    <ul v-if="conversation" class="space-y-2">
        <li v-for="(chat, index) in conversation.chats" :key="index" class="flex items-center">
            <span>{{ chat.text }}</span>
        </li>
    </ul>
  </DefaultLayout>
</template>
