<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const { props } = usePage();

const board = props.board;
const columns = ref(props.columns);
const users = props.users;

const loadCards = async (columnId) => {
    const column = columns.value.find((column) => column.id === columnId);
    if(!column.cards) {
        try {
            const response = await axios.get(`/api/${columnId}/cards`);
            column.cards = response.data;
        } catch (e) {
            console.error('Failed to load cards', e);
        }
    }
}
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
                    <button v-if="!column.cards" @click="loadCards(column.id)">Load Cards</button>
                    <ul v-if="column.cards">
                        <li v-for="card in column.cards" :key="card.id">
                            {{ card.title }}
                        </li>
                    </ul>
                    <br>
                </li>
            </ul>
        </div>
    </AuthenticatedLayout>
</template>