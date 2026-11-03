<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Event;
use App\Models\Feature;
use App\Models\SpeakerType;
use App\Services\WordPressSpeakerPreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

trait HasWordPressSpeakerSettings
{
    public $wpSpeakerSettingsMessage = '';
    public $wpSpeakerSettingsError = '';

    protected function resetWordPressSpeakerSettingsMessages()
    {
        $this->wpSpeakerSettingsMessage = '';
        $this->wpSpeakerSettingsError = '';
    }

    protected function wpSpeakerSettingsIdentity()
    {
        $source = (string) $this->wpSpeakerSource;
        $year = (string) $this->wpSpeakerYear;

        if (!isset(WordPressSpeakerPreview::SOURCES[$source])) {
            throw new RuntimeException('Select a valid speaker source.');
        }

        if (!preg_match('/^\d{4}$/', $year)) {
            throw new RuntimeException('Enter a four-digit year.');
        }

        $identity = [
            'event_id' => (int) $this->eventData['eventId'],
            'source_url' => 'https://www.gpcaforum.com',
            'post_type' => $source,
            'source_year' => $year,
        ];

        $identity['identity_hash'] = hash('sha256', json_encode([
            $identity['event_id'],
            $identity['source_url'],
            $identity['post_type'],
            $identity['source_year'],
        ]));

        return $identity;
    }

    protected function restoreWordPressSpeakerSettings()
    {
        $this->resetWordPressSpeakerSettingsMessages();

        try {
            if (!Schema::hasTable('wordpress_speaker_settings')) {
                throw new RuntimeException(
                    'Run the wordpress_speaker_settings migration first.'
                );
            }

            $identity = $this->wpSpeakerSettingsIdentity();

            $saved = DB::table('wordpress_speaker_settings')
                ->where('event_id', $identity['event_id'])
                ->where('identity_hash', $identity['identity_hash'])
                ->first();

            if (!$saved) {
                $this->wpSpeakerSettingsMessage =
                    'No saved settings for this source/year. '
                    . 'Choose mappings and save them once.';

                return;
            }

            $storedMappings = json_decode(
                $saved->role_mappings,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($storedMappings)) {
                throw new RuntimeException(
                    'Saved role mappings have an invalid format.'
                );
            }

            $destination = (int) $saved->feature_id;
            $destinationValid = $destination === 0
                || Feature::where('event_id', $identity['event_id'])
                    ->whereKey($destination)
                    ->exists();

            $this->wpSpeakerDestination = $destinationValid
                ? (string) $destination
                : '';

            $validTypeIds = SpeakerType::where(
                'event_id',
                $identity['event_id']
            )
                ->pluck('id')
                ->map(function ($id) {
                    return (string) $id;
                })
                ->all();

            $this->wpSpeakerRoleMappings = [];
            $incomplete = !$destinationValid;

            foreach ($this->getWpSpeakerRoleChoicesProperty() as $role) {
                $selected = $storedMappings[$role['key']] ?? '';

                if (
                    is_scalar($selected)
                    && in_array((string) $selected, $validTypeIds, true)
                ) {
                    $this->wpSpeakerRoleMappings[$role['key']] =
                        (string) $selected;
                } else {
                    $this->wpSpeakerRoleMappings[$role['key']] = '';
                    $incomplete = true;
                }
            }

            if ($incomplete) {
                $this->wpSpeakerSettingsMessage =
                    'Saved settings loaded. A destination or role mapping '
                    . 'needs correction; complete it and save again.';
            } else {
                $this->wpSpeakerSettingsMessage =
                    'Saved source settings loaded automatically.';
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->wpSpeakerDestination = '';
            $this->wpSpeakerRoleMappings = [];

            $this->wpSpeakerSettingsError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }

    public function saveWordPressSpeakerSettings()
    {
        $this->assertDataSyncAdmin();
        $this->resetWordPressSpeakerSettingsMessages();

        try {
            if (!Schema::hasTable('wordpress_speaker_settings')) {
                throw new RuntimeException(
                    'Run the wordpress_speaker_settings migration first.'
                );
            }

            $identity = $this->wpSpeakerSettingsIdentity();

            DB::transaction(function () use ($identity) {
                Event::whereKey($identity['event_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->checkWordPressSpeakerMapping();

                $existing = DB::table('wordpress_speaker_settings')
                    ->where('identity_hash', $identity['identity_hash'])
                    ->lockForUpdate()
                    ->first();

                $mappings = $existing
                    ? json_decode(
                        $existing->role_mappings,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    )
                    : [];

                if (!is_array($mappings)) {
                    throw new RuntimeException(
                        'Saved role mappings have an invalid format.'
                    );
                }

                // Preserve previously configured roles that are currently absent.
                foreach ($this->getWpSpeakerRoleChoicesProperty() as $role) {
                    $mappings[$role['key']] =
                        (string) $this->wpSpeakerRoleMappings[$role['key']];
                }

                ksort($mappings);

                $values = array_merge($identity, [
                    'feature_id' => (int) $this->wpSpeakerDestination,
                    'role_mappings' => json_encode(
                        $mappings,
                        JSON_THROW_ON_ERROR
                    ),
                    'updated_at' => now(),
                ]);

                if ($existing) {
                    DB::table('wordpress_speaker_settings')
                        ->where('id', $existing->id)
                        ->update($values);
                } else {
                    $values['created_at'] = now();

                    DB::table('wordpress_speaker_settings')->insert($values);
                }
            });

            $this->resetWordPressSpeakerLinking();
            $this->resetWordPressSpeakerUpdates();
            $this->resetWordPressSpeakerCreation();

            $this->wpSpeakerMappingMessage = '';

            $this->wpSpeakerSettingsMessage =
                'Speaker source settings saved. '
                . 'They will load automatically with this source/year. '
                . 'No speaker profiles or links were changed.';
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            $this->wpSpeakerSettingsError = mb_substr(
                $exception->getMessage(),
                0,
                600
            );
        }
    }
}