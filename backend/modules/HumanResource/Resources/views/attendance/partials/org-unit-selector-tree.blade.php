@if (!empty($nodes))
    <ul class="wf-org-hierarchy-tree">
        @foreach ($nodes as $node)
            @php
                $hasChildren = !empty($node['children']);
                $isActive = (int) ($selected_org_unit_id ?? 0) === (int) $node['id'];
                $isSelectable = array_key_exists('is_selectable', $node) ? (bool) $node['is_selectable'] : true;
            @endphp
            <li class="wf-org-tree-item {{ $hasChildren ? 'has-children' : '' }} {{ $isActive ? 'is-selected-branch' : '' }}"
                data-org-unit-id="{{ (int) $node['id'] }}">
                <div class="wf-org-tree-row">
                    @if ($hasChildren)
                        <button type="button" class="wf-org-tree-toggle" aria-label="{{ localize('toggle_children') }}"
                            aria-expanded="false"
                            onclick="if(window.wfOrgTreeToggle){window.wfOrgTreeToggle(this); return false;}">
                            <span class="toggle-symbol">+</span>
                        </button>
                    @else
                        <span class="wf-org-tree-toggle-placeholder"></span>
                    @endif

                    <button
                        type="button"
                        class="wf-org-tree-node {{ $isActive ? 'is-active' : '' }}"
                        data-org-unit-id="{{ (int) $node['id'] }}"
                        data-org-unit-path="{{ $node['path'] }}"
                        @disabled(!$isSelectable)
                        title="{{ $node['path'] }}"
                        onclick="if(window.wfOrgTreeSelect){window.wfOrgTreeSelect(this); return false;}"
                    >
                        @if (!is_null($node['sort_order'] ?? null))
                            <span class="wf-org-tree-order">{{ str_pad((string) ((int) $node['sort_order']), 2, '0', STR_PAD_LEFT) }}</span>
                        @endif
                        <i class="fa fa-folder wf-org-tree-icon" aria-hidden="true"></i>
                        <span class="wf-org-tree-name">{{ $node['name'] }}</span>
                        <span class="wf-org-tree-type">- {{ $node['type'] }}</span>
                    </button>
                </div>

                @if ($hasChildren)
                    @include('humanresource::attendance.partials.org-unit-selector-tree', [
                        'nodes' => $node['children'],
                        'selected_org_unit_id' => $selected_org_unit_id ?? 0,
                    ])
                @endif
            </li>
        @endforeach
    </ul>
@endif
