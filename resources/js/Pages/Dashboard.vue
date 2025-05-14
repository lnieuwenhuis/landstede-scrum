<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import ConfirmModal from './Boards/ShowComponents/ConfirmModal.vue';

const toast = useToast();
const page = usePage();

const showMessage = ref(page.props.justLoggedIn ?? false);
const boards = ref(page.props.boards);
const showDeleteConfirmation = ref(false);
const boardToDelete = ref(null);

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
                        <div class="flex justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Boards</h3>
                            <div class="flex justify-between mb-4">
                                <a
                                    href="/boards/create"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                >
                                    Create Board
                                </a>
                            </div>
                        </div>
                        <div class="space-y-4">
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
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
        v-if="showDeleteConfirmation"
        :show="showDeleteConfirmation"
        title="Delete Board"
        message="Are you sure you want to delete this board? This action cannot be undone."
        confirm-text="Delete"
        cancel-text="Cancel"
        @cancel="toggleDeleteConfirmation()"
        @confirm="handleDelete"
    />
</template>