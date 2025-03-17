<script setup>
defineProps({
    showDeleteConfirmation: Boolean,
    showDeleteSprintConfirmation: Boolean,
    showSprintEditModal: Boolean,
    editedSprintData: Object
});

const emit = defineEmits([
    'close-delete-card',
    'delete-card',
    'close-delete-sprint',
    'delete-sprint',
    'close-sprint-edit',
    'save-sprint'
]);
</script>

<template>
    <!-- Delete Card Confirmation Modal -->
    <div v-if="showDeleteConfirmation" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Card</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete this card? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <button 
                    @click="emit('close-delete-card')" 
                    class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button 
                    @click="emit('delete-card')" 
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                >
                    Delete
                </button>
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
                    @click="emit('close-delete-sprint')" 
                    class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button 
                    @click="emit('delete-sprint')" 
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
                        id="sprint-status" 
                        v-model="editedSprintData.status" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="active">Active (Current Sprint)</option>
                        <option value="inactive">Inactive (Future Sprint)</option>
                        <option value="locked">Locked (Completed, Not Checked)</option>
                        <option value="checked">Checked (Completed & Verified)</option>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">
                        <span v-if="editedSprintData.status === 'active'">This is the currently active sprint.</span>
                        <span v-else-if="editedSprintData.status === 'inactive'">This sprint is scheduled for the future.</span>
                        <span v-else-if="editedSprintData.status === 'locked'">This sprint is completed but not yet checked.</span>
                        <span v-else-if="editedSprintData.status === 'checked'">This sprint is completed and has been verified.</span>
                    </p>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button 
                    @click="emit('close-sprint-edit')" 
                    class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button 
                    @click="emit('save-sprint')" 
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                >
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</template>