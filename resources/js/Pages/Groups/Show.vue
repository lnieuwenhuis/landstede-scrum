<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';

let { group, users, boards } = usePage().props;
const email = ref('');

const addUser = async (groupId, emailValue) => {
    if (!emailValue.trim()) {
        console.error('Email is required');
        return;
    }

    try {
        const response = await axios.get(`/api/addUserToGroup/${groupId}/${emailValue}`);
        
        users.push(response.data.user);
        email.value = '';

        window.location.reload();
    } catch (e) {
        console.error('Failed to add user', e);
    }
};

const removeUser = async (groupId, userId) => {
    try {
        await axios.get(`/api/removeUserFromGroup/${groupId}/${userId}`);
        users = users.filter((user) => user.id !== userId);

        window.location.reload();
    } catch (e) {
        console.error('Failed to remove user', e);
    }
};
</script>

<template>
    <Head title="Group Details" />

    <AuthenticatedLayout>
        <div>
            <h1>{{ group.title }}</h1>
            <p>{{ group.description }}</p>

            <h2>Members</h2>
            <ul>
                <li v-for="user in users" :key="user.id">
                    {{ user.name }}
                    <button @click="removeUser(group.id, user.id)">Remove</button>
                </li>
            </ul>
            <br>

            <h2>Add Member</h2>
            <input type="text" v-model="email" placeholder="Email">
            <button @click="addUser(group.id, email)">Add</button>
            <br>

            <h2>Boards</h2>
            <ul>
                <li v-for="board in boards" :key="board.id">
                    <a style="color: blue;" :href="`/boards/${board.id}`">{{ board.title }}</a>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
