<script setup>
import { ref, onMounted, computed } from "vue";
import { useRoute, useRouter } from "vue-router";
import DefaultLayout from "@/layouts/DefaultLayout.vue";
import { useWebsiteStore } from "@/stores/websiteStore";

const route = useRoute();
const router = useRouter();
const store = useWebsiteStore();

const website = ref(null);
const results = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const data = await store.fetchWebsite(route.params.id);
        website.value = data;
        results.value = data.results || [];
    } catch (error) {
        console.error("Failed to load website:", error);
        router.push({ name: "websites.index" });
    } finally {
        loading.value = false;
    }
});

const groupedResults = computed(() => {
    const grouped = {};
    results.value.forEach((result) => {
        const userAgent = result.user_agent?.name || "Unknown";
        if (!grouped[userAgent]) {
            grouped[userAgent] = [];
        }
        grouped[userAgent].push(result);
    });
    return grouped;
});

async function runCheck() {
    await store.checkWebsite(website.value.id);
    // Refresh results after a short delay
    setTimeout(async () => {
        const data = await store.fetchWebsite(website.value.id);
        results.value = data.results || [];
    }, 2000);
}

function formatDate(date) {
    if (!date) return "Never";
    return new Date(date).toLocaleString();
}

function getStatusClass(accessible) {
    return accessible ? "text-green-600" : "text-red-600";
}

function getStatusText(accessible) {
    return accessible ? "✓ Yes" : "✗ No";
}
</script>

<template>
    <DefaultLayout>
        <div class="p-6">
            <div v-if="loading" class="text-center py-8">
                <div class="text-neutral-600">Loading...</div>
            </div>

            <div v-else-if="website">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h1 class="text-2xl font-bold">{{ website.domain }}</h1>
                        <button
                            @click="runCheck"
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        >
                            Run Check
                        </button>
                    </div>

                    <div class="bg-neutral-50 p-4 rounded-lg">
                        <div
                            class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm"
                        >
                            <div>
                                <span class="text-neutral-600">Base URL:</span>
                                <a
                                    :href="website.base_url"
                                    target="_blank"
                                    class="ml-2 text-blue-600 hover:underline"
                                >
                                    {{ website.base_url }}
                                </a>
                            </div>
                            <div>
                                <span class="text-neutral-600">Protocol:</span>
                                <span class="ml-2">{{
                                    website.protocol || "https"
                                }}</span>
                            </div>
                            <div>
                                <span class="text-neutral-600"
                                    >Last Checked:</span
                                >
                                <span class="ml-2">{{
                                    formatDate(website.last_checked_at)
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div v-if="results.length > 0">
                    <h2 class="text-xl font-semibold mb-4">
                        Accessibility Results
                    </h2>

                    <!-- Results by User Agent -->
                    <div class="space-y-6">
                        <div
                            v-for="(agentResults, agentName) in groupedResults"
                            :key="agentName"
                            class="bg-white border border-neutral-200 rounded-lg p-6"
                        >
                            <h3 class="text-lg font-medium mb-4">
                                {{ agentName }}
                            </h3>

                            <div class="space-y-4">
                                <div
                                    v-for="result in agentResults"
                                    :key="result.id"
                                    class="border-l-4 pl-4"
                                    :class="{
                                        'border-green-500':
                                            result.http_accessible &&
                                            result.robots_txt_allowed,
                                        'border-red-500':
                                            !result.http_accessible ||
                                            !result.robots_txt_allowed,
                                        'border-yellow-500':
                                            result.http_accessible !==
                                            result.robots_txt_allowed,
                                    }"
                                >
                                    <div
                                        class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                    >
                                        <div>
                                            <h4
                                                class="font-medium text-sm text-neutral-700 mb-2"
                                            >
                                                Access Status
                                            </h4>
                                            <div class="space-y-1 text-sm">
                                                <div class="flex items-center">
                                                    <span
                                                        class="text-neutral-600 w-32"
                                                        >HTTP Accessible:</span
                                                    >
                                                    <span
                                                        :class="
                                                            getStatusClass(
                                                                result.http_accessible
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            getStatusText(
                                                                result.http_accessible
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center">
                                                    <span
                                                        class="text-neutral-600 w-32"
                                                        >Robots.txt
                                                        Allowed:</span
                                                    >
                                                    <span
                                                        :class="
                                                            getStatusClass(
                                                                result.robots_txt_allowed
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            getStatusText(
                                                                result.robots_txt_allowed
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center">
                                                    <span
                                                        class="text-neutral-600 w-32"
                                                        >Firewall
                                                        Detected:</span
                                                    >
                                                    <span
                                                        :class="
                                                            getStatusClass(
                                                                !result.firewall_detected
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            result.firewall_detected
                                                                ? "✗ Yes"
                                                                : "✓ No"
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <h4
                                                class="font-medium text-sm text-neutral-700 mb-2"
                                            >
                                                Technical Details
                                            </h4>
                                            <div class="space-y-1 text-sm">
                                                <div
                                                    v-if="
                                                        result.http_status_code
                                                    "
                                                >
                                                    <span
                                                        class="text-neutral-600"
                                                        >HTTP Status:</span
                                                    >
                                                    <span class="ml-2">{{
                                                        result.http_status_code
                                                    }}</span>
                                                </div>
                                                <div
                                                    v-if="
                                                        result.response_time_ms
                                                    "
                                                >
                                                    <span
                                                        class="text-neutral-600"
                                                        >Response Time:</span
                                                    >
                                                    <span class="ml-2"
                                                        >{{
                                                            result.response_time_ms
                                                        }}ms</span
                                                    >
                                                </div>
                                                <div v-if="result.waf_type">
                                                    <span
                                                        class="text-neutral-600"
                                                        >WAF Type:</span
                                                    >
                                                    <span class="ml-2">{{
                                                        result.waf_type
                                                    }}</span>
                                                </div>
                                                <div
                                                    v-if="
                                                        result.blocking_method
                                                    "
                                                >
                                                    <span
                                                        class="text-neutral-600"
                                                        >Blocking Method:</span
                                                    >
                                                    <span class="ml-2">{{
                                                        result.blocking_method
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="result.error_message"
                                        class="mt-3"
                                    >
                                        <span class="text-sm text-red-600"
                                            >Error:
                                            {{ result.error_message }}</span
                                        >
                                    </div>

                                    <div class="mt-2 text-xs text-neutral-500">
                                        Checked:
                                        {{ formatDate(result.checked_at) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-12 bg-neutral-50 rounded-lg">
                    <p class="text-neutral-600 mb-4">
                        No accessibility results yet.
                    </p>
                    <button
                        @click="runCheck"
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                    >
                        Run First Check
                    </button>
                </div>
            </div>
        </div>
    </DefaultLayout>
</template>
