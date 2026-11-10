<script setup lang="ts">
import { ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import ThemeSelector from '@/Components/ThemeSelector.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useBrandRoute } from '@/composables/useBrandRoute';
import { computed } from 'vue';

const route = useBrandRoute();
const page = usePage();

const showingNavigationDropdown = ref(false);

const isAdmin = computed(() => page.props.auth.user?.role === 'admin');
const isManager = computed(() => page.props.auth.user?.role === 'gestor');

// La guía de inicio enlaza pantallas reales con ?from=onboarding: se
// muestra un banner fino para volver al asistente.
const fromOnboarding = computed(() =>
    typeof window !== 'undefined' && window.location.search.includes('from=onboarding'),
);
const activeBrand = computed(() => page.props.brand);
const assignedBrands = computed(() => page.props.brands ?? []);

const brandPlanUrl = (slug: string) => {
    const now = new Date();
    return route('plans.show', {
        brand: slug,
        year: now.getFullYear(),
        month: now.getMonth() + 1,
    });
};
</script>

<template>
    <div>
        <div class="min-h-screen bg-surface-2">
            <nav
                class="border-b border-line bg-surface"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <span class="text-xl font-semibold text-brand-ink dark:text-fg">Contenia<span class="text-brand-primary">.</span></span>
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <!-- Selector de marca -->
                                <div v-if="activeBrand" class="relative flex items-stretch">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                class="inline-flex h-full items-center gap-2 border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-fg-2 transition duration-150 ease-in-out hover:border-line-strong focus:outline-none"
                                            >
                                                <img
                                                    :src="activeBrand.logo_url"
                                                    :alt="activeBrand.name"
                                                    class="h-5 w-5 object-contain"
                                                />
                                                {{ activeBrand.name }}
                                                <svg
                                                    class="-me-0.5 ms-1 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </template>

                                        <template #content>
                                            <DropdownLink
                                                v-for="b in assignedBrands"
                                                :key="b.id"
                                                :href="brandPlanUrl(b.slug)"
                                                as="a"
                                            >
                                                <span class="flex items-center gap-2">
                                                    <img
                                                        :src="b.logo_url"
                                                        :alt="b.name"
                                                        class="h-4 w-4 object-contain"
                                                    />
                                                    {{ b.name }}
                                                </span>
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>

                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard') || route().current('plans.*')"
                                >
                                    Grilla
                                </NavLink>

                                <div class="relative flex items-stretch">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                class="inline-flex h-full items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                                                :class="
                                                    route().current('settings.*')
                                                        ? 'border-indigo-400 text-fg focus:border-indigo-700'
                                                        : 'border-transparent text-muted hover:border-line-strong hover:text-fg-2 focus:border-line-strong focus:text-fg-2'
                                                "
                                            >
                                                Configuración
                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </template>

                                        <template #content>
                                            <DropdownLink :href="route('settings.brand.edit')">
                                                Perfil de marca
                                            </DropdownLink>
                                            <DropdownLink :href="route('settings.networks.index')">
                                                Estrategias por red
                                            </DropdownLink>
                                            <DropdownLink :href="route('settings.pillars.index')">
                                                Pilares de contenido
                                            </DropdownLink>
                                            <DropdownLink :href="route('settings.calendar.index')">
                                                Calendario
                                            </DropdownLink>
                                            <DropdownLink :href="route('settings.guidelines.index')">
                                                Pautas
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>

                                <div v-if="isAdmin || isManager" class="relative flex items-stretch">
                                    <Dropdown align="left" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                class="inline-flex h-full items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none"
                                                :class="
                                                    route().current('brands.*') || route().current('users.*')
                                                        || route().current('admin.plans.*') || route().current('portfolio.*')
                                                        ? 'border-indigo-400 text-fg focus:border-indigo-700'
                                                        : 'border-transparent text-muted hover:border-line-strong hover:text-fg-2 focus:border-line-strong focus:text-fg-2'
                                                "
                                            >
                                                Administración
                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </template>

                                        <template #content>
                                            <DropdownLink :href="route('brands.index')">
                                                Marcas
                                            </DropdownLink>
                                            <DropdownLink v-if="isAdmin" :href="route('users.index')">
                                                Usuarios
                                            </DropdownLink>
                                            <DropdownLink v-if="isAdmin" :href="route('admin.plans.index')">
                                                Planes
                                            </DropdownLink>
                                            <DropdownLink v-if="isAdmin" :href="route('portfolio.index')">
                                                Cartera
                                            </DropdownLink>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                        </div>

                        <div class="hidden shrink-0 sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-surface px-3 py-2 text-sm font-medium leading-4 text-muted transition duration-150 ease-in-out hover:text-fg-2 focus:outline-none"
                                                :title="$page.props.auth.user.name"
                                            >
                                                <span class="hidden whitespace-nowrap lg:inline">{{ $page.props.auth.user.name }}</span>
                                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-primary text-xs font-semibold text-white lg:hidden">
                                                    {{ $page.props.auth.user.name.charAt(0).toUpperCase() }}
                                                </span>

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <ThemeSelector />
                                        <div class="border-t border-line" />
                                        <DropdownLink
                                            :href="route('onboarding.restart')"
                                            method="post"
                                            as="button"
                                        >
                                            Guía de inicio
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-muted transition duration-150 ease-in-out hover:bg-surface-3 hover:text-muted focus:bg-surface-3 focus:text-muted focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard') || route().current('plans.*')"
                        >
                            Grilla
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('settings.brand.edit')"
                            :active="route().current('settings.brand.edit')"
                        >
                            Perfil de marca
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('settings.networks.index')"
                            :active="route().current('settings.networks.index')"
                        >
                            Estrategias por red
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('settings.pillars.index')"
                            :active="route().current('settings.pillars.index')"
                        >
                            Pilares de contenido
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('settings.calendar.index')"
                            :active="route().current('settings.calendar.index')"
                        >
                            Calendario
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('settings.guidelines.index')"
                            :active="route().current('settings.guidelines.index')"
                        >
                            Pautas
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-line pb-1 pt-4"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-fg"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-muted">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                            <ThemeSelector />
                        </div>
                    </div>
                </div>
            </nav>

            <div
                v-if="fromOnboarding"
                class="border-b border-brand-primary/30 bg-brand-tint px-4 py-2 text-center text-xs text-fg-2 dark:bg-brand-primary/10"
            >
                Estás viendo la app desde la guía ·
                <a :href="route('onboarding.show')" class="font-medium text-brand-primary underline">
                    ← Volver a la guía
                </a>
            </div>

            <!-- Page Heading -->
            <header
                class="bg-surface shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
