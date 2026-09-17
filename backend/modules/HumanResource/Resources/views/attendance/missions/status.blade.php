@if($mission->lifecycle_status)
    <span class="badge bg-{{ $mission->lifecycle_status->badgeColor() }}">{{ $mission->lifecycle_status->label() }}</span>
@else
    @php
        $labels = ['draft' => 'សេចក្ដីព្រាង', 'pending' => 'រង់ចាំអនុម័ត', 'approved' => 'បានអនុម័ត', 'rejected' => 'បានបដិសេធ', 'cancelled' => 'បានបោះបង់', 'in_progress' => 'កំពុងដំណើរការ', 'completed' => 'បានបញ្ចប់'];
        $tone = ['pending' => 'warning', 'approved' => 'success', 'in_progress' => 'success', 'completed' => 'info', 'rejected' => 'danger'][$mission->display_status] ?? 'secondary';
    @endphp
    <span class="badge bg-{{ $tone }}">{{ $labels[$mission->display_status] ?? $mission->status }}</span>
@endif
