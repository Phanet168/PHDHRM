<?php

namespace Modules\UserManagement\Http\DataTables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Modules\HumanResource\Entities\UserOrgRole;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UserListDataTable extends DataTable
{
    /**
     * Build DataTable class.
     *
     * @param QueryBuilder $query Results from query() method.
     * @return \Yajra\DataTables\EloquentDataTable
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->addColumn('user_role', function ($data) {
                $role = '';
                foreach ($data->userRole as $key => $value) {
                    $role .= '<span class="badge bg-success rounded-pill me-1">' . $value->name . '</span>';
                }
                return $role;
            })
            ->addColumn('user_org_roles', function ($data) {
                $labels = [
                    UserOrgRole::ROLE_HEAD => localize('head_of_unit', 'Head of Unit'),
                    UserOrgRole::ROLE_DEPUTY_HEAD => localize('deputy_head', 'Deputy Head'),
                    UserOrgRole::ROLE_MANAGER => localize('manager', 'Manager'),
                ];

                $html = '';
                $roles = collect($data->orgRoles ?? [])
                    ->filter(function ($role) {
                        return (bool) ($role->is_active ?? false);
                    })
                    ->sortBy(function ($role) {
                        return (string) ($role->org_role ?? '');
                    })
                    ->values();

                foreach ($roles as $role) {
                    $name = $labels[(string) ($role->org_role ?? '')] ?? (string) ($role->org_role ?? '-');
                    $unit = (string) optional($role->department)->department_name;
                    $html .= '<span class="badge bg-info rounded-pill me-1 mb-1">' . e(trim($name . ($unit !== '' ? ' - ' . $unit : ''))) . '</span>';
                }

                return $html !== '' ? $html : '<span class="text-muted">-</span>';
            })
            ->addColumn('mobile_devices', function ($data) {
                $total = (int) ($data->devices_total_count ?? 0);
                if ($total < 1) {
                    return '<span class="text-muted">-</span>';
                }

                $badges = [];
                $badges[] = '<span class="badge bg-primary rounded-pill me-1 mb-1">' . e(localize('total', 'Total')) . ': ' . $total . '</span>';

                $pending = (int) ($data->devices_pending_count ?? 0);
                $active = (int) ($data->devices_active_count ?? 0);
                $blocked = (int) ($data->devices_blocked_count ?? 0);
                $rejected = (int) ($data->devices_rejected_count ?? 0);

                if ($pending > 0) {
                    $badges[] = '<span class="badge bg-warning rounded-pill me-1 mb-1">' . e(localize('pending', 'Pending')) . ': ' . $pending . '</span>';
                }
                if ($active > 0) {
                    $badges[] = '<span class="badge bg-success rounded-pill me-1 mb-1">' . e(localize('active', 'Active')) . ': ' . $active . '</span>';
                }
                if ($blocked > 0) {
                    $badges[] = '<span class="badge bg-danger rounded-pill me-1 mb-1">' . e(localize('blocked', 'Blocked')) . ': ' . $blocked . '</span>';
                }
                if ($rejected > 0) {
                    $badges[] = '<span class="badge bg-secondary rounded-pill me-1 mb-1">' . e(localize('rejected', 'Rejected')) . ': ' . $rejected . '</span>';
                }

                return implode('', $badges);
            })
            ->addColumn('image', function ($data) {
                if (!empty($data->profile_image)) {
                    $image = "<img src='" . asset('storage/' . $data->profile_image) . "' class='img-fluid rounded-circle' width='35' height='35' alt='user'>";
                    return $image;
                } else {
                    $image = '';
                    return $image;
                }
            })
            ->addColumn('created_at', function ($data) {
                return date('d M Y h:i A', strtotime($data->created_at));
            })
            ->addColumn('status', function ($data) {
                $status = '';
                if ($data->is_active == 1) {
                    $status .= '<span class="badge bg-success rounded-pill me-1">' . localize('active') . '</span>';
                } else {
                    $status .= '<span class="badge bg-danger rounded-pill me-1">' . localize('inactive') . '</span>';
                }
                return $status;
            })

            ->addColumn('action', function ($data) {
                $button = '';
                $button .= '<a href="' . route('user-org-roles.index', ['user_id' => $data->id]) . '" class="btn btn-info-soft btn-sm me-1" title="' . e(localize('org_role_management', 'Org Role Management')) . '"><i class="fa fa-sitemap"></i></a>';
                $button .= '<button onclick="detailsView(' . $data->id . ')" id="detailsView-' . $data->id . '" data-url="' . route('role.user.edit', $data->id) . '" class="btn btn-success-soft btn-sm me-1" ><i class="fa fa-edit"></i></button>';

                $button .= '<button onclick="deleteUser(' . $data->id . ')" id="deleteUser' . $data->id . '"  data-user_delete_url="' . route('role.user.delete') . '" class="btn btn-danger-soft btn-sm me-1"><i class="fa fa-trash"></i></button>';
                return $button;

            })

            ->rawColumns(['user_role', 'user_org_roles', 'mobile_devices', 'image', 'status', 'action']);

    }

    /**
     * Get query source of dataTable.
     */
    public function query(User $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['userRole', 'orgRoles.department'])
            ->withCount([
                'mobileDeviceRegistrations as devices_total_count',
                'mobileDeviceRegistrations as devices_pending_count' => function ($query) {
                    $query->where('status', 'pending');
                },
                'mobileDeviceRegistrations as devices_active_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'mobileDeviceRegistrations as devices_blocked_count' => function ($query) {
                    $query->where('status', 'blocked');
                },
                'mobileDeviceRegistrations as devices_rejected_count' => function ($query) {
                    $query->where('status', 'rejected');
                },
            ])
            ->orderBy('id', orderByData($this->request->get('order')));
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('user-table')
            ->setTableAttribute('class', 'table table-hover table-bordered align-middle')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->language([
                'processing' => '<div class="lds-spinner">
                <div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>',
            ])
            ->selectStyleSingle()
            ->lengthMenu([[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']])
            ->dom("<'row mb-3'<'col-md-4'l><'col-md-4 text-center'B><'col-md-4'f>>rt<'bottom'<'row'<'col-md-6'i><'col-md-6'p>>><'clear'>")
            ->buttons([]);
    }

    /**
     * Get the dataTable columns definition.
     *
     * @return array
     */
    public function getColumns(): array
    {
        return [

            Column::make('DT_RowIndex')
                ->width(10)
                ->title(localize('sl'))
                ->addClass('text-center')
                ->searchable(false)
                ->orderable(false),
            Column::make('full_name')
                ->title(localize('name'))
                ->searchable(true),
            Column::make('email')
                ->title(localize('email'))
                ->searchable(true),
            Column::make('contact_no')
                ->title(localize('mobile'))
                ->searchable(true),
            Column::make('mobile_devices')
                ->title(localize('mobile_device_management', 'Mobile Device Management'))
                ->searchable(false)
                ->orderable(false),
            Column::make('user_role')
                ->title(localize('role'))
                ->searchable(true),
            Column::make('user_org_roles')
                ->title(localize('org_role_management', 'Org Role Management'))
                ->searchable(false),
            Column::make('image')
                ->addClass('text-center')
                ->title(localize('image'))
                ->searchable(false),
            Column::make('created_at')
                ->title(localize('created_date'))
                ->searchable(false),
            Column::make('status')
                ->title(localize('status'))
                ->searchable(false),
            Column::make('action')
                ->width(70)
                ->title(localize('action'))
                ->searchable(false)
                ->printable(false)
                ->exportable(false),
        ];
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename(): string
    {
        return 'Users-' . date('YmdHis');
    }
}
