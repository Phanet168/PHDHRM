<?php

namespace Modules\HumanResource\Enums\Mission;

enum MissionStatus: string
{
    case Draft = 'draft';
    case PendingOfficeHead = 'pending_office_head';
    case OfficeHeadEndorsed = 'office_head_endorsed';
    case PendingDirector = 'pending_director';
    case DirectorApproved = 'director_approved';
    case PendingMissionOrder = 'pending_mission_order';
    case PreparingMissionOrder = 'preparing_mission_order';
    case Issued = 'issued';
    case OnMission = 'on_mission';
    case PendingReport = 'pending_report';
    case Completed = 'completed';
    case ReturnedForCorrection = 'returned_for_correction';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'សេចក្ដីព្រាង',
            self::PendingOfficeHead => 'រង់ចាំប្រធានការិយាល័យ',
            self::OfficeHeadEndorsed => 'ប្រធានការិយាល័យឯកភាព',
            self::PendingDirector => 'រង់ចាំប្រធានមន្ទីរ',
            self::DirectorApproved => 'ប្រធានមន្ទីរអនុម័ត',
            self::PendingMissionOrder => 'រង់ចាំរៀបចំលិខិត',
            self::PreparingMissionOrder => 'កំពុងរៀបចំលិខិត',
            self::Issued => 'បានចេញលិខិត',
            self::OnMission => 'កំពុងបេសកកម្ម',
            self::PendingReport => 'រង់ចាំរបាយការណ៍',
            self::Completed => 'បញ្ចប់',
            self::ReturnedForCorrection => 'ត្រូវកែសម្រួល',
            self::Rejected => 'បដិសេធ',
            self::Cancelled => 'បានលុបចោល',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::PendingOfficeHead, self::PendingDirector, self::PendingMissionOrder, self::PendingReport => 'warning',
            self::OfficeHeadEndorsed, self::DirectorApproved, self::Issued => 'info',
            self::PreparingMissionOrder => 'info',
            self::OnMission => 'primary',
            self::Completed => 'success',
            self::ReturnedForCorrection => 'warning',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    /** Resting states shown in the "approved requests" queue (director-approved, awaiting/receiving the order). */
    public static function approvedQueue(): array
    {
        return [self::DirectorApproved, self::PendingMissionOrder, self::PreparingMissionOrder];
    }
}

