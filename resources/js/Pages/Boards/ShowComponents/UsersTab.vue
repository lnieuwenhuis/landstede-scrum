<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import ConfirmModal from './ConfirmModal.vue';
import { getInitials } from '../ShowHelpers/BoardTabHelper';
import { useTranslations } from '../../../translations';

const toast = useToast();
const { __ } = useTranslations();

const props = defineProps({
    users: Array,
    board: Object
});

const emit = defineEmits(['users-updated']);

const users = ref(props.users);
const ownerId = users.value.length > 0 ? users.value[0].id : null;

const showAddUserModal = ref(false);
const userSearchInput = ref('');
const usersToAdd = ref([]);
const searchResults = ref([]);
const isSearching = ref(false);
const searchTimeout = ref(null);

// Toggle add user modal
const toggleAddUserModal = () => {
    showAddUserModal.value = !showAddUserModal.value;
    if (!showAddUserModal.value) {
        // Reset state when closing modal
        userSearchInput.value = '';
        usersToAdd.value = [];
        searchResults.value = [];
    }
    document.body.style.overflow = showAddUserModal.value ? 'hidden' : '';
};

// Debounced search for users
const debouncedSearch = () => {
    // Clear any existing timeout
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }
    
    // Set a new timeout
    searchTimeout.value = setTimeout(() => {
        searchUsers();
    }, 300); // 300ms debounce time
};

// Watch for changes in the search input
watch(userSearchInput, (newValue) => {
    if (newValue.trim()) {
        debouncedSearch();
    } else {
        searchResults.value = [];
    }
});

// Search for users
const searchUsers = async () => {
    if (!userSearchInput.value.trim()) {
        searchResults.value = [];
        return;
    }
    
    isSearching.value = true;
    try {
        const response = await axios.post('/api/users/searchUsers', {
            searchTerm: userSearchInput.value,
            board_id: props.board.id
        });
        
        if (response.data && Array.isArray(response.data)) {
            // Filter out users that are already in the board or already in the usersToAdd list
            const currentUserIds = users.value.map(user => user.id);
            const toAddUserIds = usersToAdd.value.map(user => user.id);
            searchResults.value = response.data.filter(user => 
                !currentUserIds.includes(user.id) && !toAddUserIds.includes(user.id)
            );
        } else if (response.data.users) {
            // Fallback to the previous format if it exists
            const currentUserIds = users.value.map(user => user.id);
            const toAddUserIds = usersToAdd.value.map(user => user.id);
            searchResults.value = response.data.users.filter(user => 
                !currentUserIds.includes(user.id) && !toAddUserIds.includes(user.id)
            );
        }
    } catch (error) {
        console.error('Error searching users:', error);
        toast.error(__('Failed to search users'));
    } finally {
        isSearching.value = false;
    }
};

// Add user to temporary list
const addUserToList = (user) => {
    usersToAdd.value.push(user);
    searchResults.value = searchResults.value.filter(u => u.id !== user.id);
};

// Remove user from temporary list
const removeUserFromList = (userId) => {
    // Find the user before removing from usersToAdd
    const userToRemove = usersToAdd.value.find(user => user.id === userId);
    
    // Remove from usersToAdd array
    usersToAdd.value = usersToAdd.value.filter(user => user.id !== userId);
    
    if (userToRemove && userSearchInput.value.trim()) {
        // Check if the user matches current search criteria
        if (userToRemove.name.toLowerCase().includes(userSearchInput.value.toLowerCase())) {
            // Add back to search results if not already there
            if (!searchResults.value.some(user => user.id === userToRemove.id)) {
                searchResults.value.push(userToRemove);
            }
        }
    }
};

const isSaving = ref(false);

// Save users to board
const saveUsers = async () => {
    if (usersToAdd.value.length === 0) {
        toggleAddUserModal();
        return;
    }
    
    isSaving.value = true;
    
    try {
        const userIds = usersToAdd.value.map(user => user.id);
        const response = await axios.post('/api/users/addUsersToBoard', {
            board_id: props.board.id,
            user_ids: userIds
        });
        
        if (response.data.message) {
            // Add the new users to the users list
            users.value = [...users.value, ...usersToAdd.value];
            toast.success(__('Users added successfully'));
            emit('users-updated', users.value);
            toggleAddUserModal();
        } else {
            throw new Error(response.data.error || __('Failed to add users'));
        }
    } catch (error) {
        console.error('Error adding users:', error);
        toast.error(error.message || __('Failed to add users'));
    } finally {
        isSaving.value = false;
    }
};

const showDeleteConfirmation = ref(false);
const userToDelete = ref(null);

const toggleDeleteConfirmation = (userId = null) => {
    showDeleteConfirmation.value = !showDeleteConfirmation.value;
    userToDelete.value = userId;
};

const handleDeleteUser = async (userId) => {
    try {
        const response = await axios.post('/api/users/removeUserFromBoard', {
            board_id: props.board.id,
            user_id: userId
        });

        if (response.data.message) {
            users.value = users.value.filter(user => user.id !== userId);
            toast.success(__('User removed successfully'));
            emit('users-updated', users.value);
        } else {
            throw new Error(response.data.error || __('Failed to remove user'));
        }
    } catch (error) {
        console.error('Error removing user:', error);
        toast.error(error.message || __('Failed to remove user'));
    }
};

const confirmDeleteUser = () => {
    handleDeleteUser(userToDelete.value);
    toggleDeleteConfirmation();
};

// Helper function to validate hex color
const isValidHexColor = (color) => {
    return color && /^#([0-9A-F]{3}){1,2}$/i.test(color);
};

// Helper function to determine if a color is light or dark
const isLightColor = (hexColor) => {
    // Default to false if color is invalid
    if (!isValidHexColor(hexColor)) return false;
    
    // Convert hex to RGB
    let r, g, b;
    if (hexColor.length === 4) {
        // For 3-digit hex codes (#RGB)
        r = parseInt(hexColor[1] + hexColor[1], 16);
        g = parseInt(hexColor[2] + hexColor[2], 16);
        b = parseInt(hexColor[3] + hexColor[3], 16);
    } else {
        // For 6-digit hex codes (#RRGGBB)
        r = parseInt(hexColor.substring(1, 3), 16);
        g = parseInt(hexColor.substring(3, 5), 16);
        b = parseInt(hexColor.substring(5, 7), 16);
    }
    
    // Calculate perceived brightness using the formula:
    // (0.299*R + 0.587*G + 0.114*B)
    const brightness = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    // Return true if the color is light (brightness > 0.5)
    return brightness > 0.5;
};
</script>

<template>
    <div class="flex-1">
        <div class="bg-white p-4 shadow-md rounded-lg overflow-hidden">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2">
                <h2 class="text-2xl font-semibold text-gray-800 ml-2">{{ __('Team Members') }}</h2>
                <button 
                    @click="toggleAddUserModal"
                    class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 h-10 m-2 md:ml-auto max-w-52"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
                    </svg>
                    {{ __('Add User') }}
                </button>
            </div>
            <div class="p-2 pt-0">
                <div class="flex justify-between items-center mb-4">
                    
                </div>
                <div class="space-y-4">
                    <div 
                        v-for="user in users" 
                        :key="user.id" 
                        class="bg-gray-50 p-4 rounded-lg shadow-sm transition-colors duration-200 hover:bg-gray-100 flex items-center space-x-4"
                    >
                        <div class="flex-shrink-0">
                            <div 
                                class="h-12 w-12 rounded-full flex items-center justify-center font-bold text-lg"
                                :style="{ 
                                    backgroundColor: isValidHexColor(user.color) ? user.color : '#3b82f6',
                                    color: isLightColor(user.color) ? '#000000' : '#ffffff'
                                }"
                            >
                                {{ getInitials(user.name) }}
                            </div>
                        </div>
                        <div class="ml-4 flex-1 min-w-0">
                            <div class="flex items-center">
                                <p class="text-gray-800 font-medium truncate max-w-xs">{{ user.name }}</p>
                                <!-- Owner badge next to name -->
                                <span 
                                    v-if="user.id === ownerId" 
                                    class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800 flex-shrink-0"
                                >
                                    {{ __('Owner') }}
                                </span>
                            </div>
                            <p class="text-gray-500 text-sm truncate max-w-xs">{{ user.email }}</p>
                        </div>
                        <!-- Delete button for users -->
                        <button 
                            @click="toggleDeleteConfirmation(user.id)"
                            class="p-2 rounded-lg focus:outline-none focus:ring-2 bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 flex-shrink-0"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                    <div v-if="users.length === 0" class="py-4 text-center text-gray-500 italic">
                        {{ __('No team members yet') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div v-if="showAddUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Add Team Members') }}</h3>
            
            <div class="space-y-4">
                <!-- Search input - removed button -->
                <div class="relative">
                    <input 
                        v-model="userSearchInput" 
                        type="text" 
                        :placeholder="__('Search by username or email')" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                    />
                    <div v-if="isSearching" class="absolute right-3 top-2.5">
                        <svg class="animate-spin h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
                
                <!-- Search results -->
                <div v-if="searchResults.length > 0" class="border border-gray-200 rounded-md overflow-hidden">
                    <div class="text-sm font-medium text-gray-700 bg-gray-50 px-4 py-2">
                        {{ __('Search Results') }}
                    </div>
                    <div class="divide-y divide-gray-200 max-h-40 overflow-y-auto">
                        <div 
                            v-for="user in searchResults" 
                            :key="user.id"
                            class="px-4 py-2 hover:bg-gray-50 flex justify-between items-center"
                        >
                            <div>
                                <p class="font-medium text-gray-800">{{ user.name }}</p>
                                <p class="text-sm text-gray-500">{{ user.email }}</p>
                            </div>
                            <button 
                                @click="addUserToList(user)"
                                class="p-1 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- No results message -->
                <div v-else-if="userSearchInput && !isSearching" class="text-center py-2 text-gray-500">
                    {{ __('No users found') }}
                </div>
                
                <!-- Users to add list -->
                <div v-if="usersToAdd.length > 0" class="border border-gray-200 rounded-md overflow-hidden">
                    <div class="text-sm font-medium text-gray-700 bg-gray-50 px-4 py-2">
                        {{ __('Users to Add') }} ({{ usersToAdd.length }})
                    </div>
                    <div class="divide-y divide-gray-200 max-h-40 overflow-y-auto">
                        <div 
                            v-for="user in usersToAdd" 
                            :key="user.id"
                            class="px-4 py-2 hover:bg-gray-50 flex justify-between items-center"
                        >
                            <div>
                                <p class="font-medium text-gray-800">{{ user.name }}</p>
                                <p class="text-sm text-gray-500">{{ user.email }}</p>
                            </div>
                            <button 
                                @click="removeUserFromList(user.id)"
                                class="p-1 bg-red-600 text-white rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button 
                    @click="toggleAddUserModal" 
                    class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                    :disabled="isSaving"
                >
                    {{ __('Cancel') }}
                </button>
                <button 
                    @click="saveUsers" 
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    :disabled="usersToAdd.length === 0 || isSaving"
                    :class="{ 'opacity-50 cursor-not-allowed': usersToAdd.length === 0 || isSaving }"
                >
                    <span v-if="isSaving" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ __('Adding...') }}
                    </span>
                    <span v-else>{{ __('Add Users') }}</span>
                </button>
            </div>
        </div>
    </div>

    <ConfirmModal
        :show="showDeleteConfirmation"
        :title="__('Remove Team Member')"
        :message="__('Are you sure you want to remove this user from the board? This action cannot be undone.')"
        :confirm-text="__('Remove')"
        @confirm="confirmDeleteUser"
        @cancel="toggleDeleteConfirmation"
    />
</template>