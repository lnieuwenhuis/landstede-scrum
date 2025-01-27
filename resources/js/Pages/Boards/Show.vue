<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const { props } = usePage();

const board = props.board;
const columns = props.columns;
const users = props.users;

console.log(columns);
</script>


<template>
    <Head title="Board" />

    <AuthenticatedLayout>
        <div>
            <h1>{{ board.title }}</h1>
            <p>{{ board.description }}</p>
            <br>

            <h2>Members</h2>
            <ul>
                <li v-for="user in users" :key="user.id">
                    {{ user.name }}
                </li>
            </ul>
            <br>

            <h1>Columns</h1>
            <ul>
                <li v-for="column in columns" :key="column.id">
                    <h3>{{ column.title }}</h3>
                    <ul>
                        <li v-for="card in column.cards" :key="card.id">
                            {{ card.title }}
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>
