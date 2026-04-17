@if (!empty($nodes))
    <ul class="org-hierarchy-tree">
        @foreach ($nodes as $node)
            @php
                $hasChildren = !empty($node['children']);
                $isActive = (int) ($selected_org_unit_id ?? 0) === (int) $node['id'];
            @endphp
            <li class="org-tree-item {{ $hasChildren ? 'has-children' : '' }}"
                data-org-unit-id="{{ (int) $node['id'] }}"
                data-org-edit-url="{{ route('departments.edit', $node['uuid']) }}"
                data-org-delete-url="{{ route('departments.destroy', $node['uuid']) }}"
                data-org-csrf="{{ csrf_token() }}">
                <div class="org-tree-row">
                    @if ($hasChildren)
                        <button type="button" class="org-tree-toggle" aria-label="{{ localize('toggle_children') }}"
                            aria-expanded="false">
                            <span class="toggle-symbol">+</span>
                        </button>
                    @else
                        <span class="org-tree-toggle-placeholder"></span>
                    @endif

                    <a class="org-tree-node {{ $isActive ? 'is-active' : '' }}" title="{{ $node['path'] }}"
                        href="{{ route('departments.index', ['org_unit_id' => $node['id']]) }}">
                        @if (!is_null($node['sort_order'] ?? null))
                            <span class="org-tree-order">{{ str_pad((string) ((int) $node['sort_order']), 2, '0', STR_PAD_LEFT) }}</span>
                        @endif
                        <i class="fa fa-folder org-tree-icon" aria-hidden="true"></i>
                        <span class="org-tree-name">{{ $node['name'] }}</span>
                        <span class="org-tree-type">- {{ $node['type'] }}</span>
                    </a>

                    <span class="org-node-actions">
                        @can('create_department')
                            <button type="button" class="org-node-action js-node-add-child"
                                title="{{ localize('add_sub_unit') }}" data-parent-id="{{ (int) $node['id'] }}"
                                data-org-open-create="1" data-bs-toggle="modal" data-bs-target="#create-department">
                                <i class="fa fa-plus"></i>
                            </button>
                        @endcan

                        @can('update_department')
                            <button type="button" class="org-node-action js-node-edit"
                                title="{{ localize('edit_unit') }}" data-edit-url="{{ route('departments.edit', $node['uuid']) }}"
                                onclick="if(window.orgUnitOpenEditModal){window.orgUnitOpenEditModal(this.getAttribute('data-edit-url')); return false;}">
                                <i class="fa fa-edit"></i>
                            </button>
                        @endcan

                        @can('delete_department')
                            <button type="button" class="org-node-action text-danger js-node-delete"
                                title="{{ localize('delete_unit') }}"
                                data-delete-url="{{ route('departments.destroy', $node['uuid']) }}"
                                data-csrf="{{ csrf_token() }}"
                                onclick="if(window.orgUnitDeleteUnit){window.orgUnitDeleteUnit(this.getAttribute('data-delete-url'), this.getAttribute('data-csrf')); return false;}">
                                <i class="fa fa-trash"></i>
                            </button>
                        @endcan
                    </span>
                </div>

                @if ($hasChildren)
                    @include('humanresource::department.partials.hierarchy-tree', [
                        'nodes' => $node['children'],
                        'selected_org_unit_id' => $selected_org_unit_id ?? 0,
                    ])
                @endif
            </li>
        @endforeach
    </ul>
@endif
