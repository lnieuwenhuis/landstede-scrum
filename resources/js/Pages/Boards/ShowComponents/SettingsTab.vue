<script setup>
import { ref, computed, watch, onMounted } from 'vue'; // Added watch and onMounted
import axios from 'axios';
import { useToast } from 'vue-toastification';
import { useTranslations } from '@/translations.js';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import VueDatePicker from '@vuepic/vue-datepicker'; 
import '@vuepic/vue-datepicker/dist/main.css'; 

const props = defineProps({
    board: Object,
    currentUser: Object,
    weekdays: Array | String,
});

const emit = defineEmits(['board-updated']);
const toast = useToast();
const { __ } = useTranslations();

const isOwner = computed(() => props.board.creator_id === props.currentUser.id);

// Helper to parse date string/object from backend/datepicker into a Date object
const parseDate = (dateInput) => {
    if (!dateInput) return null;
    // Handle cases where it might already be a Date object or a string
    return dateInput instanceof Date ? dateInput : new Date(dateInput);
};

// Helper function to initialize or parse weekdays state from props
const initializeWeekdays = (boardWeekdaysInput) => {
    const defaultWeekdays = [
        { 'monday': 0 }, { 'tuesday': 0 }, { 'wednesday': 0 }, { 'thursday': 0 }, { 'friday': 0 }, { 'saturday': 1 }, { 'sunday': 1 }
    ];

    // Handle potential string input from backend/storage
    let boardWeekdays = boardWeekdaysInput;
    if (typeof boardWeekdaysInput === 'string') {
        try {
            boardWeekdays = JSON.parse(JSON.parse(boardWeekdaysInput));
        } catch (e) {
            console.error(__("Failed to parse board weekdays JSON string:"), e);
            boardWeekdays = null;
        }
    }

    // Add validation: Check if it's a valid array of the expected structure
    if (Array.isArray(boardWeekdays) && boardWeekdays.length === 7 && boardWeekdays.every(item => typeof item === 'object' && item !== null && Object.keys(item).length === 1)) {
        // It's valid, return it
        return boardWeekdays;
    } else {
        // It's invalid or null/undefined after parsing attempt
        if (boardWeekdays !== null && boardWeekdays !== undefined && typeof boardWeekdays !== 'string') { // Avoid logging for initial undefined/null or strings that failed parse
            console.warn(__("Board weekdays data received but was invalid or empty, using default."), boardWeekdaysInput);
        }
        // Return a deep copy of the default to prevent accidental mutation if needed elsewhere
        return JSON.parse(JSON.stringify(defaultWeekdays));
    }
};

const title = ref(props.board.title);
const description = ref(props.board.description || '');
const startDate = ref(parseDate(props.board.start_date));
const endDate = ref(parseDate(props.board.end_date));
// Initialize nonWorkingDays as empty, it will be calculated
const nonWorkingDays = ref([]);
const weekdaysState = ref(initializeWeekdays(props.weekdays));
const status = ref(props.board.status || 'active');
const loading = ref(false);
const errors = ref({});

// Keep options for Monday-Sunday order and string values for weekdaysState UI
const daysOfWeekOptions = [
    { label: __('Monday'), value: 'monday' },
    { label: __('Tuesday'), value: 'tuesday' },
    { label: __('Wednesday'), value: 'wednesday' },
    { label: __('Thursday'), value: 'thursday' },
    { label: __('Friday'), value: 'friday' },
    { label: __('Saturday'), value: 'saturday' },
    { label: __('Sunday'), value: 'sunday' },
];

// Keep helper function to get status for weekdaysState
const getWeekdayStatus = (dayKey) => {
    const dayObject = weekdaysState.value.find(item => Object.keys(item)[0] === dayKey);
    return dayObject ? dayObject[dayKey] : 0; // Default to 0 if not found
};

// Helper to get lowercase day name from Date object's getDay()
const getDayNameFromDate = (date) => {
    const dayIndex = date.getDay(); // 0 = Sun, 1 = Mon, ..., 6 = Sat
    const names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    return names[dayIndex];
};

// Helper to format Date object to 'YYYY-MM-DD' string
const formatDate = (date) => {
    if (!date) return null;
    const d = date instanceof Date ? date : new Date(date);
    if (isNaN(d.getTime())) return null;
    const year = d.getFullYear();
    const month = (d.getMonth() + 1).toString().padStart(2, '0');
    const day = d.getDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// New function to calculate non-working days based on weekdaysState and date range
const recalculateNonWorkingDays = () => {
    if (!startDate.value || !endDate.value || !Array.isArray(weekdaysState.value)) {
        nonWorkingDays.value = []; // Clear if inputs are invalid
        return;
    }

    const calculatedDays = [];
    const start = new Date(startDate.value);
    const end = new Date(endDate.value);

    // Ensure start date is not after end date
    if (start > end) {
         nonWorkingDays.value = [];
         return;
    }

    // Iterate through each day in the range
    for (let day = new Date(start); day <= end; day.setDate(day.getDate() + 1)) {
        const currentDay = new Date(day); // Create copy to avoid modifying loop variable directly
        const dayNameLower = getDayNameFromDate(currentDay);
        const status = getWeekdayStatus(dayNameLower);

        if (status === 1) { // If the weekday is marked as non-working (1)
            const formattedDate = formatDate(currentDay);
            if (formattedDate) { // Ensure formatting was successful
                 calculatedDays.push(formattedDate);
            }
        }
    }

    nonWorkingDays.value = calculatedDays.sort();
};


// Keep function to toggle status in weekdaysState AND trigger recalculation
const toggleWeekday = (dayKey) => {
    const dayObject = weekdaysState.value.find(item => Object.keys(item)[0] === dayKey);
    if (dayObject) {
        dayObject[dayKey] = dayObject[dayKey] === 0 ? 1 : 0;
        recalculateNonWorkingDays(); // Recalculate when a weekday is toggled
    } else {
        console.warn(`Could not find weekday object for key: ${dayKey}`);
    }
};

// Recalculate on mount after initial values are set
onMounted(() => {
    recalculateNonWorkingDays();
});


// Helper to format Date object to 'YYYY-MM-DD' string for submission (can reuse formatDate)
const formatDateForSubmit = formatDate;


const submit = async () => {
    if (!isOwner.value) {
        toast.warning(__("Only the board owner can update settings."));
        return;
    }

    loading.value = true;
    errors.value = {};

    try {
        // Prepare payload
        const payload = {
            boardId: props.board.id,
            title: title.value,
            description: description.value,
            endDate: endDate.value ? formatDateForSubmit(endDate.value) : null,
            non_working_days: JSON.stringify(nonWorkingDays.value), 
            weekdays: JSON.stringify(weekdaysState.value), 
            status: status.value,
        };

        const response = await axios.post(`/api/boards/updateBoard`, payload);

        if (response.data && response.data.board) {
            emit('board-updated', response.data.board);
            title.value = response.data.board.title;
            description.value = response.data.board.description || '';
            weekdaysState.value = initializeWeekdays(response.data.board.weekdays);
            status.value = response.data.board.status;

            // Recalculate nonWorkingDays based on updated weekdays from response
            recalculateNonWorkingDays();

            toast.success(response.data.message || __("Board updated successfully!"));
        } else {
            const fallbackPayload = { ...props.board, ...payload };
            emit('board-updated', fallbackPayload);
            toast.success(__("Board updated successfully (fallback)!"));
        }

    } catch (error) {
        console.error(__("Error updating board settings:"), error);
        if (error.response && error.response.data && error.response.data.data.errors) {
            errors.value = error.response.data.errors;
            if (errors.value.non_working_days && !Array.isArray(errors.value.non_working_days)) {
                errors.value.non_working_days = [__("Invalid selection for non-working days (numeric).")];
            } else if (Array.isArray(errors.value.non_working_days)) {
                errors.value.non_working_days = [errors.value.non_working_days.join(' ')];
            }

            if (errors.value.weekdays && !Array.isArray(errors.value.weekdays)) {
                errors.value.weekdays = [__("Invalid selection or format for weekdays (object).")];
            } else if (Array.isArray(errors.value.weekdays)) {
                errors.value.weekdays = [errors.value.weekdays.join(' ')];
            }
            toast.error(error.response.data.message || __("Validation failed. Please check the form."));
        } else if (error.response && error.response.data && error.response.data.message) {
            toast.error(`${__("Error:")}: ${error.response.data.message}`);
        } else {
            toast.error(error.message || __("An unexpected error occurred while updating the board."));
        }
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <div class="flex justify-center">
        <div class="bg-white p-6 rounded-lg shadow w-full max-w-2xl">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">{{ __("Board Settings") }}</h2>

            <form @submit.prevent="submit">
                <!-- Board Title -->
                <div class="mb-4">
                    <InputLabel for="title" :value="__('Board Title')" />
                    <TextInput
                        id="title"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="title"
                        required
                        autofocus
                        :disabled="!isOwner || loading"
                        :class="{ 'bg-gray-100 cursor-not-allowed': !isOwner }"
                    />
                    <InputError class="mt-2" :message="errors.title ? errors.title[0] : ''" />
                </div>

                <!-- Board Description -->
                <div class="mb-6">
                    <InputLabel for="description" :value="__('Description')" />
                    <textarea
                        id="description"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        rows="4"
                        v-model="description"
                        :disabled="!isOwner || loading"
                        :class="{ 'bg-gray-100 cursor-not-allowed': !isOwner }"
                    ></textarea>
                    <InputError class="mt-2" :message="errors.description ? errors.description[0] : ''" />
                </div>
                
                <!-- End Date Picker -->
                <div class="mb-6">
                    <InputLabel for="end-date" :value="__('End Date')" />
                    <p class="text-xs text-gray-500 mt-1">{{ __("You can change the end date of the board. The start date cannot be modified.") }}</p>
                    <VueDatePicker 
                        id="end-date"
                        v-model="endDate" 
                        :enable-time-picker="false"
                        :placeholder="__('Select end date')"
                        class="w-full mt-1"
                        :disabled="!isOwner || loading"
                        :class="{ 'cursor-not-allowed': !isOwner }"
                    />
                    <InputError class="mt-2" :message="errors.endDate ? errors.endDate[0] : ''" />
                </div>
                
                <!-- Weekdays Selection (UI controls weekdaysState) -->
                <div class="mb-6">
                    <InputLabel :value="__('Working Days (Click to toggle)')" />
                    <p class="text-xs text-gray-500 mt-1">{{ __("Define the standard working (white) and non-working (blue) days for this board.") }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="day in daysOfWeekOptions"
                            :key="day.value"
                            type="button"
                            @click="isOwner && !loading ? toggleWeekday(day.value) : null"
                            :disabled="!isOwner || loading"
                            :class="[
                                // Base styles: padding, rounded corners, border, text size/alignment
                                'px-2.5 py-1.5 rounded-md text-sm font-medium border text-center transition-colors duration-150 ease-in-out',
                                // Conditional styles based on status (0 = working, 1 = non-working)
                                getWeekdayStatus(day.value) === 1 // Non-working (Selected)
                                    ? 'bg-blue-600 text-white border-blue-700' // Blue background, white text
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50', // White background, dark text
                                // Disabled/Loading styles
                                !isOwner || loading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' // Removed hover:opacity-90, added transition
                            ]"
                        >
                            {{ day.label }}
                        </button>
                    </div>
                    <!-- Display errors for weekdaysState -->
                    <InputError class="mt-2" :message="errors.weekdays ? errors.weekdays[0] : ''" />
                    <!-- Optionally display non_working_days errors if needed, though not directly editable here -->
                    <InputError v-if="errors.non_working_days" class="mt-2 text-red-600 text-xs" :message="`${__('Non-working days (numeric) error:')}: ${errors.non_working_days[0]}`" />
                </div>

                <!-- Status -->
                <div class="mb-6">
                    <InputLabel for="status" :value="__('Board Status')" />
                    <select
                        id="status"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                        v-model="status"
                        required
                        :disabled="!isOwner || loading"
                        :class="{ 'bg-gray-100 cursor-not-allowed': !isOwner }"
                    >
                        <option value="active">{{ __("Active") }}</option>
                        <option value="archived">{{ __("Archived") }}</option>
                    </select>
                    <InputError class="mt-2" :message="errors.status ? errors.status[0] : ''" />
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end">
                    <p v-if="!isOwner" class="text-sm text-gray-500 mr-4">
                        {{ __("Only the board owner can change these settings.") }}
                    </p>
                    <PrimaryButton :class="{ 'opacity-25': loading }" :disabled="!isOwner || loading">
                        {{ loading ? __("Saving...") : __("Save Changes") }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>