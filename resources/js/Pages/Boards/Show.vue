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

// Delete confirmation
const showDeleteConfirmation = ref(false);
const cardToDelete = ref(null);

// Card handlers
const handleUpdateCard = async ({ cardId, columnId, title, description, points }) => {
    // Update card optimistically
    columns.value.forEach(column => {
        const cardIndex = column.cards.findIndex(c => c.id === cardId);
        if (cardIndex !== -1) {
            column.cards[cardIndex] = {
                ...column.cards[cardIndex],
                title,
                description,
                points
            };
        }
    });

    // Send to backend
    const response = await axios.post(`/api/updateCard/${cardId}`, {
        title,
        description,
        points
    });

    if (!response.data.message) {
        // Revert changes if failed
        columns.value.forEach(column => {
            const cardIndex = column.cards.findIndex(c => c.id === cardId);
            if (cardIndex !== -1) {
                column.cards[cardIndex] = response.data.card;
            }
        });
        toast.error('Failed to update card');
    } else {
        toast.success('Card updated successfully');
    }
};

const toggleDeleteCard = (cardId = null) => {
    document.body.style.overflow = showDeleteConfirmation.value ? '' : 'hidden';
    showDeleteConfirmation.value = !showDeleteConfirmation.value;
    cardToDelete.value = cardId;
};

const handleDeleteCard = async (cardId) => {
    // Store card data for potential rollback
    let deletedCard;
    let deletedFromColumn;

    // Remove card optimistically
    columns.value.forEach(column => {
        const cardIndex = column.cards.findIndex(c => c.id === cardId);
        if (cardIndex !== -1) {
            deletedCard = column.cards[cardIndex];
            deletedFromColumn = column;
            column.cards = column.cards.filter(card => card.id !== cardId);
        }
    });

    // Close modal
    toggleDeleteCard();

    // Send to backend
    const response = await axios.post(`/api/deleteCard/${cardId}`);
    if (!response.data.message) {
        // Revert changes if failed
        if (deletedCard && deletedFromColumn) {
            deletedFromColumn.cards.push(deletedCard);
        }
        toast.error('Failed to delete card');
    } else {
        toast.success(response.data.message);
    }
};

const generateTempId = () => `temp-${Date.now()}`;

const handleAddCard = async ({ columnId, title, description, points }) => {
    // Add card optimistically
    const tempId = generateTempId();
    const column = columns.value.find(col => col.id === columnId);
    if (column) {
        const tempCard = {
            id: tempId,
            title,
            description,
            points
        };
        column.cards.push(tempCard);
    }

    // Send to backend
    const response = await axios.post(`/api/addCardToColumn/${columnId}`, {
        title,
        description,
        points
    });

    if (response.data.card) {
        // Replace temp card with real one
        const column = columns.value.find(col => col.id === columnId);
        if (column) {
            const index = column.cards.findIndex(c => c.id === tempId);
            if (index !== -1) {
                column.cards[index] = response.data.card;
            }
        }
        toast.success('Card added successfully');
    }
};

// Column handlers
const handleAddColumn = async ({ title, done, board_id, status }) => {
    if (!status) status = "active";
    const tempId = generateTempId();
    
    // Add column optimistically
    const tempColumn = {
        id: tempId,
        title,
        done,
        board_id,
        status,
        cards: []
    };
    columns.value.push(tempColumn);

    try {
        // Send to backend
        const response = await axios.post('/api/addColumn', {
            title,
            done,
            board_id,
            status
        });

        if (response.data && response.data.column) {
            // Replace temp column with real one
            const index = columns.value.findIndex(col => col.id === tempId);
            if (index !== -1) {
                // Create a new array with the updated column to ensure reactivity
                const updatedColumns = [...columns.value];
                updatedColumns[index] = response.data.column;
                columns.value = updatedColumns;
            }
            toast.success('Column added successfully!');
        } else {
            throw new Error('No column data received');
        }
    } catch (error) {
        // Remove temp column if request failed
        columns.value = columns.value.filter(col => col.id !== tempId);
        toast.error('Failed to add column');
    }
};

const handleDeleteColumn = async (columnId) => {
    // Store column data for potential rollback
    const deletedColumn = columns.value.find(col => col.id === columnId);
    const columnIndex = columns.value.findIndex(col => col.id === columnId);
    
    // Remove column optimistically
    columns.value = columns.value.filter(column => column.id !== columnId);

    // Send to backend
    const response = await axios.post(`/api/deleteColumn`, {
        column_id: columnId
    });

    if (!response.data.message) {
        // Revert changes if failed
        if (deletedColumn && columnIndex !== -1) {
            columns.value.splice(columnIndex, 0, deletedColumn);
        }
        toast.error('Failed to delete column');
    } else {
        toast.success(response.data.message);
    }
};

// Card movement handler
const handleMoveCard = async ({ cardId, sourceColumnId, targetColumnId }) => {
    const sourceColumn = columns.value.find(col => col.id == sourceColumnId);
    const targetColumn = columns.value.find(col => col.id == targetColumnId);
    const cardIndex = sourceColumn.cards.findIndex(c => c.id == cardId);
    if (cardIndex === -1) return;

    const [movedCard] = sourceColumn.cards.splice(cardIndex, 1);
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

const ownerId = users.value.length > 0 ? users.value[0].id : null;

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
                        v-for="tab in ['board', 'list', 'burndown', 'users']"
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

            <!-- Existing tabs content -->
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
                    :board="board"
                    @updateCard="handleUpdateCard"
                    @deleteCard="toggleDeleteCard"
                    @addCard="handleAddCard"
                    @deleteColumn="handleDeleteColumn"
                    @addColumn="handleAddColumn"
                    @moveCard="handleMoveCard"
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

            <!-- New Users Tab -->
            <div v-else-if="activeTab === 'users'" class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Board Members</h3>
                <div class="space-y-4">
                    <div v-if="users && users.length > 0">
                        <div v-for="user in users" :key="user.id" 
                            class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 flex items-center mt-2">
                            <div class="flex-shrink-0 mr-4">
                                <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                            </div>
                            <div>
                                <span class="font-medium text-gray-900">{{ user.name }}</span>
                                <span v-if="user.id === ownerId" class="mr-2 pb-1 text-yellow-500" title="Scrum master">
                                👑
                                </span>
                                <p class="text-gray-500 text-sm">{{ user.email }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-gray-500 text-center py-4">
                        No users assigned to this board.
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteConfirmation" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <p class="text-gray-800 mb-4">Are you sure you want to delete this card?</p>
                <div class="flex justify-between">
                    <button @click="handleDeleteCard(cardToDelete)" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Delete
                    </button>
                    <button @click="toggleDeleteCard()" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>