<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Resources;

use App\Modules\Core\Auth\User;
use App\Modules\Report\Domain\CitizenReportEntry;
use App\Modules\Report\Domain\CitizenReportHistory;
use App\Modules\Review\Models\ReviewNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Props for pages/report/MyReports.vue. Never sends invalid_reason, coordinates, device_hash,
 * google_id, or synthetic demo email addresses (LS-R2, LS-R4).
 *
 * @property-read CitizenReportHistory $resource
 */
final class CitizenReportHistoryResource extends JsonResource
{
    public function __construct(
        CitizenReportHistory $history,
        private readonly User $citizen,
    ) {
        parent::__construct($history);
    }

    /**
     * @return array<string, mixed>
     */
    public static function guest(): array
    {
        return [
            'state' => 'guest',
            'session' => null,
            'summary' => [
                'total_count' => 0,
                'active_count' => 0,
                'reviewed_or_in_review_count' => 0,
            ],
            'reports' => [],
            'suggested_project' => null,
            'urls' => self::sharedUrls(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $history = $this->resource;
        $isGoogleCitizen = $this->citizen->village_id === null;
        $suggested = $history->suggestedProject?->project;

        return [
            'state' => 'authenticated',
            'session' => [
                'auth_type' => $isGoogleCitizen ? 'google' : 'demo',
                'display_name' => $this->citizen->name,
                'email' => $isGoogleCitizen ? $this->citizen->email : null,
                'village_name' => $history->villageName,
                'village_slug' => $history->villageSlug,
            ],
            'summary' => [
                'total_count' => $history->totalCount,
                'active_count' => $history->activeCount,
                'reviewed_or_in_review_count' => $history->reviewedOrInReviewCount,
            ],
            'reports' => array_map(
                static function (CitizenReportEntry $entry): array {
                    $report = $entry->report;
                    $project = $entry->project;
                    $review = $entry->bpdReview;

                    return [
                        'id' => $report->id,
                        'created_at' => $report->created_at?->toIso8601String(),
                        'kind' => $report->kind->value,
                        'status' => $entry->status->value,
                        'note' => $report->note,
                        'project' => [
                            'name' => $project->name,
                            'slug' => $project->slug,
                            'village_name' => $project->village->name,
                            'village_slug' => $project->village->slug,
                            'year' => $project->year,
                        ],
                        'mismatch_count' => [
                            'current' => $entry->mismatchReportersCount,
                            'threshold' => $entry->threshold,
                        ],
                        'bpd_review' => [
                            'status' => $review->status,
                            'in_review_since' => $review->inReviewSince?->toIso8601String(),
                            'outcome' => $review->outcome?->value,
                            'note' => $review->note,
                            'reviewed_at' => $review->reviewedAt?->toIso8601String(),
                            'notes' => array_map(
                                static fn (ReviewNote $note): array => [
                                    'note' => $note->note,
                                    'created_at' => $note->created_at?->toIso8601String(),
                                ],
                                $review->notes,
                            ),
                            'submitted_after_review' => $review->submittedAfterReview,
                        ],
                        'urls' => [
                            'project' => route('projects.show', $project->slug),
                            'check' => route('reports.check', $project->slug),
                        ],
                    ];
                },
                $history->entries,
            ),
            'suggested_project' => $suggested === null ? null : [
                'name' => $suggested->name,
                'slug' => $suggested->slug,
                'village_name' => $suggested->village->name,
                'urls' => [
                    'project' => route('projects.show', $suggested->slug),
                    'check' => route('reports.check', $suggested->slug),
                ],
            ],
            'urls' => self::sharedUrls(),
        ];
    }

    /**
     * @return array{google_login: string, demo: string, home: string, logout: string}
     */
    private static function sharedUrls(): array
    {
        return [
            'google_login' => route('reports.mine.login'),
            'demo' => route('demo'),
            'home' => route('home'),
            'logout' => route('logout'),
        ];
    }
}
