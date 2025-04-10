<script setup>
import { ref, watch } from 'vue';
import { Line } from 'vue-chartjs';

const props = defineProps({
    board: Object,
    columns: Array,
    sprints: Array,
    freeDates: Array,
    chartData: Object,
    chartOptions: Object,
    currentSprint: Object,
    showDescription: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['period-change', 'toggle-description']);

const selectedPeriod = ref('board');

const handlePeriodChange = (event) => {
    selectedPeriod.value = event.target.value;
    emit('period-change', selectedPeriod.value);
};

const toggleDescription = () => {
    emit('toggle-description');
};

// Format date to show only the date part
const formatDate = (dateString) => {
    if (!dateString) return '';
    return dateString.split(' ')[0];
};

// Replace the existing columns watch with this deep watcher
watch(() => [
    props.columns,
    props.chartData,
    props.chartOptions,
    props.freeDates,
    props.currentSprint
], () => {
    // Force chart update by changing component key
    chartKey.value++
}, { deep: true });

const chartKey = ref(0);
</script>

<template>
    <div class="flex justify-center">
        <div class="bg-white p-6 rounded-lg shadow w-full">
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-semibold text-gray-800">Burndown Chart</h2>
                <div class="flex items-center space-x-4">
                    <h2 class="text-xl font-semibold text-gray-800">{{ board.title }}</h2>
                    <span v-if="currentSprint" class="ml-3 text-gray-600">
                        <span>({{ currentSprint.title }})</span>
                    </span>
                    <span v-else class="ml-3 text-sm text-gray-600">
                        <span class="font-medium">No active sprint</span>
                    </span>
                    <button 
                        @click="toggleDescription" 
                        class="ml-2 text-gray-500 hover:text-gray-700 focus:outline-none"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path v-if="!showDescription" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            <path v-else fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Board description section -->
            <div v-if="showDescription && board.description" class="py-3 rounded-lg">
                <p class="text-gray-700">{{ board.description }}</p>
            </div>
            
            <div class="mb-6 px-4">
                <label for="period-select" class="block text-sm font-medium text-gray-700 mb-1" :class="{ '': showDescription, 'mt-6': !showDescription }" >Select Period</label>
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
                        <Line :key="chartKey" :data="chartData" :options="chartOptions"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>