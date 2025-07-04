<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref, watch, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import ConfirmModal from '../Boards/ShowComponents/ConfirmModal.vue';
import { useTranslations } from '../../translations.js';

const { __ } = useTranslations();
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
    axios.post(`/api/boards/deleteBoard`, { boardId: boardToDelete.value })
        .then(response => {
            if (response.data.message) {
                boards.value = boards.value.filter(board => board.id !== boardToDelete.value);
                searchResults.value.boards = searchResults.value.boards.filter(board => board.id !== boardToDelete.value);
                toggleDeleteConfirmation();
                toast.success(__('Board deleted successfully'));
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
        toast.error(__('Failed to perform search'));
        searchResults.value = { boards: [], users: [] }; 
    } finally {
        isSearching.value = false;
    }
};

</script>

<template>
    <Head :title="__('Dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('Dashboard') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="showMessage" class="overflow-hidden bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 text-gray-900 flex justify-between items-center">
                        <span>{{ __('You\'re logged in!') }}</span>
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
                        <div class="mb-4">
                            <div class="mb-3">
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('Search Boards & Users') }}</h3>
                                <span class="text-sm text-gray-500">({{ boardCount }} {{ __('total boards') }})</span>
                            </div>
                            
                            <div class="relative mb-6">
                                <input 
                                    v-model="searchInput"
                                    type="text" 
                                    :placeholder="__('Search by board title, user name or email')"
                                    class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                />
                                <div v-if="isSearching" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                    <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                
                                <a href="/boards/create"
                                   class="absolute right-0 top-0 -mt-14 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:hidden" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="hidden sm:inline">{{ __('Create Board') }}</span>
                                </a>
                            </div>
                        </div>
                        
                        <div v-if="!isSearching && searchInput.trim() && !searchResults.boards.length && !searchResults.users.length" class="text-center text-gray-500 py-4">
                            {{ __('No results found for') }} "{{ searchInput }}".
                        </div>
                        
                        <div v-if="searchResults.boards.length > 0 || searchResults.users.length > 0">
                            <div v-if="searchResults.boards.length > 0" class="mb-6">
                                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b">{{ __('Boards') }} ({{ searchResults.boards.length }})</h4>
                                <div class="space-y-3">
                                    <div v-for="board in searchResults.boards" :key="'board-' + board.id" 
                                        class="border border-gray-200 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                                        <div class="flex justify-between items-center">
                                            <div class="flex-1 min-w-0 mr-4">
                                                <h5 class="font-medium text-gray-900 truncate">{{ board.title }}</h5>
                                                <p class="text-sm text-gray-500 mt-1 truncate">{{ board.description }}</p>
                                            </div>
                                            <div class="flex flex-col space-y-2 sm:flex-row sm:space-x-2 sm:space-y-0 flex-shrink-0">
                                                <a :href="`/boards/${board.id}`" 
                                                    class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 shadow-sm text-center flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 sm:hidden" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="hidden sm:inline">{{ __('View') }}</span>
                                                </a>
                                                <button @click="toggleDeleteConfirmation(board.id)" 
                                                        class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-md hover:bg-red-700 shadow-sm text-center flex items-center justify-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 sm:hidden" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="hidden sm:inline">{{ __('Delete') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="searchResults.users.length > 0">
                                <h4 class="text-md font-semibold text-gray-700 mb-3 pb-2 border-b">{{ __('Users') }} ({{ searchResults.users.length }})</h4>
                                <div class="space-y-3">
                                    <div v-for="user in searchResults.users" :key="'user-' + user.id" 
                                        class="border border-gray-200 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                                        <div class="flex justify-between items-center">
                                            <div class="flex-1 min-w-0 mr-4">
                                                <h5 class="font-medium text-gray-900 truncate">{{ user.name }}</h5>
                                                <p class="text-sm text-gray-500 mt-1 truncate">{{ user.email }}</p>
                                            </div>
                                            <a :href="`/admin/users/${user.id}/boards`" 
                                               class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 shadow-sm flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                </svg>
                                                <span class="ml-1 hidden sm:inline">{{ __('View Boards') }}</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white shadow-sm sm:rounded-lg mt-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Administration') }}</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <a href="/admin/vacations" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <div class="mr-4 bg-blue-100 p-3 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ __('Manage Vacations') }}</h4>
                                    <p class="text-gray-500 mt-1">{{ __('Create vacations for the new school year') }}</p>
                                </div>
                            </a>
                            <a href="/admin/categories" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <div class="mr-4 bg-blue-100 p-3 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ __('Manage Categories') }}</h4>
                                    <p class="text-gray-500 mt-1">{{ __('Create Categories for Students') }}</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <ConfirmModal
        v-if="showDeleteConfirmation"
        :show="showDeleteConfirmation"
        :title="__('Delete Board')"
        :message="__('Are you sure you want to delete this board? This action cannot be undone.')"
        :confirm-text="__('Delete')"
        :cancel-text="__('Cancel')"
        @cancel="toggleDeleteConfirmation()"
        @confirm="handleDelete"
    />
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>