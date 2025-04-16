<script setup>
import CardForm from './CardForm.vue';
import Column from './BoardTabComponents/Column.vue';
import ConfirmModal from './ConfirmModal.vue';
import { ref, computed, onMounted, nextTick } from 'vue';
import { useToast } from 'vue-toastification';
import { 
    getInitials,
    tryAssignUserToCard, 
    tryMoveCard, 
    tryAddCard, 
    tryUpdateCard, 
    tryDeleteCard, 
    tryAddColumn
} from '../ShowHelpers/BoardTabHelper';

const toast = useToast();

const props = defineProps({
    columns: Array,
    board: Object,
    users: Array,
    showDescription: {
        type: Boolean,
        default: false
    },
    currentSprint: Object,
});

const userDropdownOpen = ref(null);
const userDropdownPosition = ref({ top: '0px', left: '0px' });

// User assignment modal
const toggleUserDropdown = (cardId, event) => {
    // Check if the sprint is locked - silently return without showing warning
    if (props.currentSprint && (props.currentSprint.status === 'locked' || props.currentSprint.status === 'checked')) {
        // No warning toast here
        return;
    }
    
    if (userDropdownOpen.value === cardId) {
        userDropdownOpen.value = null;
        return;
    }
    
    userDropdownOpen.value = cardId;
    
    nextTick(() => {
        const avatarElement = event.target.closest('.relative');
        if (avatarElement) {
            const rect = avatarElement.getBoundingClientRect();
            const dropdownHeight = 240;
            const viewportHeight = window.innerHeight;
            
            const spaceBelow = viewportHeight - rect.bottom;
            
            if (spaceBelow < dropdownHeight) {
                userDropdownPosition.value = {
                    top: `${rect.top + window.scrollY - 160}px`,
                    left: `${rect.left + window.scrollX - 100}px`
                };
            } else {
                userDropdownPosition.value = {
                    top: `${rect.bottom + window.scrollY + 5}px`,
                    left: `${rect.left + window.scrollX - 100}px`
                };
            }
        }
    });
};

// assign user to card
const assignUserToCard = async (cardId, userId) => {
    if (props.currentSprint && (props.currentSprint.status === 'locked' || props.currentSprint.status === 'checked')) {
        toast.warning("Cannot assign users while sprint is locked");
        userDropdownOpen.value = null;
        return;
    }

    loading.value = true;
    try {
        const updatedColumns = await tryAssignUserToCard(cardId, userId, props);
        
        emit('columns-updated', updatedColumns);
        toast.success(userId ? 'User assigned successfully' : 'User unassigned successfully');
    } catch (error) {
        toast.error('Failed to assign user to card');
    } finally {
        loading.value = false;
        userDropdownOpen.value = null;
    }
};

// close dropdown when clicking outside
// Add near other modal state
const showUserFilter = ref(false);
const userFilterPosition = ref({ right: 'auto' });

// Add click handler similar to toggleUserDropdown
const toggleUserFilter = (event) => {
    showUserFilter.value = !showUserFilter.value;
    
    // Calculate position when opening the dropdown
    if (showUserFilter.value) {
        nextTick(() => {
            const buttonElement = event.target.closest('button');
            if (buttonElement) {
                const rect = buttonElement.getBoundingClientRect();
                const dropdownWidth = 200;
                const viewportWidth = window.innerWidth;
                
                if (rect.right + dropdownWidth > viewportWidth) {
                    userFilterPosition.value = { right: '0px', left: 'auto' };
                } else {
                    userFilterPosition.value = { right: 'auto', left: '0px' };
                }
            }
        });
    }
    
    // Prevent interference with user assignment modal
    if (userDropdownOpen.value) {
        userDropdownOpen.value = null;
    }
};

// Update the click-outside handler in onMounted
onMounted(() => {
    document.addEventListener('click', (e) => {
        // Close user assignment modal
        if (userDropdownOpen.value && !e.target.closest('.relative')) {
            userDropdownOpen.value = null;
        }
        
        // Close user filter dropdown
        if (showUserFilter.value && !e.target.closest('.relative')) {
            showUserFilter.value = false;
        }
    });
});

const isColumnLocked = computed(() => {
    return (columnTitle) => {
        if (!props.currentSprint || !props.currentSprint.status) {
            return false;
        }
        
        const status = props.currentSprint.status;
        
        if (status === 'planning') {
            return columnTitle !== 'Project Backlog' && columnTitle !== 'Sprint Backlog';
        } else if (status === 'locked' || status === 'checked') {
            return true;
        }
        
        return false;
    };
});

const emit = defineEmits([
    'columns-updated',
    'toggle-description',
    'burndown-update'
]);

// Add filter state
const selectedUserId = ref(null);

// Modify regularColumns and doneColumn computed properties to filter cards
const regularColumns = computed(() => {
    return props.columns.filter(column => column.title !== 'Done').map(column => ({
        ...column,
        cards: selectedUserId.value 
            ? column.cards.filter(card => card.user_id === selectedUserId.value)
            : column.cards
    }));
});

const doneColumn = computed(() => {
    const found = props.columns.find(column => column.title === 'Done');
    return found ? {
        ...found,
        cards: selectedUserId.value 
            ? found.cards.filter(card => card.user_id === selectedUserId.value)
            : found.cards
    } : null;
});

// Toggle description visibility
const toggleDescription = () => {
    emit('toggle-description');
};

// Local state management
const cardOpen = ref({});
const cardTitle = ref('');
const cardDesc = ref('');
const cardPoints = ref(0);
const cardEditing = ref(null);
const newColumnTitle = ref('');
const showNewColumn = ref(false);
const currentDragCardId = ref(null);
const currentDragColumnId = ref(null);
const currentDragSourceColumnId = ref(null);
const loading = ref(false);

// Card drag and drop handlers
const handleDragStart = (event, cardId, columnId) => {
    const sourceColumn = props.columns.find(col => col.id === columnId);
    if (!sourceColumn || isColumnLocked.value(sourceColumn.title)) {
        event.preventDefault();
        return;
    }
    
    currentDragCardId.value = cardId;
    currentDragSourceColumnId.value = columnId;
    event.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (event, columnId) => {
    event.preventDefault();
    currentDragColumnId.value = columnId;
};

const handleDragLeave = () => {
    currentDragColumnId.value = null;
};

const handleDrop = async (event, targetColumnId) => {
    event.preventDefault();
    
    const targetColumn = props.columns.find(col => col.id === targetColumnId);
    if (!targetColumn || isColumnLocked.value(targetColumn.title)) {
        toast.error('Cannot move cards to locked columns');
        currentDragColumnId.value = null;
        return;
    }
    
    if (currentDragCardId.value && currentDragSourceColumnId.value && targetColumnId) {
        await handleMoveCard({
            cardId: currentDragCardId.value,
            sourceColumnId: currentDragSourceColumnId.value,
            targetColumnId
        });
    }
    
    // Reset drag state
    currentDragCardId.value = null;
    currentDragSourceColumnId.value = null;
    currentDragColumnId.value = null;
};

// Card form handlers
const toggleAddCard = (columnId) => {
    cardOpen.value = { ...cardOpen.value, [columnId]: !cardOpen.value[columnId] };
    resetForm();
};

const toggleEditCard = (cardId) => {
    if (cardEditing.value === cardId) {
        cardEditing.value = null;
        resetForm();
        return;
    }
    
    let foundCard = null;
    for (const column of props.columns) {
        const card = column.cards.find(c => c.id === cardId);
        if (card) {
            foundCard = card;
            break;
        }
    }
    
    if (foundCard) {
        cardEditing.value = foundCard.id;
        cardTitle.value = foundCard.title;
        cardDesc.value = foundCard.description;
        cardPoints.value = foundCard.points;
    }
};

const resetForm = () => {
    cardTitle.value = '';
    cardDesc.value = '';
    cardPoints.value = 0;
    cardEditing.value = null;
};

// Column form handlers
const toggleNewColumn = () => {
    showNewColumn.value = !showNewColumn.value;
    resetNewColumnForm();
};

const resetNewColumnForm = () => {
    newColumnTitle.value = '';
};

// Card movement handler
const handleMoveCard = async ({ cardId, sourceColumnId, targetColumnId }) => {
    const updatedColumns = await tryMoveCard({ 
        cardId, 
        sourceColumnId, 
        targetColumnId, 
        columns: props.columns,
        onSuccess: () => emit('burndown-update')
    });
    
    if (updatedColumns) {
        emit('columns-updated', updatedColumns);
    }
};

// API handlers
const handleUpdateCard = async ({ cardId, title, description, points }) => {
    loading.value = true;
    try {
        const updatedColumns = await tryUpdateCard({ 
            cardId, 
            title, 
            description, 
            points, 
            columns: props.columns 
        });
        
        if (updatedColumns) {
            emit('columns-updated', updatedColumns);
        }
    } catch (error) {
        // Error is already handled in the helper
    } finally {
        loading.value = false;
        cardEditing.value = null;
        resetForm();
    }
};

const handleAddCard = async ({ columnId, title, description, points }) => {
    loading.value = true;
    try {
        const updatedColumns = await tryAddCard({ 
            columnId, 
            title, 
            description, 
            points, 
            columns: props.columns 
        });
        
        if (updatedColumns) {
            emit('columns-updated', updatedColumns);
            cardOpen.value[columnId] = false;
        }
    } catch (error) {
        // Error is already handled in the helper
    } finally {
        loading.value = false;
        resetForm();
    }
};

const pendingDeleteCardId = ref(null);
const showDeleteCardModal = ref(false);

const confirmDeleteCard = (cardId) => {
    pendingDeleteCardId.value = cardId;
    showDeleteCardModal.value = true;
};

const handleDeleteCard = async () => {
    const cardId = pendingDeleteCardId.value;
    if (!cardId) return;
    
    loading.value = true;
    try {
        const updatedColumns = await tryDeleteCard(cardId, props.columns);
        
        if (updatedColumns) {
            emit('columns-updated', updatedColumns);
            toast.success('Card deleted successfully');
        }
    } catch (error) {
        toast.error('Failed to delete card');
    } finally {
        loading.value = false;
        showDeleteCardModal.value = false;
        pendingDeleteCardId.value = null;
    }
};

const handleAddColumn = async ({ title, done, board_id, status }) => {
    loading.value = true;
    try {
        const newColumn = await tryAddColumn({ title, done, board_id, status });
        
        if (newColumn) {
            const updatedColumns = [...props.columns, newColumn];
            emit('columns-updated', updatedColumns);
            resetNewColumnForm();
        }
    } catch (error) {
        // Error is already handled in the helper
    } finally {
        loading.value = false;
        showNewColumn.value = false; 
    }
};

const isUserAssigned = (cardId, userId) => {
    for (const column of props.columns) {
        const card = column.cards.find(c => c.id === cardId);
        if (card) {
            return card.user_id === userId;
        }
    }
    return false;
};
// Add modal state if needed for any confirmations in BoardTab.vue
const showConfirmModal = ref(false);
const confirmModalMessage = ref('');
const confirmModalAction = ref(null);

const showConfirmation = (message, action) => {
    confirmModalMessage.value = message;
    confirmModalAction.value = action;
    showConfirmModal.value = true;
};

const handleConfirm = () => {
    if (confirmModalAction.value) {
        confirmModalAction.value();
    }
    showConfirmModal.value = false;
};
</script>

<template>
    <div class="flex justify-center">
        <div class="bg-white p-6 rounded-lg shadow w-full">
            <!-- Board header -->
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4 min-w-0 flex-1">
                    <h1 class="text-2xl font-semibold text-gray-800 truncate">{{ board.title }}</h1>
                    <span v-if="currentSprint" class="text-gray-600 truncate flex-shrink-0">
                        <span>({{ currentSprint.title }})</span>
                        <button 
                        @click="toggleDescription" 
                        class="text-gray-500 hover:text-gray-700 focus:outline-none flex-shrink-0 ml-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 15 15" fill="currentColor">
                            <path v-if="!showDescription" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            <path v-else fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    </span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative min-w-[200px]">
                        <button 
                            @click.stop="toggleUserFilter"
                            class="flex items-center justify-between w-full px-2 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors min-w-[200px]"
                        >
                            <div class="flex items-center space-x-2 flex-1 min-w-0">
                                <div v-if="selectedUserId" class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium flex-shrink-0">
                                    {{ getInitials(users.find(u => u.id === selectedUserId)?.name || '') }}
                                </div>
                                <span class="truncate">{{ selectedUserId ? users.find(u => u.id === selectedUserId)?.name : 'All Users' }}</span>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 flex-shrink-0 ml-3 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path v-if="!showUserFilter" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                <path v-else fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        
                        <div 
                            v-if="showUserFilter"
                            class="absolute z-50 mt-1 min-w-[200px] w-max bg-white rounded-md shadow-lg border border-gray-200"
                            :style="userFilterPosition"
                        >
                            <div class="py-1 max-h-60 overflow-auto">
                                <div 
                                    @click="selectedUserId = null; showUserFilter = false"
                                    class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer flex items-center space-x-2"
                                    :class="{ 'bg-blue-50': !selectedUserId }"
                                >
                                    <span class="w-6"></span>
                                    <span>All Users</span>
                                </div>
                                <div 
                                    v-for="user in users" 
                                    :key="user.id" 
                                    @click="selectedUserId = user.id; showUserFilter = false"
                                    class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer flex items-center space-x-2"
                                    :class="{ 'bg-blue-50': selectedUserId === user.id }"
                                >
                                    <div class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium">
                                        {{ getInitials(user.name) }}
                                    </div>
                                    <span>{{ user.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="showDescription" class="py-2 rounded-lg">
                <p class="text-gray-500">{{ board.description }}</p>
            </div>

            <!-- Columns container -->
            <div class="flex justify-center">
                <div 
                    class="flex space-x-4 overflow-x-auto pb-4 w-full"
                    :class="{ '': showDescription, 'mt-6': !showDescription }"
                >
                    <!-- Regular columns -->
                    <Column 
                        v-for="column in regularColumns" 
                        :key="column.id"
                        :column="column"
                        :users="props.users"
                        :is-done="false"
                        :is-locked="isColumnLocked(column.title)"
                        :is-dragging="currentDragColumnId === column.id"
                        :card-open="cardOpen"
                        :columns="props.columns"
                        :cardEditing="cardEditing"
                        @add-card-toggle="toggleAddCard"
                        @card-editing-changed="toggleEditCard"
                        @delete-card="handleDeleteCard"
                        @drag-start="handleDragStart"
                        @drag-over="handleDragOver"
                        @drag-leave="handleDragLeave"
                        @drop="handleDrop"
                        @toggle-user-dropdown="toggleUserDropdown"
                        @columns-updated="emit('columns-updated', $event)"
                    >
                        <template #card-edit-form="{ card }">
                            <CardForm 
                                :loading="loading"
                                :column-id="card.column_id"
                                :initial-title="cardTitle"
                                :initial-description="cardDesc"
                                :initial-points="cardPoints"
                                :is-editing="true"
                                @save="(data) => handleUpdateCard({
                                    cardId: card.id,
                                    columnId: card.column_id,
                                    title: data.title,
                                    description: data.description,
                                    points: data.points
                                })"
                                @cancel="resetForm"
                            />
                        </template>
                        <template #add-card-form>
                            <CardForm 
                                :loading="loading"
                                :column-id="column.id"
                                :key="`form-${column.id}-${Date.now()}`"
                                @save="handleAddCard"
                                @cancel="toggleAddCard(column.id)"
                            />
                        </template>
                    </Column>
                    
                    <!-- Done column -->
                    <Column 
                        v-if="doneColumn"
                        :key="doneColumn.id"
                        :column="doneColumn"
                        :users="props.users"
                        :is-done="true"
                        :is-locked="isColumnLocked(doneColumn.title)"
                        :is-dragging="currentDragColumnId === doneColumn.id"
                        :card-open="cardOpen"
                        :columns="props.columns"
                        :cardEditing="cardEditing"
                        @add-card-toggle="toggleAddCard"
                        @card-editing-changed="toggleEditCard"
                        @delete-card="handleDeleteCard"
                        @drag-start="handleDragStart"
                        @drag-over="handleDragOver"
                        @drag-leave="handleDragLeave"
                        @drop="handleDrop"
                        @toggle-user-dropdown="toggleUserDropdown"
                    >
                        <template #card-edit-form="{ card }">
                            <CardForm 
                                :loading="loading"
                                :column-id="card.column_id"
                                :initial-title="cardTitle"
                                :initial-description="cardDesc"
                                :initial-points="cardPoints"
                                :is-editing="true"
                                @save="(data) => handleUpdateCard({
                                    cardId: card.id,
                                    columnId: card.column_id,
                                    title: data.title,
                                    description: data.description,
                                    points: data.points
                                })"
                                @cancel="resetForm"
                            />
                        </template>
                        <template #add-card-form>
                            <CardForm 
                                :loading="loading"
                                :column-id="doneColumn.id"
                                :key="`form-${doneColumn.id}-${Date.now()}`"
                                @save="handleAddCard"
                                @cancel="toggleAddCard(doneColumn.id)"
                            />
                        </template>
                    </Column>
                    
                    <!-- Add column form -->
                    <div v-if="showNewColumn" class="flex-shrink-0 w-72 bg-white p-3 rounded-lg shadow">
                        <h3 class="font-medium text-gray-700 mb-2">Add New Column</h3>
                        <div class="relative">
                            <input 
                                v-model="newColumnTitle" 
                                class="w-full px-3 py-2 border border-gray-300 rounded mb-2 pr-8"
                                placeholder="Column title"
                                :disabled="loading"
                            />
                            <span v-if="loading" class="absolute right-3 top-3">⏳</span>
                        </div>
                        <div class="flex items-center mb-3">
                            <input 
                                id="done-column" 
                                type="checkbox" 
                                v-model="newColumnDone" 
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                :disabled="loading"
                            />
                            <label for="done-column" class="ml-2 block text-sm text-gray-900">
                                This is a "Done" column
                            </label>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button 
                                @click="toggleNewColumn" 
                                class="px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 rounded"
                                :disabled="loading"
                            >
                                Cancel
                            </button>
                            <button 
                                @click="handleAddColumn({
                                    title: newColumnTitle,
                                    done: newColumnDone,
                                    board_id: board.id,
                                    status: board.status
                                })"
                                class="px-3 py-1 text-sm bg-blue-600 text-white hover:bg-blue-700 rounded relative"
                                :disabled="loading"
                            >
                                <span v-if="loading">⏳</span>
                                {{ loading ? 'Adding...' : 'Add Column' }}
                            </button>
                        </div>
                    </div>
                    
                    <!-- Add column button -->
                    <button 
                        v-else
                        @click="toggleNewColumn"
                        class="flex-shrink-0 w-72 bg-gray-100 rounded-lg shadow-sm border-2 border-dashed border-gray-300 flex items-center justify-center p-6 hover:bg-gray-200"
                    >
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mx-auto text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                            </svg>
                            <span class="mt-2 block text-sm font-medium text-gray-600">Add Column</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User assignment modal -->
    <div v-if="userDropdownOpen && (!props.currentSprint || (props.currentSprint.status !== 'locked' && props.currentSprint.status !== 'checked'))" class="absolute inset-0 z-50" @click.self="userDropdownOpen = null">
        <div 
            class="bg-white rounded-md shadow-lg w-64 absolute"
            :style="userDropdownPosition"
        >
            <div class="py-1">
                <div v-for="user in props.users" :key="user.id" 
                    @click="assignUserToCard(userDropdownOpen, user.id)"
                    class="px-4 py-2 text-sm text-gray-700 hover:bg-blue-100 cursor-pointer flex items-center"
                    :class="{ 'bg-green-100': isUserAssigned(userDropdownOpen, user.id) }"
                >
                    <div class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium mr-2">
                        {{ getInitials(user.name) }}
                    </div>
                    {{ user.name }}
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add at the end of the template -->
    <ConfirmModal
        :show="showDeleteCardModal"
        title="Delete Card"
        message="Are you sure you want to delete this card?"
        confirm-text="Delete"
        @confirm="handleDeleteCard"
        @cancel="showDeleteCardModal = false"
    />
</template>
