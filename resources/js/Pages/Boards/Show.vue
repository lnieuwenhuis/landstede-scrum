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
const sprints = ref(props.sprints);
const expandedColumns = ref({});
const freeDates = props.freeDates;

// Burndown chart settings
const selectedPeriod = ref('board');
const selectedSprint = ref(null);
const startDate = ref(board.start_date);
const endDate = ref(board.end_date);

// Initialize chart with board data
const { chartData, chartOptions } = buildChart(
    board, 
    selectedSprint.value, 
    columns, 
    startDate.value, 
    endDate.value,
    freeDates
);

// Handle period selection change
const handlePeriodChange = (periodValue) => {
    selectedPeriod.value = periodValue;
    
    if (periodValue === 'board') {
        // Use board dates
        selectedSprint.value = null;
        startDate.value = board.start_date;
        endDate.value = board.end_date;
    } else {
        // Find the selected sprint
        const sprint = sprints.value.find(s => s.id == periodValue);
        if (sprint) {
            selectedSprint.value = sprint;
            startDate.value = sprint.start_date;
            endDate.value = sprint.end_date;
        }
    }
    
    // Regenerate chart with new date range
    const chartResult = buildChart(
        board, 
        selectedSprint.value, 
        columns, 
        startDate.value, 
        endDate.value,
        freeDates
    );
    
    chartData.value = chartResult.chartData.value;
    chartOptions.value = chartResult.chartOptions.value;
};

// Update chart when columns change
watch(columns, () => {
    chartData.value.datasets[0].data = generateBurndownData(
        board, 
        columns, 
        startDate.value, 
        endDate.value,
        freeDates
    );
}, { deep: true });

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

const handleUpdateColumn = async ({ id, title }) => {
    const column = columns.value.find(col => col.id === id);
    if (!column) return;
    
    const originalTitle = column.title;
    
    column.title = title;
    
    try {
        const response = await axios.post(`/api/updateColumn`, {
            column_id: id,
            title
        });
        
        if (response.data.message) {
            toast.success('Column updated successfully');
        } else {
            throw new Error('Failed to update column');
        }
    } catch (error) {
        if (column) {
            column.title = originalTitle;
        }
        toast.error('Failed to update column');
    }
};

const ownerId = users.value.length > 0 ? users.value[0].id : null;

const showSprintEditModal = ref(false);
const sprintToEdit = ref(null);
const editedSprintData = ref({
    title: '',
    start_date: '',
    end_date: '',
    status: ''
});

// Add this function to handle opening the sprint edit modal
const openSprintEditModal = (sprint) => {
    sprintToEdit.value = sprint;
    editedSprintData.value = {
        title: sprint.title,
        start_date: sprint.start_date,
        end_date: sprint.end_date,
        status: sprint.status
    };
    showSprintEditModal.value = true;
    document.body.style.overflow = 'hidden';
};

// Add this function to handle closing the sprint edit modal
const closeSprintEditModal = () => {
    showSprintEditModal.value = false;
    document.body.style.overflow = '';
    sprintToEdit.value = null;
};

const handleSaveSprint = async () => {
    try {
        const response = await axios.post(`/api/updateSprint`, {
            board_id: board.id,
            sprint_id: sprintToEdit.value.id,
            title: editedSprintData.value.title,
            start_date: editedSprintData.value.start_date,
            end_date: editedSprintData.value.end_date,
            status: editedSprintData.value.status
        });
        
        if (response.data.message) {
            const sprintIndex = sprints.value.findIndex(s => s.id === sprintToEdit.value.id);
            if (sprintIndex !== -1) {
                sprints.value[sprintIndex] = {
                    ...sprints.value[sprintIndex],
                    ...editedSprintData.value
                };
            }
            
            if (selectedPeriod.value == sprintToEdit.value.id) {
                handlePeriodChange(selectedPeriod.value);
            }
            
            toast.success('Sprint updated successfully');
            closeSprintEditModal();
        } else {
            throw new Error(response.data.error || 'Failed to update sprint');
        }
    } catch (error) {
        console.error('Error updating sprint:', error);
        toast.error(error.message || 'Failed to update sprint');
    }
};
</script>

<template>
    <Head title="Board" />

    <AuthenticatedLayout>
        <div class="container mx-auto px-6 py-3">
            <!-- Board header with title and description -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-800">{{ board.title }}</h1>
                    <p class="text-gray-600 mt-1">{{ board.description }}</p>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="flex space-x-6" aria-label="Tabs">
                    <button
                        v-for="tab in ['board', 'list', 'burndown', 'users', 'sprints']"
                        :key="tab"
                        @click="activeTab = tab"
                        :class="[
                            'py-2 px-1 border-b-2 font-medium text-sm',
                            activeTab === tab
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]"
                    >
                        {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div v-if="activeTab === 'board'">
                <Scrumboard
                    :columns="columns"
                    :users="users"
                    :board="board"
                    @update-card="handleUpdateCard"
                    @delete-card="toggleDeleteCard"
                    @add-card="handleAddCard"
                    @add-column="handleAddColumn"
                    @delete-column="handleDeleteColumn"
                    @move-card="handleMoveCard"
                    @update-column="handleUpdateColumn"
                />
            </div>

            <div v-if="activeTab === 'burndown'" class="flex-1">
                <!-- Sprint/Board Selection Dropdown -->
                <div class="mb-6 px-4">
                    <label for="period-selector" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Period:
                    </label>
                    <select 
                        id="period-selector" 
                        v-model="selectedPeriod" 
                        @change="handlePeriodChange(selectedPeriod)"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                    >
                        <option value="board">Entire Board</option>
                        <option v-for="sprint in sprints" :key="sprint.id" :value="sprint.id">
                            {{ sprint.title }} ({{ new Date(sprint.start_date).toLocaleDateString() }} - {{ new Date(sprint.end_date).toLocaleDateString() }})
                        </option>
                    </select>
                </div>
                
                <!-- Burndown Chart with horizontal scroll on mobile -->
                <div class="overflow-x-auto">
                    <div class="flex justify-center items-center h-full min-w-[800px]">
                        <div class="w-full h-[500px]">
                            <Line :data="chartData" :options="chartOptions"/>
                        </div>
                    </div>
                </div>
            </div>
            <!-- List View Tab -->
            <div v-if="activeTab === 'list'" class="flex-1">
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="divide-y divide-gray-200">
                        <div v-for="column in columns" :key="column.id" class="p-6">
                            <div 
                                @click="expandedColumns[column.id] = !expandedColumns[column.id]"
                                class="flex justify-between items-center cursor-pointer"
                            >
                                <h2 class="text-xl font-semibold text-gray-800">{{ column.title }}</h2>
                                <svg 
                                    xmlns="http://www.w3.org/2000/svg" 
                                    class="h-5 w-5 text-gray-500 transition-transform duration-200"
                                    :class="expandedColumns[column.id] ? 'transform rotate-180' : ''"
                                    viewBox="0 0 20 20" 
                                    fill="currentColor"
                                >
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div v-show="expandedColumns[column.id]" class="space-y-4 mt-4">
                                <div 
                                    v-for="card in column.cards" 
                                    :key="card.id"
                                    class="bg-black/5 p-4 rounded-lg shadow-sm transition-all duration-200 hover:bg-black/10"
                                >
                                    <!-- Card content remains the same -->
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-gray-700 font-medium">{{ card.title }}</p>
                                            <p class="text-gray-500 mt-1">{{ card.description }}</p>
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">
                                                    {{ card.points || 0 }} points
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-2">
                                            <button 
                                                @click="toggleDeleteCard(card.id)"
                                                class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                            <!-- Edit button for cards -->
                                            <button 
                                                class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="column.cards.length === 0" class="py-4 text-center text-gray-500 italic bg-black/5 rounded-lg">
                                    No cards in this column
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Tab -->
            <div v-if="activeTab === 'users'" class="flex-1">
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Team Members</h2>
                        <div class="space-y-4">
                            <div 
                                v-for="user in users" 
                                :key="user.id" 
                                class="bg-black/5 p-4 rounded-lg shadow-sm transition-all duration-200 hover:bg-black/10 flex items-center"
                            >
                                <div class="flex-shrink-0">
                                    <div class="h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-lg">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="flex items-center">
                                        <p class="text-gray-800 font-medium">{{ user.name }}</p>
                                        <!-- Owner badge next to name -->
                                        <span 
                                            v-if="user.id === ownerId" 
                                            class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800"
                                        >
                                            Owner
                                        </span>
                                    </div>
                                    <p class="text-gray-500 text-sm">{{ user.email }}</p>
                                </div>
                                <!-- Delete button for users -->
                                <button 
                                    class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'sprints'">
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Sprints</h2>
                        <div class="space-y-4">
                            <div 
                                v-for="sprint in sprints" 
                                :key="sprint.id"
                                class="bg-black/5 p-4 rounded-lg shadow-sm transition-all duration-200 hover:bg-black/10"
                            >
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-800">{{ sprint.title }}</h3>
                                        <div class="mt-2 space-y-1">
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">Start Date:</span> 
                                                {{ new Date(sprint.start_date).toLocaleDateString() }}
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">End Date:</span> 
                                                {{ new Date(sprint.end_date).toLocaleDateString() }}
                                            </p>
                                            <p class="text-sm text-gray-600">
                                                <span class="font-medium">Status: </span>
                                                <span 
                                                    :class="{
                                                        'text-green-600': new Date() >= new Date(sprint.start_date) && new Date() <= new Date(sprint.end_date),
                                                        'text-red-600': new Date() > new Date(sprint.end_date),
                                                        'text-blue-600': new Date() < new Date(sprint.start_date)
                                                    }"
                                                >
                                                    {{ sprint.status.charAt(0).toUpperCase() + sprint.status.slice(1) }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button 
                                            @click="openSprintEditModal(sprint)"
                                            class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button 
                                            class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="sprints.length === 0" class="py-4 text-center text-gray-500 italic">
                                No sprints created yet
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Delete Card Confirmation Modal -->
            <div v-if="showDeleteConfirmation" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Card</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to delete this card? This action cannot be undone.</p>
                    <div class="flex justify-end space-x-3">
                        <button 
                            @click="toggleDeleteCard()" 
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="handleDeleteCard(cardToDelete)" 
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="showSprintEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Sprint</h3>
                    
                    <div class="space-y-4">
                        <!-- Sprint Title -->
                        <div>
                            <label for="sprint-title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input 
                                id="sprint-title" 
                                v-model="editedSprintData.title" 
                                type="text" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        
                        <!-- Sprint Start Date -->
                        <div>
                            <label for="sprint-start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input 
                                id="sprint-start-date" 
                                v-model="editedSprintData.start_date" 
                                type="date" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        
                        <!-- Sprint End Date -->
                        <div>
                            <label for="sprint-end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input 
                                id="sprint-end-date" 
                                v-model="editedSprintData.end_date" 
                                type="date" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        
                        <!-- Sprint Status -->
                        <div>
                            <label for="sprint-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select 
                                id="sprint-status" 
                                v-model="editedSprintData.status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="active">Active (Current Sprint)</option>
                                <option value="inactive">Inactive (Future Sprint)</option>
                                <option value="locked">Locked (Completed, Not Checked)</option>
                                <option value="checked">Checked (Completed & Verified)</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                <span v-if="editedSprintData.status === 'active'">This is the currently active sprint.</span>
                                <span v-else-if="editedSprintData.status === 'inactive'">This sprint is scheduled for the future.</span>
                                <span v-else-if="editedSprintData.status === 'locked'">This sprint is completed but not yet checked.</span>
                                <span v-else-if="editedSprintData.status === 'checked'">This sprint is completed and has been verified.</span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button 
                            @click="closeSprintEditModal" 
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="handleSaveSprint" 
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                        >
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>            