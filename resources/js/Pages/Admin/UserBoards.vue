<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();

const boards = ref(page.props.boards);
const user = ref(page.props.user);

// This is the admin viewing the page, not necessarily props.user
const viewingAdmin = computed(() => page.props.auth.user);

// Helper to check if the displayed user is the owner of a board
const isOwner = (board) => {
    // Assuming board object has an owner_id or a nested owner object with id
    if (board.owner_id) {
        return board.owner_id === user.value.id; // Corrected to use user.value.id
    }
    if (board.owner && board.owner.id) {
        return board.owner.id === user.value.id; // Corrected to use user.value.id
    }
    return false; // Fallback if owner information is not in expected format
};

const goBack = () => {
    window.history.back();
};

</script>

<template>
    <Head :title="`Boards for ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link
                    href="#"
                    @click.prevent="goBack"
                    class="mr-4 text-gray-600 hover:text-gray-800"
                    aria-label="Go back"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Boards for {{ user.name }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div v-if="boards && boards.length > 0" class="space-y-4">
                            <div
                                v-for="board in boards"
                                :key="board.id"
                                class="border border-gray-200 p-4 rounded-lg hover:shadow-md transition-shadow"
                            >
                                <div class="flex justify-between items-center"> 
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            {{ board.title }}
                                        </h3>
                                        <p v-if="board.description" class="text-sm text-gray-600 mt-1 truncate max-w-xl">
                                            {{ board.description }}
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <span v-if="typeof board.user_count !== 'undefined'">
                                                {{ board.user_count }} user(s)
                                            </span>
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end space-y-2 sm:flex-row sm:items-center sm:space-y-0 sm:space-x-3 mt-2 sm:mt-0">
                                        <span
                                            v-if="isOwner(board)"
                                            class="px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-full"
                                        >
                                            Owner
                                        </span>
                                        <Link
                                            :href="`/boards/${board.id}`"
                                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 whitespace-nowrap"
                                        >
                                            View Board
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-500 py-8">
                            <p>{{ user.name }} is not part of any boards yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Add any specific styles if needed */
</style>