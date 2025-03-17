<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const toast = useToast();

const props = defineProps({
    users: Array,
    board: Object
});

const users = ref(props.users);
const ownerId = users.value.length > 0 ? users.value[0].id : null;

const handleDeleteUser = async (userId) => {
    try {
        const response = await axios.post('/api/removeUserFromBoard', {
            board_id: props.board.id,
            user_id: userId
        });

        if (response.data.message) {
            users.value = users.value.filter(user => user.id !== userId);
            toast.success('User removed successfully');
        } else {
            throw new Error(response.data.error || 'Failed to remove user');
        }
    } catch (error) {
        console.error('Error removing user:', error);
        toast.error(error.message || 'Failed to remove user');
    }
};
</script>

<template>
    <div class="flex-1">
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Team Members</h2>
                <div class="space-y-4">
                    <div 
                        v-for="user in users" 
                        :key="user.id" 
                        class="bg-black/5 p-4 rounded-lg shadow-sm transition-all duration-200 hover:bg-black/10 flex items-center"
                    >
                        <div class="flex-shrink-0">
                            <div class="h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-lg">
                                {{ user.name.charAt(0).toUpperCase() }}
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex items-center">
                                <p class="text-gray-800 font-medium">{{ user.name }}</p>
                                <!-- Owner badge next to name -->
                                <span 
                                    v-if="user.id === ownerId" 
                                    class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-800"
                                >
                                    Owner
                                </span>
                            </div>
                            <p class="text-gray-500 text-sm">{{ user.email }}</p>
                        </div>
                        <!-- Delete button for users -->
                        <button 
                            @click="handleDeleteUser(user.id)"
                            :class="'p-2 rounded-lg focus:outline-none focus:ring-2 bg-red-600 text-white hover:bg-red-700 focus:ring-red-500'"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>