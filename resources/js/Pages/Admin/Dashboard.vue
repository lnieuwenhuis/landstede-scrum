<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const page = usePage();

const showMessage = ref(page.props.justLoggedIn ?? false);
const boards = ref(page.props.boards ?? []); 
const boardCount = page.props.boardCount?? 0;
const showDeleteConfirmation = ref(false);
const boardToDelete = ref(null);

const searchInput = ref('');
const searchResults = ref({ boards: [], users: [] });
const isSearching = ref(false);
const searchTimeout = ref(null);

const hideMessage = () => {
    showMessage.value = false;
    axios.post(`/api/disableLoginMessage`);
};

const toggleDeleteConfirmation = (boardId = null) => {
    showDeleteConfirmation.value = !showDeleteConfirmation.value;
    boardToDelete.value = boardId;
};

const handleDelete = () => {
    axios.post(`/api/boards/deleteBoard`, { board_id: boardToDelete.value })
        .then(response => {
            if (response.data.message) {
                boards.value = boards.value.filter(board => board.id !== boardToDelete.value);
                toggleDeleteConfirmation();
                toast.success(response.data.message);
            } else {
                toast.error(response.data.error);
            }
        })
        .catch(error => {
            console.error('Error deleting board:', error);
        });
}

const debouncedSearch = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }
    searchTimeout.value = setTimeout(() => {
        performSearch();
    }, 300);
};

watch(searchInput, (newValue) => {
    if (newValue.trim().length > 1 || newValue.trim().length === 0) { 
        debouncedSearch();
    } else {
        searchResults.value = { boards: [], users: [] };
    }
});

const performSearch = async () => {
    if (!searchInput.value.trim()) {
        searchResults.value = { boards: [], users: [] };
        return;
    }

    isSearching.value = true;
    try {
        const response = await axios.post('/api/admin/search-all', { 
            searchTerm: searchInput.value 
        });
        
        searchResults.value = {
            boards: response.data.boards || [],
            users: response.data.users || []
        };
    } catch (error) {
        console.error('Error searching:', error);
        toast.error('Failed to perform search');
        searchResults.value = { boards: [], users: [] }; 
    } finally {
        isSearching.value = false;
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="showMessage" class="overflow-hidden bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 text-gray-900 flex justify-between items-center">
                        <span>You're logged in!</span>
                        <button 
                            @click="hideMessage" 
                            class="text-gray-500 hover:text-gray-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <!-- Changed title, removed toggle, kept count -->
                            <h3 class="text-lg font-semibold text-gray-900">Search Boards & Users</h3>
                            <div class="flex items-center">
                                <span class="text-sm text-gray-500 mr-4">({{ boardCount }} total boards)</span>
                                <a
                                    href="/boards/create"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                >
                                    Create Board
                                </a>
                            </div>
                        </div>
                        
                        <!-- Search Input -->
                        <div class="relative mb-6">
                            <input 
                                v-model="searchInput"
                                type="text" 
                                placeholder="Search by board title, user name or email..." 
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                            <div v-if="isSearching" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Search Results Area -->
                        <div v-if="!isSearching && searchInput.trim() && !searchResults.boards.length && !searchResults.users.length" class="text-center text-gray-500 py-4">
                            No results found for "{{ searchInput }}".
                        </div>

                        <div v-if="searchResults.boards.length > 0 || searchResults.users.length > 0">
                            <!-- Board Results -->
                            <div v-if="searchResults.boards.length > 0" class="mb-6">
                                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b">Boards ({{ searchResults.boards.length }})</h4>
                                <div class="space-y-3">
                                    <div v-for="board in searchResults.boards" :key="'board-' + board.id" 
                                        class="border border-gray-200 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                                        <div class="flex justify-between items-center">
                                            <div class="flex-1 min-w-0 mr-4">
                                                <h5 class="font-medium text-gray-900 truncate">{{ board.title }}</h5>
                                                <p class="text-sm text-gray-500 mt-1 truncate">{{ board.description }}</p>
                                            </div>
                                            <div class="flex space-x-2 flex-shrink-0">
                                                <a :href="`/boards/${board.id}`" 
                                                    class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 shadow-sm text-center">
                                                    View
                                                </a>
                                                <button @click="toggleDeleteConfirmation(board.id)" 
                                                        class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-md hover:bg-red-700 shadow-sm text-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- User Results -->
                            <div v-if="searchResults.users.length > 0">
                                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b">Users ({{ searchResults.users.length }})</h4>
                                <div class="space-y-3">
                                    <div v-for="user in searchResults.users" :key="'user-' + user.id" 
                                        class="border border-gray-200 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h5 class="font-medium text-gray-900 truncate">{{ user.name }}</h5>
                                                <p class="text-sm text-gray-500 truncate">{{ user.email }}</p>
                                            </div>
                                            <a :href="`/admin/users/${user.id}/boards`" 
                                               class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 shadow-sm">
                                                View Boards
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Search Results Area -->
                    </div>
                </div>
                
                <!-- Vacation Management Section -->
                <div class="bg-white shadow-sm sm:rounded-lg mt-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Administration</h3>
                        <div class="space-y-4">
                            <a href="/admin/vacations" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <div class="mr-4 bg-blue-100 p-3 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">Manage Vacations</h4>
                                    <p class="text-gray-500 mt-1">Create vacations for the new school year</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirmation" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <p class="text-gray-800 mb-4">Are you sure you want to delete this board?</p>
            <div class="flex justify-between">
                <button @click="handleDelete" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Delete
                </button>
                <button @click="toggleDeleteConfirmation()" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>