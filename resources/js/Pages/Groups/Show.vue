<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const { props } = usePage();

const group = props.group;
const users = props.users;
const boards = props.boards;

</script>

<template>
    <Head title="" />

    <AuthenticatedLayout>
        <div>
            <h1>{{ group.title }}</h1>
            <p>{{ group.description }}</p>

            <h2>Members</h2>
            <ul>
                <li v-for="user in users" :key="user.id">
                    {{ user.name }}
                </li>
            </ul>

            <h2>Boards</h2>
            <ul>
                <li v-for="board in boards" :key="board.id">
                    <a style="color: blue;" :href="`/boards/${board.id}`">{{ board.title }}</a>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
