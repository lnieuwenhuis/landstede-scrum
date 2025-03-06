<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const page = usePage();

const showMessage = ref(page.props.justLoggedIn ?? false);
const boards = ref(page.props.boards);
const showDeleteConfirmation = ref(false);
const boardToDelete = ref(null);
const showBoardsList = ref(false); // Add this line to control boards visibility

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

const toggleBoardsList = () => {
    showBoardsList.value = !showBoardsList.value;
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
                        <div class="flex justify-between items-center">
                            <div @click="toggleBoardsList" class="flex items-center cursor-pointer">
                                <h3 class="text-lg font-semibold text-gray-900">All Boards</h3>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 transition-transform" :class="showBoardsList ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                <span class="text-sm text-gray-500 ml-2">({{ boards.length }} boards)</span>
                            </div>
                            <div class="flex justify-between">
                                <a
                                    href="/boards/create"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                >
                                    Create Board
                                </a>
                            </div>
                        </div>
                        
                        <transition name="fade">
                            <div v-if="showBoardsList" class="space-y-4 mt-4">
                                <div v-for="board in boards" :key="board.id" 
                                    class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-medium text-gray-900">{{ board.title }}</h4>
                                            <p class="text-gray-500 mt-1">{{ board.description }}</p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <a :href="`/boards/${board.id}`" 
                                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                                View Board
                                            </a>
                                            <button @click="toggleDeleteConfirmation(board.id)" class="px-2 py-2 bg-red-500 hover:bg-red-700 rounded-lg">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </transition>
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