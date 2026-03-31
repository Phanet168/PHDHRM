@php
    $emp = $employee ?? null;

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

    $isKhmerUi = app()->getLocale() === 'km';
    $bankAttachmentsTitle = $isKhmerUi ? 'ឯកសារភ្ជាប់ (ព័ត៌មានធនាគារ)' : 'Bank attachments';
    $nssfCardLabel = $isKhmerUi ? 'លេខប័ណ្ណសមាជិក ប.ស.ស' : 'NSSF membership card number';
@endphp

<div class="gov-section-card mb-3">
    <div class="form-group mb-0 mx-0 row">
        <label for="sos" class="col-lg-3 col-form-label ps-0">{{ $nssfCardLabel }}</label>
        <div class="col-lg-9">
            <input type="text" name="sos" id="sos" class="form-control"
                value="{{ old('sos', optional($emp)->sos) }}" autocomplete="off">
            @if ($errors->has('sos'))
                <div class="error text-danger text-start">{{ $errors->first('sos') }}</div>
            @endif
        </div>
    </div>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ localize('bank_account') }}</h6>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="bank-account-table">
            <thead>
                <tr>
                    <th>{{ localize('account_name') }}</th>
                    <th>{{ localize('account_number') }}</th>
                    <th>{{ localize('bank_name') }}</th>
                    <th>{{ localize('file') }}</th>
                    <th width="80">{{ localize('action') }}</th>
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
                                <a href="{{ asset('storage/' . $row['attachment_path']) }}" target="_blank">{{ $row['attachment_name'] ?? localize('file') }}</a>
                            @endif
                        </td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#bank-account-table" data-repeater="bank_accounts">
        + {{ localize('add_more') }}
    </button>
</div>

<div class="gov-section-card mb-3">
    <h6 class="gov-section-title">{{ $bankAttachmentsTitle }}</h6>
    <div class="table-responsive mb-2">
        <table class="table table-bordered" id="bank-attachments-table">
            <thead>
                <tr>
                    <th>{{ localize('doc_title') }}</th>
                    <th>{{ localize('file') }}</th>
                    <th>{{ localize('expiry_date') }}</th>
                    <th width="80">{{ localize('action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bankAttachmentRows as $idx => $row)
                    <tr>
                        <td><input type="text" name="bank_attachments[{{ $idx }}][title]" class="form-control" value="{{ $row['title'] ?? '' }}"></td>
                        <td>
                            <input type="file" name="bank_attachments[{{ $idx }}][file]" class="form-control">
                            @if (!empty($row['file_path']))
                                <a href="{{ asset('storage/' . $row['file_path']) }}" target="_blank">{{ $row['file_name'] ?? localize('file') }}</a>
                            @endif
                        </td>
                        <td><input type="date" name="bank_attachments[{{ $idx }}][expiry_date]" class="form-control" value="{{ $row['expiry_date'] ?? '' }}"></td>
                        <td><button type="button" class="btn btn-sm btn-danger repeater-remove">{{ localize('delete') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-primary repeater-add" data-target="#bank-attachments-table" data-repeater="bank_attachments">
        + {{ localize('add_more') }}
    </button>
</div>
