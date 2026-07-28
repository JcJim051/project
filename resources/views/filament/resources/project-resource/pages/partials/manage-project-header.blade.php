<style>
    .project-manage-header {
        display: flex;
        align-items: flex-end;
        gap: 18px;
        width: 100%;
    }
    .project-manage-heading {
        flex: 0 0 auto;
        min-width: 230px;
        max-width: 34%;
    }
    .project-manage-workflow {
        flex: 1 1 auto;
        min-width: 0;
    }
    .project-manage-page > section {
        row-gap: 12px !important;
    }
    @media (max-width: 1023px) {
        .project-manage-header {
            align-items: stretch;
            flex-direction: column;
            gap: 10px;
        }
        .project-manage-heading {
            max-width: none;
        }
    }
</style>

<header class="fi-header project-manage-header">
    <div class="project-manage-heading">
        @if($breadcrumbs)
            <x-filament::breadcrumbs
                :breadcrumbs="$breadcrumbs"
                class="mb-1.5 hidden sm:block"
            />
        @endif

        <h1 class="fi-header-heading truncate text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
            {{ $heading }}
        </h1>
    </div>

    <div class="project-manage-workflow">
        @include('projects.partials.workflow-navigation')
    </div>
</header>
