<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    isAdmin: Boolean,
    sprints: Array,
    board: Object
});

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

const handleCreateSprint = async () => {
    try {
        const response = await axios.post('/api/createSprint', {
            board_id: props.board.id,
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

const toggleDeleteSprint = (sprintId = null) => {
    document.body.style.overflow = showDeleteSprintConfirmation.value ? '' : 'hidden';
    showDeleteSprintConfirmation.value = !showDeleteSprintConfirmation.value;
    sprintToDelete.value = sprintId;
};

const handleDeleteSprint = async () => {
    try {
        const response = await axios.post('/api/deleteSprint', {
            board_id: props.board.id,
            sprint_id: sprintToDelete.value
        });

        if (response.data.message) {
            sprints.value = sprints.value.filter(sprint => sprint.id !== sprintToDelete.value);
            emit('sprint-deleted', sprintToDelete.value);
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
                ...editedSprintData.value
            });
            
            toast.success('Sprint updated successfully');
            closeSprintEditModal();
        } else {
            throw new Error(response.data.error || 'Failed to update sprint');
        }
    } catch (error) {
        console.error('Error updating sprint:', error);
        toast.error(error.message || 'Failed to update sprint');
    }
};

// Update the emit definition to include the full sprint object
const emit = defineEmits(['sprint-deleted', 'sprint-updated']);
</script>

<template>
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Sprints</h2>
                <button 
                    @click="handleCreateSprint"
                    class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    New Sprint
                </button>
            </div>                        
            <div class="space-y-4">
                <div 
                    v-for="sprint in sprints" 
                    :key="sprint.id"
                    class="p-4 rounded-lg shadow-sm transition-all duration-200 hover:bg-black/5"
                    :class="{
                        'border-l-4 border-green-500 bg-green-50': sprint.status === 'active',
                        'border-l-4 border-blue-500 bg-blue-50': sprint.status === 'planning',
                        'border-l-4 border-gray-500 bg-gray-50': sprint.status === 'inactive',
                        'border-l-4 border-yellow-500 bg-yellow-50': sprint.status === 'locked',
                        'border-l-4 border-purple-500 bg-purple-50': sprint.status === 'checked'
                    }"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 flex items-center">
                                {{ sprint.title }}
                                <span 
                                    class="ml-2 px-2 py-1 text-xs font-semibold rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-800': sprint.status === 'active',
                                        'bg-blue-100 text-blue-800': sprint.status === 'planning',
                                        'bg-gray-100 text-gray-800': sprint.status === 'inactive',
                                        'bg-yellow-100 text-yellow-800': sprint.status === 'locked',
                                        'bg-purple-100 text-purple-800': sprint.status === 'checked'
                                    }"
                                >
                                    {{ sprint.status.charAt(0).toUpperCase() + sprint.status.slice(1) }}
                                </span>
                            </h3>
                            <div class="mt-2 space-y-1">
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Start Date:</span> 
                                    {{ new Date(sprint.start_date).toLocaleDateString() }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">End Date:</span> 
                                    {{ new Date(sprint.end_date).toLocaleDateString() }}
                                </p>
                                <p class="text-sm text-gray-600 mt-2 flex items-center">
                                    <span class="font-medium mr-2">Status: </span>
                                    <span class="flex items-center">
                                        <!-- Status icons -->
                                        <svg v-if="sprint.status === 'active'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <svg v-if="sprint.status === 'planning'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-blue-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z" />
                                        </svg>
                                        <svg v-if="sprint.status === 'inactive'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                        </svg>
                                        <svg v-if="sprint.status === 'locked'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-yellow-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                        <svg v-if="sprint.status === 'checked'" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 text-purple-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                        <span 
                                            :class="{
                                                'text-green-600': sprint.status === 'active',
                                                'text-blue-600': sprint.status === 'planning',
                                                'text-gray-600': sprint.status === 'inactive',
                                                'text-yellow-600': sprint.status === 'locked',
                                                'text-purple-600': sprint.status === 'checked'
                                            }"
                                        >
                                            {{ sprint.status.charAt(0).toUpperCase() + sprint.status.slice(1) }}
                                        </span>
                                    </span>
                                </p>
                                <div class="mt-2">
                                    <div class="text-xs text-gray-500">
                                        <span v-if="sprint.status === 'active'">Currently active sprint</span>
                                        <span v-else-if="sprint.status === 'planning'">Sprint in planning phase</span>
                                        <span v-else-if="sprint.status === 'inactive'">Future sprint</span>
                                        <span v-else-if="sprint.status === 'locked'">Completed, awaiting verification</span>
                                        <span v-else-if="sprint.status === 'checked'">Completed and verified</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button 
                                @click="openSprintEditModal(sprint)"
                                class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                </svg>
                            </button>
                            <button 
                                @click="toggleDeleteSprint(sprint.id)"
                                class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="sprints.length === 0" class="py-4 text-center text-gray-500 italic">
                    No sprints created yet
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Sprint Confirmation Modal -->
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

    <!-- Sprint Edit Modal -->
    <div v-if="showSprintEditModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Sprint</h3>
            
            <div class="space-y-4">
                <!-- Sprint Title -->
                <div>
                    <label for="sprint-title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input 
                        id="sprint-title" 
                        v-model="editedSprintData.title" 
                        type="text" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                
                <!-- Sprint Start Date -->
                <div>
                    <label for="sprint-start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input 
                        id="sprint-start-date" 
                        v-model="editedSprintData.start_date" 
                        type="date" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                
                <!-- Sprint End Date -->
                <div>
                    <label for="sprint-end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input 
                        id="sprint-end-date" 
                        v-model="editedSprintData.end_date" 
                        type="date" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                
                <!-- Sprint Status -->
                <div>
                    <label for="sprint-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select 
                        :disabled="!isAdmin"
                        id="sprint-status" 
                        v-model="editedSprintData.status" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        :class="{ 'bg-gray-100 text-gray-500 cursor-not-allowed': !isAdmin }"
                    >
                        <option value="active">Active (Current Sprint)</option>
                        <option value="inactive">Inactive (Future Sprint)</option>
                        <option value="planning">Planning (Not Started)</option>
                        <option value="locked">Locked (Completed, Not Checked)</option>
                        <option value="checked">Checked (Completed & Verified)</option>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">
                        <span v-if="editedSprintData.status === 'active'">This is the currently active sprint.</span>
                        <span v-else-if="editedSprintData.status === 'inactive'">This sprint is scheduled for the future.</span>
                        <span v-else-if="editedSprintData.status === 'planning'">This sprint is not yet started.</span>
                        <span v-else-if="editedSprintData.status === 'locked'">This sprint is completed but not yet checked.</span>
                        <span v-else-if="editedSprintData.status === 'checked'">This sprint is completed and has been verified.</span>
                    </p>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button 
                    @click="closeSprintEditModal" 
                    class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button 
                    @click="handleSaveSprint" 
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                >
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</template>