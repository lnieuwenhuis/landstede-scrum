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
    chartOptions: Object
});

const selectedPeriod = ref('board');

const emit = defineEmits(['period-change']);

const handlePeriodChange = (value) => {
    emit('period-change', value);
};

</script>

<template>
    <div class="flex-1">
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
</template>