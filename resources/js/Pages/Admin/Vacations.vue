<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import { ref, watch } from 'vue';
import { useToast } from 'vue-toastification';

const toast = useToast();
const page = usePage();

const vacations = ref(page.props.vacations);
const showDeleteConfirmation = ref(false);
const vacationToDelete = ref(null);
const selectedVacationId = ref(null);
const selectedVacation = ref(null);

const selectedDates = ref([])
const datesJson = JSON.stringify(selectedDates.value);

watch(datesJson, (newValue, oldValue) => {
    console.log('New value:', newValue);
    console.log('Old value:', oldValue);
})

// New vacation form
const showNewVacationForm = ref(false);
const newVacation = ref({
    schoolyear: '',
    vacation_dates: '[]'
});
const vacationDates = ref([]);

// Watch for changes in selectedDates
watch(selectedDates, (newDates) => {
    if (newDates && newDates.length > 0) {
        // Format dates to ISO strings and update vacationDates
        vacationDates.value = newDates.map(date => date.toISOString().split('T')[0]);
        // Update the JSON string in newVacation
        newVacation.value.vacation_dates = JSON.stringify(vacationDates.value);
    } else {
        vacationDates.value = [];
        newVacation.value.vacation_dates = '[]';
    }
});

// Function to remove a date from the array
const removeDate = (dateToRemove) => {
    // Remove from vacationDates
    vacationDates.value = vacationDates.value.filter(date => date !== dateToRemove);
    // Also remove from selectedDates
    selectedDates.value = selectedDates.value.filter(date => 
        date.toISOString().split('T')[0] !== dateToRemove
    );
    // Update JSON string
    newVacation.value.vacation_dates = JSON.stringify(vacationDates.value);
};

const toggleNewVacationForm = () => {
    showNewVacationForm.value = !showNewVacationForm.value;
    if (!showNewVacationForm.value) {
        // Reset form when closing
        newVacation.value = {
            schoolyear: '',
            vacation_dates: '[]'
        };
        vacationDates.value = [];
        selectedDates.value = [];
    }
};
const createVacation = () => {
    // Ensure vacation_dates is properly formatted as JSON string
    newVacation.value.vacation_dates = JSON.stringify(vacationDates.value);
    
    console.log('Sending data:', newVacation.value);

    axios.post('/api/vacations/createVacation', newVacation.value)
        .then(response => {
            if (response.data.vacation) {
                vacations.value.push(response.data.vacation);
                selectedVacationId.value = response.data.vacation.id;
                toggleNewVacationForm();
                toast.success('Vacation created successfully');
            } else {
                toast.error(response.data.error || 'Failed to create vacation');
            }
        })
        .catch(error => {
            console.error('Error creating vacation:', error);
            toast.error('Failed to create vacation');
        });
};

// Watch for changes in selectedVacationId and fetch vacation details
watch(selectedVacationId, async (newId) => {
    if (newId) {
        try {
            const response = await axios.post('/api/vacations/getVacation', { vacationId: newId });
            const vacation = response.data;
            selectedVacation.value = {
                ...vacation,
                vacation_dates: JSON.parse(vacation.vacation_dates || '[]')
            };
        } catch (error) {
            console.error('Error fetching vacation:', error);
            toast.error('Failed to load vacation details');
            selectedVacation.value = null;
        }
    } else {
        selectedVacation.value = null;
    }
});

// Initialize with highest ID vacation
if (vacations.value && vacations.value.length > 0) {
    const highestIdVacation = vacations.value.reduce((prev, current) => 
        (prev.id > current.id) ? prev : current
    );
    selectedVacationId.value = highestIdVacation.id;
}

const toggleDeleteConfirmation = (vacationId = null) => {
    showDeleteConfirmation.value = !showDeleteConfirmation.value;
    vacationToDelete.value = vacationId;
};

const handleDelete = () => {
    axios.post(`/api/vacations/deleteVacation`, { vacationId: vacationToDelete.value })
        .then(response => {
            if (response.data.message) {
                vacations.value = vacations.value.filter(vacation => vacation.id !== vacationToDelete.value);
                if (selectedVacationId.value === vacationToDelete.value) {
                    if (vacations.value.length > 0) {
                        const highestIdVacation = vacations.value.reduce((prev, current) => 
                            (prev.id > current.id) ? prev : current
                        );
                        selectedVacationId.value = highestIdVacation.id;
                    } else {
                        selectedVacationId.value = null;
                        showNewVacationForm.value = true;
                    }
                }
                toggleDeleteConfirmation();
                toast.success(response.data.message);
            } else {
                toast.error(response.data.error);
            }
        })
        .catch(error => {
            console.error('Error deleting vacation:', error);
            toast.error('Failed to delete vacation');
        });
};
</script>

<template>
    <Head title="Manage Vacations" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Manage Vacations
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <h3 class="text-lg font-semibold text-gray-900">School Vacations</h3>
                                <select 
                                    v-if="vacations"
                                    v-model="selectedVacationId"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                >
                                    <option v-for="vacation in vacations" :key="vacation.id" :value="vacation.id">
                                        {{ vacation.schoolyear }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex space-x-2">
                                <button
                                    v-if="selectedVacation"
                                    @click="toggleDeleteConfirmation(selectedVacation.id)"
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                                >
                                    Delete Vacation
                                </button>
                                <button
                                    @click="toggleNewVacationForm"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                >
                                    {{ (!vacations || vacations.length === 0) ? 'Save Vacation' : (showNewVacationForm ? 'Cancel' : 'Add Vacation') }}
                                </button>
                            </div>
                        </div>
                        <!-- New Vacation Form -->
                        <div v-if="showNewVacationForm || (!vacations || vacations.length === 0)" class="mt-6 p-4 border border-gray-200 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-4">
                                {{ (!vacations || vacations.length === 0) ? 'Create First Vacation' : 'Create New Vacation' }}
                            </h4>
                            <div class="space-y-4">
                                <div>
                                    <label for="schoolyear" class="block text-sm font-medium text-gray-700">School Year</label>
                                    <input 
                                        type="text" 
                                        id="schoolyear" 
                                        v-model="newVacation.schoolyear" 
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="e.g. 2023-2024"
                                    >
                                </div>
                                <!-- Date Picker -->
                                <div>
                                    <label for="vacation-date" class="block text-sm font-medium text-gray-700">Select Vacation Dates</label>
                                    <div class="mt-1">
                                        <VueDatePicker 
                                            v-model="selectedDates" 
                                            multi-dates 
                                            :enable-time-picker="false"
                                            placeholder="Select vacation dates"
                                            class="w-full"
                                        />
                                    </div>
                                </div>
                                <!-- Selected Dates List -->
                                <div v-if="vacationDates.length > 0" class="mt-4">
                                    <h5 class="text-sm font-medium text-gray-700 mb-2">Selected Dates:</h5>
                                    <div class="space-y-2">
                                        <div v-for="date in vacationDates" :key="date" 
                                            class="border border-gray-200 rounded-lg p-2 hover:bg-gray-50 flex justify-between items-center">
                                            <div>
                                                <span class="text-gray-900">{{ new Date(date).toLocaleDateString() }}</span>
                                            </div>
                                            <button @click="removeDate(date)" class="text-red-500 hover:text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end">
                                    <button 
                                        @click="createVacation" 
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                                        :disabled="!newVacation.schoolyear"
                                    >
                                        {{ (!vacations || vacations.length === 0) ? 'Save Vacation' : 'Create Vacation' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Selected Vacation Details -->
                        <div v-if="selectedVacation" class="mt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="font-medium text-gray-900">Vacation Dates for {{ selectedVacation.schoolyear }}</h4>
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Add Date
                                </button>
                            </div>
                            <!-- Vacation Dates List -->
                            <div class="space-y-2">
                                <div v-if="!selectedVacation.vacation_dates.length" class="text-center py-4 text-gray-500">
                                    No dates have been added to this vacation.
                                </div>
                                <div v-else v-for="date in selectedVacation.vacation_dates" :key="date" 
                                    class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50 flex justify-between items-center">
                                    <div>
                                        <span class="text-gray-900">{{ new Date(date).toLocaleDateString() }}</span>
                                    </div>
                                    <button class="text-red-500 hover:text-red-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- No Vacations Message -->
                        <div v-if="(!vacations || vacations.length === 0) && !showNewVacationForm" class="text-center py-8 text-gray-500">
                            No vacations have been created yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirmation" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <p class="text-gray-800 mb-4">Are you sure you want to delete this vacation?</p>
            <div class="flex justify-between">
                <button @click="handleDelete" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Delete
                </button>
                <button @click="toggleDeleteConfirmation()" class="px-4 py-2 bg-gray-400 text-white rounded-lg hover:bg-gray-500">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>