<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { addUser, removeUser } from '../../Api/Groups/GroupsFunctions';
import { useToast } from 'vue-toastification';

let { group, boards } = usePage().props;
const users = ref(usePage().props.users);
const email = ref('');
const toast = useToast();

const handleAddUser = async () => {
    const response = await addUser(group.id, email.value);

    console.log(response);

    if (! response.user) {
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
    }
};
</script>

<template>
    <Head title="Group Details" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">
            <h1 class="text-3xl font-semibold text-gray-800">{{ group.title }}</h1>
            <p class="mt-2 text-gray-600">{{ group.description }}</p>

            <section class="mt-8">
                <h2 class="text-2xl font-semibold text-gray-800">Members</h2>
                <ul class="mt-4 space-y-4">
                    <li v-for="user in users" :key="user.id" class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm">
                        <span class="text-gray-700">{{ user.name }}</span>
                        <button @click="handleRemoveUser(user.id)" class="text-red-600 hover:text-red-800 focus:outline-none">
                            Remove
                        </button>
                    </li>
                </ul>
            </section>

            <section class="mt-8">
                <h2 class="text-2xl font-semibold text-gray-800">Add Member</h2>
                <div class="mt-4 flex items-center space-x-4">
                    <input
                        type="text"
                        v-model="email"
                        placeholder="Enter email or name"
                        class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <button
                        @click="handleAddUser"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        Add
                    </button>
                </div>
            </section>

            <section class="mt-8">
                <h2 class="text-2xl font-semibold text-gray-800">Boards</h2>
                <ul class="mt-4 space-y-2">
                    <li v-for="board in boards" :key="board.id" class="text-blue-600 hover:text-blue-800">
                        <a :href="`/boards/${board.id}`">{{ board.title }}</a>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
