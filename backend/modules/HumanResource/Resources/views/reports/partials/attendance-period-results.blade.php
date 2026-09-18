{{--
    Attendance Management Phase E: shared results table for the weekly/
    quarterly/semester/yearly attendance & duty-hour reports. Expects
    $rows (from PeriodAttendanceSummaryService::summarize()), $periodFrom,
    $periodTo.
--}}
<div class="text-muted small mb-2">
    ចាប់ពី {{ $periodFrom->format('d-m-Y') }} ដល់ {{ $periodTo->format('d-m-Y') }}
</div>
<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>ល.រ</th>
                <th>បុគ្គលិក</th>
                <th class="text-center">ថ្ងៃមកធ្វើការ</th>
                <th class="text-center">ថ្ងៃអវត្តមាន</th>
                <th class="text-center">ថ្ងៃឈប់សម្រាក</th>
                <th class="text-center">ថ្ងៃឈប់/បុណ្យ</th>
                <th class="text-center">ម៉ោងវេនយាម</th>
                <th class="text-center">ម៉ោងធម្មតា</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row['employee_name'] }}</td>
                    <td class="text-center">{{ $row['present_days'] }}</td>
                    <td class="text-center">{{ $row['absent_days'] }}</td>
                    <td class="text-center">{{ $row['leave_days'] }}</td>
                    <td class="text-center">{{ $row['holiday_days'] }}</td>
                    <td class="text-center">{{ $row['duty_hours'] }}</td>
                    <td class="text-center">{{ $row['regular_hours'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted p-4">មិនមានទិន្នន័យសម្រាប់រយៈពេលនេះទេ។</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
