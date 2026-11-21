<?php

declare(strict_types=1);

namespace App\Modules\Demo\Actions;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\Village;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies the Desa Contoh template into a new demo village (PRD Arsitektur 6.7, M6-02, decision A9).
 *
 * Works on the query builder, so global village scopes and model events do not interfere.
 * Files are not copied: cloned rows keep pointing at the template's read-only files.
 * Every table linked to villages must be listed here; the F6-2 test fails otherwise.
 */
final class VillageCloner
{
    /**
     * Copy order, with the foreign keys to remap. The first column selects the rows to copy.
     *
     * @var array<string, array<string, string>>
     */
    public const TABLES = [
        'audits' => ['village_id' => 'villages', 'owner_id' => 'users'],
        'audit_items' => ['audit_id' => 'audits'],
        'projects' => ['village_id' => 'villages', 'audit_id' => 'audits'],
        'project_measurements' => ['project_id' => 'projects'],
        'progress_photos' => ['project_id' => 'projects'],
        'project_changes' => ['project_id' => 'projects'],
        'review_cycles' => ['project_id' => 'projects'],
        'review_notes' => ['cycle_id' => 'review_cycles'],
        'reports' => ['project_id' => 'projects', 'cycle_id' => 'review_cycles'],
        'report_answers' => ['report_id' => 'reports', 'measurement_id' => 'project_measurements'],
        'report_photos' => ['report_id' => 'reports'],
    ];

    /**
     * Foreign keys that point forward or to their own table. Inserted with the template's value, then remapped.
     * Not null: reports.superseded_by decides which report is active, and an active copy of a superseded report
     * would break the one-active-report index (M4-02).
     *
     * @var array<string, array<string, string>>
     */
    public const DEFERRED = [
        'projects' => ['current_cycle_id' => 'review_cycles'],
        'reports' => ['superseded_by' => 'reports'],
    ];

    /** Roles that get a demo account per clone (PRD Arsitektur 6.7). Warga browse without login until M4. */
    public const ACCOUNT_ROLES = [Role::Desa, Role::Bpd];

    public function clone(Village $template): Village
    {
        return DB::transaction(function () use ($template): Village {
            $village = Village::query()->create([
                'name' => $template->name,
                'slug' => 'demo-'.Str::lower(Str::random(12)),
                'regency' => $template->regency,
                'is_demo' => true,
                'demo_expires_at' => now()->addHours((int) config('sonar.demo.ttl_hours')),
            ]);

            /** @var array<string, array<int, int>> $map */
            $map = [
                'villages' => [$template->id => $village->id],
                'users' => $this->createAccounts($template, $village),
            ];
            /** @var list<array{string, int, string, int, string}> $pending */
            $pending = [];

            foreach (self::TABLES as $table => $foreignKeys) {
                $map[$table] = $this->copyTable($table, $foreignKeys, $map, $pending, $village->id);
            }

            foreach ($pending as [$table, $newId, $column, $oldValue, $source]) {
                DB::table($table)->where('id', $newId)->update([$column => $map[$source][$oldValue] ?? null]);
            }

            return $village;
        });
    }

    /**
     * @return array<int, int> template user id => demo account id
     */
    private function createAccounts(Village $template, Village $village): array
    {
        $accounts = [];
        foreach (self::ACCOUNT_ROLES as $role) {
            $accounts[$role->value] = User::query()->create([
                'name' => $role === Role::Desa ? 'Pemerintah Desa (demo)' : 'BPD (demo)',
                'email' => "demo-{$role->value}-{$village->id}@demo.sonar-desa.test",
                'role' => $role,
                'village_id' => $village->id,
                'password' => null,
            ])->id;
        }

        /** @var array<int, string> $templateUsers */
        $templateUsers = DB::table('users')->where('village_id', $template->id)->pluck('role', 'id')->all();

        return array_map(
            fn (string $role): int => $accounts[$role] ?? $accounts[Role::Desa->value],
            $templateUsers,
        );
    }

    /**
     * @param  array<string, string>  $foreignKeys
     * @param  array<string, array<int, int>>  $map
     * @param  list<array{string, int, string, int, string}>  $pending
     * @return array<int, int> template id => clone id
     */
    private function copyTable(string $table, array $foreignKeys, array $map, array &$pending, int $villageId): array
    {
        $ownerColumn = (string) array_key_first($foreignKeys);
        $rows = DB::table($table)
            ->whereIn($ownerColumn, array_keys($map[$foreignKeys[$ownerColumn]]))
            ->orderBy('id')
            ->get();

        $ids = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed> $data */
            $data = (array) $row;
            $oldId = (int) $data['id'];
            unset($data['id']);

            foreach ($foreignKeys as $column => $source) {
                // Ids outside the template (e.g. warga accounts on reports) are kept as they are.
                if ($data[$column] !== null && isset($map[$source][(int) $data[$column]])) {
                    $data[$column] = $map[$source][(int) $data[$column]];
                }
            }

            $deferred = [];
            foreach (self::DEFERRED[$table] ?? [] as $column => $source) {
                if ($data[$column] !== null) {
                    $deferred[$column] = [(int) $data[$column], $source];
                }
            }

            if ($table === 'projects') {
                // Slugs are globally unique and appear in public URLs (/p/{slug}).
                $data['slug'] = $data['slug'].'-d'.$villageId;
            }

            $newId = (int) DB::table($table)->insertGetId($data);
            $ids[$oldId] = $newId;

            foreach ($deferred as $column => [$oldValue, $source]) {
                $pending[] = [$table, $newId, $column, $oldValue, $source];
            }
        }

        return $ids;
    }
}
