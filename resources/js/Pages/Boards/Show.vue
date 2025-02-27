<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';
import { Line } from 'vue-chartjs';
import { buildChart, generateBurndownData } from '@/Helpers/BurndownHelper';
import Scrumboard from './Components/Scrumboard.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const toast = useToast();
const { props } = usePage();

// Board data
const board = props.board;
const columns = ref(props.columns);
const users = ref(props.users);
const activeTab = ref('board');

// Card state
const cardOpen = ref({});
const cardEditing = ref(null);
const cardTitle = ref('');
const cardDesc = ref('');
const cardPoints = ref(0);

// Delete confirmation
const showDeleteConfirmation = ref(false);
const cardToDelete = ref(null);

// Column state
const showNewColumn = ref(false);
const newColumnTitle = ref('');
const newColumnDone = ref(false);
const currentDragColumnId = ref(null);

// Card handlers
const toggleDeleteCard = (cardId = null) => {
    document.body.style.overflow = showDeleteConfirmation.value ? '' : 'hidden';
    showDeleteConfirmation.value = !showDeleteConfirmation.value;
    cardToDelete.value = cardId;
};

const toggleAddCard = (columnId) => {
    cardOpen.value[columnId] = !cardOpen.value[columnId];
    cardEditing.value = null;
};

const toggleEditCard = (card) => {
    cardEditing.value = card.id;
    cardTitle.value = card.title;
    cardDesc.value = card.description;
    cardPoints.value = card.points;
};

const handleAddCard = async (columnId) => {
    const response = await axios.post(`/api/addCardToColumn/${columnId}`, {
        title: cardTitle.value,
        description: cardDesc.value,
        points: cardPoints.value
    });

    if (response.data.card) {
        const column = columns.value.find(col => col.id === columnId);
        if (column) {
            column.cards.push(response.data.card);
        }
        resetForm();
    }
};

const handleEditCard = async (columnId, cardId) => {
    const response = await axios.post(`/api/updateCard/${cardId}`, {
        title: cardTitle.value,
        description: cardDesc.value,
        points: cardPoints.value
    });

    if (response.data.message) {
        columns.value.forEach(column => {
            const cardIndex = column.cards.findIndex(c => c.id === cardId);
            if (cardIndex !== -1) {
                column.cards[cardIndex] = response.data.card;
            }
        });
        resetForm();
    }
};

const handleDeleteCard = async () => {
    const response = await axios.post(`/api/deleteCard/${cardToDelete.value}`);
    if (response.data.message) {
        columns.value.forEach(column => {
            column.cards = column.cards.filter(card => card.id !== cardToDelete.value);
        });
        toggleDeleteCard();
        toast.success(response.data.message);
    }
};

const resetForm = () => {
    cardEditing.value = null;
    cardTitle.value = '';
    cardDesc.value = '';
    cardPoints.value = 0;
};

// Column handlers
const toggleNewColumn = () => {
    showNewColumn.value = !showNewColumn.value; 
};

const handleAddColumn = async () => {
    const response = await axios.post('/api/addColumn', {
        title: newColumnTitle.value,
        done: newColumnDone.value,
        board_id: board.id
    });

    if (response.data.column) {
        columns.value.push(response.data.column);
        resetNewColumnForm();
        toggleNewColumn();
        toast.success('Column added successfully!');
    }
};

const handleDeleteColumn = async (columnId) => {
    const response = await axios.post(`/api/deleteColumn`, {
        column_id: columnId
    });

    if (response.data.message) {
        columns.value = columns.value.filter(column => column.id !== columnId);
        toast.success(response.data.message);
    }
};

const resetNewColumnForm = () => {
    newColumnTitle.value = '';
    newColumnDone.value = false;
};

// Drag and drop handlers
const handleDragStart = (event, cardId, columnId) => {
    event.dataTransfer.setData('text/plain', JSON.stringify({ cardId, columnId }));
    event.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (event, columnId) => {
    event.preventDefault();
    currentDragColumnId.value = columnId;
    event.dataTransfer.dropEffect = 'move';
};

const handleDragLeave = () => {
    currentDragColumnId.value = null;
};

const handleDrop = async (event, targetColumnId) => {
    event.preventDefault();
    currentDragColumnId.value = null;
    
    const { cardId, columnId: sourceColumnId } = JSON.parse(event.dataTransfer.getData('text/plain'));
    if (sourceColumnId === targetColumnId) return;

    const sourceColumn = columns.value.find(col => col.id == sourceColumnId);
    const targetColumn = columns.value.find(col => col.id == targetColumnId);
    const cardIndex = sourceColumn.cards.findIndex(c => c.id == cardId);
    if (cardIndex === -1) return;

    // Remove from source column
    const [movedCard] = sourceColumn.cards.splice(cardIndex, 1);
    
    // Add to target column
    targetColumn.cards.push(movedCard);

    try {
        await axios.post(`/api/cards/${cardId}/move`, {
            column_id: targetColumnId
        });
        toast.success('Card moved successfully');
    } catch (error) {
        // Revert changes if API call fails
        sourceColumn.cards.splice(cardIndex, 0, movedCard);
        targetColumn.cards.pop();
        toast.error('Failed to move card');
    }
};

//Generating Burndown Chart with a custom helper function
const { chartData, chartOptions } = buildChart(board, columns);

watch(columns, () => {
    chartData.value.datasets[0].data = generateBurndownData(board, columns);
}, { deep: true });
</script>

<template>
    <Head title="Board" />

    <AuthenticatedLayout>
        <div class="container mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">{{ board.title }}</h1>
            </div>

            <p class="mb-6">{{ board.description }}</p>

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="flex space-x-8" aria-label="Tabs">
                    <button
                        v-for="tab in ['board', 'list', 'burndown']"
                        :key="tab"
                        @click="activeTab = tab"
                        :class="[
                            activeTab === tab
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                            'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
                        ]"
                    >
                        {{ tab.charAt(0).toUpperCase() + tab.slice(1) }} View
                    </button>
                </nav>
            </div>

            <div v-if="activeTab === 'burndown'" class="flex-1">
                <div class="flex justify-center items-center h-full">
                    <div class="w-full h-[500px]">
                        <Line :data="chartData" :options="chartOptions" />
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'board'" class="flex-1">
                <Scrumboard 
                    :columns="columns"
                    :cardOpen="cardOpen"
                    :cardEditing="cardEditing"
                    v-model:cardTitle="cardTitle"
                    v-model:cardDesc="cardDesc"
                    v-model:cardPoints="cardPoints"
                    :showNewColumn="showNewColumn"
                    v-model:newColumnTitle="newColumnTitle"
                    v-model:newColumnDone="newColumnDone"
                    :currentDragColumnId="currentDragColumnId"
                    @toggleAddCard="toggleAddCard"
                    @toggleEditCard="toggleEditCard"
                    @toggleDeleteCard="toggleDeleteCard"
                    @handleEditCard="handleEditCard"
                    @resetForm="resetForm"
                    @handleAddCard="handleAddCard"
                    @toggleEditColumn="toggleEditColumn"
                    @handleDeleteColumn="handleDeleteColumn"
                    @handleDragStart="handleDragStart"
                    @handleDragOver="handleDragOver"
                    @handleDragLeave="handleDragLeave"
                    @handleDrop="handleDrop"
                    @toggleNewColumn="toggleNewColumn"
                    @handleAddColumn="handleAddColumn"
                />
            </div>

            <div v-else-if="activeTab === 'list'" class="space-y-8">
                <div v-for="column in columns" :key="column.id" class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ column.title }}</h3>
                    <div class="space-y-4">
                        <div v-for="card in column.cards" :key="card.id" 
                            class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                            <h4 class="font-medium text-gray-900">{{ card.title }}</h4>
                            <p class="text-gray-500 mt-1">{{ card.description }}</p>
                            <div class="mt-2 text-sm text-gray-500">Points: {{ card.points }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteConfirmation" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <p class="text-gray-800 mb-4">Are you sure you want to delete this card?</p>
                <div class="flex justify-between">
                    <button @click="handleDeleteCard" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete
                    </button>
                    <button @click="toggleDeleteCard" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>