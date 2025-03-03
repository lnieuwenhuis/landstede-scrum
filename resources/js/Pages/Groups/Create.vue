<script setup>
import { reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const form = reactive({
    title: '',
    description: '',
    boardTitle: '',
    boardDescription: '',
    startDate: '',
    endDate: '',
});

const submit = async () => {
    const response = await axios.post('/api/groups/createGroup', form);

    if (response.data.status === 'redirect') {
        window.location.href = '/dashboard';
    }
};
</script>

<template>
    <Head title="Create Group" />

    <AuthenticatedLayout>
        <div class="container mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Create New Group</h1>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Group Title</label>
                            <input 
                                type="text" 
                                id="title" 
                                v-model="form.title" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Group Description</label>
                            <textarea 
                                id="description" 
                                v-model="form.description" 
                                rows="3" 
                                resize="none"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>

                        <div>
                            <label for="boardTitle" class="block text-sm font-medium text-gray-700">Board Title</label>
                            <input 
                                type="text" 
                                id="boardTitle" 
                                v-model="form.boardTitle" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label for="boardDescription" class="block text-sm font-medium text-gray-700">Board Description</label>
                            <textarea 
                                id="boardDescription" 
                                v-model="form.boardDescription" 
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                        </div>

                        <div>
                            <label for="startDate" class="block text-sm font-medium text-gray-700">Start Date</label>
                            <input
                                type="date"
                                id="startDate"
                                v-model="form.startDate"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <label for="endDate" class="block text-sm font-medium text-gray-700">End Date</label>
                            <input
                                type="date"
                                id="endDate"
                                v-model="form.endDate"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                        </div>

                        <div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Create Group
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>