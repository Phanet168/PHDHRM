<x-admin.layout title="តួនាទី និងសិទ្ធិ">
    <x-admin.data-table :headers="['តួនាទី', 'ចំនួនសិទ្ធិ', 'ចំនួនអ្នកប្រើប្រាស់', '']">
        @foreach ($roles as $role)
            <tr>
                <td class="px-4 py-3 font-bold text-ink text-[14px]">{{ $role->name }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $role->permissions_count }}</td>
                <td class="px-4 py-3 text-[13px] text-ink">{{ $role->users_count }}</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('admin.roles.edit', $role) }}" class="text-brand-blue text-[13px] font-bold">គ្រប់គ្រងសិទ្ធិ →</a>
                </td>
            </tr>
        @endforeach
    </x-admin.data-table>
</x-admin.layout>
