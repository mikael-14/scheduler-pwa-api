<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */

    'column.name' => 'Nome',
    'column.guard_name' => 'Nome da Proteção',
    'column.roles' => 'Funções',
    'column.permissions' => 'Permissões',
    'column.updated_at' => 'Atualizado em',

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */

    'field.name' => 'Nome',
    'field.guard_name' => 'Nome da Proteção',
    'field.permissions' => 'Permissões',
    'field.select_all.name' => 'Selecionar Todos',
    'field.select_all.message' => 'Selecionar todas as Permissões <span class="text-primary font-medium">Ativas</span> para esta função',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => 'Administração',
    'nav.role.label' => 'Funções',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => 'Função',
    'resource.label.roles' => 'Funções',

    /*
    |--------------------------------------------------------------------------
    | Section & Tabs
    |--------------------------------------------------------------------------
    */

    'section' => 'Entidades',
    'resources' => 'Recursos',
    'widgets' => 'Widgets',
    'pages' => 'Páginas',
    'custom' => 'Permissões Personalizadas',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'forbidden' => 'Não possui permissões para aceder',

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions' Labels
    |--------------------------------------------------------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => 'Visualizar',
        'view_any' => 'Visualizar Tudo',
        'create' => 'Criar',
        'update' => 'Actualizar',
        'delete' => 'Eliminar',
        'delete_any' => 'Eliminar Tudo',
        'force_delete' => 'Eliminar Permanentemente',
        'force_delete_any' => 'Eliminar Permanentemente Tudo',
        'restore' => 'Restaurar',
        'reorder' => 'Reordenar',
        'restore_any' => 'Restaurar Tudo',
        'replicate' => 'Replicar',
    ],

    'column.team' => 'Equipa',
    'field.team' => 'Equipa',
    'field.team.placeholder' => 'Selecione uma equipa ...',
];
