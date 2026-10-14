@php $user = $user ?? null; @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-admin.form-input name="name" label="ឈ្មោះ" :value="$user?->name" required />
    <x-admin.form-input name="email" label="អ៊ីមែល" type="email" :value="$user?->email" required />
    <x-admin.form-input name="password" label="ពាក្យសម្ងាត់{{ $user ? ' (ទុកទទេប្រសិនបើមិនផ្លាស់ប្តូរ)' : '' }}" type="password" :required="! $user" />
    <x-admin.select name="role" label="តួនាទី" required :value="$user?->roles->first()?->name" :options="$roles" />
    <x-admin.select name="facility_id" label="មូលដ្ឋានទទួលបន្ទុក" :value="$user?->facility_id" :options="$facilities" placeholder="— មិនកំណត់ (សម្រាប់តួនាទីទូទាំងគេហទំព័រ) —" />
</div>

<label class="flex items-center gap-2 text-[13px] text-ink">
    <input type="checkbox" name="is_active" value="1" @checked($user ? $user->is_active : true) class="rounded border-line">
    គណនីសកម្ម
</label>
