<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Services\WordPressExhibitorPreview;
use Throwable;

trait HasWordPressExhibitorPreview
{
    public $wpExhibitorPreviewRows = [];
    public $wpExhibitorPreviewLoaded = false;
    public $wpExhibitorPreviewError = '';


    public function loadWordPressExhibitorPreview()
    {
        $this->assertDataSyncAdmin();

        // Allow the bounded 90-second source preview to finish locally.
        set_time_limit(120);

        $this->wpExhibitorPreviewRows = [];
        $this->wpExhibitorPreviewLoaded = false;
        $this->wpExhibitorPreviewError = '';

        try {
            $event = Event::whereKey(
                $this->eventData['eventId']
            )
                ->where(
                    'category',
                    $this->eventData['eventCategory']
                )
                ->firstOrFail();

            $this->wpExhibitorPreviewRows = app(
                WordPressExhibitorPreview::class
            )->fetch($event);

            $this->wpExhibitorPreviewLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->wpExhibitorPreviewError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }
}
