<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('legal_document');

        return $document ? $this->user()->can('update', $document) : $this->user()->can('create', \App\Models\LegalDocument::class);
    }

    public function rules(): array
    {
        $document = $this->route('legal_document');

        // A record imported from a link may not have a known number/date/year yet;
        // it stays editable without them, but new records and dated ones still need them.
        $requiredUnlessUnknown = fn (string $attribute) => ! $document || $document->{$attribute} !== null ? 'required' : 'nullable';

        return [
            'category_id' => ['nullable', 'exists:legal_document_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => [$requiredUnlessUnknown('document_number'), 'string', 'max:100'],
            'issuing_authority' => ['required', 'string', 'max:255'],
            'issue_date' => [$requiredUnlessUnknown('issue_date'), 'date'],
            'year' => [$requiredUnlessUnknown('year'), 'integer', 'min:1900', 'max:2100'],
            'description' => ['nullable', 'string'],
            'external_url' => ['nullable', 'url:http,https', 'max:2048'],
            'file' => [$document ? 'nullable' : 'required_without:external_url', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }
}
