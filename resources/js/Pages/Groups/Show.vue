<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { addUser, removeUser } from '@/Helpers/GroupsHelper';
import { useToast } from 'vue-toastification';

let { group, board } = usePage().props;
const users = ref(usePage().props.users);
const email = ref('');
const toast = useToast();

const handleAddUser = async () => {
    const response = await addUser(group.id, email.value);

    if (!response.user) {
        toast.error(response.error);
        return;
    }
    email.value = '';
    users.value.push(response.user);
    toast.success('User added');
};

const handleRemoveUser = async (userId) => {
    const removedUser = await removeUser(group.id, userId);
    if (removedUser) {
        users.value = users.value.filter(user => user.id !== userId);
        toast.success('User removed');
        
        // If the current user removed themselves, redirect to dashboard
        if (userId === usePage().props.auth.user.id) {
            window.location.href = '/dashboard';
        }
    }
};
</script>

<template>
    <Head title="Group Details" />
    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg">
            <div class="flex items-center justify-between border-b pb-4 mb-6">
                <h1 class="text-3xl font-bold text-gray-900">{{ group.title }}</h1>
                <a :href="`/boards/${board.id}`" class="px-4 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600">
                    Scrum board
                </a>
            </div>
            <p class="text-gray-600">{{ group.description }}</p>
            
            <section class="mt-8">
                <h2 class="text-2xl font-semibold text-gray-900">Add Member</h2>
                <div class="mt-4 flex items-center space-x-4">
                    <input
                        type="text"
                        v-model="email"
                        placeholder="Enter email or name"
                        class="flex-1 px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    />
                    <button
                        @click="handleAddUser"
                        class="px-6 py-2 bg-pink-500 text-white rounded-lg shadow hover:bg-pink-600"
                    >
                        Add
                    </button>
                </div>
            </section>
            
            <section class="mt-8">
                <h2 class="text-2xl font-semibold text-gray-900">Members</h2>
                <ul class="mt-4 divide-y divide-gray-200">
                    <li v-for="user in users" :key="user.id" class="flex justify-between items-center p-4 bg-gray-50 rounded-lg shadow-sm mt-2">
                        <span class="text-gray-800">{{ user.name }}</span>
                        <button
                            @click="handleRemoveUser(user.id)"
                            class="text-red-600 hover:text-red-800"
                        >
                            Remove
                        </button>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
