<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Services\WordPressMeetingRoomPartnerPreview;
use Throwable;

trait HasWordPressMeetingRoomPartnerPreview
{
    public $wpMrpRows = [];
    public $wpMrpLoaded = false;
    public $wpMrpError = '';

    public function loadWordPressMeetingRoomPartnerPreview()
    {
        $this->assertDataSyncAdmin();

        $this->wpMrpRows = [];
        $this->wpMrpLoaded = false;
        $this->wpMrpError = '';

        try {
            $event = Event::findOrFail(
                $this->eventData['eventId']
            );

            $rows = app(
                WordPressMeetingRoomPartnerPreview::class
            )->fetch($event);

            $this->wpMrpRows = $rows;
            $this->wpMrpLoaded = true;
        } catch (Throwable $exception) {
            report($exception);

            $this->wpMrpError = 'Preview failed: '
                . mb_substr($exception->getMessage(), 0, 500);
        }
    }
}