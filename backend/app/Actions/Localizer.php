<?php

namespace App\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class Localizer
{
    /**
     * The cached localization data.
     *
     * @var array
     */
    protected $localData = [];

    /**
     * Per-request translation memoization to avoid repeated HTTP calls.
     *
     * @var array<string, string>
     */
    protected $translationMemo = [];

    /**
     * Get the path to the localization file for the given locale.
     *
     * @return string
     */
    public function getLocalizePath(string $locale)
    {
        return base_path('lang/').$locale.'/language.php';
    }

    /**
     * Create the localization file for the given locale.
     */
    public function createLocalizeFile(string $locale, ?string $builder_local = null): void
    {
        // get localize path
        $localizePath = $this->getLocalizePath($locale);
        //
        if ($builder_local) {
            $locale = $this->getLocalizeData($builder_local);
        } else {
            $locale = [];
        }
        //    create a php file and push empty array
        File::put($localizePath, '<?php return '.var_export($locale, true).';');
    }

    public function updateLocalizeFile(string $old_locale, string $new_local, ?string $builder_local = null): void
    {
        if ($builder_local && $builder_local != $new_local) {
            $this->deleteLocalizeFile($new_local);
            $this->createLocalizeFile($new_local, $builder_local);

            return;
        }
        // get localize path
        $localizePath = $this->getLocalizePath($old_locale);
        $new_localizePath = $this->getLocalizePath($new_local);
        // check if localize file exists
        if (! File::exists($localizePath)) {
            //    create a php file and push empty array
            $this->createLocalizeFile($old_locale);
        }
        // rename localize file
        File::move($localizePath, $new_localizePath);
    }

    /**
     * Delete the localization file for the given locale.
     */
    public function deleteLocalizeFile(string $locale): void
    {
        // get localize path
        $localizePath = $this->getLocalizePath($locale);
        // delete localize file
        File::delete($localizePath);
    }

    /**
     * Get the localization data for the given locale.
     *
     * @return mixed
     */
    public function getLocalizeData(string $locale)
    {
        // check if locale data is empty
        if (empty($this->localData[$locale])) {
            // get localize path
            $localizePath = $this->getLocalizePath($locale);
            // check if localize file exists
            if (! File::exists($localizePath)) {
                // create a php file and push empty array
                $this->createLocalizeFile($locale);
            }
            // Use include instead of require_once to ensure the file is evaluated
            $localData = include $localizePath;

            // Check if the included data is an array
            if (is_array($localData)) {
                $this->localData[$locale] = $localData;
            } else {
                // Handle the case where the included data is not an array
                // You might want to log an error or handle it in a way that makes sense for your application.
                // For now, let's assume it's an empty array.
                $this->localData[$locale] = [];
            }
        }

        return $this->localData[$locale];
    }

    /**
     * Get the localized value for the given key.
     */
    public function localize(string $key, ?string $default_value = null, ?string $locale = null): string
    {
        // Get locale
        $locale = $locale ?? app()->getLocale();

        // Get localize data
        $local = $this->getLocalizeData($locale);

        // Format key
        $formattedKey = $this->formatKey($key);

        // Check if $local is an array
        if (! is_array($local)) {
            // Handle the case where $local is not an array, e.g., set it to an empty array or log an error.
            // Here, we set it to an empty array as a fallback.
            $local = [];
        }

        // Find the key in the localized data, or use the default value if not found.
        if (array_key_exists($formattedKey, $local) === false) {
            // If the key is not found, store the default value in the localization file.
            $value = $default_value ?? $this->keyToValue($formattedKey);
            $value = $this->normalizeLocalizedValue((string) $value);

            // Auto-translate to Khmer when locale is km and value is still English.
            $value = $this->maybeAutoTranslate((string) $value, $locale);

            // Store the default value in the localization file.
            $this->storeLocal($key, $value, $locale);
        } else {
            // If the key is found, use the localized value.
            $value = $this->normalizeLocalizedValue((string) $local[$formattedKey]);

            if ($value !== (string) $local[$formattedKey]) {
                $this->storeLocal($key, $value, $locale);
            }

            // If current km value is English, translate once and persist.
            $translatedValue = $this->maybeAutoTranslate((string) $value, $locale);
            if ($translatedValue !== (string) $value) {
                $value = $translatedValue;
                $this->storeLocal($key, $value, $locale);
            }
        }

        return $this->normalizeLocalizedValue((string) $value);
    }

    /**
     * Format the given key.
     */
    public function formatKey(string $key): string
    {
        // trim white space
        $formattedKey = trim($key);
        // remove special characters
        $formattedKey = preg_replace('/[.,\/\\\\\s-]|(["\'])/', '_', $formattedKey);
        // remove multiple underscore
        $formattedKey = preg_replace('/_+/', '_', $formattedKey);
        // remove underscore from start and end
        $formattedKey = trim($formattedKey, '_');
        // lowercase
        $formattedKey = strtolower($formattedKey);

        return $formattedKey;
    }

    /**
     * Convert the given key to a value.
     */
    public function keyToValue(string $key): string
    {
        // remove Underscore to space and uc first
        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Store the given key and value in the localization file for the given locale.
     */
    public function storeLocal(string $key, ?string $value = null, ?string $locale = null): void
    {
        // Get locale
        $locale = $locale ?? app()->getLocale();

        // Get localize path
        $localizePath = $this->getLocalizePath($locale);

        // Format key
        $formattedKey = $this->formatKey($key);

        // Get localize data
        $local = $this->getLocalizeData($locale);

        // Ensure $local is an array, or initialize it as an empty array
        $local = is_array($local) ? $local : [];

        // If the value is not provided, use the formatted key as the value.
        $value = $value ?? $formattedKey;

        // Update the localization data.
        $local[$formattedKey] = $value;

        // Write language PHP file
        File::put($localizePath, '<?php return '.var_export($local, true).';');

        // Update the cached data for this locale.
        $this->localData[$locale] = $local;
    }

    /**
     * Translate English-looking text to Khmer when locale is km.
     */
    protected function maybeAutoTranslate(string $text, string $locale): string
    {
        $text = $this->normalizeLocalizedValue($text);
        if ($text === '') {
            return $text;
        }

        // Keep production/staging fast by limiting auto translation to local env.
        if (! app()->environment('local') || $locale !== 'km') {
            return $text;
        }

        $memoKey = $locale.'|'.$text;
        if (array_key_exists($memoKey, $this->translationMemo)) {
            return $this->translationMemo[$memoKey];
        }

        // Skip values that already contain Khmer, or are mostly non-letter tokens.
        if (preg_match('/[\x{1780}-\x{17FF}]/u', $text) === 1) {
            $this->translationMemo[$memoKey] = $text;
            return $text;
        }

        if (preg_match('/[A-Za-z]/', $text) !== 1) {
            $this->translationMemo[$memoKey] = $text;
            return $text;
        }

        // Keep common technical tokens unchanged.
        if (preg_match('/^(id|url|api|pdf|csv|json|xml|sms|otp)$/i', $text) === 1) {
            $this->translationMemo[$memoKey] = $text;
            return $text;
        }

        try {
            $response = Http::timeout(3)->get('https://api.mymemory.translated.net/get', [
                'q' => $text,
                'langpair' => 'en|km',
            ]);

            if (! $response->ok()) {
                $this->translationMemo[$memoKey] = $text;
                return $text;
            }

            $translated = trim((string) data_get($response->json(), 'responseData.translatedText', ''));
            if ($translated === '') {
                $this->translationMemo[$memoKey] = $text;
                return $text;
            }

            $translated = $this->normalizeLocalizedValue($translated);

            $translated = $translated !== '' ? $translated : $text;
            $this->translationMemo[$memoKey] = $translated;

            return $translated;
        } catch (\Throwable $e) {
            $this->translationMemo[$memoKey] = $text;
            return $text;
        }
    }

    /**
     * Normalize localized value to plain, display-safe text.
     */
    protected function normalizeLocalizedValue(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Store the given key and value in the localization file for the given locale.
     */
    public function bulkStore(array $local, ?string $locale = null): void
    {
        // get locale
        $locale = $locale ?? app()->getLocale();
        // get localize path
        $localizePath = $this->getLocalizePath($locale);
        // get localize data
        $localData = $this->getLocalizeData($locale);
        // marge two array
        $local = array_merge($localData, $local);
        // Write the updated data back to the file.
        File::put($localizePath, '<?php return '.var_export($local, true).';');
        // Update the cached data for this locale.
        $this->localData[$locale] = $local;
    }

    /**
     * Delete the given key from the localization file for the given locale.
     */
    public function deleteLocal(string $key, ?string $locale = null): void
    {
        // get locale
        $locale = $locale ?? app()->getLocale();
        // get localize path
        $localizePath = $this->getLocalizePath($locale);
        // format key
        $formattedKey = $this->formatKey($key);
        // get localize data
        $local = $this->getLocalizeData($locale);

        // Remove the key from the localization data.
        unset($local[$formattedKey]);

        // Write the updated data back to the file.
        File::put($localizePath, '<?php return '.var_export($local, true).';');

        // Update the cached data for this locale.
        $this->localData[$locale] = $local;
    }
}
