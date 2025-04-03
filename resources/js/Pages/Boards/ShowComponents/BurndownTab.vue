<script setup>
import { ref, watch, computed } from 'vue';
import { Line } from 'vue-chartjs';
import { buildChart, generateBurndownData } from '@/Helpers/BurndownHelper';

const props = defineProps({
    board: Object,
    columns: Array,
    sprints: Array,
    freeDates: Array,
    chartData: Object,
    chartOptions: Object,
    currentSprint: Object // Add this prop
});

const emit = defineEmits(['period-change']);

const selectedPeriod = ref('board');

const handlePeriodChange = (event) => {
    selectedPeriod.value = event.target.value;
    emit('period-change', selectedPeriod.value);
};

// Format date to show only the date part (YYYY-MM-DD)
const formatDate = (dateString) => {
    if (!dateString) return '';
    return dateString.split(' ')[0];
};

// Add a watch to detect changes in columns data
watch(() => props.columns, () => {
    // This will trigger when columns change, but the parent component
    // will handle the actual chart update through the burndown-update event
}, { deep: true });

// Modified chartData computed property
const chartData = computed(() => {
    if (!props.currentSprint?.days?.length) {
        return {
            labels: [],
            datasets: [{
                label: 'Remaining Points',
                data: [],
                borderColor: '#3B82F6',
                fill: true
            }]
        };
    }
    
    return {
        labels: props.currentSprint.days.map(day => day.date),
        datasets: [{
            label: 'Remaining Points',
            data: props.currentSprint.days.map(day => day.remaining_points),
            borderColor: '#3B82F6',
            fill: true
        }]
    };
});

// Add fallback for undefined sprint
const sprintProgress = computed(() => {
    if (!props.currentSprint?.total_points) return 0;
    const completed = props.currentSprint.completed_points || 0;
    return ((completed / props.currentSprint.total_points) * 100).toFixed(1);
});
</script>

<template>
    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="mb-6 px-4">
            <label for="period-select" class="block text-sm font-medium text-gray-700 mb-1">Select Period</label>
            <select 
                id="period-select" 
                v-model="selectedPeriod"
                @change="handlePeriodChange"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
            >
                <option value="board">Entire Board ({{ formatDate(board.start_date) }} to {{ formatDate(board.end_date) }})</option>
                <option 
                    v-for="sprint in sprints" 
                    :key="sprint.id" 
                    :value="sprint.id"
                >
                    {{ sprint.title }} ({{ formatDate(sprint.start_date) }} to {{ formatDate(sprint.end_date) }})
                </option>
            </select>
        </div>
        
        <div class="overflow-x-auto">
            <div class="flex justify-center items-center h-full min-w-[800px]">
                <div class="w-full h-[450px]">
                    <Line 
                        v-if="chartData.labels.length > 0" 
                        :data="chartData" 
                        :options="chartOptions"
                    />
                    <div v-else class="h-full flex items-center justify-center text-gray-500">
                        No sprint data available
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>