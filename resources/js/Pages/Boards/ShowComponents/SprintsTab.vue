<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import ConfirmModal from './ConfirmModal.vue';
import { useTranslations } from '../../../translations.js';

const { __ } = useTranslations();
const toast = useToast();

const props = defineProps({
    isAdmin: Boolean,
    sprints: Array,
    board: Object
});

let loading = ref(false);

// Enhanced status styling function with icons
const getStatusStyles = (status) => {
    const styles = {
        active: {
            border: 'border-l-4 border-green-500 bg-green-50',
            badge: 'bg-green-100 text-green-800',
            text: 'text-green-600',
            icon: 'text-green-600',
            description: __('Currently active sprint'),
            iconPath: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />'
        },
        planning: {
            border: 'border-l-4 border-blue-500 bg-blue-50',
            badge: 'bg-blue-100 text-blue-800',
            text: 'text-blue-600',
            icon: 'text-blue-600',
            description: __('Sprint in planning phase'),
            iconPath: '<path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z" />'
        },
        inactive: {
            border: 'border-l-4 border-gray-500 bg-gray-50',
            badge: 'bg-gray-100 text-gray-800',
            text: 'text-gray-600',
            icon: 'text-gray-600',
            description: __('Future sprint'),
            iconPath: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />'
        },
        locked: {
            border: 'border-l-4 border-yellow-500 bg-yellow-50',
            badge: 'bg-yellow-100 text-yellow-800',
            text: 'text-yellow-600',
            icon: 'text-yellow-600',
            description: __('Completed, awaiting verification'),
            iconPath: '<path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />'
        },
        checked: {
            border: 'border-l-4 border-purple-500 bg-purple-50',
            badge: 'bg-purple-100 text-purple-800',
            text: 'text-purple-600',
            icon: 'text-purple-600',
            description: __('Completed and verified'),
            iconPath: '<path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />'
        }
    };
    
    return styles[status] || styles.inactive;
};

const sprints = ref(props.sprints);
const showSprintEditModal = ref(false);
const showDeleteSprintConfirmation = ref(false);
const sprintToDelete = ref(null);
const sprintToEdit = ref(null);
const editedSprintData = ref({
    title: '',
    start_date: '',
    end_date: '',
    status: ''
});

const renumberSprints = async () => {
    const sortedSprints = [...sprints.value].sort((a, b) => 
        new Date(a.start_date) - new Date(b.start_date)
    );
    
    const updatedSprints = [];
    let updatePromises = [];
    
    for (let i = 0; i < sortedSprints.length; i++) {
        const sprint = sortedSprints[i];
        const isDefaultName = /^Sprint \d+$/.test(sprint.title);
        const newTitle = isDefaultName ? `${__('Sprint')} ${i + 1}` : sprint.title;
        
        if (isDefaultName && sprint.title !== newTitle) {
            const updatePromise = axios.post('/api/updateSprint', {
                board_id: props.board.id,
                sprint_id: sprint.id,
                title: newTitle,
                start_date: sprint.start_date,
                end_date: sprint.end_date,
                status: sprint.status
            });
            
            updatePromises.push(updatePromise);
            sprint.title = newTitle;
        }
        
        updatedSprints.push(sprint);
    }
    
    if (updatePromises.length > 0) {
        try {
            await Promise.all(updatePromises);
            sprints.value = updatedSprints;
        } catch (error) {
            console.error('Error renumbering sprints:', error);
            toast.error(__('Failed to renumber some sprints'));
        }
    }
};

const toggleDeleteSprint = (sprintId = null) => {
    if (sprintId !== null && !showDeleteSprintConfirmation.value) {
        const sprintToCheck = sprints.value.find(sprint => sprint.id === sprintId);
        
        if (sprintToCheck && sprintToCheck.status === 'active') {
            toast.error(__('Cannot delete an active sprint'));
            return;
        }
    }
    
    document.body.style.overflow = showDeleteSprintConfirmation.value ? '' : 'hidden';
    showDeleteSprintConfirmation.value = !showDeleteSprintConfirmation.value;
    sprintToDelete.value = sprintId;
};

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

const closeSprintEditModal = () => {
    showSprintEditModal.value = false;
    document.body.style.overflow = '';
    sprintToEdit.value = null;
};

const handleSaveSprint = async () => {
    try {
        const response = await axios.post(`/api/updateSprint`, {
            board_id: props.board.id,
            sprint_id: sprintToEdit.value.id,
            title: editedSprintData.value.title,
            start_date: editedSprintData.value.start_date,
            end_date: editedSprintData.value.end_date,
            status: editedSprintData.value.status
        });
        
        if (response.data.message) {
            // Update the entire sprints array with the one returned from the server
            if (response.data.sprints) {
                sprints.value = response.data.sprints;
            } else {
                // Fallback to the old behavior if sprints array is not in the response
                const sprintIndex = sprints.value.findIndex(s => s.id === sprintToEdit.value.id);
                if (sprintIndex !== -1) {
                    sprints.value[sprintIndex] = {
                        ...sprints.value[sprintIndex],
                        ...editedSprintData.value
                    };
                }
            }
            
            // Emit the updated sprint with full data for column locking updates
            emit('sprint-updated', {
                id: sprintToEdit.value.id,
                sprints: sprints.value,
                sprint: response.data.sprints.find(s => s.id === sprintToEdit.value.id),
                columns: response.data.columns,
            });
            
            toast.success(__('Sprint updated successfully'));
            closeSprintEditModal();
        } else {
            throw new Error(response.data.error || __('Failed to update sprint'));
        }
    } catch (error) {
        console.error('Error updating sprint:', error);
        toast.error(error.message || __('Failed to update sprint'));
    }
};

const emit = defineEmits(['sprint-deleted', 'sprint-updated']);
</script>

<template>
    <div class="flex justify-center">
        <div class="bg-white p-4 rounded-lg shadow w-full">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-semibold text-gray-800 m-2">{{ __("Sprints") }}</h2>
                </div>                        
                <div class="space-y-4">
                    <div 
                        v-for="sprint in sprints" 
                        :key="sprint.id"
                        class="p-4 rounded-lg shadow-sm transition-all duration-200 hover:bg-black/5"
                        :class="getStatusStyles(sprint.status).border"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-medium text-gray-800 flex items-center">
                                    {{ sprint.title }}
                                    <span 
                                        class="ml-2 px-2 py-1 text-xs font-semibold rounded-full"
                                        :class="getStatusStyles(sprint.status).badge"
                                    >
                                        {{ __(sprint.status.charAt(0).toUpperCase() + sprint.status.slice(1)) }}
                                    </span>
                                </h3>
                                <div class="mt-2 space-y-1">
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">{{ __("Start Date") }}:</span> 
                                        {{ new Date(sprint.start_date).toLocaleDateString() }}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">{{ __("End Date") }}:</span> 
                                        {{ new Date(sprint.end_date).toLocaleDateString() }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-2 flex items-center">
                                        <span class="font-medium mr-2">{{ __("Status") }}: </span>
                                        <span class="flex items-center">
                                            <!-- Status icon using v-html -->
                                            <svg 
                                                xmlns="http://www.w3.org/2000/svg" 
                                                class="h-4 w-4 mr-1" 
                                                :class="getStatusStyles(sprint.status).icon" 
                                                viewBox="0 0 20 20" 
                                                fill="currentColor"
                                                v-html="getStatusStyles(sprint.status).iconPath"
                                            ></svg>
                                            <span :class="getStatusStyles(sprint.status).text">
                                                {{ __(sprint.status.charAt(0).toUpperCase() + sprint.status.slice(1)) }}
                                            </span>
                                        </span>
                                    </p>
                                    <div class="mt-2">
                                        <div class="text-xs text-gray-500">
                                            {{ getStatusStyles(sprint.status).description }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button 
                                    @click="openSprintEditModal(sprint)"
                                    class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <h1>{{ __("Edit") }}</h1>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="sprints.length === 0" class="py-4 text-center text-gray-500 italic">
                        {{ __("No sprints created yet") }}
                    </div>
                </div>
            </div>

        <!-- Sprint Edit Modal -->
        <div v-if="showSprintEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __("Edit Sprint") }}</h3>
                
                <div class="space-y-4">
                    <!-- Sprint Title -->
                    <div>
                        <label for="sprint-title" class="block text-sm font-medium text-gray-700 mb-1">{{ __("Title") }}</label>
                        <input 
                            id="sprint-title" 
                            v-model="editedSprintData.title" 
                            type="text" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    
                    <!-- Sprint Start Date -->
                    <div>
                        <label for="sprint-start-date" class="block text-sm font-medium text-gray-700 mb-1">{{ __("Start Date") }}</label>
                        <input 
                            id="sprint-start-date" 
                            disabled
                            v-model="editedSprintData.start_date" 
                            type="date" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-200"
                        />
                    </div>
                    
                    <!-- Sprint End Date -->
                    <div>
                        <label for="sprint-end-date" class="block text-sm font-medium text-gray-700 mb-1">{{ __("End Date") }}</label>
                        <input 
                            id="sprint-end-date" 
                            disabled
                            v-model="editedSprintData.end_date" 
                            type="date" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-gray-200"
                        />
                    </div>
                    
                    <!-- Sprint Status -->
                    <div>
                        <label for="sprint-status" class="block text-sm font-medium text-gray-700 mb-1">{{ __("Status") }}</label>
                        <select 
                            :disabled="!isAdmin"
                            id="sprint-status" 
                            v-model="editedSprintData.status" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'bg-gray-100 text-gray-500 cursor-not-allowed': !isAdmin }"
                        >
                            <option value="active">{{ __("Active (Current Sprint)") }}</option>
                            <option value="inactive">{{ __("Inactive (Future Sprint)") }}</option>
                            <option value="planning">{{ __("Planning (Not Started)") }}</option>
                            <option value="locked">{{ __("Locked (Completed, Not Checked)") }}</option>
                            <option value="checked">{{ __("Checked (Completed & Verified)") }}</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">
                            <span v-if="editedSprintData.status === 'active'">{{ __("This is the currently active sprint.") }}</span>
                            <span v-else-if="editedSprintData.status === 'inactive'">{{ __("This sprint is scheduled for the future.") }}</span>
                            <span v-else-if="editedSprintData.status === 'planning'">{{ __("This sprint is not yet started.") }}</span>
                            <span v-else-if="editedSprintData.status === 'locked'">{{ __("This sprint is completed but not yet checked.") }}</span>
                            <span v-else-if="editedSprintData.status === 'checked'">{{ __("This sprint is completed and has been verified.") }}</span>
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button 
                        @click="closeSprintEditModal" 
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                    >
                        {{ __("Cancel") }}
                    </button>
                    <button 
                        @click="handleSaveSprint" 
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                        {{ __("Save Changes") }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>