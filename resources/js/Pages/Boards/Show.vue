<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend } from 'chart.js';
import { buildChart } from '@/Helpers/BurndownHelper';
import { useToast } from 'vue-toastification';
import { useTranslations } from '@/translations';

const toast = useToast();
const { __ } = useTranslations();

// Importing all tabs
import BoardTab from './ShowComponents/BoardTab.vue';
import ListTab from './ShowComponents/ListTab.vue';
import BurndownTab from './ShowComponents/BurndownTab.vue';
import UsersTab from './ShowComponents/UsersTab.vue';
import SprintsTab from './ShowComponents/SprintsTab.vue';
import SettingsTab from './ShowComponents/SettingsTab.vue'; // Import the new SettingsTab component

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend);

const { props } = usePage();

// Board data
const board = ref(props.board); 
const columns = ref(props.columns);
const users = ref(props.users);
const activeTab = ref(__('Kanban Board'));
const categories = ref(props.categories);
const isAdmin = props.currentUser.role === 'admin';
const currentUser = ref(props.currentUser);
const currentSprint = ref(props.currentSprint);

// Parse sprints from board if it's a string
const sprints = ref(typeof props.board.sprints === 'string' 
    ? JSON.parse(props.board.sprints) 
    : props.sprints);

// Parse freeDates from board if it's a string
const freeDates = typeof props.freeDates === 'string' 
    ? JSON.parse(props.freeDates) 
    : props.freeDates;

const weekdays = typeof props.weekdays === 'string'
    ? JSON.parse(props.weekdays)
    : props.weekdays;

// Burndown chart settings
const selectedPeriod = ref('board');
const selectedSprint = ref(null);
const startDate = ref(board.start_date);
const endDate = ref(board.end_date);

let { chartData, chartOptions } = buildChart(
    board.value,
    currentSprint.value || null, // Handle undefined currentSprint
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
        startDate.value = board.value.start_date;
        endDate.value = board.value.end_date;
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
        board.value, 
        selectedSprint.value, 
        columns, 
        startDate.value, 
        endDate.value,
        freeDates
    );
    
    chartData.value = chartResult.chartData.value;
    chartOptions.value = chartResult.chartOptions.value;
};

onMounted(() => {
    handlePeriodChange('board')
})

// Update chart when columns change
// Add this watch to handle column updates
watch(columns, (newColumns) => {
    updateBurndownChart();
}, { deep: true });

const updateBurndownChart = () => {
    // Force fresh calculation with current columns value
    const result = buildChart(
        board.value,
        selectedSprint.value,
        columns, 
        startDate.value,
        endDate.value,
        freeDates
    );
    
    // Replace chart data with new references
    chartData.value = { ...result.chartData.value };
    chartOptions.value = { ...result.chartOptions.value };
};

const showDescription = ref(false);

const handleSprintUpdated = (payload) => {
    // Check if the payload contains the expected arrays
    if (payload && payload.sprints && payload.columns) {
        // Directly replace the local columns state with the array from the backend
        columns.value = payload.columns;

        // Update the local list of all sprints
        sprints.value = payload.sprints;

        // Find and update the current sprint based on the updated list
        // Prioritize 'active', then 'planning', then fallback
        const updatedCurrent = 
            payload.sprints.find(s => s.status === 'active') || 
            payload.sprints.find(s => s.status === 'planning') || 
            payload.sprints[0] || // Fallback to the first sprint if none are active/planning
            null; 
        currentSprint.value = updatedCurrent;

        // Update the burndown chart as both sprints and columns might have changed
        updateBurndownChart(); 

    } else {
        console.error("Received unexpected payload from sprint update:", payload);
        toast.error("Failed to process sprint update data."); // Use toast for user feedback
    }
};


const handleSprintDeleted = (sprintId) => {
    // If the deleted sprint is the current sprint, find a new current sprint
    if (currentSprint.value && currentSprint.value.id === sprintId) {
         // Remove the deleted sprint first
        const remainingSprints = sprints.value.filter(s => s.id !== sprintId);
        // Find a new current sprint (active > planning > first)
        currentSprint.value = 
            remainingSprints.find(s => s.status === 'active') ||
            remainingSprints.find(s => s.status === 'planning') ||
            remainingSprints[0] ||
            null;
        sprints.value = remainingSprints; // Update the main sprints list
    } else {
        // Just remove the sprint from the list if it wasn't the current one
        sprints.value = sprints.value.filter(s => s.id !== sprintId);
    }
    
    // Update the burndown chart if the selected sprint for the chart was deleted
    if (selectedSprint.value && selectedSprint.value.id === sprintId) {
        selectedSprint.value = null;
        selectedPeriod.value = 'board'; // Reset period selector to 'board'
        handlePeriodChange('board'); // Trigger chart update for board period
    } else {
        // Even if the selected wasn't deleted, the data might change, so update
        updateBurndownChart();
    }
};

// Add handler for board updates from SettingsTab
const handleBoardUpdated = (updatedBoardData) => {
    board.value = { ...board.value, ...updatedBoardData }; // Merge updates into the local board ref
};

const dropdownRef = ref(null);
const dropdownOpen = ref(false);

const selectTab = (tab) => {
    activeTab.value = tab;
    if (dropdownRef.value) {
        dropdownRef.value.open = false;
    }
};

</script>

<template>
    <Head :title="__('Board')" />

    <AuthenticatedLayout>
        <div class="container mx-auto px-6 py-3">
            <!-- Tab Navigation -->
                <div class="border-b border-gray-200 mb-4">
                    <nav class="flex flex-wrap justify-between" aria-label="Tabs">
                        <!-- Mobile dropdown menu -->
                        <div class="mobile-dropdown w-full sm:hidden border-b border-gray-200">
                            <details ref="dropdownRef" class="dropdown w-full relative">
                                <summary class="py-2 px-1 font-medium text-sm flex items-center justify-between w-full cursor-pointer focus:outline-none transition-all duration-200 border-b-2" :class="dropdownOpen ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-700'">
                                    {{ __(activeTab) }}
                                    <svg class="h-5 w-5 ml-2 transition-transform duration-200" :class="{'rotate-180': dropdownOpen}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </summary>
                                <div class="dropdown-menu absolute z-10 mt-1 w-full bg-white shadow-lg py-1 text-base overflow-auto max-h-60 transition-all duration-200">
                                    <button
                                        v-for="tab in [__('Kanban Board'), __('List'), __('Burndown'), __('Sprints'), __('Users'), __('Settings')]" 
                                        :key="tab"
                                        @click="selectTab(tab)"
                                        class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 transition-colors duration-150"
                                        :class="activeTab === tab ? 'text-blue-600' : 'text-gray-700'"
                                    >
                                        {{ tab }}
                                    </button>
                                </div>
                            </details>
                        </div>
                        <!-- Main tabs on the left -->
                        <div class="hidden sm:flex space-x-2 sm:space-x-4 md:space-x-6">
                            <button
                                v-for="tab in [__('Kanban Board'), __('List'), __('Burndown'), __('Sprints')]" 
                                :key="tab"
                                @click="activeTab = tab"
                                :class="[
                                    'py-2 px-1 border-b-2 font-medium text-sm whitespace-nowrap',
                                    activeTab === tab
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                {{ tab }}
                            </button>
                        </div>
                        
                        <!-- User and Settings tabs on the right -->
                        <div class="hidden sm:flex space-x-2 sm:space-x-4 md:space-x-6">
                            <button
                                v-for="tab in [__('Users'), __('Settings')]" 
                                :key="tab"
                                @click="activeTab = tab"
                                :class="[
                                    'py-2 px-1 border-b-2 font-medium text-sm whitespace-nowrap',
                                    activeTab === tab
                                        ? 'border-blue-500 text-blue-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                ]"
                            >
                                {{ tab }}
                            </button>
                        </div>
                    </nav>
                </div>

            <!-- Tab Content -->
            <div v-if="activeTab === __('Kanban Board')">
                <BoardTab
                    v-if="activeTab === __('Kanban Board')"
                    :columns="columns"
                    :board="board" 
                    :users="users"
                    :show-description="showDescription"
                    :current-sprint="currentSprint"
                    :categories="categories"
                    @columns-updated="columns = $event"
                    @burndown-update="updateBurndownChart"
                    @toggle-description="showDescription = !showDescription"
                />
            </div>

            <div v-if="activeTab === __('Burndown')">
                <BurndownTab
                    :board="board"
                    :columns="columns"
                    :sprints="sprints"
                    :freeDates="freeDates"
                    :chartData="chartData"
                    :chartOptions="chartOptions"
                    :show-description="showDescription"
                    :current-sprint="currentSprint"
                    @period-change="handlePeriodChange"
                    @toggle-description="showDescription = !showDescription"
                />
            </div>

            <!-- List View Tab -->
            <div v-if="activeTab === __('List')">
                <ListTab
                    :columns="columns"
                    :board="board" 
                    :show-description="showDescription"
                    :categories="categories"
                    @toggle-description="showDescription = !showDescription"
                />
            </div>

            <!-- Users Tab -->
            <div v-if="activeTab === __('Users')">
                <UsersTab
                    :users="users"
                    :board="board"
                    @users-updated="users = $event"
                />
            </div>

            <!-- Sprints Tab -->
            <SprintsTab
                v-if="activeTab === __('Sprints')"
                :sprints="sprints"
                :board="board" 
                :is-admin="isAdmin"
                @sprint-updated="handleSprintUpdated"
                @sprint-deleted="handleSprintDeleted"
            />

            <SettingsTab
                v-if="activeTab === __('Settings')"
                :board="board"
                :weekdays="weekdays"
                :current-user="currentUser"
                @board-updated="handleBoardUpdated"
            />

        </div>
    </AuthenticatedLayout>
</template>
