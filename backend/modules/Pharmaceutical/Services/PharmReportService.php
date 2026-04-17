<?php

namespace Modules\Pharmaceutical\Services;

use Illuminate\Support\Carbon;
use Modules\Pharmaceutical\Entities\PharmDispensingItem;
use Modules\Pharmaceutical\Entities\PharmDistribution;
use Modules\Pharmaceutical\Entities\PharmDistributionItem;
use Modules\Pharmaceutical\Entities\PharmFacilityStock;
use Modules\Pharmaceutical\Entities\PharmMedicine;
use Modules\Pharmaceutical\Entities\PharmReport;
use Modules\Pharmaceutical\Entities\PharmReportItem;
use Modules\Pharmaceutical\Entities\PharmStockAdjustment;

class PharmReportService
{
    /**
     * Auto-generate report item data for a facility + period.
     *
     * Returns an array keyed by medicine_id:
     *   [medicine_id => [opening_stock, received_qty, dispensed_qty, damaged_qty, expired_qty, adjustment_qty, closing_stock]]
     */
    public function generateReportData(int $departmentId, string $periodStart, string $periodEnd): array
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        // 1 ── Opening stock: previous period's closing stock from the latest approved/reviewed/submitted report
        $prevReport = PharmReport::where('department_id', $departmentId)
            ->where('period_end', '<', $start->toDateString())
            ->whereIn('status', [
                PharmReport::STATUS_SUBMITTED,
                PharmReport::STATUS_REVIEWED,
                PharmReport::STATUS_APPROVED,
            ])
            ->latest('period_end')
            ->latest('id')
            ->first();

        $openingMap = [];
        if ($prevReport) {
            $prevItems = PharmReportItem::where('report_id', $prevReport->id)->get();
            foreach ($prevItems as $pi) {
                $openingMap[$pi->medicine_id] = (float) $pi->closing_stock;
            }
        }

        // 2 ── Received: distributions received by this department in the period
        $receivedMap = [];
        $distIds = PharmDistribution::where('to_department_id', $departmentId)
            ->whereIn('status', [
                PharmDistribution::STATUS_RECEIVED,
                PharmDistribution::STATUS_PARTIAL,
                PharmDistribution::STATUS_COMPLETED,
            ])
            ->where('received_date', '>=', $start->toDateString())
            ->where('received_date', '<=', $end->toDateString())
            ->pluck('id');

        if ($distIds->isNotEmpty()) {
            $items = PharmDistributionItem::whereIn('distribution_id', $distIds)
                ->where('quantity_received', '>', 0)
                ->selectRaw('medicine_id, SUM(quantity_received) as total')
                ->groupBy('medicine_id')
                ->get();
            foreach ($items as $item) {
                $receivedMap[$item->medicine_id] = (float) $item->total;
            }
        }

        // 3 ── Dispensed: dispensing items from this department in the period
        $dispensedMap = [];
        $dispItems = PharmDispensingItem::whereHas('dispensing', function ($q) use ($departmentId, $start, $end) {
            $q->where('department_id', $departmentId)
              ->where('dispensing_date', '>=', $start->toDateString())
              ->where('dispensing_date', '<=', $end->toDateString());
        })
        ->selectRaw('medicine_id, SUM(quantity) as total')
        ->groupBy('medicine_id')
        ->get();

        foreach ($dispItems as $item) {
            $dispensedMap[$item->medicine_id] = (float) $item->total;
        }

        // 4 ── Stock adjustments: damaged, expired, loss, correction in the period
        $adjustments = PharmStockAdjustment::where('department_id', $departmentId)
            ->where('adjustment_date', '>=', $start->toDateString())
            ->where('adjustment_date', '<=', $end->toDateString())
            ->selectRaw('medicine_id, adjustment_type, SUM(quantity) as total')
            ->groupBy('medicine_id', 'adjustment_type')
            ->get();

        $damagedMap = [];
        $expiredMap = [];
        $adjustmentMap = []; // corrections + losses

        foreach ($adjustments as $adj) {
            $mid = $adj->medicine_id;
            $total = (float) $adj->total;
            switch ($adj->adjustment_type) {
                case PharmStockAdjustment::TYPE_DAMAGED:
                    $damagedMap[$mid] = ($damagedMap[$mid] ?? 0) + $total;
                    break;
                case PharmStockAdjustment::TYPE_EXPIRED:
                    $expiredMap[$mid] = ($expiredMap[$mid] ?? 0) + $total;
                    break;
                case PharmStockAdjustment::TYPE_LOSS:
                    // losses are negative adjustments
                    $adjustmentMap[$mid] = ($adjustmentMap[$mid] ?? 0) - $total;
                    break;
                case PharmStockAdjustment::TYPE_CORRECTION:
                    // corrections can be positive (added) or treated as positive adjustment
                    $adjustmentMap[$mid] = ($adjustmentMap[$mid] ?? 0) + $total;
                    break;
            }
        }

        // 5 ── Current real-time stock (for fallback opening stock when no previous report)
        $currentStockMap = [];
        if (empty($openingMap)) {
            $stocks = PharmFacilityStock::where('department_id', $departmentId)
                ->where('quantity', '>', 0)
                ->selectRaw('medicine_id, SUM(quantity) as total')
                ->groupBy('medicine_id')
                ->get();
            foreach ($stocks as $s) {
                $currentStockMap[$s->medicine_id] = (float) $s->total;
            }
        }

        // 6 ── Merge all medicine IDs
        $allMedicineIds = collect()
            ->merge(array_keys($openingMap))
            ->merge(array_keys($receivedMap))
            ->merge(array_keys($dispensedMap))
            ->merge(array_keys($damagedMap))
            ->merge(array_keys($expiredMap))
            ->merge(array_keys($adjustmentMap))
            ->merge(array_keys($currentStockMap))
            ->unique()
            ->values();

        // 7 ── Build result
        $result = [];
        foreach ($allMedicineIds as $medId) {
            $opening = $openingMap[$medId] ?? $currentStockMap[$medId] ?? 0;
            $received = $receivedMap[$medId] ?? 0;
            $dispensed = $dispensedMap[$medId] ?? 0;
            $damaged = $damagedMap[$medId] ?? 0;
            $expired = $expiredMap[$medId] ?? 0;
            $adjustment = $adjustmentMap[$medId] ?? 0;

            // closing = opening + received - dispensed - damaged - expired + adjustment
            $closing = max(0, $opening + $received - $dispensed - $damaged - $expired + $adjustment);

            $result[$medId] = [
                'medicine_id'    => $medId,
                'opening_stock'  => round($opening, 2),
                'received_qty'   => round($received, 2),
                'dispensed_qty'  => round($dispensed, 2),
                'damaged_qty'    => round($damaged, 2),
                'expired_qty'    => round($expired, 2),
                'adjustment_qty' => round($adjustment, 2),
                'closing_stock'  => round($closing, 2),
            ];
        }

        return $result;
    }

    /**
     * Get previous period report data for comparison.
     * Returns items keyed by medicine_id, or empty array.
     */
    public function getPreviousPeriodData(int $departmentId, string $periodStart): array
    {
        $prevReport = PharmReport::where('department_id', $departmentId)
            ->where('period_end', '<', $periodStart)
            ->whereIn('status', [
                PharmReport::STATUS_SUBMITTED,
                PharmReport::STATUS_REVIEWED,
                PharmReport::STATUS_APPROVED,
            ])
            ->latest('period_end')
            ->latest('id')
            ->first();

        if (!$prevReport) {
            return [];
        }

        $result = ['report' => $prevReport];
        $items = [];
        foreach ($prevReport->items as $item) {
            $items[$item->medicine_id] = [
                'opening_stock'  => (float) $item->opening_stock,
                'received_qty'   => (float) $item->received_qty,
                'dispensed_qty'  => (float) $item->dispensed_qty,
                'damaged_qty'    => (float) ($item->damaged_qty ?? 0),
                'expired_qty'    => (float) $item->expired_qty,
                'adjustment_qty' => (float) $item->adjustment_qty,
                'closing_stock'  => (float) $item->closing_stock,
            ];
        }
        $result['items'] = $items;

        return $result;
    }

    /**
     * Medicine usage summary report — aggregates dispensing data per medicine.
     * Grouped by medicine with total quantity dispensed, number of patients, number of dispensing events.
     */
    public function usageReport(int $departmentId, string $periodStart, string $periodEnd): array
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end   = Carbon::parse($periodEnd)->endOfDay();

        $rows = PharmDispensingItem::query()
            ->join('pharm_dispensings as d', 'd.id', '=', 'pharm_dispensing_items.dispensing_id')
            ->where('d.department_id', $departmentId)
            ->where('d.dispensing_date', '>=', $start->toDateString())
            ->where('d.dispensing_date', '<=', $end->toDateString())
            ->whereNull('d.deleted_at')
            ->selectRaw('
                pharm_dispensing_items.medicine_id,
                SUM(pharm_dispensing_items.quantity) as total_qty,
                COUNT(DISTINCT d.id) as dispense_count,
                COUNT(DISTINCT d.patient_name) as patient_count
            ')
            ->groupBy('pharm_dispensing_items.medicine_id')
            ->get();

        $medicineIds = $rows->pluck('medicine_id')->unique()->values()->toArray();
        $medicines   = PharmMedicine::whereIn('id', $medicineIds)->get()->keyBy('id');

        // Current stock for each medicine
        $stockMap = PharmFacilityStock::where('department_id', $departmentId)
            ->whereIn('medicine_id', $medicineIds)
            ->selectRaw('medicine_id, SUM(quantity) as total')
            ->groupBy('medicine_id')
            ->pluck('total', 'medicine_id');

        $result = [];
        foreach ($rows as $row) {
            $med = $medicines[$row->medicine_id] ?? null;
            $result[] = [
                'medicine_id'    => $row->medicine_id,
                'medicine_name'  => $med?->name ?? '-',
                'medicine_name_kh' => $med?->name_kh ?? '',
                'medicine_code'  => $med?->code ?? '',
                'unit'           => $med?->unit ?? '',
                'total_qty'      => (float) $row->total_qty,
                'dispense_count' => (int) $row->dispense_count,
                'patient_count'  => (int) $row->patient_count,
                'current_stock'  => (float) ($stockMap[$row->medicine_id] ?? 0),
            ];
        }

        // Sort by total_qty descending (most used first)
        usort($result, fn ($a, $b) => $b['total_qty'] <=> $a['total_qty']);

        return $result;
    }

    /**
     * Opening & Closing stock summary — beginning-of-period vs end-of-period per medicine.
     * Shows: opening, received, dispensed, damaged, expired, adjustment, closing, current real-time stock.
     */
    public function stockSummaryReport(int $departmentId, string $periodStart, string $periodEnd): array
    {
        $data = $this->generateReportData($departmentId, $periodStart, $periodEnd);

        $medicineIds = array_keys($data);
        $medicines   = PharmMedicine::whereIn('id', $medicineIds)->get()->keyBy('id');

        // Real-time stock
        $stockMap = PharmFacilityStock::where('department_id', $departmentId)
            ->whereIn('medicine_id', $medicineIds)
            ->selectRaw('medicine_id, SUM(quantity) as total')
            ->groupBy('medicine_id')
            ->pluck('total', 'medicine_id');

        $result = [];
        foreach ($data as $medId => $row) {
            $med = $medicines[$medId] ?? null;
            $result[] = array_merge($row, [
                'medicine_name'    => $med?->name ?? '-',
                'medicine_name_kh' => $med?->name_kh ?? '',
                'medicine_code'    => $med?->code ?? '',
                'unit'             => $med?->unit ?? '',
                'current_stock'    => (float) ($stockMap[$medId] ?? 0),
                'variance'         => round((float) ($stockMap[$medId] ?? 0) - $row['closing_stock'], 2),
            ]);
        }

        // Sort by medicine name
        usort($result, fn ($a, $b) => strcmp($a['medicine_name'], $b['medicine_name']));

        return $result;
    }
}
