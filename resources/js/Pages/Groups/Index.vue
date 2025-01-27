<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

const { props } = usePage();

const groups = props.groups;
</script>

<template>
    <Head title="Your Groups" />

    <AuthenticatedLayout>
        <div>
            <h1>Groups Index</h1>
            <ul>
                <li v-for="group in groups" :key="group.id">
                    <a style="color: blue;" :href="`/groups/${group.id}`">{{ group.title }}</a>
                </li>
            </ul>
            <!-- Display a message if groups is empty -->
            <p v-if="groups.length === 0">No groups found.</p>
        </div>
    </AuthenticatedLayout>
</template>
