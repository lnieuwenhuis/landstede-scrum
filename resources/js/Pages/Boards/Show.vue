<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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

// Add this function to handle burndown chart updates
const updateBurndownChart = () => {
    // Regenerate chart with current settings
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

const showDescription = ref(false);
</script>

<template>
    <Head title="Board" />

    <AuthenticatedLayout>
        <div class="container mx-auto px-6 py-3">
            <!-- Tab Navigation - moved up to hug the navbar -->
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
                    :show-description="showDescription"
                    @columns-updated="() => {}"
                    @burndown-update="updateBurndownChart"
                    @toggle-description="showDescription = !showDescription"
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
                    :show-description="showDescription"
                    @period-change="handlePeriodChange"
                    @toggle-description="showDescription = !showDescription"
                />
            </div>
            
            <!-- List View Tab -->
            <div v-if="activeTab === 'list'">
                <ListTab 
                    :columns="columns"
                    :board="board"
                    :show-description="showDescription"
                    @toggle-description="showDescription = !showDescription"
                />
            </div>
            
            <!-- Users Tab -->
            <div v-if="activeTab === 'users'">
                <UsersTab 
                    :users="users"
                    :board="board"
                    :show-description="showDescription"
                    @toggle-description="showDescription = !showDescription"
                />
            </div>

            <div v-if="activeTab === 'sprints'">
                <SprintsTab 
                    :sprints="sprints"
                    :board="board"
                    :show-description="showDescription"
                    @toggle-description="showDescription = !showDescription"
                    @sprint-deleted="handlePeriodChange('board')"
                    @sprint-updated="id => id === selectedPeriod && handlePeriodChange(id)"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>