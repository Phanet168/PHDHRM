{{-- National motto, the provincial logo and the issuing authority, as on the official templates. --}}
@php($logo = app_setting()->logo ?? null)
<header class="letter-head">
    <div class="national">ព្រះរាជាណាចក្រកម្ពុជា<div class="motto">ជាតិ សាសនា ព្រះមហាក្សត្រ</div><div class="ornament" aria-hidden="true">{{ $mottoOrnament ?? '6' }}</div></div>
    @if($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    <div class="issuer">{{ $details['issuing_authority'] ?? '........................................' }}<br>{{ $details['issuing_department'] ?? '........................................' }}</div>
</header>
