<?php

namespace App\Http\Livewire\Concerns;

use App\Models\Media;
use App\Models\Speaker;
use App\Services\WordPressSpeakerPreview;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

trait HasWordPressSpeakerProfileFields
{
    protected function wpSpeakerProfileNameText(array $parts)
    {
        return implode(' ', array_filter([
            $parts['salutation'] ?? '',
            $parts['first_name'] ?? '',
            $parts['middle_name'] ?? '',
            $parts['last_name'] ?? '',
        ], function ($value) {
            return trim((string) $value) !== '';
        }));
    }

    protected function wpSpeakerProfileName(array $sourceRow)
    {
        $suggested = app(WordPressSpeakerPreview::class)
            ->suggestNameParts($sourceRow['name']);

        $parts = $suggested;

        if ($suggested['review_required']) {
            $key = $sourceRow['source'] . '-' . $sourceRow['wp_id'];
            $reviewed = $this->wpSpeakerNames[$key] ?? [];

            if (($reviewed['reviewed'] ?? false) !== true) {
                return null;
            }

            $parts = $reviewed;
        }

        $name = [];

        foreach (
            ['salutation', 'first_name', 'middle_name', 'last_name']
            as $field
        ) {
            $value = $parts[$field] ?? '';

            if (!is_string($value)) {
                throw new RuntimeException('Invalid name field format.');
            }

            $name[$field] = trim($value);
        }

        Validator::make($name, [
            'salutation' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(config('app.salutations', [])),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
        ])->validate();

        // Allow splitting corrections, not silently replacing the source name.
        if (
            $this->wpSpeakerUpdateNormalize(
                $this->wpSpeakerProfileNameText($name)
            ) !== $this->wpSpeakerUpdateNormalize($sourceRow['name'])
        ) {
            throw new RuntimeException(
                'The reviewed name does not reconstruct the WordPress name. '
                . 'Correct the source name or the proposed split.'
            );
        }

        return $name;
    }

    protected function wpSpeakerProfileImageAllowed(string $url)
    {
        if (
            strlen($url) > 2048
            || filter_var($url, FILTER_VALIDATE_URL) === false
        ) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === 'www.gpcaforum.com'
            && !isset($parts['user'])
            && !isset($parts['pass'])
            && (!isset($parts['port']) || (int) $parts['port'] === 443)
            && strpos($parts['path'] ?? '', '/wp-content/uploads/') === 0;
    }

    protected function wpSpeakerProfilePhotoUrl(Speaker $speaker)
    {
        return $speaker->pfp_media_id
            ? (string) Media::whereKey($speaker->pfp_media_id)
                ->value('file_url')
            : '';
    }

    protected function wpSpeakerProfilePhotoHash(Speaker $speaker)
    {
        return $this->wpSpeakerUpdateHash([
            'media_id' => $speaker->pfp_media_id,
            'url' => $this->wpSpeakerProfilePhotoUrl($speaker),
        ]);
    }

    protected function wpSpeakerProfileWritePhoto(
        Speaker $speaker,
        string $url
    ) {
        if (!$this->wpSpeakerProfileImageAllowed($url)) {
            throw new RuntimeException('The proposed image URL is not allowed.');
        }

        $oldMediaId = $speaker->pfp_media_id;

        $usageId = $oldMediaId
            ? getMediaUsageId(
                $oldMediaId,
                \App\Enums\MediaEntityTypes::SPEAKER_PFP->value,
                $speaker->id
            )
            : null;

        // Create a separate external-URL record; never rewrite shared media.
        $path = parse_url($url, PHP_URL_PATH);
        $fileName = basename($path ?: '') ?: 'speaker-image';
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $media = Media::create([
            'file_url' => $url,
            'file_directory' => 'external-url',
            'file_name' => $fileName,
            'file_type' => $extension ?: 'image',
            'file_size' => 0,
            'width' => null,
            'height' => null,
            'date_uploaded' => now(),
        ]);

        mediaUsageUpdate(
            $usageId
                ? \App\Enums\MediaUsageUpdateTypes::REMOVED_THEN_ADD->value
                : \App\Enums\MediaUsageUpdateTypes::ADD_ONLY->value,
            $media->id,
            \App\Enums\MediaEntityTypes::SPEAKER_PFP->value,
            $speaker->id,
            $usageId
        );

        return $media->id;
    }
}