@php
    $dbData = $dbData ?? $employee ?? null;

    if (!isset($profileImageUrl) && $dbData) {
        if (!empty($dbData->profile_img_location)) {
            $profileImageUrl = asset('storage/' . ltrim((string) $dbData->profile_img_location, '/'));
        } else {
            $profileImageUrl = asset('backend/assets/dist/img/avatar-1.jpg');
        }
    }

    $publicProfileUrl = $publicProfileUrl ?? ($dbData?->uuid ? route('idprint.public-profile', $dbData->uuid) : url()->current());
    $autoPrint = $autoPrint ?? false;

    if (!isset($qrCodePng) && !empty($publicProfileUrl)) {
        $qrCodePng = app('DNS2D')->getBarcodePNG($publicProfileUrl, 'QRCODE', 4, 4);
    }
@endphp

@include('humanresource::idprint.employeeid', [
    'dbData' => $dbData,
    'profileImageUrl' => $profileImageUrl ?? asset('backend/assets/dist/img/avatar-1.jpg'),
    'publicProfileUrl' => $publicProfileUrl,
    'qrCodePng' => $qrCodePng ?? '',
    'autoPrint' => $autoPrint,
])
