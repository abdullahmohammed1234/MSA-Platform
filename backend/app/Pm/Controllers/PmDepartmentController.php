<?php

namespace App\Pm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Pm\Enums\DepartmentRole;
use Illuminate\Http\JsonResponse;

class PmDepartmentController extends Controller
{
    /**
     * List all canonical departments with their appointed leads and member counts.
     */
    public function index(): JsonResponse
    {
        $departments = Department::where('is_active', true)
            ->withCount([
                'memberships as active_members_count' => function ($q) {
                    $q->active();
                },
            ])
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'success' => true,
            'departments' => $departments->map(function (Department $d) {
                $lead = $d->memberships()->active()->where('department_role', DepartmentRole::Lead->value)->first()?->user;

                return [
                    'id' => $d->id,
                    'uuid' => $d->uuid,
                    'name' => $d->name,
                    'slug' => $d->slug,
                    'code' => match ($d->slug) {
                        'presidential' => 'PRE',
                        'marketing' => 'MAR',
                        'finance' => 'FIN',
                        'education' => 'EDU',
                        'graphics' => 'GRA',
                        'events' => 'EVE',
                        'it' => 'IT',
                        'jumuah' => 'JUM',
                        default => strtoupper(substr($d->slug, 0, 3)),
                    },
                    'description' => $d->description,
                    'color' => $d->color,
                    'display_order' => $d->display_order,
                    'active_members_count' => $d->active_members_count,
                    'lead' => $lead ? [
                        'id' => $lead->id,
                        'name' => $lead->name,
                        'email' => $lead->email,
                        'avatar' => $lead->avatar,
                    ] : null,
                ];
            }),
        ]);
    }
}
