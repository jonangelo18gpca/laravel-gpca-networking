<?php

namespace App\Http\Livewire\Concerns;

use App\Services\WordPressSpeakerPreview;
use Illuminate\Support\Facades\Log;
use Throwable;

trait HasWordPressSpeakerPreview
{
    public $wpSpeakerSource = '';
    public $wpSpeakerYear = '2026';
    public $wpSpeakerRows = [];
    public $wpSpeakerError = '';
    public $wpSpeakerLoaded = false;
    public $wpSpeakerNames = [];

    public function updatedWpSpeakerSource()
    {
        $this->resetWordPressSpeakerPreview();
    }

    public function updatedWpSpeakerYear()
    {
        $this->resetWordPressSpeakerPreview();
    }

    protected function resetWordPressSpeakerPreview()
    {
        $this->wpSpeakerRows = [];
        $this->wpSpeakerNames = [];
        $this->wpSpeakerError = '';
        $this->wpSpeakerLoaded = false;


        $this->resetWordPressSpeakerMapping();
        $this->resetValidation();
    }
    public function loadWordPressSpeakerPreview()
    {
        $this->assertDataSyncAdmin();
        $this->resetWordPressSpeakerPreview();

        $this->validate([
            'wpSpeakerSource' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_-]+$/',
            ],
            'wpSpeakerYear' => [
                'required',
                'regex:/^\d{4}$/',
            ],
        ]);

        try {
            $service = app(WordPressSpeakerPreview::class);

            $identity = $this->wpSpeakerSettingsIdentity();

            $this->wpSpeakerRows = $service->fetch(
                $identity['post_type'],
                $identity['source_year'],
                $identity['source_url']
            );

            foreach ($this->wpSpeakerRows as $row) {
                if ($row['eligibility'] !== 'Eligible') {
                    continue;
                }

                $key = $row['source'] . '-' . $row['wp_id'];

                $this->wpSpeakerNames[$key] = $service->suggestNameParts(
                    $row['name']
                );
            }

            $this->wpSpeakerLoaded = true;
            $this->restoreWordPressSpeakerSettings();
        } catch (Throwable $exception) {
            Log::warning('WordPress speaker preview failed.', [
                'source' => $this->wpSpeakerSource,
                'year' => $this->wpSpeakerYear,
                'message' => $exception->getMessage(),
            ]);

            $this->wpSpeakerError =
                'Unable to load the complete speaker preview. '
                . 'Check the Laravel log for the failure details. '
                . 'No app records were changed.';
        }
    }
    public function getWpSpeakerSourceChoicesProperty()
    {
        $event = \App\Models\Event::findOrFail(
            $this->eventData['eventId']
        );

        $sourceUrl = rtrim(
            trim((string) $event->wordpress_source_url),
            '/'
        );

        if (
            $sourceUrl === ''
            || !\Illuminate\Support\Facades\Schema::hasTable(
                'wordpress_speaker_settings'
            )
        ) {
            return [];
        }

        $features = \App\Models\Feature::where(
            'event_id',
            $event->id
        )->pluck('short_name', 'id')->all();

        $settings = \Illuminate\Support\Facades\DB::table(
            'wordpress_speaker_settings'
        )
            ->where('event_id', $event->id)
            ->where('source_url', $sourceUrl)
            ->where('source_year', (string) $this->wpSpeakerYear)
            ->orderBy('id')
            ->get();

        $choices = [];

        foreach ($settings as $setting) {
            $featureId = (int) $setting->feature_id;

            if (
                $featureId !== 0
                && !array_key_exists($featureId, $features)
            ) {
                continue;
            }

            $choices[] = [
                'post_type' => (string) $setting->post_type,
                'label' => $featureId === 0
                    ? $event->short_name
                    : $features[$featureId],
            ];
        }

        return $choices;
    }
    public function updatedWpSpeakerNames($value, $key)
    {

        $this->resetWordPressSpeakerUpdates();


        $parts = explode('.', (string) $key, 2);

        if (count($parts) !== 2) {
            return;
        }

        [$rowKey, $field] = $parts;

        if (
            !isset($this->wpSpeakerNames[$rowKey])
            || !in_array(
                $field,
                ['salutation', 'first_name', 'middle_name', 'last_name'],
                true
            )
        ) {
            return;
        }

        $this->wpSpeakerNames[$rowKey]['reviewed'] = false;
    }
}
