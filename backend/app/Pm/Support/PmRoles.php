<?php

namespace App\Pm\Support;

class PmRoles
{
    public const SUPER_ADMIN = 'super-admin';
    public const PRESIDENT = 'president';
    public const EXECUTIVE = 'executive';
    public const DEPARTMENT_LEAD = 'department-lead';
    public const COORDINATOR = 'coordinator';
    public const TEAM_MEMBER = 'team-member';
    public const PMS_ADMIN = 'pms-administrator';

    /**
     * The six canonical PMS roles required by specification.
     *
     * @return array<string, array{name: string, description: string, permissions: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            self::SUPER_ADMIN => [
                'name' => 'Super Admin',
                'description' => 'Platform-level administration with comprehensive PMS administrative, audit, and operational capabilities.',
                'permissions' => [
                    PmPermissions::ACCESS,
                    PmPermissions::VIEW,
                    PmPermissions::PROJECTS_VIEW,
                    PmPermissions::PROJECTS_CREATE,
                    PmPermissions::PROJECTS_UPDATE,
                    PmPermissions::PROJECTS_DELETE,
                    PmPermissions::PROJECTS_MANAGE,
                    PmPermissions::DEPARTMENTS_VIEW,
                    PmPermissions::DEPARTMENTS_MANAGE,
                    PmPermissions::TASKS_VIEW,
                    PmPermissions::TASKS_CREATE,
                    PmPermissions::TASKS_UPDATE,
                    PmPermissions::TASKS_DELETE,
                    PmPermissions::TASKS_ASSIGN,
                    PmPermissions::TASKS_MANAGE,
                    PmPermissions::MEMBERS_VIEW,
                    PmPermissions::MEMBERS_MANAGE,
                    PmPermissions::INVITATIONS_CREATE,
                    PmPermissions::INVITATIONS_MANAGE,
                    PmPermissions::ROLES_VIEW,
                    PmPermissions::ROLES_MANAGE,
                    PmPermissions::PERMISSIONS_VIEW,
                    PmPermissions::PERMISSIONS_MANAGE,
                    PmPermissions::SETTINGS_MANAGE,
                    PmPermissions::ADMIN,
                ],
            ],
            self::PRESIDENT => [
                'name' => 'MSA President',
                'description' => 'MSA leadership role with shared project visibility, executive oversight, and strategic project management.',
                'permissions' => [
                    PmPermissions::ACCESS,
                    PmPermissions::VIEW,
                    PmPermissions::PROJECTS_VIEW,
                    PmPermissions::PROJECTS_CREATE,
                    PmPermissions::PROJECTS_UPDATE,
                    PmPermissions::PROJECTS_MANAGE,
                    PmPermissions::DEPARTMENTS_VIEW,
                    PmPermissions::TASKS_VIEW,
                    PmPermissions::TASKS_CREATE,
                    PmPermissions::TASKS_UPDATE,
                    PmPermissions::TASKS_DELETE,
                    PmPermissions::TASKS_ASSIGN,
                    PmPermissions::TASKS_MANAGE,
                    PmPermissions::MEMBERS_VIEW,
                    PmPermissions::MEMBERS_MANAGE,
                    PmPermissions::INVITATIONS_CREATE,
                    PmPermissions::INVITATIONS_MANAGE,
                    PmPermissions::ROLES_VIEW,
                    PmPermissions::PERMISSIONS_VIEW,
                ],
            ],
            self::EXECUTIVE => [
                'name' => 'MSA Executive',
                'description' => 'MSA executive role with shared project collaboration and cross-functional team coordination.',
                'permissions' => [
                    PmPermissions::ACCESS,
                    PmPermissions::VIEW,
                    PmPermissions::PROJECTS_VIEW,
                    PmPermissions::PROJECTS_CREATE,
                    PmPermissions::PROJECTS_UPDATE,
                    PmPermissions::PROJECTS_MANAGE,
                    PmPermissions::DEPARTMENTS_VIEW,
                    PmPermissions::TASKS_VIEW,
                    PmPermissions::TASKS_CREATE,
                    PmPermissions::TASKS_UPDATE,
                    PmPermissions::TASKS_DELETE,
                    PmPermissions::TASKS_ASSIGN,
                    PmPermissions::TASKS_MANAGE,
                    PmPermissions::MEMBERS_VIEW,
                    PmPermissions::INVITATIONS_CREATE,
                    PmPermissions::ROLES_VIEW,
                    PmPermissions::PERMISSIONS_VIEW,
                ],
            ],
            self::DEPARTMENT_LEAD => [
                'name' => 'Department Lead',
                'description' => 'Manages their department\'s permitted PMS activities, department-private projects, and member assignments.',
                'permissions' => [
                    PmPermissions::ACCESS,
                    PmPermissions::VIEW,
                    PmPermissions::PROJECTS_VIEW,
                    PmPermissions::PROJECTS_CREATE,
                    PmPermissions::PROJECTS_UPDATE,
                    PmPermissions::PROJECTS_MANAGE,
                    PmPermissions::DEPARTMENTS_VIEW,
                    PmPermissions::DEPARTMENTS_MANAGE,
                    PmPermissions::TASKS_VIEW,
                    PmPermissions::TASKS_CREATE,
                    PmPermissions::TASKS_UPDATE,
                    PmPermissions::TASKS_DELETE,
                    PmPermissions::TASKS_ASSIGN,
                    PmPermissions::TASKS_MANAGE,
                    PmPermissions::MEMBERS_VIEW,
                    PmPermissions::MEMBERS_MANAGE,
                    PmPermissions::INVITATIONS_CREATE,
                    PmPermissions::INVITATIONS_MANAGE,
                ],
            ],
            self::COORDINATOR => [
                'name' => 'Project Coordinator',
                'description' => 'Coordinates assigned departmental activities, tasks, milestones, and collaborative projects.',
                'permissions' => [
                    PmPermissions::ACCESS,
                    PmPermissions::VIEW,
                    PmPermissions::PROJECTS_VIEW,
                    PmPermissions::PROJECTS_CREATE,
                    PmPermissions::PROJECTS_UPDATE,
                    PmPermissions::DEPARTMENTS_VIEW,
                    PmPermissions::TASKS_VIEW,
                    PmPermissions::TASKS_CREATE,
                    PmPermissions::TASKS_UPDATE,
                    PmPermissions::TASKS_ASSIGN,
                    PmPermissions::TASKS_MANAGE,
                    PmPermissions::MEMBERS_VIEW,
                    PmPermissions::INVITATIONS_CREATE,
                ],
            ],
            self::TEAM_MEMBER => [
                'name' => 'Team Member',
                'description' => 'Participates in authorized PMS departmental and shared activities, manages assigned tasks, and tracks milestones.',
                'permissions' => [
                    PmPermissions::ACCESS,
                    PmPermissions::VIEW,
                    PmPermissions::PROJECTS_VIEW,
                    PmPermissions::DEPARTMENTS_VIEW,
                    PmPermissions::TASKS_VIEW,
                    PmPermissions::TASKS_CREATE,
                    PmPermissions::TASKS_UPDATE,
                    PmPermissions::MEMBERS_VIEW,
                ],
            ],
            self::PMS_ADMIN => [
                'name' => 'PMS Administrator',
                'description' => 'Full administrative access to PMS application settings, project management, and access controls.',
                'permissions' => [
                    PmPermissions::ACCESS,
                    PmPermissions::VIEW,
                    PmPermissions::PROJECTS_VIEW,
                    PmPermissions::PROJECTS_CREATE,
                    PmPermissions::PROJECTS_UPDATE,
                    PmPermissions::PROJECTS_DELETE,
                    PmPermissions::PROJECTS_MANAGE,
                    PmPermissions::DEPARTMENTS_VIEW,
                    PmPermissions::DEPARTMENTS_MANAGE,
                    PmPermissions::TASKS_VIEW,
                    PmPermissions::TASKS_CREATE,
                    PmPermissions::TASKS_UPDATE,
                    PmPermissions::TASKS_DELETE,
                    PmPermissions::TASKS_ASSIGN,
                    PmPermissions::TASKS_MANAGE,
                    PmPermissions::MEMBERS_VIEW,
                    PmPermissions::MEMBERS_MANAGE,
                    PmPermissions::INVITATIONS_CREATE,
                    PmPermissions::INVITATIONS_MANAGE,
                    PmPermissions::ROLES_VIEW,
                    PmPermissions::ROLES_MANAGE,
                    PmPermissions::PERMISSIONS_VIEW,
                    PmPermissions::PERMISSIONS_MANAGE,
                    PmPermissions::SETTINGS_MANAGE,
                    PmPermissions::ADMIN,
                ],
            ],
        ];
    }
}
