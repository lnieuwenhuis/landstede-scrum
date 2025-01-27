<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const { props } = usePage();

const boards = props.boards;
</script>

<template>
    <Head title="Your Boards" />

    <AuthenticatedLayout>
        <div>
            <h1>Boards Index</h1>
            <ul>
                <li v-for="board in boards" :key="board.id">
                    <a style="color: blue;" :href="`/boards/${board.id}`">{{ board.title }}</a>
                </li>
            </ul>
            <!-- Display a message if groups is empty -->
            <p v-if="boards.length === 0">No groups found.</p>
        </div>
    </AuthenticatedLayout>
</template>
