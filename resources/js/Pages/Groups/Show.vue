<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { addUser, removeUser } from '../../Api/Groups/GroupsFunctions';

let { group, boards } = usePage().props;
const users = ref(usePage().props.users);
const email = ref('');

const handleAddUser = async () => {
    const newUser = await addUser(group.id, email.value);
    if (newUser) {
        users.value.push(newUser);
    }
};

const handleRemoveUser = async (userId) => {
    const removedUser = await removeUser(group.id, userId);
    if (removedUser) {
        users.value = users.value.filter(user => user.id !== userId);
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
                    <button @click="handleRemoveUser(user.id)">Remove</button>
                </li>
            </ul>
            <br>

            <h2>Add Member</h2>
            <input type="text" v-model="email" placeholder="Email">
            <button @click="handleAddUser">Add</button>
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
