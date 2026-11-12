<?php

namespace App\Pm\Support;

class PmPermissions
{
    public const MODULE = 'PMS';

    // Core application access
    public const ACCESS = 'pm.access';
    public const VIEW = 'pm.view';
    public const ADMIN = 'pm.admin';

    // Projects
    public const PROJECTS_VIEW = 'pm.projects.view';
    public const PROJECTS_CREATE = 'pm.projects.create';
    public const PROJECTS_UPDATE = 'pm.projects.update';
    public const PROJECTS_DELETE = 'pm.projects.delete';
    public const PROJECTS_MANAGE = 'pm.projects.manage';

    // Departments
    public const DEPARTMENTS_VIEW = 'pm.departments.view';
    public const DEPARTMENTS_MANAGE = 'pm.departments.manage';

    // Tasks
    public const TASKS_VIEW = 'pm.tasks.view';
    public const TASKS_CREATE = 'pm.tasks.create';
    public const TASKS_UPDATE = 'pm.tasks.update';
    public const TASKS_DELETE = 'pm.tasks.delete';
    public const TASKS_ASSIGN = 'pm.tasks.assign';
    public const TASKS_MANAGE = 'pm.tasks.manage';

    // Members
    public const MEMBERS_VIEW = 'pm.members.view';
    public const MEMBERS_MANAGE = 'pm.members.manage';

    // Invitations
    public const INVITATIONS_CREATE = 'pm.invitations.create';
    public const INVITATIONS_MANAGE = 'pm.invitations.manage';

    // Administration
    public const ROLES_VIEW = 'pm.roles.view';
    public const ROLES_MANAGE = 'pm.roles.manage';
    public const PERMISSIONS_VIEW = 'pm.permissions.view';
    public const PERMISSIONS_MANAGE = 'pm.permissions.manage';
    public const SETTINGS_MANAGE = 'pm.settings.manage';

    /**
     * @return array<string, array{name: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            self::ACCESS => [
                'name' => 'Access Project Management System',
                'description' => 'Permits access to the PMS application at projects.sfumsa.ca.',
            ],
            self::VIEW => [
                'name' => 'View Projects and Tasks',
                'description' => 'View projects, tasks, and milestones within authorized departments and shared MSA.',
            ],
            self::PROJECTS_VIEW => [
                'name' => 'View PMS Projects',
                'description' => 'View project overviews, milestones, and details within authorized scope.',
            ],
            self::PROJECTS_CREATE => [
                'name' => 'Create Projects',
                'description' => 'Create new projects within member department or as shared MSA projects.',
            ],
            self::PROJECTS_UPDATE => [
                'name' => 'Update PMS Projects',
                'description' => 'Update project details, timelines, status, and metadata.',
            ],
            self::PROJECTS_DELETE => [
                'name' => 'Delete PMS Projects',
                'description' => 'Archive or delete projects within authorized ownership or administrative scope.',
            ],
            self::PROJECTS_MANAGE => [
                'name' => 'Manage Projects',
                'description' => 'Comprehensive management of projects, settings, and member rosters.',
            ],
            self::DEPARTMENTS_VIEW => [
                'name' => 'View PMS Departments',
                'description' => 'View canonical department information, rosters, and leadership.',
            ],
            self::DEPARTMENTS_MANAGE => [
                'name' => 'Manage Departments',
                'description' => 'Configure department settings, assign department leads, and manage department rosters.',
            ],
            self::TASKS_VIEW => [
                'name' => 'View PMS Tasks',
                'description' => 'View project tasks, details, and milestone associations.',
            ],
            self::TASKS_CREATE => [
                'name' => 'Create PMS Tasks',
                'description' => 'Create new project tasks and subtasks.',
            ],
            self::TASKS_UPDATE => [
                'name' => 'Update PMS Tasks',
                'description' => 'Modify task descriptions, statuses, due dates, and priorities.',
            ],
            self::TASKS_DELETE => [
                'name' => 'Delete PMS Tasks',
                'description' => 'Delete tasks and subtasks within authorized scope.',
            ],
            self::TASKS_ASSIGN => [
                'name' => 'Assign PMS Tasks',
                'description' => 'Assign and reassign project tasks to authorized team members.',
            ],
            self::TASKS_MANAGE => [
                'name' => 'Manage Tasks and Milestones',
                'description' => 'Comprehensive task and milestone management capability.',
            ],
            self::MEMBERS_VIEW => [
                'name' => 'View PMS Members',
                'description' => 'View project members and their assigned roles.',
            ],
            self::MEMBERS_MANAGE => [
                'name' => 'Manage PMS Members',
                'description' => 'Add, update roles of, and remove project members.',
            ],
            self::INVITATIONS_CREATE => [
                'name' => 'Create PMS Invitations',
                'description' => 'Invite MSA team members to collaborate on projects.',
            ],
            self::INVITATIONS_MANAGE => [
                'name' => 'Manage PMS Invitations',
                'description' => 'Resend, modify, or revoke pending project collaboration invitations.',
            ],
            self::ROLES_VIEW => [
                'name' => 'View PMS Roles',
                'description' => 'View canonical PMS roles and permission configurations.',
            ],
            self::ROLES_MANAGE => [
                'name' => 'Manage PMS Roles',
                'description' => 'Assign and update user PMS role designations.',
            ],
            self::PERMISSIONS_VIEW => [
                'name' => 'View PMS Permissions',
                'description' => 'View registered PMS permissions and role mappings.',
            ],
            self::PERMISSIONS_MANAGE => [
                'name' => 'Manage PMS Permissions',
                'description' => 'Configure and adjust PMS role-permission assignments.',
            ],
            self::SETTINGS_MANAGE => [
                'name' => 'Manage PMS Settings',
                'description' => 'Configure PMS system-wide policies and operational settings.',
            ],
            self::ADMIN => [
                'name' => 'Full PMS Administration',
                'description' => 'Global PMS administration, audit log inspection, and platform access provisioning.',
            ],
        ];
    }
}
