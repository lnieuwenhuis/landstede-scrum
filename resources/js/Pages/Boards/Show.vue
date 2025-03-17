<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';
import { Line } from 'vue-chartjs';
import { buildChart, generateBurndownData } from '@/Helpers/BurndownHelper';

// Importing all tabs
import BoardTab from './ShowComponents/BoardTab.vue';
import ListTab from './ShowComponents/ListTab.vue';
import BurndownTab from './ShowComponents/BurndownTab.vue';
import UsersTab from './ShowComponents/UsersTab.vue';
import SprintsTab from './ShowComponents/SprintsTab.vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const toast = useToast();
const { props } = usePage();

// Board data
const board = props.board;
const columns = ref(props.columns);
const users = ref(props.users);
const activeTab = ref('board');
// Parse sprints from board if it's a string
const sprints = ref(typeof props.board.sprints === 'string' 
    ? JSON.parse(props.board.sprints) 
    : props.sprints);
const expandedColumns = ref({});
// Parse freeDates from board if it's a string
const freeDates = typeof props.board.non_working_days === 'string' 
    ? JSON.parse(props.board.non_working_days) 
    : props.freeDates;

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

// Add these refs near the other ref declarations
const showDeleteSprintConfirmation = ref(false);
const sprintToDelete = ref(null);

// Add these functions near the other sprint-related functions
const toggleDeleteSprint = (sprintId = null) => {
    document.body.style.overflow = showDeleteSprintConfirmation.value ? '' : 'hidden';
    showDeleteSprintConfirmation.value = !showDeleteSprintConfirmation.value;
    sprintToDelete.value = sprintId;
};

const handleDeleteSprint = async () => {
    try {
        const response = await axios.post('/api/deleteSprint', {
            board_id: board.id,
            sprint_id: sprintToDelete.value
        });

        if (response.data.message) {
            sprints.value = sprints.value.filter(sprint => sprint.id !== sprintToDelete.value);
            
            // If deleted sprint was selected in burndown chart, switch to board view
            if (selectedPeriod.value === sprintToDelete.value) {
                handlePeriodChange('board');
            }
            
            toast.success('Sprint deleted successfully');
            toggleDeleteSprint();
        } else {
            throw new Error(response.data.error || 'Failed to delete sprint');
        }
    } catch (error) {
        console.error('Error deleting sprint:', error);
        toast.error(error.message || 'Failed to delete sprint');
    }
};

const handleCreateSprint = async () => {
    try {
        const response = await axios.post('/api/createSprint', {
            board_id: board.id,
            title: 'New Sprint',
        });
        if (response.data.sprints) {
            sprints.value = response.data.sprints;
        } else {
            throw new Error(response.data.error || 'Failed to create sprint');
        }
    } catch (error) {
        console.error('Error creating sprint:', error);
        toast.error(error.message || 'Failed to create sprint');
    }
};

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
                <BoardTab
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

            <div v-if="activeTab === 'burndown'">
                <BurndownTab
                    :board="board"
                    :columns="columns"
                    :sprints="sprints"
                    :freeDates="freeDates"
                    :chartData="chartData"
                    :chartOptions="chartOptions"
                    @period-change="handlePeriodChange"
                />
            </div>
            <!-- List View Tab -->
            <div v-if="activeTab === 'list'">
                <ListTab 
                    :columns="columns"
                    @delete-card="toggleDeleteCard"
                />
            </div>
            <!-- Users Tab -->
            <div v-if="activeTab === 'users'">
                <UsersTab 
                    :users="users"
                    :board="board"
                />
            </div>

            <div v-if="activeTab === 'sprints'">
                <SprintsTab 
                    :sprints="sprints"
                    :board="board"
                    @sprint-deleted="handlePeriodChange('board')"
                    @sprint-updated="id => id === selectedPeriod && handlePeriodChange(id)"
                />
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

            <div v-if="showDeleteSprintConfirmation" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Sprint</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to delete this sprint? This action cannot be undone.</p>
                    <div class="flex justify-end space-x-3">
                        <button 
                            @click="toggleDeleteSprint()" 
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="handleDeleteSprint" 
                            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>