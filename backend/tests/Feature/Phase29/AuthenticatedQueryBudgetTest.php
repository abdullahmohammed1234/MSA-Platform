<?php

namespace Tests\Feature\Phase29;

use App\Http\Middleware\ScopeAuthorizationMemo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationAccessService;
use Database\Seeders\Ems\EmsRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Statement counts for the heaviest authenticated routes, measured over real bearer-token
 * requests. Set P29_PROFILE=<file> to also write a per-route breakdown of repeated statements.
 */
class AuthenticatedQueryBudgetTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{0: string, 1: string}> route label => [persona, uri] */
    private const ROUTES = [
        'member auth/me' => ['member', '/api/v1/auth/me'],
        'member account/summary' => ['member', '/api/v1/account/summary'],
        'member dashboard' => ['member', '/api/v1/dashboard'],
        'member notifications' => ['member', '/api/v1/notifications'],
        'staff auth/me' => ['staff', '/api/v1/auth/me'],
        'staff ems/events' => ['staff', '/api/v1/ems/events'],
        'admin auth/me' => ['admin', '/api/v1/auth/me'],
        'admin account/summary' => ['admin', '/api/v1/account/summary'],
        'admin admin/systems' => ['admin', '/api/v1/admin/systems'],
        'admin admin/users' => ['admin', '/api/v1/admin/users'],
    ];

    /**
     * Measured with the request-scoped authorization memo: 10/20/20/3, 10/8, 6/16/3/6 (151, 163,
     * 164, 3, 143, 15, 32, 43, 5, 10 without it). The margin absorbs unrelated changes; a return
     * of per-check authorization queries (tens of statements) fails loudly.
     *
     * @var array<string, int>
     */
    private const BUDGETS = [
        'member auth/me' => 15,
        'member account/summary' => 28,
        'member dashboard' => 28,
        'member notifications' => 6,
        'staff auth/me' => 15,
        'staff ems/events' => 12,
        'admin auth/me' => 10,
        'admin account/summary' => 22,
        'admin admin/systems' => 8,
        'admin admin/users' => 10,
    ];

    /** @var array<string, User> */
    private array $personas = [];

    /** @var array<string, string> */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmsRolePermissionSeeder::class);
        foreach (['admin' => 'Admin', 'volunteer' => 'Volunteer', 'super-admin' => 'Super Admin'] as $slug => $name) {
            Role::firstOrCreate(['slug' => $slug], ['uuid' => (string) Str::uuid(), 'name' => $name, 'description' => $name]);
        }
        Permission::firstOrCreate(['slug' => 'manage_users'], [
            'uuid' => (string) Str::uuid(), 'name' => 'manage_users', 'module' => 'Users', 'description' => 'manage_users',
        ]);

        $this->personas['member'] = $this->persona('member@p29.test', 'volunteer');
        $this->personas['staff'] = $this->persona('staff@p29.test', 'event-administrator');
        $this->personas['admin'] = $this->persona('admin@p29.test', 'admin');
        app(ApplicationAccessService::class)->grant($this->personas['staff'], 'ems');

        foreach ($this->personas as $key => $user) {
            $this->tokens[$key] = $user->createToken('p29-'.$key)->plainTextToken;
        }
    }

    private function persona(string $email, string $roleSlug): User
    {
        $user = User::factory()->create(['email' => $email, 'is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('slug', $roleSlug)->firstOrFail()->id);

        return $user;
    }

    /**
     * @return array{status: int, count: int, ms: float, statements: list<string>}
     */
    private function measure(string $persona, string $uri): array
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $statements = [];
        DB::flushQueryLog();
        $listener = function ($query) use (&$statements) {
            $statements[] = $query->sql;
        };
        DB::listen($listener);

        $started = microtime(true);
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->tokens[$persona], 'Accept' => 'application/json'])
            ->getJson($uri);
        $ms = (microtime(true) - $started) * 1000;

        $recorded = $statements;
        $statements = [];

        return ['status' => $response->status(), 'count' => count($recorded), 'ms' => $ms, 'statements' => $recorded];
    }

    public function test_heavy_authenticated_routes_stay_within_statement_budgets(): void
    {
        $report = [];
        $failures = [];

        foreach (self::ROUTES as $label => [$persona, $uri]) {
            $this->measure($persona, $uri);
            $result = $this->measure($persona, $uri);

            $this->assertSame(200, $result['status'], "{$label} returned {$result['status']}");
            $report[$label] = $result;
            if ($result['count'] > self::BUDGETS[$label]) {
                $failures[] = "{$label}: {$result['count']} statements (budget ".self::BUDGETS[$label].')';
            }
        }

        $this->writeProfile($report);

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_responses_are_identical_with_and_without_the_memo(): void
    {
        foreach (self::ROUTES as $label => [$persona, $uri]) {
            $memoized = $this->bearer($persona)->getJson($uri);
            $this->withoutMiddleware(ScopeAuthorizationMemo::class);
            $direct = $this->bearer($persona)->getJson($uri);
            $this->app->forgetInstance(ScopeAuthorizationMemo::class);

            $this->assertSame($direct->status(), $memoized->status(), $label);
            $this->assertSame($this->stable($direct->json()), $this->stable($memoized->json()), $label);
        }
    }

    public function test_repeated_calls_do_not_accumulate_statements(): void
    {
        \Illuminate\Support\Carbon::setTestNow(now());
        try {
            $counts = [];
            foreach (range(1, 4) as $ignored) {
                $counts[] = $this->measure('member', '/api/v1/auth/me')['count'];
            }

            $this->assertCount(1, array_unique(array_slice($counts, 1)), implode(',', $counts));
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    private function bearer(string $persona): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withHeaders(['Authorization' => 'Bearer '.$this->tokens[$persona], 'Accept' => 'application/json']);
    }

    /**
     * Drops timestamps that legitimately differ between two calls.
     */
    private function stable(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/(_at|_used|generated|timestamp|now)$/i', $key)) {
                continue;
            }
            $out[$key] = $this->stable($item);
        }

        return $out;
    }

    /**
     * @param  array<string, array{status: int, count: int, ms: float, statements: list<string>}>  $report
     */
    private function writeProfile(array $report): void
    {
        $file = getenv('P29_PROFILE');
        if (! is_string($file) || $file === '') {
            return;
        }

        $lines = [];
        foreach ($report as $label => $result) {
            $lines[] = sprintf('%-28s status=%d statements=%d time_ms=%.1f', $label, $result['status'], $result['count'], $result['ms']);
            $grouped = array_count_values($result['statements']);
            arsort($grouped);
            foreach (array_slice($grouped, 0, 6, true) as $sql => $times) {
                if ($times > 1) {
                    $lines[] = sprintf('    x%-3d %s', $times, Str::limit($sql, 160));
                }
            }
        }

        file_put_contents($file, implode(PHP_EOL, $lines).PHP_EOL);
    }
}
