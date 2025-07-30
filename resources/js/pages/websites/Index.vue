<script setup>
import { ref, onMounted } from "vue";
import DefaultLayout from "@/layouts/DefaultLayout.vue";
import { useWebsiteStore } from "@/stores/websiteStore";

const store = useWebsiteStore();
const newDomain = ref("");
const newUrl = ref("");
const results = ref([]);

onMounted(() => {
    store.fetchWebsites();
});

async function addSite() {
    await store.addWebsite({ domain: newDomain.value, base_url: newUrl.value });
    newDomain.value = "";
    newUrl.value = "";
}

async function runCheck(id) {
    await store.checkWebsite(id);
    results.value = await store.loadResults(id);
}
</script>

<template>
    <DefaultLayout>
        <div class="p-4">
            <h2 class="text-xl mb-2">Websites</h2>
            <div class="mb-4 flex gap-2">
                <input
                    v-model="newDomain"
                    placeholder="Domain"
                    class="border px-2"
                />
                <input
                    v-model="newUrl"
                    placeholder="Base URL"
                    class="border px-2"
                />
                <button @click="addSite" class="bg-blue-500 text-white px-2">
                    Add
                </button>
            </div>
            <ul>
                <li
                    v-for="site in store.websites"
                    :key="site.id"
                    class="mb-2 flex items-center gap-2"
                >
                    <router-link
                        :to="{ name: 'websites.show', params: { id: site.id } }"
                        class="text-blue-600 hover:underline"
                    >
                        {{ site.domain }}
                    </router-link>
                    <button
                        @click="runCheck(site.id)"
                        class="text-sm underline cursor-pointer"
                    >
                        Check
                    </button>
                </li>
            </ul>
            <div v-if="results.length" class="mt-4">
                <h3 class="font-bold">Results</h3>
                <ul>
                    <li v-for="r in results" :key="r.id" class="text-sm">
                        {{ r.user_agent }} - robots allow:
                        {{ r.robots_txt_allowed }}, http accessible:
                        {{ r.http_accessible }}
                    </li>
                </ul>
            </div>
        </div>
    </DefaultLayout>
</template>
