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
    tryAddColumn,
    isValidHexColor,
    isLightColor
} from '../ShowHelpers/BoardTabHelper';
import { groupBy } from 'lodash-es';

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
    categories: Array,
});

const userDropdownOpen = ref(null);
const userDropdownPosition = ref({ top: '0px', left: '0px' });

const getStatusStyles = (status) => {
    const styles = {
        active: {
            border: 'border-green-500 bg-green-50',
            text: 'text-green-600',
        },
        planning: {
            border: 'border-blue-500 bg-blue-50',
            text: 'text-blue-600',
        },
        inactive: {
            border: 'border-gray-500 bg-gray-50',
            text: 'text-gray-600',
        },
        locked: {
            border: 'border-yellow-500 bg-yellow-50',
            text: 'text-yellow-600',
        },
        checked: {
            border: 'border-purple-500 bg-purple-50',
            text: 'text-purple-600',
        }
    };
    
    return styles[status] || styles.inactive;
};

// User assignment modal
const toggleUserDropdown = (cardId, event) => {
    // Check if the sprint is locked
    if (props.currentSprint && (props.currentSprint.status === 'locked' || props.currentSprint.status === 'checked')) {
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
            const dropdownWidth = 256;
            const viewportHeight = window.innerHeight;
            const viewportWidth = window.innerWidth;
            
            const spaceBelow = viewportHeight - rect.bottom;
            
            // Vertical positioning
            if (spaceBelow < dropdownHeight) {
                userDropdownPosition.value = {
                    top: `${rect.top + window.scrollY - 160}px`,
                };
            } else {
                userDropdownPosition.value = {
                    top: `${rect.bottom + window.scrollY + 5}px`,
                };
            }
            
            // Horizontal positioning
            if (rect.right + dropdownWidth > viewportWidth) {
                userDropdownPosition.value.right = `${window.innerWidth - rect.right - window.scrollX}px`;
                userDropdownPosition.value.left = 'auto';
            } else {
                userDropdownPosition.value.left = `${rect.left + window.scrollX}px`;
                userDropdownPosition.value.right = 'auto';
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

const showUserFilter = ref(false);
const userFilterPosition = ref({ right: 'auto' });

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

onMounted(() => {
    document.addEventListener('click', (e) => {
        // Close user assignment modal
        if (userDropdownOpen.value && !e.target.closest('.user-dropdown') && !e.target.closest('.user-avatar')) {
            userDropdownOpen.value = null;
        }
        
        // Close user filter dropdown
        if (showUserFilter.value && !e.target.closest('.user-filter-dropdown')) {
            showUserFilter.value = false;
        }
        
        // Close category filter dropdown
        if (showCategoryFilter.value && !e.target.closest('.category-filter-dropdown')) {
            showCategoryFilter.value = false;
        }
        
        // Close sort options dropdown
        if (showSortOptions.value && !e.target.closest('.sort-options-dropdown')) {
            showSortOptions.value = false;
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

const selectedUserId = ref(null);

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
    // Find the source column directly from props.columns
    const sourceColumn = props.columns.find(col => col.id === columnId);
    // Check the status property directly on the column object
    if (!sourceColumn || sourceColumn.status === 'locked') { 
        toast.warning('Cannot move cards from a locked column.'); // Optional: Add feedback
        event.preventDefault();
        return;
    }
    
    currentDragCardId.value = cardId;
    currentDragSourceColumnId.value = columnId;
    event.dataTransfer.effectAllowed = 'move';
};

const handleDragOver = (event, columnId) => {
    // Check if the target column is locked before allowing drop indication
    const targetColumn = props.columns.find(col => col.id === columnId);
    if (targetColumn && targetColumn.status === 'locked') {
        event.dataTransfer.dropEffect = 'none';
    } else {
        event.dataTransfer.dropEffect = 'move';
        event.preventDefault();
        currentDragColumnId.value = columnId;
    }
};

const handleDragLeave = () => {
    currentDragColumnId.value = null;
};

const handleDrop = async (event, targetColumnId) => {
    event.preventDefault();
    
    // Find the target column directly from props.columns
    const targetColumn = props.columns.find(col => col.id === targetColumnId);
    // Check the status property directly on the column object
    if (!targetColumn || targetColumn.status === 'locked') { 
        toast.error('Cannot move cards to a locked column.');
        currentDragCardId.value = null;
        currentDragSourceColumnId.value = null;
        currentDragColumnId.value = null;
        return;
    }
    
    if (currentDragCardId.value && currentDragSourceColumnId.value && targetColumnId && currentDragSourceColumnId.value !== targetColumnId) {
        loading.value = true;
        try {
            const updatedColumns = await tryMoveCard({
                cardId: currentDragCardId.value,
                sourceColumnId: currentDragSourceColumnId.value,
                targetColumnId: targetColumnId,
                columns: props.columns
            });
            
            // Emit the updated columns array received from the helper
            emit('columns-updated', updatedColumns); 
            emit('burndown-update');
            
        } catch (error) {
            console.error("Error moving card:", error);
            toast.error('Failed to move card.'); 
        } finally {
            loading.value = false;
            // Reset drag state regardless of success/failure
            currentDragCardId.value = null;
            currentDragSourceColumnId.value = null;
            currentDragColumnId.value = null;
        }
    } else {
         // Reset drag state if dropped on the same column or invalid drop
         currentDragCardId.value = null;
         currentDragSourceColumnId.value = null;
         currentDragColumnId.value = null;
    }
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
const handleUpdateCard = async ({ cardId, title, description, points, categoryId }) => {
    loading.value = true;
    try {
        const updatedColumns = await tryUpdateCard({ 
            cardId, 
            title, 
            description, 
            points, 
            categoryId,
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

const handleAddCard = async ({ columnId, title, description, points, categoryId }) => {
    loading.value = true;
    try {
        const updatedColumns = await tryAddCard({ 
            columnId, 
            title, 
            description, 
            points, 
            categoryId,
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

// Helper function to create swimlanes structure
const createSwimlanes = (cards, users) => {
    const grouped = groupBy(cards, 'user_id');
    
    const swimlanes = [];
    
    // Add Unassigned lane first if it exists
    if (grouped['null'] || grouped['undefined']) {
        swimlanes.push({
            userId: null,
            userName: 'Unassigned',
            userColor: null,
            cards: grouped['null'] || grouped['undefined'] || []
        });
    }
    
    // Add lanes for assigned users, ensuring order matches props.users
    users.forEach(user => {
        if (grouped[user.id]) {
            swimlanes.push({
                userId: user.id,
                userName: user.name,
                userColor: user.color,
                cards: grouped[user.id]
            });
        }
    });
    
    return swimlanes;
};

// Modify regularColumns and doneColumn computed properties
const regularColumns = computed(() => {
    const filteredColumns = props.columns.filter(column => !column.is_done_column);
    
    return filteredColumns.map(column => {
        // Apply user filter first
        let cardsToProcess = selectedUserId.value
            ? column.cards.filter(card => card.user_id === selectedUserId.value || card.user_id === null)
            : column.cards;
            
        // Apply category filter
        if (selectedCategoryId.value !== null) {
            cardsToProcess = cardsToProcess.filter(card => {
                if (selectedCategoryId.value === 'uncategorized') {
                    return !card.category_id;
                }
                return card.category_id === selectedCategoryId.value;
            });
        }
        
        // Sort cards
        const sortedCards = sortCards(cardsToProcess);
        
        const swimlanes = createSwimlanes(sortedCards, props.users);
        
        // If filtering by user, only keep relevant swimlanes
        const finalSwimlanes = selectedUserId.value
            ? swimlanes.filter(lane => lane.userId === selectedUserId.value || lane.userId === null)
            : swimlanes;

        return {
            ...column,
            swimlanes: finalSwimlanes,
        };
    });
});

const doneColumn = computed(() => {
    const found = props.columns.find(column => column.is_done_column);
    if (!found) return null;

    // Apply user filter first
    let cardsToProcess = selectedUserId.value
        ? found.cards.filter(card => card.user_id === selectedUserId.value || card.user_id === null)
        : found.cards;
        
    // Apply category filter
    if (selectedCategoryId.value !== null) {
        cardsToProcess = cardsToProcess.filter(card => {
            if (selectedCategoryId.value === 'uncategorized') {
                return !card.category_id;
            }
            return card.category_id === selectedCategoryId.value;
        });
    }
    
    // Sort cards
    const sortedCards = sortCards(cardsToProcess);

    const swimlanes = createSwimlanes(sortedCards, props.users);

    // If filtering by user, only keep relevant swimlanes
    const finalSwimlanes = selectedUserId.value
        ? swimlanes.filter(lane => lane.userId === selectedUserId.value || lane.userId === null)
        : swimlanes;
        
    return {
        ...found,
        swimlanes: finalSwimlanes,
    };
});

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
// Add touch-specific state variables
const touchDragCardId = ref(null);
const touchDragSourceColumnId = ref(null);
const touchDragColumnId = ref(null);

// Touch event handlers
const handleTouchDragStart = (cardId, columnId) => {
    // Find the source column directly from props.columns
    const sourceColumn = props.columns.find(col => col.id === columnId);
    // Check the status property directly on the column object
    if (!sourceColumn || sourceColumn.status === 'locked') { 
        toast.warning('Cannot move cards from a locked column.');
        return;
    }
    
    touchDragCardId.value = cardId;
    touchDragSourceColumnId.value = columnId;
};

const handleTouchDragOver = (columnId) => {
    // Check if the target column is locked before allowing drop indication
    const targetColumn = props.columns.find(col => col.id === columnId);
    if (targetColumn && targetColumn.status === 'locked') {
        return;
    }
    
    touchDragColumnId.value = columnId;
};

const handleTouchDragEnd = () => {
    touchDragColumnId.value = null;
};

const handleTouchDrop = async (targetColumnId) => {
    // Use the same column finding logic as in handleDrop
    const targetColumn = props.columns.find(col => col.id === parseInt(targetColumnId) || col.id === targetColumnId);
    
    if (!targetColumn) {
        toast.error('Target column not found');
        touchDragCardId.value = null;
        touchDragSourceColumnId.value = null;
        touchDragColumnId.value = null;
        return;
    }
    
    // Check if the column is locked
    if (targetColumn.status === 'locked') {
        toast.error('Cannot move cards to a locked column');
        touchDragCardId.value = null;
        touchDragSourceColumnId.value = null;
        touchDragColumnId.value = null;
        return;
    }
    
    if (touchDragCardId.value && touchDragSourceColumnId.value && targetColumnId && touchDragSourceColumnId.value !== targetColumnId) {
        loading.value = true;
        try {
            const updatedColumns = await tryMoveCard({
                cardId: touchDragCardId.value,
                sourceColumnId: touchDragSourceColumnId.value,
                targetColumnId: targetColumnId,
                columns: props.columns
            });
            
            // Emit the updated columns array received from the helper
            emit('columns-updated', updatedColumns); 
            emit('burndown-update');
            
        } catch (error) {
            console.error("Error moving card:", error);
            toast.error('Failed to move card.'); 
        } finally {
            loading.value = false;
            // Reset drag state regardless of success/failure
            touchDragCardId.value = null;
            touchDragSourceColumnId.value = null;
            touchDragColumnId.value = null;
        }
    } else {
        // Reset drag state if dropped on the same column or invalid drop
        touchDragCardId.value = null;
        touchDragSourceColumnId.value = null;
        touchDragColumnId.value = null;
    }
};

// Add new reactive state after existing refs
const selectedCategoryId = ref(null);
const sortBy = ref('date-old'); // 'date-old', 'date-new', 'category', 'points-high', 'points-low'
const showCategoryFilter = ref(false);
const categoryFilterPosition = ref({ right: 'auto' });
const showSortOptions = ref(false);
const sortOptionsPosition = ref({ right: 'auto' });

// Add category filter toggle function after toggleUserFilter
const toggleCategoryFilter = (event) => {
    showCategoryFilter.value = !showCategoryFilter.value;
    
    if (showCategoryFilter.value) {
        nextTick(() => {
            const buttonElement = event.target.closest('button');
            if (buttonElement) {
                const rect = buttonElement.getBoundingClientRect();
                const dropdownWidth = 200;
                const viewportWidth = window.innerWidth;
                
                if (rect.right + dropdownWidth > viewportWidth) {
                    categoryFilterPosition.value = { right: '0px', left: 'auto' };
                } else {
                    categoryFilterPosition.value = { right: 'auto', left: '0px' };
                }
            }
        });
    }
    
    if (userDropdownOpen.value) {
        userDropdownOpen.value = null;
    }
    if (showUserFilter.value) {
        showUserFilter.value = false;
    }
};

// Add sort options toggle function
const toggleSortOptions = (event) => {
    showSortOptions.value = !showSortOptions.value;
    
    if (showSortOptions.value) {
        nextTick(() => {
            const buttonElement = event.target.closest('button');
            if (buttonElement) {
                const rect = buttonElement.getBoundingClientRect();
                const dropdownWidth = 200;
                const viewportWidth = window.innerWidth;
                
                if (rect.right + dropdownWidth > viewportWidth) {
                    sortOptionsPosition.value = { right: '0px', left: 'auto' };
                } else {
                    sortOptionsPosition.value = { right: 'auto', left: '0px' };
                }
            }
        });
    }
    
    if (userDropdownOpen.value) {
        userDropdownOpen.value = null;
    }
    if (showUserFilter.value) {
        showUserFilter.value = false;
    }
    if (showCategoryFilter.value) {
        showCategoryFilter.value = false;
    }
};

// Add sorting function
const sortCards = (cards) => {
    const sortedCards = [...cards];
    
    switch (sortBy.value) {
        case 'category':
            return sortedCards.sort((a, b) => {
                const categoryA = props.categories.find(cat => cat.id === a.category_id);
                const categoryB = props.categories.find(cat => cat.id === b.category_id);
                const nameA = categoryA ? categoryA.name : 'Uncategorized';
                const nameB = categoryB ? categoryB.name : 'Uncategorized';
                return nameA.localeCompare(nameB);
            });
        case 'points-high':
            return sortedCards.sort((a, b) => (b.points || 0) - (a.points || 0));
        case 'points-low':
            return sortedCards.sort((a, b) => (a.points || 0) - (b.points || 0));
        case 'date-new':
            return sortedCards.sort((a, b) => {
                console.log(a, b)
                const dateA = new Date(a.created_at);
                const dateB = new Date(b.created_at);
                return dateB.getTime() - dateA.getTime();
            });
        case 'date-old':
        default:
            return sortedCards.sort((a, b) => {
                const dateA = new Date(a.created_at);
                const dateB = new Date(b.created_at);
                return dateA.getTime() - dateB.getTime();
            });
    }
};
</script>

<template>
    <div class="flex justify-center">
        <div class="bg-white p-6 rounded-lg shadow w-full">
            <!-- Board header - restructured -->
            <div class="flex flex-col mb-3">
                <!-- Title row with board title and sprint info -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center mb-3">
                        <h1 class="text-2xl font-semibold text-gray-800 truncate">{{ board.title }}</h1>
                        <div v-if="currentSprint" class="text-gray-600 truncate flex items-center ml-4">
                            <span>({{ currentSprint.title }})</span>
                            <span
                                :class="[
                                    getStatusStyles(currentSprint.status).border,
                                    getStatusStyles(currentSprint.status).text,
                                    'text-sm px-2.5 py-1 rounded-full font-semibold whitespace-nowrap ml-2'
                                ]"
                            >
                                {{ currentSprint.status }}
                            </span>
                            <button 
                                @click="toggleDescription" 
                                class="text-gray-500 hover:text-gray-700 focus:outline-none ml-2"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 15 15" fill="currentColor">
                                    <path v-if="!showDescription" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    <path v-else fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                <!-- Filter and sort controls row -->
                <div class="flex items-center space-x-4">
                    <!-- User Filter -->
                    <div class="relative user-filter-dropdown mb-3">
                        <button 
                            @click="toggleUserFilter"
                            class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            User
                            <span v-if="selectedUserId" class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                {{ users.find(u => u.id === selectedUserId)?.name || 'Unknown' }}
                            </span>
                        </button>
                        
                        <!-- User Filter Dropdown -->
                        <div v-if="showUserFilter" 
                            class="absolute z-50 mt-2 w-64 bg-white border border-gray-200 rounded-md shadow-lg"
                            :style="userFilterPosition">
                            <div class="p-2">
                                <div class="space-y-1">
                                    <button 
                                        @click="selectedUserId = null; showUserFilter = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors',
                                            selectedUserId === null 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        All Users
                                    </button>
                                    <button 
                                        v-for="user in users" 
                                        :key="user.id"
                                        @click="selectedUserId = user.id; showUserFilter = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors flex items-center',
                                            selectedUserId === user.id 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        <div 
                                            class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-medium mr-2 flex-shrink-0"
                                            :style="{ 
                                                backgroundColor: isValidHexColor(user.color) ? user.color : '#3b82f6',
                                                color: isLightColor(user.color) ? '#000000' : '#ffffff'
                                            }"
                                        >
                                            {{ getInitials(user.name) }}
                                        </div>
                                        {{ user.name }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="relative category-filter-dropdown mb-3">
                        <button 
                            @click="toggleCategoryFilter"
                            class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.023.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            Category
                            <span v-if="selectedCategoryId !== null" class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                {{ selectedCategoryId === 'uncategorized' ? 'Uncategorized' : (categories.find(c => c.id === selectedCategoryId)?.name || 'Unknown') }}
                            </span>
                        </button>
                        
                        <!-- Category Filter Dropdown -->
                        <div v-if="showCategoryFilter" 
                             class="absolute z-50 mt-2 w-64 bg-white border border-gray-200 rounded-md shadow-lg"
                             :style="categoryFilterPosition">
                            <div class="p-2">
                                <div class="space-y-1">
                                    <button 
                                        @click="selectedCategoryId = null; showCategoryFilter = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors',
                                            selectedCategoryId === null 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        All Categories
                                    </button>
                                    <button 
                                        @click="selectedCategoryId = 'uncategorized'; showCategoryFilter = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors',
                                            selectedCategoryId === 'uncategorized' 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        Uncategorized
                                    </button>
                                    <button 
                                        v-for="category in categories" 
                                        :key="category.id"
                                        @click="selectedCategoryId = category.id; showCategoryFilter = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors flex items-center',
                                            selectedCategoryId === category.id 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        <div 
                                            class="w-3 h-3 rounded-full mr-2 flex-shrink-0"
                                            :style="{ backgroundColor: category.color }"
                                        ></div>
                                        {{ category.name }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sort Options -->
                    <div class="relative sort-options-dropdown mb-3">
                        <button 
                            @click="toggleSortOptions"
                            class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                            </svg>
                            Sort: {{ 
                                sortBy === 'date-old' ? 'Date (Old to New)' : 
                                sortBy === 'date-new' ? 'Date (New to Old)' : 
                                sortBy === 'category' ? 'Category' : 
                                sortBy === 'points-high' ? 'Points (High to Low)' : 
                                'Points (Low to High)' 
                            }}
                        </button>
                        
                        <!-- Sort Options Dropdown -->
                        <div v-if="showSortOptions" 
                             class="absolute z-50 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg"
                             :style="sortOptionsPosition">
                            <div class="p-2">
                                <div class="space-y-1">
                                    <button 
                                        @click="sortBy = 'date-old'; showSortOptions = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors',
                                            sortBy === 'date-old' 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        Date (Old to New)
                                    </button>
                                    <button 
                                        @click="sortBy = 'date-new'; showSortOptions = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors',
                                            sortBy === 'date-new' 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        Date (New to Old)
                                    </button>
                                    <button 
                                        @click="sortBy = 'category'; showSortOptions = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors',
                                            sortBy === 'category' 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        Category
                                    </button>
                                    <button 
                                        @click="sortBy = 'points-high'; showSortOptions = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors',
                                            sortBy === 'points-high' 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        Points (High to Low)
                                    </button>
                                    <button 
                                        @click="sortBy = 'points-low'; showSortOptions = false"
                                        :class="[
                                            'w-full text-left px-3 py-2 text-sm rounded-md transition-colors',
                                            sortBy === 'points-low' 
                                                ? 'bg-blue-100 text-blue-900 font-medium' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        Points (Low to High)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
                
                <!-- Board description (shown/hidden based on showDescription) -->
                <div v-if="showDescription" class="rounded-md text-gray-700 text-sm">
                    {{ board.description || 'No description available.' }}
                </div>  
            </div>
            <!-- Columns container -->
            <div class="flex justify-center">
                <div 
                    class="flex space-x-4 overflow-x-auto pb-4 w-full"
                >
                    <!-- Regular columns are rendered first using v-for -->
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
                        :categories="props.categories"
                        @add-card-toggle="toggleAddCard"
                        @card-editing-changed="toggleEditCard"
                        @delete-card="handleDeleteCard"
                        @drag-start="handleDragStart"
                        @drag-over="handleDragOver"
                        @drag-leave="handleDragLeave"
                        @drop="handleDrop"
                        @touch-drag-start="handleTouchDragStart"
                        @touch-drag-over="handleTouchDragOver"
                        @touch-drag-end="handleTouchDragEnd"
                        @touch-drop="handleTouchDrop"
                        @toggle-user-dropdown="toggleUserDropdown"
                        @columns-updated="emit('columns-updated', $event)"
                    >
                        <template #card-edit-form="{ card }">
                            <CardForm 
                                :loading="loading"
                                :column-id="card.column_id"
                                :card="card"
                                :initial-title="cardTitle"
                                :initial-description="cardDesc"
                                :initial-points="cardPoints"
                                :initial-category-id="card.category_id"
                                :is-editing="true"
                                :categories="props.categories"
                                @save="(data) => handleUpdateCard({
                                    cardId: card.id,
                                    columnId: card.column_id,
                                    title: data.title,
                                    description: data.description,
                                    points: data.points,
                                    categoryId: data.categoryId,
                                })"
                                @cancel="resetForm"
                            />
                        </template>
                        <template #add-card-form>
                            <CardForm 
                                :loading="loading"
                                :column-id="column.id"
                                :key="`form-${column.id}-${Date.now()}`"
                                :categories="props.categories"
                                @save="handleAddCard"
                                @cancel="toggleAddCard(column.id)"
                            />
                        </template>
                    </Column>
                    
                    <!-- Done column is rendered *after* the regular columns -->
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
                        :categories="props.categories"
                        @add-card-toggle="toggleAddCard"
                        @card-editing-changed="toggleEditCard"
                        @delete-card="handleDeleteCard"
                        @drag-start="handleDragStart"
                        @drag-over="handleDragOver"
                        @drag-leave="handleDragLeave"
                        @drop="handleDrop"
                        @touch-drag-start="handleTouchDragStart"
                        @touch-drag-over="handleTouchDragOver"
                        @touch-drag-end="handleTouchDragEnd"
                        @touch-drop="handleTouchDrop"
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
                                :categories="props.categories"
                                @save="(data) => handleUpdateCard({
                                    cardId: card.id,
                                    columnId: card.column_id,
                                    title: data.title,
                                    description: data.description,
                                    points: data.points,
                                    categoryId: data.categoryId,
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
                    
                    <!-- Add column form/button is rendered last -->
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
    <div v-if="userDropdownOpen && (!props.currentSprint || (props.currentSprint.status !== 'locked' && props.currentSprint.status !== 'checked'))" class="absolute inset-0 z-50">
        <div 
            class="bg-white rounded-md shadow-lg w-64 absolute overflow-hidden user-dropdown"
            :style="userDropdownPosition"
        >
            <div class="py-1">
                <div 
                    v-for="user in props.users" 
                    :key="user.id"
                    class="flex items-center px-4 py-2 hover:bg-gray-100 cursor-pointer"
                    @click="assignUserToCard(userDropdownOpen, user.id)"
                >
                    <div 
                        class="h-8 w-8 rounded-full flex items-center justify-center font-bold text-sm mr-2"
                        :style="{ 
                            backgroundColor: isValidHexColor(user.color) ? user.color : '#3b82f6',
                            color: isLightColor(user.color) ? '#000000' : '#ffffff'
                        }"
                    >
                        {{ getInitials(user.name) }}
                    </div>
                    <span>{{ user.name }}</span>
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
