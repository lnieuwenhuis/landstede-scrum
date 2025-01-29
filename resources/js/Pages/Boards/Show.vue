<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const { props } = usePage();

const board = props.board;
const columns = ref(props.columns);
const users = props.users;

const cardOpen = ref({}); // Track form state per column

const toggleAddCard = (columnId) => {
    cardOpen.value[columnId] = !cardOpen.value[columnId];
};

const cardTitle = ref('');
const cardDesc = ref('');
const cardPoints = ref(0);

const handleAddCard = async (columnId) => {
    const response = await axios.get(`/api/addCardToColumn/${cardTitle.value}/${cardDesc.value}/${cardPoints.value}/${columnId}`, {
        title: cardTitle.value,
        description: cardDesc.value,
        points: cardPoints.value,
        column_id: columnId,
    });

    if (response.data.card) {
        const column = columns.value.find(col => col.id === columnId);
        if (column) {
            column.cards.push(response.data.card);
        }
        // Reset form state for this column
        cardOpen.value[columnId] = false;
        cardTitle.value = '';
        cardDesc.value = '';
        cardPoints.value = 0;
    }
};
</script>

<template>
    <Head title="Board" />

    <AuthenticatedLayout>
        <div class="container mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">{{ board.title }}</h1>
                <button class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">
                    Create Board
                </button>
            </div>

            <p class="mb-6">{{ board.description }}</p>

            <div class="flex space-x-12">
                <div class="w-1/4">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Members</h2>
                    <ul class="space-y-4">
                        <li v-for="user in users" :key="user.id" class="text-gray-700">
                            {{ user.name }}
                        </li>
                    </ul>
                </div>

                <div class="flex-1">
                    <div class="flex flex-wrap gap-6">
                        <div v-for="column in columns" :key="column.id"
                            class="w-1/4 bg-black/5 shadow-md rounded-lg p-4">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">{{ column.title }}</h2>
                            <div class="space-y-4">
                                <div v-for="card in column.cards" :key="card.id"
                                    class="bg-white p-3 rounded-lg shadow-sm">
                                    <p class="text-gray-700">{{ card.title }}</p>
                                    <p class="text-gray-500">{{ card.description }}</p>
                                    <span class="text-gray-500">Points: {{ card.points }}</span>
                                    <div>
                                        <button @click="toggleEditCard(column.id)" class="bg-blue-500 text-white py-1 px-2 rounded-lg hover:bg-blue-600 mr-1">
                                            Edit
                                        </button>
                                        <button @click="toggleDeleteCard(card.id)" class="bg-red-500 text-white py-1 px-2 rounded-lg hover:bg-red-600">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <button v-if="!cardOpen[column.id]" class="mt-3 text-blue-500 hover:text-blue-700" @click="toggleAddCard(column.id)">+ Add Card</button>
                            <div v-if="cardOpen[column.id]" class="mt-3">
                                <input v-model="cardTitle" type="text" class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter card title" />
                                <textarea v-model="cardDesc" class="w-full resize-none mt-2 px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter card description"></textarea>
                                <input v-model="cardPoints" type="number" class="w-full mt-2 px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter card points" />
                                <div class="flex justify-between mt-2">
                                    <button @click="handleAddCard(column.id)" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Add</button>
                                    <button class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500" @click="toggleAddCard(column.id)">Cancel</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
