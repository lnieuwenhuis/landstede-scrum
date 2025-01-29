<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const { props } = usePage();

const board = props.board;
const columns = ref(props.columns);
const users = props.users;
</script>

<template>

    <Head title="Board" />

    <AuthenticatedLayout>
        <div class="container mx-auto p-6">
            <!-- Header Section -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">{{ board.title }}</h1>
                <button class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">
                    Create Board
                </button>
            </div>

            <!-- Board Description -->
            <p class="mb-6">{{ board.description }}</p>

            <!-- Layout for Members and Columns -->
            <div class="flex space-x-12">
                <!-- Members Section (on the left) -->
                <div class="w-1/4">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Members</h2>
                    <ul class="space-y-4">
                        <li v-for="user in users" :key="user.id" class="text-gray-700">
                            {{ user.name }}
                        </li>
                    </ul>
                </div>

                <!-- Columns for To Do, In Progress, Done (on the right) -->
                <div class="flex-1">
                    <!-- Use Flexbox Wrapping to arrange columns in rows -->
                    <div class="flex flex-wrap gap-6">
                        <!-- Columns -->
                        <div v-for="column in columns" :key="column.id"
                            class="w-1/4 bg-black/5 shadow-md rounded-lg p-4">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">{{ column.title }}</h2>
                            <div class="space-y-4">
                                <div v-for="card in column.cards" :key="card.id"
                                    class="bg-white p-3 rounded-lg shadow-sm">
                                    <p class="text-gray-700">{{ card.title }}</p>
                                </div>
                            </div>
                            <button class="mt-3 text-blue-500 hover:text-blue-700">+ Add Card</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>