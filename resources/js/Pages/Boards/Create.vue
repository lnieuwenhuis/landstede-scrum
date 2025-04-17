<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const toast = useToast();

// Helper function to initialize weekdays state
const initializeWeekdaysState = () => [
    { monday: 0 }, { tuesday: 0 }, { wednesday: 0 }, { thursday: 0 }, { friday: 0 }, { saturday: 1 }, { sunday: 1 }
];

// Board form data
const board = ref({
    title: '',
    description: '',
    startDate: null,
    endDate: null,
    sprints: [],
    non_working_days: [], 
    weekdays: initializeWeekdaysState(), 
    status: 'active'
});

// UI state
const isSubmitting = ref(false);
const showSprintEditor = ref(false);
const selectedDays = ref([]);
const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']; 
const selectedWeekdays = ref(['Saturday', 'Sunday']);

// Computed properties
const dateRange = computed(() => {
    if (!board.value.startDate || !board.value.endDate) return null;
    return {
        start: new Date(board.value.startDate),
        end: new Date(board.value.endDate)
    };
});

const totalDays = computed(() => {
    if (!dateRange.value) return 0;
    return Math.ceil((dateRange.value.end - dateRange.value.start) / (1000 * 60 * 60 * 24)) + 1;
});

const sprintCount = ref(4);
const sprintLength = computed(() => {
    if (totalDays.value === 0) return 0;
    return Math.ceil(totalDays.value / sprintCount.value);
});

// Generate sprints based on date range and sprint count
const generateSprints = () => {
    if (!dateRange.value) {
        toast.warning('Please select start and end dates first');
        return;
    }

    const sprints = [];
    const startDate = new Date(board.value.startDate);
    const sprintDuration = sprintLength.value;

    for (let i = 0; i < sprintCount.value; i++) {
        const sprintStart = new Date(startDate);
        sprintStart.setDate(sprintStart.getDate() + (i * sprintDuration));
        
        const sprintEnd = new Date(sprintStart);
        sprintEnd.setDate(sprintEnd.getDate() + sprintDuration - 1);
        
        // Ensure the last sprint doesn't exceed end date
        if (i === sprintCount.value - 1) {
            const endDate = new Date(board.value.endDate);
            if (sprintEnd > endDate) {
                sprintEnd.setTime(endDate.getTime());
            }
        }
        
        sprints.push({
            name: `Sprint ${i + 1}`,
            start_date: formatDate(sprintStart),
            end_date: formatDate(sprintEnd),
            status: 'inactive'
        });
    }
    
    board.value.sprints = sprints;
    showSprintEditor.value = true;
};

// Format date to YYYY-MM-DD
const formatDate = (date) => {
    return date.toISOString().split('T')[0];
};

// Handle weekday selection for BOTH non-working days calculation AND weekdays state
const toggleWeekday = (dayName) => {
    const index = selectedWeekdays.value.indexOf(dayName);
    if (index === -1) {
        selectedWeekdays.value.push(dayName);
    } else {
        selectedWeekdays.value.splice(index, 1);
    }
    updateNonWorkingDays(); 

    const lowerDayName = dayName.toLowerCase();
    const dayObject = board.value.weekdays.find(item => Object.keys(item)[0] === lowerDayName);
    if (dayObject) {
        dayObject[lowerDayName] = selectedWeekdays.value.includes(dayName) ? 1 : 0;
    } else {
        console.warn(`Could not find weekday object for: ${dayName}`);
    }
};

// Update non-working days based on selected weekdays (Keep existing function)
const updateNonWorkingDays = () => {
    if (!dateRange.value) return;

    const nonWorkingDays = [];
    const start = new Date(dateRange.value.start);
    const end = new Date(dateRange.value.end);

    // Add all selected weekdays within the date range
    for (let day = new Date(start); day <= end; day.setDate(day.getDate() + 1)) {
        // Adjust index calculation if needed based on how 'weekdays' array is ordered
        const dayOfWeekJS = day.getDay(); 
        const weekdayIndex = dayOfWeekJS === 0 ? 6 : dayOfWeekJS - 1;
        const weekdayName = weekdays[weekdayIndex];

        if (selectedWeekdays.value.includes(weekdayName)) {
            nonWorkingDays.push(formatDate(day));
        }
    }

    // Add individually selected dates (Keep if using selectedDays)
    if (selectedDays.value.length > 0) {
        selectedDays.value.forEach(date => {
            const formattedDate = formatDate(new Date(date));
            if (!nonWorkingDays.includes(formattedDate)) {
                nonWorkingDays.push(formattedDate);
            }
        });
    }

    board.value.non_working_days = nonWorkingDays.sort();
};

// Watch for date changes to update non-working days
watch([() => board.value.startDate, () => board.value.endDate], () => {
    if (board.value.startDate && board.value.endDate) {
        updateNonWorkingDays();
    }
});

watch(selectedDays, () => { 
    updateNonWorkingDays();
});

// Submit the form
const submitForm = async () => {
    if (!board.value.title || !board.value.description || !board.value.startDate || !board.value.endDate) {
        toast.warning('Please fill in all required fields');
        return;
    }

    isSubmitting.value = true;

    try {
        // Prepare data for submission, including BOTH non_working_days and weekdays
        const formData = {
            title: board.value.title,
            description: board.value.description,
            startDate: board.value.startDate ? formatDate(new Date(board.value.startDate)) : null,
            endDate: board.value.endDate ? formatDate(new Date(board.value.endDate)) : null,
            status: board.value.status,
            sprints: JSON.stringify(board.value.sprints),
            non_working_days: JSON.stringify(board.value.non_working_days), // Keep existing
            weekdays: JSON.stringify(board.value.weekdays) // Add new weekdays
        };

        const response = await axios.post('/api/boards/storeBoard', formData);

        if (response.data.status === 'redirect') {
            toast.success(response.data.message);
            router.visit(`/boards/${response.data.board_id}`);
        } else {
            toast.success('Board created successfully');
        }
    } catch (error) {
        console.error('Error creating board:', error);
        if (error.response && error.response.data && error.response.data.errors) {
            console.error("Validation Errors:", error.response.data.errors);
            toast.error(error.response.data.message || 'Validation failed. Please check your input.');
        } else {
            toast.error('Failed to create board. Please try again.');
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Create Board" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Create New Board
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submitForm">
                            <!-- Basic Information -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label for="title" class="block text-sm font-medium text-gray-700">Board Title</label>
                                        <input 
                                            type="text" 
                                            id="title" 
                                            v-model="board.title" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Enter board title"
                                            required
                                        >
                                    </div>
                                    <div>
                                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                        <textarea 
                                            id="description" 
                                            v-model="board.description" 
                                            rows="3"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Enter board description"
                                            required
                                        ></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Date Range -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Project Timeline</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="start-date" class="block text-sm font-medium text-gray-700">Start Date</label>
                                        <VueDatePicker 
                                            v-model="board.startDate" 
                                            :enable-time-picker="false"
                                            placeholder="Select start date"
                                            class="w-full mt-1"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label for="end-date" class="block text-sm font-medium text-gray-700">End Date</label>
                                        <VueDatePicker 
                                            v-model="board.endDate" 
                                            :enable-time-picker="false"
                                            placeholder="Select end date"
                                            class="w-full mt-1"
                                            required
                                        />
                                    </div>
                                </div>
                                <div v-if="dateRange" class="mt-2 text-sm text-gray-500">
                                    Project duration: {{ totalDays }} days
                                </div>
                            </div>

                            <!-- Sprints -->
                            <div class="mb-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Sprints (Optional)</h3>
                                    <button 
                                        type="button"
                                        @click="generateSprints"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                        :disabled="!dateRange"
                                    >
                                        Generate Sprints
                                    </button>
                                </div>
                                
                                <div v-if="!showSprintEditor && !board.sprints.length" class="text-center py-4 text-gray-500 border border-dashed border-gray-300 rounded-lg">
                                    No sprints created yet. Click "Generate Sprints" to create them automatically.
                                </div>
                                
                                <div v-if="showSprintEditor" class="mt-4">
                                    <div class="mb-4">
                                        <label for="sprint-count" class="block text-sm font-medium text-gray-700">Number of Sprints</label>
                                        <div class="flex items-center mt-1">
                                            <input 
                                                type="range" 
                                                id="sprint-count" 
                                                v-model.number="sprintCount" 
                                                min="1" 
                                                max="10"
                                                class="w-full mr-4"
                                                @change="generateSprints"
                                            >
                                            <span class="text-gray-700">{{ sprintCount }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <div v-for="(sprint, index) in board.sprints" :key="index" 
                                            class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50">
                                            <div class="flex justify-between items-center">
                                                <h4 class="font-medium text-gray-900">{{ sprint.name }}</h4>
                                            </div>
                                            <div class="mt-2 text-sm text-gray-500">
                                                {{ new Date(sprint.start_date).toLocaleDateString() }} - {{ new Date(sprint.end_date).toLocaleDateString() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Non-working Days -->
                            <div class="mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Non-working Days</h3>
                                
                                <!-- Weekday Selection -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Non-working Weekdays</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="day in weekdays"
                                            :key="day"
                                            type="button"
                                            @click="toggleWeekday(day)"
                                            :class="[
                                                'px-3 py-2 rounded-md text-sm font-medium border',
                                                selectedWeekdays.includes(day) 
                                                    ? 'bg-blue-600 text-white border-blue-700'
                                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                            ]"
                                        >
                                            {{ day }}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button 
                                    type="submit"
                                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
                                    :disabled="isSubmitting"
                                >
                                    <span v-if="isSubmitting">Creating...</span>
                                    <span v-else>Create Board</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>