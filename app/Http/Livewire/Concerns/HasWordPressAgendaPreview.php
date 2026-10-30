<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Services\WordPressAgendaPreview;

trait HasWordPressAgendaPreview
{
    public $wpAgendaPreviewRows = [];
    public $wpAgendaPreviewSources = [];
    public $wpAgendaPreviewSkipped = 0;
    public $wpAgendaPreviewIssues = 0;
    public $wpAgendaPreviewLoaded = false;
    public $wpAgendaPreviewError = '';

    public function previewWordPressAgenda()
    {
        $this->clearWordPressAgendaPreview();
        try {
            // Uses the same existing event-admin access context as EventDetails.
            $event = Event::findOrFail($this->eventData['eventId']);
            if (trim((string) $this->wpPreviewPostType) === '') {
                throw new \RuntimeException(
                    'Please select an agenda feature first.'
                );
            }

            $result = app(WordPressAgendaPreview::class)->fetch(
                $event,
                $this->wpPreviewPostType
            );
            $this->wpAgendaPreviewRows = $result['rows'];
            $this->wpAgendaPreviewSources = $result['sources'];
            $this->wpAgendaPreviewSkipped = $result['skipped_year'];
            $this->wpAgendaPreviewIssues = $result['issues'];
            $this->wpAgendaPreviewLoaded = true;
        } catch (\Throwable $e) {
            report($e);
            $this->wpAgendaPreviewError = 'Preview failed: ' . mb_substr($e->getMessage(), 0, 400);
        }
    }

    public function clearWordPressAgendaPreview()
    {
        $this->wpAgendaPreviewRows = [];
        $this->wpAgendaPreviewSources = [];
        $this->wpAgendaPreviewSkipped = 0;
        $this->wpAgendaPreviewIssues = 0;
        $this->wpAgendaPreviewLoaded = false;
        $this->wpAgendaPreviewError = '';
    }


    public $wpPreviewPostType = '';

    public function getWpPreviewSourceChoicesProperty()
    {
        $event = Event::findOrFail($this->eventData['eventId']);

        $features = \App\Models\Feature::where('event_id', $event->id)
            ->pluck('short_name', 'id')
            ->all();

        $config = $event->wordpress_agenda_config ?? [];
        $choices = [];

        foreach (($config['sources'] ?? []) as $source) {
            $featureId = (int) ($source['feature_id'] ?? -1);

            $choices[] = [
                'post_type' => $source['post_type'],
                'label' => $featureId === 0
                    ? $event->short_name
                    : ($features[$featureId] ?? $source['post_type']),
            ];
        }

        return $choices;
    }
}
