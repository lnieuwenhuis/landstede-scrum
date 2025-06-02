<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import ConfirmModal from '../../Pages/Boards/ShowComponents/ConfirmModal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ChromePicker } from 'vue-color';

const toast = useToast();

const props = defineProps({
    categories: Array
});

let loading = ref(false);
const categories = ref(props.categories || []);
const showCategoryModal = ref(false);
const showDeleteCategoryConfirmation = ref(false);
const categoryToDelete = ref(null);
const categoryToEdit = ref(null);
const isEditMode = ref(false);
const categoryData = ref({
    name: '',
    description: '',
    color: '#3B82F6' // Default blue color
});

const resetCategoryData = () => {
    categoryData.value = {
        name: '',
        description: '',
        color: '#3B82F6'
    };
    isEditMode.value = false;
    categoryToEdit.value = null;
};

const openCategoryModal = (category = null) => {
    if (category) {
        categoryToEdit.value = category;
        categoryData.value = {
            id: category.id,
            name: category.name,
            description: category.description,
            color: category.color || '#3B82F6'
        };
        isEditMode.value = true;
    } else {
        resetCategoryData();
    }
    showCategoryModal.value = true;
    document.body.style.overflow = 'hidden';
};

const closeCategoryModal = () => {
    showCategoryModal.value = false;
    document.body.style.overflow = '';
    resetCategoryData();
};

const handleCreateCategory = async () => {
    loading.value = true;
    try {
        const response = await axios.post('/api/categories/createCategory', categoryData.value);
        
        console.log(response.data);

        if (response.data) {
            // Add the new category to the list
            categories.value.push(response.data);
            toast.success('Category created successfully');
            closeCategoryModal();
        }
    } catch (error) {
        console.error('Error creating category:', error);
        toast.error(error.response?.data?.message || 'Failed to create category');
    } finally {
        loading.value = false;
    }
};

const handleUpdateCategory = async () => {
    loading.value = true;
    try {
        const response = await axios.post('/api/categories/updateCategory', categoryData.value);
        
        if (response.data) {
            // Update the category in the list
            const index = categories.value.findIndex(c => c.id === categoryData.value.id);
            if (index !== -1) {
                categories.value[index] = response.data.category || {
                    ...categories.value[index],
                    ...categoryData.value
                };
            }
            toast.success('Category updated successfully');
            closeCategoryModal();
        }
    } catch (error) {
        console.error('Error updating category:', error);
        toast.error(error.response?.data?.message || 'Failed to update category');
    } finally {
        loading.value = false;
    }
};

const toggleDeleteCategory = (categoryId = null) => {
    document.body.style.overflow = showDeleteCategoryConfirmation.value ? '' : 'hidden';
    showDeleteCategoryConfirmation.value = !showDeleteCategoryConfirmation.value;
    categoryToDelete.value = categoryId;
};

const handleDeleteCategory = async () => {
    try {
        const response = await axios.post('/api/categories/deleteCategory', {
            id: categoryToDelete.value
        });

        if (response.data) {
            categories.value = categories.value.filter(category => category.id !== categoryToDelete.value);
            toast.success('Category deleted successfully');
            toggleDeleteCategory();
        }
    } catch (error) {
        console.error('Error deleting category:', error);
        toast.error(error.response?.data?.message || 'Failed to delete category');
    }
};

const handleSaveCategory = () => {
    if (isEditMode.value) {
        handleUpdateCategory();
    } else {
        handleCreateCategory();
    }
};
</script>

<template>
    <Head title="Manage Categories" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Manage Categories
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Categories</h3>
                            <button 
                                @click="openCategoryModal()"
                                class="px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center gap-2 h-10"
                                :disabled="loading"
                                :class="{ 'opacity-50 cursor-not-allowed': loading }"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                </svg>
                                {{ loading ? 'Creating...' : 'New Category' }}
                            </button>
                        </div>                        
                        <div class="space-y-4">
                            <div 
                                v-for="category in categories" 
                                :key="category.id"
                                class="p-4 rounded-lg shadow-sm transition-all duration-200 hover:bg-black/5 border-l-4"
                                :style="{ borderLeftColor: category.color || '#3B82F6' }"
                            >
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-800 flex items-center">
                                            {{ category.name }}
                                            <span 
                                                class="ml-2 w-4 h-4 rounded-full"
                                                :style="{ backgroundColor: category.color || '#3B82F6' }"
                                            ></span>
                                        </h3>
                                        <div class="mt-2 space-y-1">
                                            <p class="text-sm text-gray-600">
                                                {{ category.description || 'No description provided' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button 
                                            @click="openCategoryModal(category)"
                                            class="p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="toggleDeleteCategory(category.id)"
                                            class="p-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-if="categories.length === 0" class="py-4 text-center text-gray-500 italic">
                                No categories created yet
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Modal (Create/Edit) -->
        <div v-if="showCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ isEditMode ? 'Edit Category' : 'Create Category' }}
                </h3>
                
                <div class="space-y-4">
                    <!-- Category Name -->
                    <div>
                        <label for="category-name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input 
                            id="category-name" 
                            v-model="categoryData.name" 
                            type="text" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter category name"
                        />
                    </div>
                    
                    <!-- Category Description -->
                    <div>
                        <label for="category-description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea 
                            id="category-description" 
                            v-model="categoryData.description" 
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter category description"
                        ></textarea>
                    </div>
                    
                    <!-- Category Color -->
                    <div>
                        <label for="category-color" class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                        <div class="flex flex-col space-y-2">
                            <ChromePicker 
                                v-model="categoryData.color"
                                class="mb-2"
                            />
                            <div class="flex items-center space-x-2">
                                <div 
                                    class="h-10 w-10 rounded-md border border-gray-300"
                                    :style="{ backgroundColor: categoryData.color }"
                                ></div>
                                <input 
                                    v-model="categoryData.color" 
                                    type="text" 
                                    class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="#RRGGBB"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button 
                        @click="closeCategoryModal" 
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="handleSaveCategory" 
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                        :disabled="loading"
                        :class="{ 'opacity-50 cursor-not-allowed': loading }"
                    >
                        {{ loading ? 'Saving...' : (isEditMode ? 'Update' : 'Create') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <ConfirmModal
            v-if="showDeleteCategoryConfirmation"
            :show="showDeleteCategoryConfirmation"
            title="Delete Category"
            message="Are you sure you want to delete this category? This action cannot be undone."
            confirm-text="Delete"
            cancel-text="Cancel"
            @cancel="toggleDeleteCategory()"
            @confirm="handleDeleteCategory"
        />
    </AuthenticatedLayout>
</template>