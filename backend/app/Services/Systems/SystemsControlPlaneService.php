<?php

namespace App\Services\Systems;

use App\Ems\Models\Event as EmsEvent;
use App\Models\CMS\Announcement;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Platform Systems control plane — registry + safe operational checks (Phase 7–8).
 *
 * Does not own CMS/DAMS/EMS domain data. Does not invent healthy status.
 * Does not leak credentials, tokens, paths, or exception traces.
 */
class SystemsControlPlaneService
{
    public const STATUS_OPERATIONAL = 'operational';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUS_UNKNOWN = 'unknown';

    private const DEP_LABELS = [
        'platform-auth' => 'Platform Authentication',
        'database' => 'Database',
        'storage' => 'Storage',
        'queues' => 'Queues',
        'email' => 'Email',
        'cms-content-apis' => 'CMS Content APIs',
        'cms-resources' => 'CMS Resources',
        'ems-event-apis' => 'EMS Event APIs',
        'academy-shared-schema' => 'Academy Shared Schema',
    ];

    public function overview(bool $refresh = false): array
    {
        $cacheKey = 'systems.control_plane.overview.v2';
        $ttl = max(5, (int) config('systems.cache_ttl_seconds', 45));

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $ttl, function () {
            $checkedAt = Carbon::now()->toIso8601String();
            $infra = $this->probeInfrastructure();

            $applications = [];
            foreach (array_keys(config('systems.applications', [])) as $id) {
                $applications[] = $this->buildApplication($id, $infra, $checkedAt);
            }

            $platformServices = [];
            foreach (array_keys(config('systems.platform_services', [])) as $id) {
                $platformServices[] = $this->buildPlatformService($id, $infra, $checkedAt);
            }

            $security = $this->buildSecurity($checkedAt);

            return [
                'checked_at' => $checkedAt,
                'summary' => $this->summarize($applications, $platformServices, $security),
                'applications' => $applications,
                'platform_services' => $platformServices,
                'security' => $security,
                'platform' => [
                    'version' => $this->normalizeVersion(config('systems.version')),
                    'build_id' => config('systems.build_id') ?: null,
                    'commit_sha' => config('systems.commit_sha')
                        ? substr((string) config('systems.commit_sha'), 0, 12)
                        : null,
                ],
                'incidents_supported' => false,
                'recent_incidents' => [],
            ];
        });
    }

    public function application(string $id, bool $refresh = false): ?array
    {
        $overview = $this->overview($refresh);
        foreach ($overview['applications'] as $app) {
            if ($app['id'] === $id) {
                return $app;
            }
        }

        return null;
    }

    public function platformService(string $id, bool $refresh = false): ?array
    {
        $overview = $this->overview($refresh);
        foreach ($overview['platform_services'] as $service) {
            if ($service['id'] === $id) {
                return $service;
            }
        }

        return null;
    }

    public function applicationHealth(string $id, bool $refresh = false): ?array
    {
        $app = $this->application($id, $refresh);
        if (! $app) {
            return null;
        }

        return [
            'id' => $app['id'],
            'status' => $app['status'],
            'health_status' => $app['health_status'],
            'status_reason' => $app['status_reason'] ?? null,
            'connection_status' => $app['connection_status'],
            'dependency_details' => $app['dependency_details'] ?? [],
            'last_checked_at' => $app['last_checked_at'],
            'checks' => $app['checks'],
            'errors' => $app['errors'],
        ];
    }

    /**
     * @return array{database: array, storage: array, email: array, queues: array, auth: array}
     */
    private function probeInfrastructure(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'email' => $this->checkEmail(),
            'queues' => $this->checkQueues(),
            'auth' => $this->checkAuth(),
        ];
    }

    private function buildApplication(string $id, array $infra, string $checkedAt): array
    {
        $meta = config("systems.applications.{$id}");
        if (! is_array($meta)) {
            return [
                'id' => $id,
                'name' => $id,
                'type' => 'application',
                'status' => self::STATUS_UNKNOWN,
                'health_status' => self::STATUS_UNKNOWN,
                'status_reason' => 'Application not found in registry',
                'connection_status' => ['database' => self::STATUS_UNKNOWN],
                'dependency_details' => [],
                'version' => 'unknown',
                'url' => null,
                'dependencies' => [],
                'last_checked_at' => $checkedAt,
                'checks' => [],
                'errors' => [['severity' => 'warning', 'message' => 'Application not found in registry', 'timestamp' => $checkedAt]],
                'recent_incidents' => [],
            ];
        }

        $appChecks = $this->applicationSpecificChecks($id);
        $depDetails = $this->dependencyDetails($meta['dependencies'] ?? [], $infra);
        $depConnections = [];
        foreach ($depDetails as $dep) {
            $depConnections[$dep['id']] = $dep['status'];
        }

        $checkBlocks = [
            'database' => array_merge($infra['database'], ['label' => 'Database connection']),
            'application' => array_merge($appChecks, ['label' => 'Application data check']),
        ];

        $errors = $this->collectErrors(array_values($checkBlocks), $checkedAt);
        foreach ($depDetails as $dep) {
            if (in_array($dep['status'], [self::STATUS_UNAVAILABLE, self::STATUS_DEGRADED], true)) {
                $errors[] = [
                    'severity' => $dep['status'] === self::STATUS_UNAVAILABLE ? 'error' : 'warning',
                    'message' => ($dep['label'] ?? $dep['id']).': '.($dep['message'] ?? $dep['status']),
                    'timestamp' => $checkedAt,
                ];
            }
        }

        $status = $this->rollupStatus([
            $infra['database']['status'],
            $appChecks['status'],
            $this->worstConnection($depConnections),
        ]);

        $statusReason = $this->explainStatus($status, $checkBlocks, $depDetails);

        $frontendBase = rtrim((string) config('app.frontend_url', ''), '/');
        $path = (string) ($meta['url'] ?? '/');
        $launchUrl = $path === '/'
            ? ($frontendBase !== '' ? $frontendBase.'/' : '/')
            : ($frontendBase !== '' ? $frontendBase.$path : $path);

        $consolePath = in_array($id, ['ems', 'main-website', 'dawah-academy'], true)
            ? ($meta['admin_path'] ?? null).'/console'
            : null;

        return [
            'id' => $meta['id'],
            'name' => $meta['name'],
            'description' => $meta['description'] ?? '',
            'type' => 'application',
            'status' => $status,
            'health_status' => $status,
            'status_reason' => $statusReason,
            'connection_status' => $depConnections,
            'dependency_details' => $depDetails,
            'version' => $this->normalizeVersion($meta['version'] ?? null),
            'url' => $meta['url'] ?? null,
            'launch_url' => $launchUrl,
            'admin_path' => $meta['admin_path'] ?? null,
            'console_path' => $consolePath,
            'dependencies' => $meta['dependencies'] ?? [],
            'owns' => $meta['owns'] ?? [],
            'does_not_own' => $meta['does_not_own'] ?? [],
            'notes' => $meta['notes'] ?? null,
            'last_checked_at' => $checkedAt,
            'checks' => $checkBlocks,
            'errors' => $errors,
            'recent_incidents' => [],
            'registered' => true,
            'configured' => true,
        ];
    }

    private function buildPlatformService(string $id, array $infra, string $checkedAt): array
    {
        $meta = config("systems.platform_services.{$id}", []);
        $probe = match ($id) {
            'queues' => $infra['queues'],
            'database' => $infra['database'],
            'email' => $infra['email'],
            'storage' => $infra['storage'],
            default => ['status' => self::STATUS_UNKNOWN, 'message' => 'No probe'],
        };

        $status = $probe['status'] ?? self::STATUS_UNKNOWN;
        $checks = [
            [
                'id' => $id,
                'label' => ($meta['name'] ?? $id).' probe',
                'status' => $status,
                'message' => $probe['message'] ?? null,
            ],
        ];

        return [
            'id' => $meta['id'] ?? $id,
            'name' => $meta['name'] ?? $id,
            'description' => $meta['description'] ?? '',
            'type' => 'platform_service',
            'status' => $status,
            'health_status' => $status,
            'status_reason' => $probe['message'] ?? ($status === self::STATUS_OPERATIONAL ? 'All checks passed' : null),
            'admin_path' => $meta['admin_path'] ?? null,
            'detail_path' => '/admin/systems/services/'.($meta['id'] ?? $id),
            'required_permission' => $meta['required_permission'] ?? null,
            'metrics' => $probe['metrics'] ?? [],
            'checks' => $checks,
            'partitions' => $probe['partitions'] ?? [],
            'last_checked_at' => $checkedAt,
            'message' => $probe['message'] ?? null,
            'errors' => in_array($status, [self::STATUS_UNAVAILABLE, self::STATUS_DEGRADED], true)
                ? [['severity' => $status === self::STATUS_UNAVAILABLE ? 'error' : 'warning', 'message' => $probe['message'] ?? $status, 'timestamp' => $checkedAt]]
                : [],
        ];
    }

    private function buildSecurity(string $checkedAt): array
    {
        $meta = config('systems.security', []);

        return [
            'id' => $meta['id'] ?? 'security-center',
            'name' => $meta['name'] ?? 'Security Center',
            'description' => $meta['description'] ?? '',
            'type' => 'security',
            'status' => self::STATUS_UNKNOWN,
            'health_status' => self::STATUS_UNKNOWN,
            'status_reason' => 'Security posture is evaluated in Security Center, not duplicated here.',
            'admin_path' => $meta['admin_path'] ?? '/admin/security',
            'required_permission' => $meta['required_permission'] ?? 'view_security',
            'last_checked_at' => $checkedAt,
            'message' => 'Open Security Center for live security posture. Systems does not duplicate security tooling.',
            'errors' => [],
        ];
    }

    private function applicationSpecificChecks(string $id): array
    {
        try {
            return match ($id) {
                'cms' => $this->tableReachable('announcements', Announcement::class, 'CMS announcements store'),
                'dams', 'dawah-academy' => $this->tableReachable('courses', Course::class, 'Academy courses store'),
                'ems' => $this->tableReachable('ems_events', EmsEvent::class, 'EMS events store'),
                'main-website' => $this->tableReachable('announcements', Announcement::class, 'CMS content consumed by Main Website'),
                default => [
                    'status' => self::STATUS_UNKNOWN,
                    'message' => 'No application-specific probe configured',
                ],
            };
        } catch (Throwable) {
            return [
                'status' => self::STATUS_UNAVAILABLE,
                'message' => 'Unable to query required application data store',
            ];
        }
    }

    /**
     * @param  class-string  $model
     */
    private function tableReachable(string $table, string $model, string $label): array
    {
        if (! Schema::hasTable($table)) {
            return [
                'status' => self::STATUS_UNAVAILABLE,
                'message' => "{$label}: required data store is not available",
            ];
        }

        $model::query()->limit(1)->exists();

        return [
            'status' => self::STATUS_OPERATIONAL,
            'message' => "{$label}: reachable",
        ];
    }

    private function dependencyDetails(array $dependencies, array $infra): array
    {
        $probeByDep = [
            'platform-auth' => $infra['auth'],
            'database' => $infra['database'],
            'storage' => $infra['storage'],
            'queues' => $infra['queues'],
            'email' => $infra['email'],
            'cms-content-apis' => $infra['database'],
            'cms-resources' => $infra['database'],
            'ems-event-apis' => $infra['database'],
            'academy-shared-schema' => $infra['database'],
        ];

        $out = [];
        foreach ($dependencies as $dep) {
            $probe = $probeByDep[$dep] ?? ['status' => self::STATUS_UNKNOWN, 'message' => 'No probe mapped'];
            $out[] = [
                'id' => $dep,
                'label' => self::DEP_LABELS[$dep] ?? $dep,
                'status' => $probe['status'] ?? self::STATUS_UNKNOWN,
                'message' => $probe['message'] ?? null,
            ];
        }

        return $out;
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1 as ok');

            return [
                'status' => self::STATUS_OPERATIONAL,
                'message' => 'Database connection successful',
                'metrics' => [
                    'connection' => 'connected',
                    'driver' => config('database.default'),
                ],
            ];
        } catch (Throwable) {
            return [
                'status' => self::STATUS_UNAVAILABLE,
                'message' => 'Database connection failed',
                'metrics' => [
                    'connection' => 'unavailable',
                ],
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk('public');
            $disk->files('');

            return [
                'status' => self::STATUS_OPERATIONAL,
                'message' => 'Public storage disk reachable (read probe)',
                'metrics' => [
                    'connection' => 'connected',
                    'readable' => true,
                    'disk' => 'public',
                ],
            ];
        } catch (Throwable) {
            return [
                'status' => self::STATUS_UNAVAILABLE,
                'message' => 'Storage read probe failed',
                'metrics' => [
                    'connection' => 'unavailable',
                    'readable' => false,
                ],
            ];
        }
    }

    private function checkEmail(): array
    {
        $default = (string) config('mail.default', '');
        if ($default === '') {
            return [
                'status' => self::STATUS_UNKNOWN,
                'message' => 'Mailer not configured',
                'metrics' => [
                    'configured' => false,
                    'mailer' => null,
                ],
            ];
        }

        // Production must not report log/array mailers as healthy delivery paths.
        if (app()->environment('production') && in_array($default, ['log', 'array'], true)) {
            return [
                'status' => self::STATUS_DEGRADED,
                'message' => "Mailer \"{$default}\" will not deliver email in production",
                'metrics' => [
                    'configured' => false,
                    'mailer' => $default,
                    'delivery' => 'unverified',
                ],
            ];
        }

        if ($default === 'smtp') {
            $host = config('mail.mailers.smtp.host');
            $configured = ! empty($host);

            return [
                'status' => $configured ? self::STATUS_OPERATIONAL : self::STATUS_DEGRADED,
                'message' => $configured ? 'SMTP mailer configured (no send performed)' : 'SMTP mailer selected but host is not set',
                'metrics' => [
                    'configured' => $configured,
                    'mailer' => 'smtp',
                    'delivery' => 'unverified',
                ],
            ];
        }

        return [
            'status' => self::STATUS_OPERATIONAL,
            'message' => "Mailer \"{$default}\" configured (no send performed)",
            'metrics' => [
                'configured' => true,
                'mailer' => $default,
                'delivery' => 'unverified',
            ],
        ];
    }

    private function checkQueues(): array
    {
        try {
            $queueNames = $this->knownQueueNames();
            $partitions = [];
            $pending = 0;
            $failed = 0;
            $active = 0;
            $lastActivity = null;

            $hasJobs = Schema::hasTable('jobs');
            $hasFailed = Schema::hasTable('failed_jobs');

            foreach ($queueNames as $qName) {
                $qPending = 0;
                $qActive = 0;
                $qFailed = 0;
                if ($hasJobs) {
                    $qPending = (int) DB::table('jobs')->where('queue', $qName)->whereNull('reserved_at')->count();
                    $qActive = (int) DB::table('jobs')->where('queue', $qName)->whereNotNull('reserved_at')->count();
                }
                if ($hasFailed) {
                    $qFailed = (int) DB::table('failed_jobs')->where('queue', $qName)->count();
                }
                $pending += $qPending;
                $active += $qActive;
                $failed += $qFailed;
                $partitions[] = [
                    'name' => $qName,
                    'pending' => $qPending,
                    'active' => $qActive,
                    'failed' => $qFailed,
                    'status' => $qActive > 0 ? 'active' : ($qPending > 0 ? 'pending' : 'idle'),
                ];
            }

            if ($hasJobs) {
                $maxAvailable = DB::table('jobs')->max('available_at');
                if ($maxAvailable) {
                    $lastActivity = Carbon::createFromTimestamp((int) $maxAvailable)->toIso8601String();
                }
            }
            if ($hasFailed) {
                $latestFailed = DB::table('failed_jobs')->max('failed_at');
                if ($latestFailed) {
                    $failedAt = Carbon::parse($latestFailed)->toIso8601String();
                    if (! $lastActivity || $failedAt > $lastActivity) {
                        $lastActivity = $failedAt;
                    }
                }
            }

            $status = self::STATUS_OPERATIONAL;
            $message = 'Queue tables reachable';
            $connection = (string) config('queue.default');

            if (app()->environment('production') && $connection === 'sync') {
                $status = self::STATUS_DEGRADED;
                $message = 'QUEUE_CONNECTION=sync in production — jobs run inline; configure database/redis workers';
            } elseif ($failed > 0) {
                $status = self::STATUS_DEGRADED;
                $message = "Failed jobs present ({$failed})";
            }

            return [
                'status' => $status,
                'message' => $message,
                'metrics' => [
                    'connection' => $connection,
                    'pending_jobs' => $pending,
                    'failed_jobs' => $failed,
                    'active_jobs' => $active,
                    'workers' => 'unknown',
                    'last_activity' => $lastActivity,
                ],
                'partitions' => $partitions,
            ];
        } catch (Throwable) {
            return [
                'status' => self::STATUS_UNAVAILABLE,
                'message' => 'Queue status unavailable',
                'metrics' => [
                    'pending_jobs' => null,
                    'failed_jobs' => null,
                    'workers' => 'unknown',
                ],
                'partitions' => [],
            ];
        }
    }

    /**
     * @return list<string>
     */
    private function knownQueueNames(): array
    {
        $names = ['high', 'default', 'low'];
        foreach ([
            config('ems.payments.queue'),
            config('ems.operations.queue'),
            config('ems.notifications.queue'),
        ] as $emsQueue) {
            if (is_string($emsQueue) && $emsQueue !== '') {
                $names[] = $emsQueue;
            }
        }

        return array_values(array_unique($names));
    }

    private function checkAuth(): array
    {
        $guard = config('auth.defaults.guard');
        $sanctum = class_exists(\Laravel\Sanctum\Sanctum::class);

        if (! $sanctum || empty($guard)) {
            return [
                'status' => self::STATUS_DEGRADED,
                'message' => 'Authentication stack incomplete',
            ];
        }

        return [
            'status' => self::STATUS_OPERATIONAL,
            'message' => 'Platform authentication available',
        ];
    }

    /**
     * @param  list<array{status?: string, message?: string, label?: string}>  $blocks
     */
    private function collectErrors(array $blocks, string $checkedAt): array
    {
        $errors = [];
        foreach ($blocks as $block) {
            $status = $block['status'] ?? null;
            if ($status === self::STATUS_UNAVAILABLE) {
                $errors[] = [
                    'severity' => 'error',
                    'message' => ($block['label'] ?? 'Check').': '.($block['message'] ?? 'Unavailable'),
                    'timestamp' => $checkedAt,
                ];
            } elseif ($status === self::STATUS_DEGRADED) {
                $errors[] = [
                    'severity' => 'warning',
                    'message' => ($block['label'] ?? 'Check').': '.($block['message'] ?? 'Degraded'),
                    'timestamp' => $checkedAt,
                ];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, array{status?: string, message?: string, label?: string}>  $checkBlocks
     * @param  list<array{id: string, label: string, status: string, message?: string|null}>  $depDetails
     */
    private function explainStatus(string $status, array $checkBlocks, array $depDetails): ?string
    {
        if ($status === self::STATUS_OPERATIONAL) {
            return 'All checks passed';
        }

        $parts = [];
        foreach ($checkBlocks as $block) {
            $s = $block['status'] ?? null;
            if (in_array($s, [self::STATUS_UNAVAILABLE, self::STATUS_DEGRADED, self::STATUS_UNKNOWN], true)
                && $s !== self::STATUS_OPERATIONAL) {
                $parts[] = ($block['label'] ?? 'Check').': '.($block['message'] ?? $s);
            }
        }
        foreach ($depDetails as $dep) {
            if (in_array($dep['status'], [self::STATUS_UNAVAILABLE, self::STATUS_DEGRADED], true)) {
                $parts[] = $dep['label'].': '.($dep['message'] ?? $dep['status']);
            }
        }

        if ($parts === []) {
            return $status === self::STATUS_UNKNOWN ? 'Insufficient probe data' : null;
        }

        return implode(' · ', array_slice($parts, 0, 4));
    }

    /**
     * @param  list<string>  $statuses
     */
    private function rollupStatus(array $statuses): string
    {
        if (in_array(self::STATUS_UNAVAILABLE, $statuses, true)) {
            return self::STATUS_UNAVAILABLE;
        }
        if (in_array(self::STATUS_DEGRADED, $statuses, true)) {
            return self::STATUS_DEGRADED;
        }
        if (in_array(self::STATUS_UNKNOWN, $statuses, true)) {
            return self::STATUS_UNKNOWN;
        }

        return self::STATUS_OPERATIONAL;
    }

    private function worstConnection(array $connections): string
    {
        return $this->rollupStatus(array_values($connections));
    }

    private function normalizeVersion(mixed $version): string
    {
        $v = is_string($version) ? trim($version) : '';
        if ($v === '' || strtolower($v) === 'null') {
            return 'unknown';
        }

        return $v;
    }

    private function summarize(array $applications, array $platformServices, array $security): array
    {
        $countBy = function (array $items) {
            $counts = [
                self::STATUS_OPERATIONAL => 0,
                self::STATUS_DEGRADED => 0,
                self::STATUS_UNAVAILABLE => 0,
                self::STATUS_UNKNOWN => 0,
            ];
            foreach ($items as $item) {
                $s = $item['status'] ?? self::STATUS_UNKNOWN;
                if (! isset($counts[$s])) {
                    $s = self::STATUS_UNKNOWN;
                }
                $counts[$s]++;
            }

            return $counts;
        };

        return [
            'applications_total' => count($applications),
            'applications_by_status' => $countBy($applications),
            'platform_services_total' => count($platformServices),
            'platform_services_by_status' => $countBy($platformServices),
            'security_status' => $security['status'] ?? self::STATUS_UNKNOWN,
        ];
    }
}
