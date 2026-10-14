@php
    $groups = $permissions->groupBy(fn ($permission) => explode('.', $permission->name)[0]);
    $groupLabels = [
        'facilities' => 'មូលដ្ឋានសុខាភិបាល',
        'news' => 'ព័ត៌មាន',
        'legal-documents' => 'លិខិតបទដ្ឋានគតិយុត្ត',
        'health-content' => 'មាតិកាអប់រំសុខភាព',
        'partners' => 'អង្គការដៃគូ',
        'media' => 'Media Library',
        'users' => 'អ្នកប្រើប្រាស់',
        'roles' => 'តួនាទី និងសិទ្ធិ',
        'settings' => 'ការកំណត់ប្រព័ន្ធ',
        'audit-logs' => 'Audit Logs',
    ];
@endphp
<x-admin.layout :title="'សិទ្ធិសម្រាប់ ' . $role->name">
    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="bg-white border border-line rounded-[10px] shadow-[0px_3px_10px_0px_rgba(0,0,0,0.06)] p-6 max-w-[800px]">
        @csrf @method('PUT')
        <p class="font-bold text-brand-navy text-[18px] mb-1">{{ $role->name }}</p>
        <p class="text-muted text-[13px] mb-6">ជ្រើសរើសសិទ្ធិសម្រាប់តួនាទីនេះ។</p>

        <div class="flex flex-col gap-5">
            @foreach ($groups as $group => $items)
                <div class="border-t border-line pt-4">
                    <p class="font-bold text-ink text-[14px] mb-2">{{ $groupLabels[$group] ?? $group }}</p>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                        @foreach ($items as $permission)
                            <label class="flex items-center gap-2 text-[13px] text-ink">
                                <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" @checked(in_array($permission->name, $assigned)) class="rounded border-line">
                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end gap-3 pt-6">
            <a href="{{ route('admin.roles.index') }}" class="text-muted text-[14px] font-bold px-4 py-2">ត្រឡប់</a>
            <button type="submit" class="bg-brand-blue text-white font-bold text-[14px] rounded-[8px] h-11 px-6">រក្សាទុក</button>
        </div>
    </form>
</x-admin.layout>
