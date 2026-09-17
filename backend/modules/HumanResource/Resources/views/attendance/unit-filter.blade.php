<div class="col-md-4 mb-2">
    <label class="form-label" for="attendance-unit">អង្គភាព</label>
    <select name="department_id" id="attendance-unit" class="form-select" onchange="this.form.querySelectorAll('[name=employee_id]').forEach(el => el.value = ''); this.form.submit()">
        @forelse($departments as $department)
            <option value="{{ $department->id }}" @selected($selectedDepartmentId == $department->id)>{{ $department->department_name }}</option>
        @empty
            <option value="">មិនមានអង្គភាពក្នុងសិទ្ធិគ្រប់គ្រង</option>
        @endforelse
    </select>
</div>
