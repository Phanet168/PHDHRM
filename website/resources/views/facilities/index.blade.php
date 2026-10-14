@php
    $legendTypes = [
        'referral_hospital' => ['label' => 'មន្ទីរពេទ្យបង្អែក', 'color' => \App\Models\Facility::typeColor('referral_hospital')],
        'health_center' => ['label' => 'មណ្ឌលសុខភាព', 'color' => \App\Models\Facility::typeColor('health_center')],
        'health_post' => ['label' => 'ប៉ុស្តិ៍សុខភាព', 'color' => \App\Models\Facility::typeColor('health_post')],
        'operational_district_office' => ['label' => 'ការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ', 'color' => \App\Models\Facility::typeColor('operational_district_office')],
    ];
@endphp
<x-layout title="មូលដ្ឋានសុខាភិបាល">
    <x-page-banner page="facilities" title="មូលដ្ឋានសុខាភិបាល" />
    <x-page-header title="មូលដ្ឋានសុខាភិបាល" />

    <div class="facility-page public-container" x-data="facilityDirectory(@js($mapFacilities))">
        <div class="facility-summary-grid">
            <x-facility-summary-card icon="✚" :count="$counts['referral_hospital']" label="មន្ទីរពេទ្យបង្អែក" :color="\App\Models\Facility::typeColor('referral_hospital')" />
            <x-facility-summary-card icon="⌂" :count="$counts['health_center']" label="មណ្ឌលសុខភាព" :color="\App\Models\Facility::typeColor('health_center')" />
            <x-facility-summary-card icon="⌖" :count="$counts['health_post']" label="ប៉ុស្តិ៍សុខភាព" :color="\App\Models\Facility::typeColor('health_post')" />
            <x-facility-summary-card icon="🏛" :count="$counts['operational_district_office']" label="ការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ" :color="\App\Models\Facility::typeColor('operational_district_office')" />
            <x-facility-summary-card icon="◈" :count="$counts['total']" label="សរុបមូលដ្ឋានសុខាភិបាល" color="#05305c" />
        </div>

        <x-facility-filter-bar :districts="$districts" :communes="$communes" />

        <div class="facility-main-layout" :class="'view-' + view">
            <x-facility-map :types="$legendTypes" />
            <x-facility-list :facilities="$facilities" />
        </div>
    </div>
</x-layout>
