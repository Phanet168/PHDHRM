<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** A disaggregation value other than age / sex: zone (តំបន់ក/ខ/គ), residence, mother / child. */
class ReportCategoryOption extends Model
{
    public const CATEGORIES = [
        'zone' => 'តំបន់',
        'residence' => 'ទីលំនៅ',
        'person' => 'ម្តាយ / កូន',
    ];

    protected $table = 'report_category_options';
    protected $fillable = ['category', 'code', 'label', 'sort_order'];
}
