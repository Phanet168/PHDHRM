@php
    $emp = $employee ?? null;

    $educationRows = old('education_histories');
    if (!is_array($educationRows)) {
        $educationRows = $emp ? $emp->educationHistories->map(fn($r) => $r->toArray())->toArray() : [];
    }
    if (empty($educationRows)) {
        $educationRows = [[]];
    }

    $languageRows = old('foreign_languages');
    if (!is_array($languageRows)) {
        $languageRows = $emp ? $emp->foreignLanguages->map(fn($r) => $r->toArray())->toArray() : [];
    }
    if (empty($languageRows)) {
        $languageRows = [[]];
    }

    $bankRows = old('bank_accounts');
    if (!is_array($bankRows)) {
        $bankRows = $emp ? $emp->bankAccounts->map(fn($r) => $r->toArray())->toArray() : [];
    }
    if (empty($bankRows)) {
        $bankRows = [[]];
    }

    $bankAttachmentRows = old('bank_attachments');
    if (!is_array($bankAttachmentRows)) {
        $bankAttachmentRows = $emp
            ? $emp->sectionAttachments->where('section', 'bank')->map(fn($r) => $r->toArray())->toArray()
            : [];
    }
    if (empty($bankAttachmentRows)) {
        $bankAttachmentRows = [[]];
    }
@endphp

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">ប្រវត្តិសិក្សា</h6>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="education-history-table">
            <thead>
                <tr>
                    <th>គ្រឹះស្ថានសិក្សា</th>
                    <th>ថ្ងៃចូលសិក្សា</th>
                    <th>ថ្ងៃបញ្ចប់</th>
                    <th>កម្រិតសញ្ញាបត្រ</th>
                    <th>មតិយោបល់</th>
                    <th width="80">សកម្មភាព</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($educationRows as $idx => $row)
                    <tr>
                        <td><input type="text" name="education_histories[{{ $idx }}][institution_name]" class="form-control" value="{{ $row['institution_name'] ?? '' }}"></td>
                        <td><input type="date" name="education_histories[{{ $idx }}][start_date]" class="form-control" value="{{ $row['start_date'] ?? '' }}"></td>
                        <td><input type="date" name="education_histories[{{ $idx }}][end_date]" class="form-control" value="{{ $row['end_date'] ?? '' }}"></td>
                        <td><input type="text" name="education_histories[{{ $idx }}][degree_level]" class="form-control" value="{{ $row['degree_level'] ?? '' }}"></td>
                        <td><input type="text" name="education_histories[{{ $idx }}][note]" class="form-control" value="{{ $row['note'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">លុប</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#education-history-table" data-repeater="education_histories">+ បន្ថែម</button>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">ភាសាបរទេស</h6>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="foreign-language-table">
            <thead>
                <tr>
                    <th>ភាសា</th>
                    <th>ការសន្ទនា</th>
                    <th>ការអាន</th>
                    <th>ការសរសេរ</th>
                    <th>គ្រឹះស្ថាន</th>
                    <th>ថ្ងៃចូល</th>
                    <th>ថ្ងៃបញ្ចប់</th>
                    <th>លទ្ធផល</th>
                    <th width="80">សកម្មភាព</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($languageRows as $idx => $row)
                    <tr>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][language_name]" class="form-control" value="{{ $row['language_name'] ?? '' }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][speaking_level]" class="form-control" value="{{ $row['speaking_level'] ?? '' }}" placeholder="A/B/C"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][reading_level]" class="form-control" value="{{ $row['reading_level'] ?? '' }}" placeholder="A/B/C"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][writing_level]" class="form-control" value="{{ $row['writing_level'] ?? '' }}" placeholder="A/B/C"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][institution_name]" class="form-control" value="{{ $row['institution_name'] ?? '' }}"></td>
                        <td><input type="date" name="foreign_languages[{{ $idx }}][start_date]" class="form-control" value="{{ $row['start_date'] ?? '' }}"></td>
                        <td><input type="date" name="foreign_languages[{{ $idx }}][end_date]" class="form-control" value="{{ $row['end_date'] ?? '' }}"></td>
                        <td><input type="text" name="foreign_languages[{{ $idx }}][result]" class="form-control" value="{{ $row['result'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">លុប</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#foreign-language-table" data-repeater="foreign_languages">+ បន្ថែម</button>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">គណនីធនាគារ</h6>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="bank-account-table">
            <thead>
                <tr>
                    <th>ឈ្មោះគណនី</th>
                    <th>លេខគណនី</th>
                    <th>ឈ្មោះធនាគារ</th>
                    <th>ឯកសារភ្ជាប់</th>
                    <th width="80">សកម្មភាព</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bankRows as $idx => $row)
                    <tr>
                        <td><input type="text" name="bank_accounts[{{ $idx }}][account_name]" class="form-control" value="{{ $row['account_name'] ?? '' }}"></td>
                        <td><input type="text" name="bank_accounts[{{ $idx }}][account_number]" class="form-control" value="{{ $row['account_number'] ?? '' }}"></td>
                        <td><input type="text" name="bank_accounts[{{ $idx }}][bank_name]" class="form-control" value="{{ $row['bank_name'] ?? '' }}"></td>
                        <td>
                            <input type="file" name="bank_accounts[{{ $idx }}][attachment]" class="form-control">
                            @if (!empty($row['attachment_path']))
                                <a href="{{ asset('storage/' . $row['attachment_path']) }}" target="_blank">{{ $row['attachment_name'] ?? 'មើលឯកសារ' }}</a>
                            @endif
                        </td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">លុប</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#bank-account-table" data-repeater="bank_accounts">+ បន្ថែម</button>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">ឯកសារភ្ជាប់ (ធនាគារ)</h6>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="bank-attachments-table">
            <thead>
                <tr>
                    <th>ចំណងជើង</th>
                    <th>ឯកសារ</th>
                    <th>ថ្ងៃផុតកំណត់</th>
                    <th width="80">សកម្មភាព</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bankAttachmentRows as $idx => $row)
                    <tr>
                        <td><input type="text" name="bank_attachments[{{ $idx }}][title]" class="form-control" value="{{ $row['title'] ?? '' }}"></td>
                        <td>
                            <input type="file" name="bank_attachments[{{ $idx }}][file]" class="form-control">
                            @if (!empty($row['file_path']))
                                <a href="{{ asset('storage/' . $row['file_path']) }}" target="_blank">{{ $row['file_name'] ?? 'មើលឯកសារ' }}</a>
                            @endif
                        </td>
                        <td><input type="date" name="bank_attachments[{{ $idx }}][expiry_date]" class="form-control" value="{{ $row['expiry_date'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">លុប</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#bank-attachments-table" data-repeater="bank_attachments">+ បន្ថែម</button>
</div>
